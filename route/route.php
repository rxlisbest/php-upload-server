<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
Route::rule('status/get/prefop', 'index/Api/prefop');
Route::rule('mkblk/<size>', 'index/Index/mkblk')->pattern(['size' => '\d+']);
Route::rule('mkfile/<size>', 'index/Index/mkfile')->pattern(['size' => '\d+']);

return [

];
