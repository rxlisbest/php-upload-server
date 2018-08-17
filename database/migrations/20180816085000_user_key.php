<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UserKey extends Migrator
{
    public function up(){
        $table = $this->table('user_key', array('engine'=>'InnoDB'));
        $table->addColumn(Column::string('access_key')->setNull(false)->setLimit(255)->setComment('access key'))
            ->addColumn(Column::string('secret_key')->setNull(false)->setLimit(255)->setComment('secret key'))
            ->addColumn(Column::integer('user_id')->setNull(false)->setDefault(0)->setComment('user id'))
            ->addColumn(Column::tinyInteger('status')->setNull(false)->setDefault(1)->setComment('status：0：invalid，1：valid'))
            ->addColumn(Column::bigInteger('create_time')->setNull(false)->setDefault(0)->setLimit(20)->setComment('create time'))
            ->addColumn(Column::bigInteger('update_time')->setNull(false)->setDefault(0)->setLimit(20)->setComment('update time'))
            ->addIndex(array('id'), array('unique' => true))
            ->addIndex(array('access_key'))
            ->addIndex(array('secret_key'))
            ->create();
    }

    public function down(){
        $this->dropTable('user_key');
    }
}
