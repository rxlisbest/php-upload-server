<?php

namespace app\http\middleware;

class FormData
{
    public function handle($request, \Closure $next)
    {
        $post = $request->post();
        if($request->key === ''){
            $request->key = $post['key'];
        }
        return $next($request);
    }
}
