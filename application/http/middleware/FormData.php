<?php

namespace app\http\middleware;

class FormData
{
    public function handle($request, \Closure $next)
    {
        $post = $request->post();
        if($request->save_key === ''){
            $request->save_key = $post['key'];
        }
        return $next($request);
    }
}
