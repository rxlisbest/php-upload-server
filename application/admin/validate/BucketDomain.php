<?php

namespace app\admin\validate;

use think\Validate;

class BucketDomain extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名'	=>	['规则1','规则2'...]
     *
     * @var array
     */	
	protected $rule = [
	    'domain' => 'require|regex:^[^\s]*$|unique:bucket_domain,domain'
    ];
    
    /**
     * 定义错误信息
     * 格式：'字段名.规则名'	=>	'错误信息'
     *
     * @var array
     */	
    protected $message = [
        'domain.require' => 'bucket_domain_create_error_empty_domain',
        'domain.regex' => 'bucket_domain_error_format_domain',
        'domain.unique' => 'bucket_domain_error_repeat_domain',
    ];
}
