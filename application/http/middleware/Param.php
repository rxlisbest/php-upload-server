<?php

namespace app\http\middleware;

use app\common\model\Bucket;
use app\common\model\PersistentPipeline;

class Param
{
    public function handle($request, \Closure $next)
    {
        $param = $request->param;
        $user_id = $request->user_id;

        if(count(explode(':', $param['scope'])) > 1){
            $bucket_name = explode(':', $param['scope'])[0];
        }
        else{
            $bucket_name = $param['scope'];
        }
        $bucket = Bucket::get(['user_id' => $user_id, 'name' => $bucket_name]);
        if(!$bucket){
            return json(['error' => 'no such bucket'], 631);
        }

        if(isset($param['persistentOps'])){
            $pipeline = PersistentPipeline::get(['user_id' => $user_id, 'name' => $param['persistentPipeline']]);
            if(!$pipeline){
                return json(['error' => 'no such pipeline'], 612);
            }
        }
        return $next($request);
    }
}
