<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;
use think\facade\Session;

class Logout extends Controller
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        Session::clear();
        return $this->redirect('login/index');
    }
}
