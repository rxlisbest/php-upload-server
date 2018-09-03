<?php

namespace app\admin\controller;

use app\common\model\User;
use think\Controller;
use think\facade\Session;
use think\Request;

class Login extends Controller
{
    protected $middleware = [
        'AdminLogin'
    ];
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index(Request $request)
    {
        if($request->isPost()){
            $post = $request->post();
            if(!trim($post['user_name'])){
                $this->error(lang('login_index_error_empty_user_name'));
            }
            if(!trim($post['password'])){
                $this->error(lang('login_index_error_empty_password'));
            }
            $where = [];
            $where['user_name'] = $post['user_name'];
            $where['status'] = User::STATUS_ON;
            $user = User::get($where);
            if(!$user){
                $this->error(lang('login_index_error_no_user'));
            }
            if($user->password !== md5($post['password'])){
                $this->error(lang('login_index_error_incorrect_password'));
            }
            if(isset($post['remember_me'])){
                Session::init([
                    'expire' => 0
                ]);
            }
            Session::set('user', $user);
            $this->success();
        }
        else{
            return $this->fetch();
        }
    }
}
