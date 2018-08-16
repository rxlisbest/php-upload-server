<?php

use think\migration\Seeder;

class User extends Seeder
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run()
    {
        Db::name('user')->where('id', '>', 0)->delete();

        $data = [
            ['id' => 1, 'user_name' => 'roy', 'password' => md5('roy'), 'create_time' => time(), 'update_time' => time()]
        ];

        Db::name('user')->insertAll($data);
    }
}