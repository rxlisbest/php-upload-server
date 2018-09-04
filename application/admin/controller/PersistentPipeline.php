<?php

namespace app\admin\controller;

use think\Controller;
use think\Request;

use app\common\model\Persistent AS PersistentModel;
use app\common\model\PersistentPipeline AS PersistentPipelineModel;
use app\admin\validate\PersistentPipeline as PersistentPipelineValidate;


class PersistentPipeline extends Controller
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
        $list = PersistentPipelineModel::all(['status' => PersistentPipelineModel::STATUS_ON]);

        if($request->id){
            $info = PersistentPipelineModel::get(['id' => $request->id, 'status' => PersistentPipelineModel::STATUS_ON]);
        }
        else{
            $info = PersistentPipelineModel::get(['user_id' => $request->user->id, 'status' => PersistentPipelineModel::STATUS_ON]);
        }
        if(!$info){
            $this->redirect(url('create'));
        }
        $persistent_list = PersistentModel::all(['pipeline' => $info->name]);
        $this->assign('persistent_list', $persistent_list);
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
        $list = PersistentPipelineModel::all(['status' => PersistentPipelineModel::STATUS_ON]);

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

        $validate = new PersistentPipelineValidate();

        if($validate->check($post) === false){
            $this->error($validate->getError());
        }

        $count = PersistentPipelineModel::where(['status' => PersistentPipelineModel::STATUS_ON, 'user_id' => $request->user->id])->count();
        if($count >= config('persistent_pipeline.maximum_number')){
            $this->error(lang('persistent_pipeline_maximum_number_alert'));
        }

        $persistent_pipe_model = new PersistentPipelineModel();
        $result = $persistent_pipe_model->add($post);
        if($result !== false){
            $this->success(lang('form_post_success'), url('index', ['id' => $persistent_pipe_model->id]));
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
        //
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
