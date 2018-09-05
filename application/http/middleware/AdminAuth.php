<?php

namespace app\http\middleware;

use think\facade\Session;

class AdminAuth
{
    public function handle($request, \Closure $next)
    {
        if(!Session::has('user')){
            return redirect(url('admin/Login/index'));
        }
        $request->user = Session::get('user');
        return $next($request);
    }
}
