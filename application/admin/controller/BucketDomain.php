<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;
use app\common\model\Bucket AS BucketModel;
use app\common\model\BucketDomain AS BucketDomainModel;
use app\admin\validate\BucketDomain as BucketDomainValidate;

class BucketDomain extends Controller
{
    protected $middleware = [
        'AdminAuth'
    ];

    /**
     * 显示创建资源表单页.
     *
     * @return \think\Response
     */
    public function create(Request $request)
    {
        $bucket_list = BucketModel::all(['status' => BucketModel::STATUS_ON]);

        $bucket_info = BucketModel::get(['id' => $request->bucket_id, 'status' => BucketModel::STATUS_ON]);

        $this->assign('bucket_list', $bucket_list);
        $this->assign('bucket_info', $bucket_info);
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

        $validate = new BucketDomainValidate();

        if($validate->check($post) === false){
            $this->error($validate->getError());
        }
        $post['bucket_id'] = $request->bucket_id;
        $bucket_domain_model = new BucketDomainModel();
        $result = $bucket_domain_model->add($post);
        if($result !== false){
            $this->success(lang('form_post_success'), url('Bucket/index', ['id' => $request->bucket_id]));
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
        $bucket_domain = BucketDomainModel::get(['id' => $id]);
        if(!$bucket_domain){
            $this->error(lang('bucket_domain_index_error_without'));
        }

        $result = $bucket_domain->deleteBucketDomain($id);

        if($result !== false){
            $this->success(lang('form_post_success'), url('Bucket/index'));
        }
        else{
            $this->error(lang('form_post_failure'));
        }
    }
}
