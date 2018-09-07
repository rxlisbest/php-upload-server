# php-upload-server
===============

#### 项目要求
- linux
- php >= 7.1
- swoole

#### 部署步骤
- git clone https://github.com/rxlisbest/php-upload-server.git
- 新建数据库upload_server
- 修改config/database.php
```
    // 服务器地址
    'hostname'        => '127.0.0.1',
    // 数据库名
    'database'        => 'upload_server',
    // 用户名
    'username'        => 'root',
    // 密码
    'password'        => 'root',
```
- 修改config/upload.php
```
    return [
        // dir
        'dir' => '/Library/WebServer/Documents/htdocs/upload_server/public/upload/', // 上传文件的根目录
    ];
```
- 项目根目录执行命令 php think migrate:run
- 项目根目录执行命令 php think seed:run
- 项目根目录执行命令 composer update
- 将虚拟目录配置到public文件夹下
- 项目根目录执行命令 php public/index.php index/Index/process（启动转码队列）

#### 说明
- 此项目可以与https://github.com/rxlisbest/dynamic-domain配合使用
