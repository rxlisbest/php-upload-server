<?php

namespace app\http\middleware;

class OctetStream
{
    public function handle($request, \Closure $next)
    {
        if ($request->save_key === '') {
            $request->save_key = $request->token[1];
        } else {
            $request->key = base64_encode($request->save_key);
        }
        return $next($request);
    }
}
