<?php

namespace app\examples\controller;

use think\Controller;
use think\Request;

use Qiniu\Auth;

class Api extends Controller
{
    public function token(Request $request){
        /*** 配置开始 ***/
        $accessKey = 'hjr7j6yL1Rr4PPLoeVflHGt0jF8qSJfg7GU8bHvb';
        $secretKey = 'UQJDIuM6vV4NpzqCXjBbhLu5wrvTOxk2O947Nj8i';
        $bucket = 'bucket_1';

        // 视频转码配置
        $persistentOps = 'avthumb/mp4/ab/128k/ar/44100/acodec/libfaac/r/30/vb/900k/vcodec/libx264/s/640x480/autoscale/1/stripmeta/0';
        $persistentPipeline = 'pipeline_1';
        $persistentNotifyUrl = 'http://cc441e81.ngrok.io/index.php/admin/public/qiniucallback';
        /*** 配置结束 ***/

        $ext = $request->get('ext');
        // 存储名称
        $key = sprintf('%s_%s.%s', date('Ymd'), uniqid(), $ext);

        $video_ext = [
            'avi', 'rm', 'rmvb', 'wmv', 'flv', 'mpg', 'mpeg', 'mp4', 'mov', '3gp', 'mkv'
        ];

        $policy = [];
        // 视频增加转码配置
        if (in_array(strtolower($ext), $video_ext)) {
            $persistentOps_arr = explode('/', $persistentOps);
            // 转码后缀
            $saveas_ext = $persistentOps_arr[1];

            // 转码后名称
            $saveas_key = sprintf('%s_%s.%s', date('Ymd'), uniqid(), $saveas_ext);
            $saveas = base64_encode($bucket . ':' . $saveas_key);

            // 重新生成转码规格，包含转码后文件名称
            $persistentOps = sprintf('%s|saveas/%s', $persistentOps, $saveas);

            $policy = [
                'persistentOps' => $persistentOps,
                'persistentNotifyUrl' => $persistentNotifyUrl,
                'persistentPipeline' => $persistentPipeline,
            ];
        }

        // 生成上传token
        $auth = new Auth($accessKey, $secretKey);
        $upToken = $auth->uploadToken($bucket, $key, 3600, $policy, true);
        return json(['upToken' => $upToken]);
    }
}
