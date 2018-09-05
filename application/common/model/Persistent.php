<?php

namespace app\common\model;

use think\Model;

class Persistent extends Model
{
    const STATUS_WAITING = 1;
    const STATUS_SUCCESS = 0;
    const STATUS_FAIL = 3;

    const NOTIFY_STATUS_WAITING = 2;
    const NOTIFY_STATUS_SUCCESS = 1;
    const NOTIFY_STATUS_FAIL = 0;

    public $desc = [
        0 => 'The fop was completed successfully',
        1 => 'The fop is waiting for execution',
        3 => 'The fop is failed',
    ];

    public function getInfo($persistent_id){
        $data = [];
        $data['items'] = [];
        $data['code'] = 0;

        $persistent = Persistent::all(['persistent_id' => $persistent_id]);
        foreach($persistent as $k => $v){
            $item = [];
            $output = sprintf('%s%s/%s', $v['upload_dir'], $v['output_bucket'], $v['output_key']);

            $item['cmd'] = $v['ops'];
            $item['code'] = $v['status'];
            $item['desc'] = $this->desc[$v['status']];
            $item['hash'] = $v['output_hash'];
//            $item['hash'] = hash_file('sha1', $output);
            $item['key'] = $v['output_key'];
            $item['returnOld'] = 0;
            if($v['status'] > $data['code']){
                $data['code'] = $v['status'];
            }
            $data['items'][] = $item;
        }

        $data['desc'] = $this->desc[$data['code']];
        $data['id'] = $persistent_id;
        $data['inputBucket'] = $v['input_bucket'];
        $data['inputKey'] = $v['input_key'];
        $data['pipeline'] = $v['pipeline'];
        $data['reqid'] = '';
        $data['notifyUrl'] = $v['notify_url'];
        return $data;
    }
}
