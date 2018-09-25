<?php

namespace app\http\middleware;

class OctetStream
{
    public function handle($request, \Closure $next)
    {
        if($request->key === ''){
            $request->key = $request->token[1];
        }
        else{
            $request->old_key = $request->token[1];
        }
        return $next($request);
    }
}
