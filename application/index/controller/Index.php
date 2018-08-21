<?php
namespace app\index\controller;

use app\common\model\Persistent;
use app\common\model\PersistentPipeline;

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
    ];

    public function index(Request $request)
    {
        $post = $request->post();
        $param = $request->param;

        $dir = '/Library/WebServer/Documents/htdocs/upload_server/public/upload/' . $request->user_id;

//        if(!file_exists($dir)){
//            mkdir($dir, 0777, true);
//            chmod($dir, 0777);
//        }

        if(count(explode(':', $param['scope'])) > 1){
            $bucket = explode(':', $param['scope'])[0];
            $post['key'] = explode(':', $param['scope'])[1];
        }
        else{
            $bucket = $param['scope'];
        }

        if (!file_exists($dir . '/' . $bucket)) {
            mkdir($dir . '/' . $bucket, 0777, true);
            chmod($dir . '/' . $bucket, 0777);
        }
        $upload_filename = $dir . '/' . $bucket . '/' . $post['key'];
        $slice_upload = new SliceUpload();
        $result = $slice_upload->saveAs($upload_filename);

        if($result === 'success'){
            if(isset($param['persistentOps'])){
                // 存入数据库
                $persistent = new Persistent();
                $persistent->ops = $param['persistentOps'];
                $persistent->pipeline = $param['persistentPipeline'];
                $persistent->notify_url = $param['persistentNotifyUrl'];

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
            }
            return json(['key' => $post['key'], 'hash' => hash_file('sha1', $upload_filename)]);
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
                    $dir = '/Library/WebServer/Documents/htdocs/upload_server/public/upload/' . $persistent->user_id;

                    if (!file_exists($dir . '/' . $persistent->output_bucket)) {
                        mkdir($dir . '/' . $persistent->output_bucket, 0777, true);
                        chmod($dir . '/' . $persistent->output_bucket, 0777);
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
                    $input = sprintf('%s/%s/%s', $dir, $persistent->input_bucket, $persistent->input_key);
                    $output = sprintf('%s/%s/%s', $dir, $persistent->output_bucket, $persistent->output_key);
                    $transcoding->exec($input, $output);
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
