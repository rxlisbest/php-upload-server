<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;
use app\common\model\Bucket AS BucketModel;
use app\common\model\BucketDomain AS BucketDomainModel;
use app\common\model\File AS FileModel;

use app\admin\validate\Bucket as BucketValidate;

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
        $bucket_domain_list = BucketDomainModel::where(['bucket_id' => $info->id])->order('id DESC')->all();
        $this->assign('info', $info);
        $this->assign('list', $list);
        $this->assign('bucket_domain_list', $bucket_domain_list);
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
        $post['user_id'] = $request->user->id;

        $validate = new BucketValidate();

        if($validate->check($post) === false){
            $this->error($validate->getError());
        }

        $bucket_model = new BucketModel();
        $bucket_model->name = $post['name'];
        $bucket_model->user_id = $post['user_id'];
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
    public function file(Request $request)
    {
        $list = BucketModel::all(['status' => BucketModel::STATUS_ON]);
        $info = BucketModel::get(['id' => $request->id, 'status' => BucketModel::STATUS_ON]);
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
        $file_list = $file_model->order('id DESC')->paginate(10, false, [
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
