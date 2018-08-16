<?php

use think\migration\Seeder;

class Bucket extends Seeder
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
        Db::name('bucket')->where('id', '>', 0)->delete();

        $data = [
            ['id' => 1, 'name' => 'bucket_1', 'user_id' => 1, 'create_time' => time(), 'update_time' => time()],
            ['id' => 2, 'name' => 'bucket_2', 'user_id' => 1, 'create_time' => time(), 'update_time' => time()]
        ];

        Db::name('bucket')->insertAll($data);
    }
}