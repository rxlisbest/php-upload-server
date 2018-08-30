<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;
use app\common\model\Bucket AS BucketModel;

class Bucket extends Controller
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
        $list = BucketModel::all(['status' => BucketModel::STATUS_ON]);
        if($request->id){
            $info = BucketModel::get(['id' => $request->id, 'status' => BucketModel::STATUS_ON]);
        }
        else{
            $info = BucketModel::get(['user_id' => $request->user->id, 'status' => BucketModel::STATUS_ON]);
        }

        $this->assign('info', $info);
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 显示创建资源表单页.
     *
     * @return \think\Response
     */
    public function create()
    {
        $list = BucketModel::all(['status' => BucketModel::STATUS_ON]);

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
        $post = $request->post();
        if(!trim($post['name'])){
            $this->error(lang('bucket_create_error_empty_name'));
        }
        preg_match("/^[0-9a-zA-Z-]*$/", $post['name'], $match);
        if(!$match){
            $this->error(lang('bucket_create_error_format_name'));
        }
        if(strlen($post['name']) > 63 || strlen($post['name']) < 4){
            $this->error(lang('bucket_create_error_length_name'));
        }
        $bucket = BucketModel::get(['name' => $post['name'], 'user_id' => $request->user->id]);
        if($bucket){
            $this->error(lang('bucket_create_error_repeat_name'));
        }

        $bucket_model = new BucketModel();
        $bucket_model->name = $post['name'];
        $bucket_model->user_id = $request->user->id;
        $id = $bucket_model->save();
        if($id !== false){
            $this->success('', url('index', ['id' => $id]));
        }
        else{
            $this->error();
        }
    }

    /**
     * 显示指定的资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function read($id)
    {
        //
    }

    /**
     * 显示编辑资源表单页.
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function edit($id)
    {
        return $this->fetch();
    }

    /**
     * 保存更新的资源
     *
     * @param  \think\Request  $request
     * @param  int  $id
     * @return \think\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * 删除指定资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function delete($id)
    {
        //
    }
}
