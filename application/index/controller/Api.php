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

        $persistent_model = new Persistent();
        $data = $persistent_model->getInfo($id);
        return json($data);
    }
}
