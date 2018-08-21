<?php

namespace app\index\controller;

use app\common\model\Persistent;
use think\Controller;
use think\Request;

class Api extends Controller
{
    /**
     * 获取转码状态
     * @name: prefop
     * @param Request $request
     * @return \think\response\Json
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    public function prefop(Request $request)
    {
        $id = $request->get('id');
        if(!$id){
            return json(['error' => 'id not specified'], 400);
        }

        $persistent = Persistent::get(['persistent_id' => $id]);
        if(!$persistent){
            return json(['error' => 'no such id in prefop'], 612);
        }

        $output = sprintf('%s%s/%s', $persistent->upload_dir, $persistent->output_bucket, $persistent->output_key);
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
        return json($data);
    }
}
