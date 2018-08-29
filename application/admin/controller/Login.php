<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;

class Login extends Controller
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index(Request $request)
    {
        if($request->isPost()){

        }
        else{
            return $this->fetch();
        }
    }
}
