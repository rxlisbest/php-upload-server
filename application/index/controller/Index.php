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
        'Cors' => ['only' => ['index', 'mkblk']],
        'Auth' => ['only' => ['index', 'mkblk']],
        'Param' => ['only' => ['index', 'mkblk']],
        'OctetStream' => ['only' => ['mkblk', 'mkfile']],
    ];

    /**
     * 表单上传
     * @name: index
     * @param Request $request
     * @return \think\response\Json
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    public function index(Request $request)
    {
        return $this->save($request);
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
        $ffmpeg_config = Config::get('ffmpeg.');
        if($option['avthumb'] == 'm3u8'){
            $transcoding = new Slice(['option' => $option, 'ffmpeg' => $ffmpeg_config]);
        }
        else{
            $transcoding = new Transcoding(['option' => $option, 'ffmpeg' => $ffmpeg_config]);
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
                $persistent_model->save(['notify_status' => Persistent::NOTIFY_STATUS_FAIL], $where);
            }
            else{
                $persistent_model->save(['notify_status' => Persistent::NOTIFY_STATUS_SUCCESS], $where);
            }
        }
    }

    /**
     * 二进制上传
     * @name: mkblk
     * @param Request $request
     * @return \think\response\Json
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    public function mkblk(Request $request){
        $param = $request->param;

        $target = $request->bucket_dir . $request->key;
        $result = $this->save($request);

        if($result === 'success'){
            if(isset($param['persistentOps'])){
                $persistent_id = $this->persistent($request);
                return json(['key' => $request->key, 'hash' => hash_file('sha1', $target), 'persistentId' => $persistent_id]);
            }
            return json(['key' => $request->key, 'hash' => hash_file('sha1', $target)]);
        }

    }

    /**
     * 二进制上传重命名
     * @name: mkfile
     * @param Request $request
     * @return \think\response\Json
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    public function mkfile(Request $request){
        $slice_upload = new SliceUpload($request->bucket_dir, $request->old_key);
        // rename
        $slice_upload->rename($request->key);

        $persistent_id = $this->persistent($request);

        $target = $request->bucket_dir . $request->key;
        return json(['key' => $request->key, 'hash' => hash_file('sha1', $target), 'persistentId' => $persistent_id]);
    }

    /**
     * 上传
     * @name: save
     * @param $request
     * @return void
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    private function save($request){
        $bucket_dir = $request->bucket_dir;

        if (!file_exists($bucket_dir)) {
            mkdir($bucket_dir, 0777, true);
            chmod($bucket_dir, 0777);
        }

        $slice_upload = new SliceUpload($bucket_dir, $request->key);

        $result = $slice_upload->save();
        if($result === 'success'){
            // insert into table file
            $file = new File();
            $file->name = $request->key;
            $file->bucket_id = $request->bucket_id;
            $file->save();
        }
        return $result;
    }

    /**
     * 转码
     * @name: persistent
     * @param $request
     * @return string
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    private function persistent($request){
        $param = $request->param;
        $persistentOps_list = explode(';', $param['persistentOps']);
        $persistent_id = uniqid('z0.', true);

        foreach($persistentOps_list as $k => $v){
            // 存入数据库
            $persistent = new Persistent();
            $persistent->persistent_id = $persistent_id;
            $persistent->ops = $v;
            $persistent->pipeline = $param['persistentPipeline'];
            $persistent->notify_url = $param['persistentNotifyUrl'];
            $persistent->upload_dir = $request->upload_dir;

            $persistent->input_bucket = $request->bucket;
            $persistent->input_key = $request->key;

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
                $persistent->output_bucket = $request->bucket;
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
        return $persistent_id;
    }
}
