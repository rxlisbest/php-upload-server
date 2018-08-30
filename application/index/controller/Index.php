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
                // 存入数据库
                $persistent = new Persistent();
                $persistent->persistent_id = uniqid('z0.', true);
                $persistent->ops = $param['persistentOps'];
                $persistent->pipeline = $param['persistentPipeline'];
                $persistent->notify_url = $param['persistentNotifyUrl'];
                $persistent->upload_dir = $user_dir;

                $persistent->input_bucket = $bucket;
                $persistent->input_key = $post['key'];

                $persistentOps = explode('|', $param['persistentOps']);
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

                $config = Config::get('beanstalkd.');
                $pheanstalk = new Pheanstalk($config['hostname'], $config['hostport']);

                $pheanstalk
                    ->useTube($param['persistentPipeline'])
                    ->put($persistent->id);
                return json(['key' => $post['key'], 'hash' => hash_file('sha1', $upload), 'persistentId' => $persistent->persistent_id]);
            }
            return json(['key' => $post['key'], 'hash' => hash_file('sha1', $upload)]);
        }
    }

    public function process(){
        $where = [];
        $where['status'] = 1;
        $persistent_pipeline_list = PersistentPipeline::all($where);
        foreach($persistent_pipeline_list as $k => $v){
            $process = new \swoole_process(function(\swoole_process $worker) use ($v){
                // 连接beanstalkd
                $config = Config::get('beanstalkd.');
                $pheanstalk = new Pheanstalk($config['hostname'], $config['hostport']);

                // 监听当前进程的tube
                $tube = $pheanstalk
                    ->watch($v['name'])
                    ->ignore('default');

                while(1){
                    $job = $tube->reserve();
                    $pheanstalk->delete($job);
                    if(!$job){
                        continue;
                    }
                    $id = $job->getData();
                    $persistent = Persistent::get($id);

                    $user_dir = $persistent['upload_dir'];

                    $bucket_dir = sprintf('%s%s/', $user_dir, $persistent->output_bucket);
                    if (!file_exists($bucket_dir)) {
                        mkdir($bucket_dir, 0777, true);
                        chmod($bucket_dir, 0777);
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
                    $input = $bucket_dir . $persistent->input_key;
                    $output = $bucket_dir . $persistent->output_key;
                    $transcoding->exec($input, $output);

                    $data = [];
                    $data['code'] = 0;
                    $data['desc'] = 'The fop was completed successfully';
                    $data['id'] = $persistent->persistent_id;
                    $data['inputBucket'] = $persistent->input_bucket;
                    $data['inputKey'] = $persistent->input_key;
                    $data['items'] = [];
                    $data['items'][0]['cmd'] = $persistent->ops;
                    $data['items'][0]['code'] = 0;
                    $data['items'][0]['desc'] = 'The fop was completed successfully';
                    $data['items'][0]['hash'] = hash_file('sha1', $output);
                    $data['items'][0]['key'] = $persistent->output_key;
                    $data['items'][0]['returnOld'] = 0;
                    $data['pipeline'] = $persistent->pipeline;
                    $data['reqid'] = '';

                    $curl = new Curl();
                    $curl->setHeader('Content-Type', 'application/json');
                    $curl->post($persistent->notify_url, $data);
                }
            }, false);
            \swoole_process::daemon();
            $process->start();
        }

//        swoole_event_add($process->pipe, function($pipe) use($process) {
//            echo sprintf(" code: %s\n", $process->read());
////            swoole_event_del($pipe);
//        });
    }
}
