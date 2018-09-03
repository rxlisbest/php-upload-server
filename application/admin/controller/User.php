<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;
use app\common\model\User AS UserModel;

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
        $post['user_id'] = $request->user->id;

        if(md5($post['password']) !== $request->user->password){
            $this->error(lang('form_post_failure'));
        }

        $user_model = new UserModel();
        $user_model->password = md5($post['password']);
        $result = $user_model->save();
        if($result !== false){
            $this->success(lang('form_post_success'));
        }
        else{
            $this->error(lang('form_post_failure'));
        }
    }
}
