<?php

use think\migration\Migrator;
use think\migration\db\Column;

class Persistent extends Migrator
{
    public function up(){
        $table = $this->table('persistent', array('engine'=>'InnoDB'));
        $table->addColumn(Column::string('persistent_id')->setNull(false)->setLimit(255)->setComment('persistentId'))
            ->addColumn(Column::string('ops')->setNull(false)->setLimit(255)->setComment('persistentOps'))
            ->addColumn(Column::string('pipeline')->setNull(false)->setLimit(255)->setComment('persistentPipeline'))
            ->addColumn(Column::string('notify_url')->setNullable()->setLimit(255)->setComment('persistentNotifyUrl'))
            ->addColumn(Column::string('upload_dir')->setNull(false)->setLimit(255)->setComment('upload dir'))
            ->addColumn(Column::string('input_bucket')->setNull(false)->setLimit(255)->setComment('input bucket'))
            ->addColumn(Column::string('input_key')->setNull(false)->setLimit(255)->setComment('source'))
            ->addColumn(Column::string('output_bucket')->setNull(false)->setLimit(255)->setComment('output bucket'))
            ->addColumn(Column::string('output_key')->setNull(false)->setLimit(255)->setComment('output key'))
            ->addColumn(Column::integer('user_id')->setNull(false)->setDefault(0)->setComment('user id'))
            ->addColumn(Column::tinyInteger('status')->setNull(false)->setDefault(0)->setComment('status: 0:waiting,1:success,2:transcoding fail,3:notify fail'))
            ->addColumn(Column::bigInteger('create_time')->setNull(false)->setDefault(0)->setLimit(20)->setComment('create time'))
            ->addColumn(Column::bigInteger('update_time')->setNull(false)->setDefault(0)->setLimit(20)->setComment('update time'))
            ->addIndex(array('id'), array('unique' => true))
            ->addIndex(array('persistent_id'))
            ->addIndex(array('user_id'))
            ->create();
    }

    public function down(){
        $this->dropTable('persistent');
    }
}
