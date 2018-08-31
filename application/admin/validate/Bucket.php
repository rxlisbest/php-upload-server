<?php

namespace app\admin\validate;

use think\Validate;

class Bucket extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名'	=>	['规则1','规则2'...]
     *
     * @var array
     */	
	protected $rule = [
	    'name' => 'require|regex:^[0-9a-zA-Z-]*$|length:4,63|unique:bucket,name^user_id'
    ];
    
    /**
     * 定义错误信息
     * 格式：'字段名.规则名'	=>	'错误信息'
     *
     * @var array
     */	
    protected $message = [
        'name.require' => 'bucket_create_error_empty_name',
        'name.regex' => 'bucket_create_error_format_name',
        'name.length' => 'bucket_create_error_length_name',
        'name.unique' => 'bucket_create_error_repeat_name',
    ];
}
