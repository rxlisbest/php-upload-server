<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;
use app\common\model\UserKey AS UserKeyModel;

class UserKey extends Controller
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
        $list = UserKeyModel::all(['user_id' => $request->user->id]);

        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 保存新建的资源
     *
     * @param  \think\Request  $request
     * @return \think\Response
     */
    public function save(Request $request)
    {

        $count = UserKeyModel::where(['user_id' => $request->user->id])->count();
        if($count >= config('user_key.maximum_number')){
            $this->error(lang('user_key_maximum_number_alert'));
        }
        $user_key_model = new UserKeyModel();
        $user_key_model->access_key = md5(123);
        $user_key_model->secret_key = md5(123);
        $user_key_model->user_id = $request->user->id;
        $result = $user_key_model->save();
        if($result !== false){
            $this->success(lang('form_post_success'));
        }
        else{
            $this->error(lang('form_post_failure'));
        }
    }

    /**
     * 保存更新的资源
     *
     * @param  \think\Request  $request
     * @param  int  $id
     * @return \think\Response
     */
    public function changeStatusSave(Request $request, $id)
    {
        $user_key = UserKeyModel::get($id);
        $user_key->status = $request->status;
        $result = $user_key->save();
        if($result !== false){
            $this->success(lang('form_post_success'));
        }
        else{
            $this->error(lang('form_post_failure'));
        }
    }

    /**
     * 删除指定资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function delete($id)
    {
        $user_key = UserKeyModel::get($id);
        $result = $user_key->delete();
        if($result !== false){
            $this->success(lang('form_post_success'));
        }
        else{
            $this->error(lang('form_post_failure'));
        }
    }
}
