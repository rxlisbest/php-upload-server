<?php

namespace app\http\middleware;

class Cors
{
    public function handle($request, \Closure $next)
    {
        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Methods:*');
        header('Access-Control-Allow-Headers:*');
        header('Access-Control-Allow-Credentials:false');
        if($request->isOptions()){
            return json([]);
        }
        return $next($request);
    }
}
