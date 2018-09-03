<?php

namespace app\admin\validate;

use think\Validate;

class UserPassword extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名'	=>	['规则1','规则2'...]
     *
     * @var array
     */	
	protected $rule = [
        'old_password' => 'require',
        'password' => 'require|confirm',
        'password_confirm' => 'require',
    ];
    
    /**
     * 定义错误信息
     * 格式：'字段名.规则名'	=>	'错误信息'
     *
     * @var array
     */	
    protected $message = [
        'old_password.require' => 'user_change_password_error_empty_old_password',
        'password.require' => 'user_change_password_error_empty_password',
        'password_confirm.require' => 'user_change_password_error_empty_confirm_password',
        'password.confirm' => 'user_change_password_error_confirm_password',
    ];
}
