<?php

namespace app\admin\controller;

use think\Controller;

class Index extends Controller
{
    protected $middleware = [
        'AdminAuth'
    ];

    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        return $this->fetch();
    }
}
