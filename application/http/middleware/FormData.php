<?php

namespace app\http\middleware;

class FormData
{
    public function handle($request, \Closure $next)
    {

        return $next($request);
    }
}
