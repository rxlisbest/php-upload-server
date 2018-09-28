<?php

namespace app\index\controller;

use app\common\model\File;
use app\common\model\Persistent;
use app\common\model\PersistentPipeline;

use Pheanstalk\Pheanstalk;
use Rxlisbest\SliceUpload\SliceUpload;

use think\Controller;
use think\facade\Config;
use think\Request;

class Index extends Controller
{
    protected $middleware = [
        'Cors' => ['only' => ['index', 'mkblk', 'mkfile']],
        'Auth' => ['only' => ['index', 'mkblk', 'mkfile']],
        'Param' => ['only' => ['index', 'mkblk', 'mkfile']],
        'FormData' => ['only' => ['index']],
        'OctetStream' => ['only' => ['mkblk', 'mkfile']],
    ];

    /**
     * 表单上传
     * @name: index
     * @param Request $request
     * @return \think\response\Json
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    public function index(Request $request)
    {
        $param = $request->param;

        $target = $request->bucket_dir . $request->save_key;
        $result = $this->save($request);

        $data = ['key' => $request->save_key];
        if ($result === 'success') {
            if (isset($param['persistentOps'])) {
                $persistent_id = $this->persistent($request);
                $data['persistentId'] = $persistent_id;
            }
            $data['hash'] = hash_file('sha1', $target);
            return json($data);
        } else {
            return $result;
        }
    }

    /**
     * 二进制上传
     * @name: mkblk
     * @param Request $request
     * @return \think\response\Json
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    public function mkblk(Request $request)
    {
        $result = $this->save($request);
        return json(['ctx' => $result]);
    }

    /**
     * 二进制上传重命名
     * @name: mkfile
     * @param Request $request
     * @return \think\response\Json
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    public function mkfile(Request $request)
    {
        $slice_upload = new SliceUpload($request->bucket_dir);

        $request->key = base64_decode($request->key);
        // rename
        if ($request->key !== $request->save_key) {
            try {
                $slice_upload->rename($request->save_key, $request->key);
            } catch (\Exception $e) {
                return json(['error' => $e->getMessage()], 405);
            }
        }

        $persistent_id = $this->persistent($request);

        $target = $request->bucket_dir . $request->key;
        return json(['key' => $request->key, 'hash' => hash_file('sha1', $target), 'persistentId' => $persistent_id]);
    }

    /**
     * 上传
     * @name: save
     * @param $request
     * @return void
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    private function save($request)
    {
        $bucket_dir = $request->bucket_dir;

        if (!file_exists($bucket_dir)) {
            mkdir($bucket_dir, 0777, true);
            chmod($bucket_dir, 0777);
        }
        $slice_upload = new SliceUpload($bucket_dir);
        $result = $slice_upload->save($request->save_key);
        if ($result === 'success') {
            // insert into table file
            $file = new File();
            $file->name = $request->save_key;
            $file->bucket_id = $request->bucket_id;
            $file->save();
        }
        return $result;
    }

    /**
     * 添加转码队列
     * @name: persistent
     * @param $request
     * @return string
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    private function persistent($request)
    {
        $param = $request->param;
        $persistentOps_list = explode(';', $param['persistentOps']);
        $persistent_id = uniqid('z0.', true);

        foreach ($persistentOps_list as $k => $v) {
            // 存入数据库
            $persistent = new Persistent();
            $persistent->persistent_id = $persistent_id;
            $persistent->ops = $v;
            $persistent->pipeline = $param['persistentPipeline'];
            $persistent->notify_url = $param['persistentNotifyUrl'];
            $persistent->upload_dir = $request->upload_dir;

            $persistent->input_bucket = $request->bucket;
            $persistent->input_key = $request->save_key;

            $persistentOps = explode('|', $v);
            if (isset($persistentOps[1])) {
                $option_arr = explode('/', $persistentOps[1]);
                $option = [];
                for ($i = 0; $i < count($option_arr) / 2; $i++) {
                    $option[$option_arr[2 * $i]] = $option_arr[2 * $i + 1];
                }
                $save_as = explode(':', base64_decode($option['saveas']));

                $persistent->output_bucket = $save_as[0];
                $persistent->output_key = $save_as[1];
            } else {
                $persistent->output_bucket = $request->bucket;
                $persistent->output_key = uniqid();
            }

            $persistent->user_id = $request->user_id;
            $persistent->create_time = time();
            $persistent->save();

            $persistent_pipeline = PersistentPipeline::get(['user_id' => $request->user_id, 'name' => $param['persistentPipeline'], 'status' => PersistentPipeline::STATUS_ON]);
            $config = Config::get('beanstalkd.');
            $pheanstalk = new Pheanstalk($config['hostname'], $config['hostport']);

            $pheanstalk
                ->useTube($persistent_pipeline->id)
                ->put($persistent->id);
        }
        return $persistent_id;
    }
}
