<?php

use think\migration\Seeder;
use think\Db;

class PersistentPipeline extends Seeder
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
        Db::name('persistent_pipeline')->where('id', '>', 0)->delete();

        $data = [
            ['id' => 1, 'name' => 'pipeline_1', 'user_id' => 1, 'create_time' => time(), 'update_time' => time()],
            ['id' => 2, 'name' => 'pipeline_2', 'user_id' => 1, 'create_time' => time(), 'update_time' => time()],
            ['id' => 3, 'name' => 'pipeline_3', 'user_id' => 1, 'create_time' => time(), 'update_time' => time()]
        ];

        Db::name('persistent_pipeline')->insertAll($data);
    }
}