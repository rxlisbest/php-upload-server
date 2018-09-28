<?php

namespace app\http\middleware;

class FormData
{
    public function handle($request, \Closure $next)
    {
        $post = $request->post();
        if ($request->save_key === '') {
            if (isset($post['key']) && trim($post['key'])) {
                $request->save_key = trim($post['key']);
            } else {
                $request->save_key = uniqid();
            }
        }
        return $next($request);
    }
}
