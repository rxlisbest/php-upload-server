<?php

use think\migration\Seeder;

class UserKey extends Seeder
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
        Db::name('user_key')->where('id', '>', 0)->delete();

        $data = [
            ['id' => 1, 'access_key' => 'hjr7j6yL1Rr4PPLoeVflHGt0jF8qSJfg7GU8bHvb', 'secret_key' => 'UQJDIuM6vV4NpzqCXjBbhLu5wrvTOxk2O947Nj8i', 'user_id' => 1, 'create_time' => time(), 'update_time' => time()]
        ];

        Db::name('user_key')->insertAll($data);
    }
}