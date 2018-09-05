<?php

namespace app\common\model;

use think\Model;
use think\Db;
use think\facade\Config;

use Pheanstalk\Pheanstalk;

class PersistentPipeline extends Model
{
    const STATUS_ON = 1;
    const STATUS_OFF = 0;

    public function add($data){
        Db::startTrans();
        $result = $this->save([
            'name' => $data['name'],
            'user_id' => $data['user_id'],
        ]);
        if($result === false){
            Db::rollback();
            return false;
        }

        // 连接beanstalkd
        $config = Config::get('beanstalkd.');
        $pheanstalk = new Pheanstalk($config['hostname'], $config['hostport']);
        // 监听当前进程的tube
        $tube = $pheanstalk
            ->useTube(config('persistent_pipeline.parent_tube'));
        $result = $tube->put($data['name']);
        if($result === false){
            Db::rollback();
            return false;
        }
        Db::commit();
        return true;
    }

    public function deletePersistentPipeline($id){
        Db::startTrans();
        $persistent_pipeline = PersistentPipeline::get(['id' => $id]);

        $persistent_pipeline->status = PersistentPipeline::STATUS_OFF;
        $pid = $persistent_pipeline->pid;
        $persistent_pipeline->pid = 0;
        $result = $persistent_pipeline->save();

        if($result === false){
            Db::rollback();
            return false;
        }

        // 连接beanstalkd
        $config = Config::get('beanstalkd.');
        $pheanstalk = new Pheanstalk($config['hostname'], $config['hostport']);
        // 监听当前进程的tube
        $tube = $pheanstalk
            ->useTube(config('persistent_pipeline.parent_delete_tube'));
        $result = $tube->put($persistent_pipeline->pid);

        if($result === false){
            Db::rollback();
            return false;
        }

        Db::commit();
        return true;
    }
}
