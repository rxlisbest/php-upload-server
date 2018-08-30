<?php

use think\migration\Migrator;
use think\migration\db\Column;

class File extends Migrator
{
    public function up(){
        $table = $this->table('file', array('engine'=>'InnoDB'));
        $table->addColumn(Column::string('name')->setNullable()->setLimit(255)->setComment('name'))
            ->addColumn(Column::integer('bucket_id')->setNull(false)->setDefault(0)->setComment('bucket id'))
            ->addColumn(Column::tinyInteger('status')->setNull(false)->setDefault(1)->setComment('status：0：invalid，1：valid'))
            ->addColumn(Column::bigInteger('create_time')->setNull(false)->setDefault(0)->setLimit(20)->setComment('create time'))
            ->addColumn(Column::bigInteger('update_time')->setNull(false)->setDefault(0)->setLimit(20)->setComment('update time'))
            ->addIndex(array('id'), array('unique' => true))
            ->addIndex(array('name'))
            ->create();
    }

    public function down(){
        $this->dropTable('file');
    }
}
