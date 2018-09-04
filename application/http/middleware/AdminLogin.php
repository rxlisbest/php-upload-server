<?php

namespace app\http\middleware;

use think\facade\Session;

class AdminLogin
{
    public function handle($request, \Closure $next)
    {
        if(Session::has('user')){
            return redirect(url('admin/Index/index'));
        }
        return $next($request);
    }
}
