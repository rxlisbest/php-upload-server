<?php
namespace app\index\controller;

use app\common\model\Bucket;
use app\common\model\File;
use app\common\model\Persistent;
use app\common\model\PersistentPipeline;

use Curl\Curl;
use Pheanstalk\Pheanstalk;
use Rxlisbest\FFmpegTranscoding\Slice;
use Rxlisbest\FFmpegTranscoding\Transcoding;
use Rxlisbest\SliceUpload\SliceUpload;

use think\Controller;
use think\facade\Config;
use think\Request;

class Index extends Controller
{
    protected $middleware = [
        'Cors' => ['only' => ['index']],
        'Auth' => ['only' => ['index']],
        'Param' => ['only' => ['index']],
    ];

    /**
     * index
     * @name: index
     * @param Request $request
     * @return \think\response\Json
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    public function index(Request $request)
    {
        $post = $request->post();
        $param = $request->param;

        $config = Config::get('upload.');
        $user_dir = sprintf('%s%s/', $config['dir'], $request->user_id);

        if(count(explode(':', $param['scope'])) > 1){
            $bucket = explode(':', $param['scope'])[0];
            $post['key'] = explode(':', $param['scope'])[1];
        }
        else{
            $bucket = $param['scope'];
        }

        $bucket_dir = sprintf('%s%s/', $user_dir, $bucket);
        if (!file_exists($bucket_dir)) {
            mkdir($bucket_dir, 0777, true);
            chmod($bucket_dir, 0777);
        }
        $upload = $bucket_dir . $post['key'];
        $slice_upload = new SliceUpload();
        $result = $slice_upload->saveAs($upload);

        if($result === 'success'){
            // insert into table file
            $bucket_info = Bucket::get(['name' => $bucket]);
            $file = new File();
            $file->name = $post['key'];
            $file->bucket_id = $bucket_info->id;
            $file->save();

            if(isset($param['persistentOps'])){

                $persistentOps_list = explode(';', $param['persistentOps']);
                $persistent_id = uniqid('z0.', true);

                foreach($persistentOps_list as $k => $v){
                    // 存入数据库
                    $persistent = new Persistent();
                    $persistent->persistent_id = $persistent_id;
                    $persistent->ops = $v;
                    $persistent->pipeline = $param['persistentPipeline'];
                    $persistent->notify_url = $param['persistentNotifyUrl'];
                    $persistent->upload_dir = $user_dir;

                    $persistent->input_bucket = $bucket;
                    $persistent->input_key = $post['key'];

                    $persistentOps = explode('|', $v);
                    if(isset($persistentOps[1])){
                        $option_arr = explode('/', $persistentOps[1]);
                        $option = [];
                        for($i = 0; $i < count($option_arr) / 2; $i ++){
                            $option[$option_arr[2 * $i]] = $option_arr[2 * $i + 1];
                        }
                        $save_as = explode(':', base64_decode($option['saveas']));

                        $persistent->output_bucket = $save_as[0];
                        $persistent->output_key = $save_as[1];
                    }
                    else{
                        $persistent->output_bucket = $bucket;
                        $persistent->output_key = uniqid();
                    }

                    $persistent->user_id = $request->user_id;
                    $persistent->create_time = time();
                    $persistent->save();

                    $persistent_pipeline = PersistentPipeline::get(['user_id' => $request->user_id, 'name' => $param['persistentPipeline'], 'status' => PersistentPipeline::STATUS_ON]);
                    $config = Config::get('beanstalkd.');
                    $pheanstalk = new Pheanstalk($config['hostname'], $config['hostport']);

                    $pheanstalk
                        ->useTube($persistent_pipeline->id)
                        ->put($persistent->id);
                }

                return json(['key' => $post['key'], 'hash' => hash_file('sha1', $upload), 'persistentId' => $persistent_id]);
            }
            return json(['key' => $post['key'], 'hash' => hash_file('sha1', $upload)]);
        }
    }

    /**
     * daemon swoole process
     * @name: process
     * @return void
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    public function process(){
        // 连接beanstalkd
        $config = Config::get('beanstalkd.');
        $pheanstalk = new Pheanstalk($config['hostname'], $config['hostport']);
        // 监听当前进程的tube
        $tube = $pheanstalk
            ->useTube(config('persistent_pipeline.parent_tube'));

        $where = [];
        $where['status'] = PersistentPipeline::STATUS_ON;
        $persistent_pipeline_list = PersistentPipeline::all($where);
        foreach($persistent_pipeline_list as $k => $v){
            $tube->put($v->id);
        }
        // add process
        $process = new \swoole_process(function(\swoole_process $worker) use ($config){
            $pheanstalk = new Pheanstalk($config['hostname'], $config['hostport']);
            // 监听当前进程的tube
            $tube = $pheanstalk
                ->watch(config('persistent_pipeline.parent_tube'))
                ->ignore('default');

            while(1){
                $job = $tube->reserve();
                $pheanstalk->delete($job);
                if(!$job){
                    continue;
                }
                $persistent_pipeline_id = $job->getData();
                // transcoding process
                $process = new \swoole_process(function(\swoole_process $worker) use ($config, $persistent_pipeline_id){
                    $pheanstalk = new Pheanstalk($config['hostname'], $config['hostport']);
                    // 监听当前进程的tube
                    $tube = $pheanstalk
                        ->watch($persistent_pipeline_id)
                        ->ignore('default');

                    while(1){
                        $job = $tube->reserve();
                        $pheanstalk->delete($job);
                        if(!$job){
                            continue;
                        }
                        $id = $job->getData();
                        $this->transcoding($id);
                    }
                }, false);
                \swoole_process::daemon();
                $process->start();
                $pid = $process->pid;

                // update table persistent_pipeline field pid
                $persistent_pipeline = PersistentPipeline::get(['id' => $persistent_pipeline_id]);
                $persistent_pipeline->pid = $pid;
                $persistent_pipeline->save();
            }
        }, false);
        \swoole_process::daemon();
        $process->start();

        // delete process
        $process = new \swoole_process(function(\swoole_process $worker) use ($config){
            $pheanstalk = new Pheanstalk($config['hostname'], $config['hostport']);
            // 监听当前进程的tube
            $tube = $pheanstalk
                ->watch(config('persistent_pipeline.parent_delete_tube'))
                ->ignore('default');

            while(1){
                $job = $tube->reserve();
                $pheanstalk->delete($job);
                if(!$job){
                    continue;
                }
                $pid = $job->getData();
                \swoole_process::kill($pid, $signo = SIGTERM);
            }
        }, false);
        \swoole_process::daemon();
        $process->start();
//        swoole_event_add($process->pipe, function($pipe) use($process) {
//            echo sprintf(" code: %s\n", $process->read());
////            swoole_event_del($pipe);
//        });
    }

    /**
     * transcoding
     * @name: transcoding
     * @param $id
     * @return void
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    private function transcoding($id){
        $persistent = Persistent::get($id);

        $user_dir = $persistent->upload_dir;

        $input_bucket_dir = sprintf('%s%s/', $user_dir, $persistent->input_bucket);
        $output_bucket_dir = sprintf('%s%s/', $user_dir, $persistent->output_bucket);
        if (!file_exists($output_bucket_dir)) {
            mkdir($output_bucket_dir, 0777, true);
            chmod($output_bucket_dir, 0777);
        }
        $persistentOps = explode('|', $persistent['ops']);
        $option_arr = explode('/', $persistentOps[0]);
        $option = [];
        for($i = 0; $i < count($option_arr) / 2; $i ++){
            $option[$option_arr[2 * $i]] = $option_arr[2 * $i + 1];
        }
        if($option['avthumb'] == 'm3u8'){
            $transcoding = new Slice(['option' => $option]);
        }
        else{
            $transcoding = new Transcoding(['option' => $option]);
        }
        $input = $input_bucket_dir . $persistent->input_key;
        $output = $output_bucket_dir . $persistent->output_key;
        // if the input file is not exists, then continue;
        if(!is_file($input)){
            return false;
        }
        $result = $transcoding->exec($input, $output);

        // update table persistent field status
        if($result === 0){
            $persistent->status = Persistent::STATUS_SUCCESS;
        }
        else{
            $persistent->status = Persistent::STATUS_FAIL;
        }
        $persistent->output_hash = hash_file('sha1', $output);
        $persistent->save();
        // get bucket info
        $bucket = Bucket::get(['name' => $persistent->output_bucket, 'user_id' => $persistent->user_id]);

        // insert into table file
        $file_model = new File();
        $file_model->bucket_id = $bucket->id;
        $file_model->name = $persistent->output_key;
        $file_model->save();

        // notify client
        $this->notify($persistent->persistent_id);
    }

    /**
     * persistent notify
     * @name: notify
     * @param $persistent_id
     * @return void
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    public function notify($persistent_id){
        $persistent_model = new Persistent();
        $data = $persistent_model->getInfo($persistent_id);
        if($data['code'] != Persistent::STATUS_WAITING){
            $curl = new Curl();
            $curl->setHeader('Content-Type', 'application/json');
            $result = $curl->post($data['notifyUrl'], $data);
            $where = [];
            $where['persistent_id'] = $persistent_id;
            if(!$result){
                $persistent_model->save(['notify_status' => Persistent::NOTIFY_STATUS_SUCCESS], $where);
            }
            else{
                $persistent_model->save(['notify_status' => Persistent::NOTIFY_STATUS_FAIL], $where);
            }
        }
    }
}
