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
        $result = $tube->put($this->name);
        if($result === false){
            Db::rollback();
            return false;
        }
        Db::commit();
        return true;
    }
}
