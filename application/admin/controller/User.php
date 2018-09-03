<?php

namespace app\admin\controller;

use app\admin\validate\UserPassword;
use think\Controller;
use think\Request;
use app\common\model\User AS UserModel;
use app\admin\validate\UserPassword AS UserPasswordValidate;

class User extends Controller
{
    protected $middleware = [
        'AdminAuth'
    ];
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index(Request $request)
    {
        $this->assign('info', $request->user);
        return $this->fetch();
    }

    public function changePassword(){
        return $this->fetch('change_password');
    }

    public function changePasswordSave(Request $request){
        $post = $request->post();

        $validate = new UserPasswordValidate();
        if($validate->check($post) === false){
            $this->error($validate->getError());
        }

        $user_model = UserModel::get(['id' => $request->user->id, 'password' => md5($post['old_password'])]);
        if(!$user_model){
            $this->error(lang('user_change_password_error_old_password'));
        }
        $user_model->password = md5($post['password']);
        $result = $user_model->save();
        if($result !== false){
            $this->success(lang('form_post_success'), url('index'));
        }
        else{
            $this->error(lang('form_post_failure'));
        }
    }
}
