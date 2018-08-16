<?php
namespace app\index\controller;

use Rxlisbest\SliceUpload\SliceUpload;
use think\Request;

class Index
{
    public function index(Request $request)
    {
        $post = $request->post();
        $token = $request->post('token');
        $token = explode(':', $token);
        $param = json_decode(base64_decode($token[2]), true);
        $dir = '/Library/WebServer/Documents/htdocs/upload_server/public/upload' . '/' . $param['scope'];
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $filename = $dir . '/' . $post['key'];
        $slice_upload = new SliceUpload();
        $result = $slice_upload->saveAs($filename);
        var_dump($result);exit;
    }
}
