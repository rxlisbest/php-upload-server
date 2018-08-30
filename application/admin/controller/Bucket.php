<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;
use app\common\model\Bucket AS BucketModel;
use app\common\model\File AS FileModel;

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
        $file_model = new FileModel();
        $where = [];
        $where['bucket_id'] = $info->id;
        $where['status'] = FileModel::STATUS_ON;
        $file_model = $file_model->where($where);

        $query = [];
        if($request->name){
            $file_model = $file_model->whereLike('name', "%{$request->name}%");
            $query['name'] = $request->name;
        }
        $file_list = $file_model->paginate(10, false, [
            'query' => $query
        ]);
        $file_page = $file_list->render();
        $this->assign('info', $info);
        $this->assign('list', $list);
        $this->assign('file_list', $file_list);
        $this->assign('file_page', $file_page);
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
        $result = $bucket_model->save();
        if($result !== false){
            $this->success(lang('form_post_success'), url('index', ['id' => $bucket_model->id]));
        }
        else{
            $this->error(lang('form_post_failure'));
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
