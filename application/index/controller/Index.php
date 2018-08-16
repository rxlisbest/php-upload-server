<?php
namespace app\index\controller;

use app\common\model\PersistentPipeline;
use Pheanstalk\Pheanstalk;
use Rxlisbest\FFmpegTranscoding\Slice;
use Rxlisbest\FFmpegTranscoding\Transcoding;
use Rxlisbest\SliceUpload\SliceUpload;
use think\Request;

class Index
{
    public function index(Request $request)
    {
        if($request->isOptions()){
            header('Access-Control-Allow-Origin: *');
            exit;
        }
        $post = $request->post();
        $token = $request->post('token');
        $token = explode(':', $token);
        $param = json_decode(base64_decode($token[2]), true);
        $dir = '/Library/WebServer/Documents/htdocs/upload_server/public/upload';
        if (!file_exists($dir . '/' . $param['scope'])) {
            mkdir($dir . '/' . $param['scope'], 0777, true);
        }
        $upload_filename = $dir . '/' . $param['scope'] . '/' . $post['key'];
        $slice_upload = new SliceUpload();
        $result = $slice_upload->saveAs($upload_filename);
        if(isset($param['persistentOps'])){
            $pheanstalk = new Pheanstalk('127.0.0.1');

            $data = [];
            $data['input'] = $upload_filename;
            $data['param'] = $param;
            $data = json_encode($data);
            $pheanstalk
                ->useTube($param['persistentPipeline'])
                ->put($data);
        }
        var_dump($result);exit;
    }

    public function process(){
        $where = [];
        $where['user_id'] = 1;
        $where['status'] = 1;
        $persistent_pipeline_list = PersistentPipeline::all($where);
        foreach($persistent_pipeline_list as $k => $v){
            $process = new \swoole_process(function(\swoole_process $worker) use ($v){
                // 连接beanstalkd
                $pheanstalk = new Pheanstalk('127.0.0.1');

                // 监听当前进程的tube
                $tube = $pheanstalk
                    ->watch($v['name'])
                    ->ignore('default');

                while(true){
                    $job = $tube->reserve();
                    $pheanstalk->delete($job);
                    if(!$job){
                        continue;
                    }
                    $data = $job->getData();
                    $data = json_decode($data);
                    $param = $data['param'];
                    $dir = '/Library/WebServer/Documents/htdocs/upload_server/public/upload';

                    $persistentOps = explode('|', $param['persistentOps']);
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
                    if(isset($persistentOps[1])){
                        $option_arr = explode('/', $persistentOps[1]);
                        $option = [];
                        for($i = 0; $i < count($option_arr) / 2; $i ++){
                            $option[$option_arr[2 * $i]] = $option_arr[2 * $i + 1];
                        }
                        $save_as = explode(':', base64_decode($option['saveas']));

                        $transcoding_filename = $dir . '/' . $save_as[0] . '/' . $save_as[1];
                    }
                    else{
                        $upload_filename = $dir . '/' . $param['scope'] . '/' . uniqid();
                    }
                    $transcoding->exec($upload_filename, $transcoding_filename);
                }
            }, true);

            $process->start();
        }
    }
}
