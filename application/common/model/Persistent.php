<?php

namespace app\common\model;

use think\Model;

class Persistent extends Model
{
    const STATUS_WAITING = 1;

    public $desc = [
        0 => 'The fop was completed successfully',
        1 => 'The fop is waiting for execution',
        3 => 'The fop is failed',
    ];

    public function getInfo($persistent_id){
        $data = [];
        $data['items'] = [];

        $persistent = Persistent::all(['persistent_id' => $persistent_id]);
        $item = [];
        foreach($persistent as $k => $v){
            $output = sprintf('%s%s/%s', $v['upload_dir'], $v['output_bucket'], $v['output_key']);

            $item['cmd'] = $v['ops'];
            $item['code'] = $v['status'];
            $item['desc'] = $this->desc[$v['status']];
            $item['hash'] = hash_file('sha1', $output);
            $item['key'] = $v['output_key'];
            $item['returnOld'] = 0;
        }
        $data['items'][] = $item;

        $data['code'] = 0;
        $data['desc'] = 'The fop was completed successfully';
        $data['id'] = $persistent_id;
        $data['inputBucket'] = $v['input_bucket'];
        $data['inputKey'] = $v['input_key'];
        $data['pipeline'] = $v['pipeline'];
        $data['reqid'] = '';
        return $data;
    }
}
