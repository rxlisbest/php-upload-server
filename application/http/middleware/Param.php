<?php

namespace app\http\middleware;

use app\common\model\Bucket;
use app\common\model\PersistentPipeline;

use think\facade\Config;

class Param
{
    public function handle($request, \Closure $next)
    {
        $param = $request->param;
        $user_id = $request->user_id;

        if(!isset($param['scope']) || !$param['scope']){
            return json(['error' => 'scope not specified'], 400);
        }

        if(count(explode(':', $param['scope'])) > 1){
            $bucket_name = explode(':', $param['scope'])[0];
            $key = explode(':', $param['scope'])[1];
        }
        else{
            $bucket_name = $param['scope'];
            $key = '';
        }
        $request->key = $key;

        $bucket = Bucket::get(['user_id' => $user_id, 'name' => $bucket_name]);
        if(!$bucket){
            return json(['error' => 'no such bucket'], 631);
        }
        $request->bucket = $bucket->name;
        $request->bucket_id = $bucket->id;

        if(isset($param['persistentOps'])){
            $pipeline = PersistentPipeline::get(['user_id' => $user_id, 'name' => $param['persistentPipeline'], 'status' => PersistentPipeline::STATUS_ON]);
            if(!$pipeline){
                return json(['error' => 'no such pipeline'], 612);
            }
        }

        // file directory
        $config = Config::get('upload.');
        $request->upload_dir = $config['dir'];
        $request->user_dir = sprintf('%s%s/', $config['dir'], $request->user_id);
        $request->bucket_dir = sprintf('%s%s/', $request->user_dir, $request->bucket);
        return $next($request);
    }
}
