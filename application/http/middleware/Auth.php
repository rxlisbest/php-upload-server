<?php

namespace app\http\middleware;

use app\common\model\UserKey;
use Qiniu\Auth AS QiniuAuth;

class Auth
{
    public function handle($request, \Closure $next)
    {
        if(!$request->isPost()){
            return json(['error' => 'only allow POST method'], 405);
        }
        $token = $request->post('token');
        $token = explode(':', $token);
        $param = json_decode(base64_decode($token[2]), true);
        $access_key = $token[0];
        $user_key = UserKey::get(['access_key' => $access_key]);
        if(!$user_key){
            return json(['error' => 'bad token'], 401);
        }
        $secret_key = $user_key->secret_key;
        $auth = new QiniuAuth($access_key, $secret_key);
        $sign = $auth->signWithData(json_encode($param));

        if($sign !== implode(':', $token)){
            return json(['error' => 'bad token'], 401);
        }
        $request->param = $param;
        $request->user_id = $user_key->user_id;
        return $next($request);
    }
}