<?php

namespace app\http\middleware;

use app\common\model\UserKey;
use Qiniu\Auth AS QiniuAuth;
use app\common\model\UserKey AS UserKeyModel;

class Auth
{
    public function handle($request, \Closure $next)
    {
        if (!$request->isPost()) {
            return json(['error' => 'only allow POST method'], 405);
        }

        $header = getallheaders();
        $authorization = '';
        foreach ($header as $k => $v) {
            if(strtolower($k) == 'authorization'){
                $authorization = $v;
            }
        }

        if ($authorization !== '') {
            $authorization = explode(' ', $authorization);
            $token = $authorization[1];
        } else {
            $token = $request->post('token');
        }

        if (!$token) {
            return json(['error' => 'token can not empty'], 405);
        }

        $token = explode(':', $token);
        $request->token = $token;

        $param = json_decode(base64_decode($token[2]), true);
        $access_key = $token[0];
        $user_key = UserKey::get(['access_key' => $access_key, 'status' => UserKeyModel::STATUS_ON]);
        if (!$user_key) {
            return json(['error' => 'bad token'], 401);
        }
        $secret_key = $user_key->secret_key;
        $auth = new QiniuAuth($access_key, $secret_key);
        $sign = $auth->signWithData(json_encode($param));

        if ($sign !== implode(':', $token)) {
            return json(['error' => 'bad token'], 401);
        }

        $request->param = $param;
        $request->user_id = $user_key->user_id;
        return $next($request);
    }
}
