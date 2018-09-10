<?php

namespace app\common\model;

use think\Model;
use think\Db;

use think\facade\Config;

use Curl\Curl;

class BucketDomain extends Model
{
    public function add($data){
        Db::startTrans();
        $bucekt_domain = BucketDomain::create([
            'domain' => $data['domain'],
            'bucket_id' => $data['bucket_id'],
        ]);
        if($bucekt_domain === false){
            Db::rollback();
            return false;
        }

        $bucket = Bucket::get(['id' => $data['bucket_id']]);
        if($bucket === false){
            Db::rollback();
            return false;
        }

        $dynamic_domain = Config::get('dynamic_domain.');
        $curl = new Curl();
        $post= [];
        $post['domain'] = $bucekt_domain->domain;
        $post['directory'] = sprintf('/%s/%s/', $bucket->user_id, $bucket['name']);
        $result = $curl->post($dynamic_domain['url'] . '/api/add', $post);
        if($result && $result->code == 1){
            Db::commit();
            return true;
        }
        else{
            Db::rollback();
            return false;
        }
    }

    public function deleteBucketDomain($id){
        Db::startTrans();
        $bucekt_domain = BucketDomain::get(['id' => $id]);
        if($bucekt_domain === false){
            Db::rollback();
            return false;
        }

        $result = $bucekt_domain->delete();
        if($result === false){
            Db::rollback();
            return false;
        }

        $bucket = Bucket::get(['id' => $bucekt_domain ->bucket_id]);
        if($bucket === false){
            Db::rollback();
            return false;
        }

        $dynamic_domain = Config::get('dynamic_domain.');
        $curl = new Curl();
        $post= [];
        $post['domain'] = $bucekt_domain->domain;
        $result = $curl->post($dynamic_domain['url'] . '/api/delete', $post);
        if($result && $result->code == 1){
            Db::commit();
            return true;
        }
        else{
            Db::rollback();
            return false;
        }
    }
}
