<?php

use think\migration\Migrator;
use think\migration\db\Column;

class User extends Migrator
{
    public function up(){
        $table = $this->table('user', array('engine'=>'InnoDB'));
        $table->addColumn(Column::string('user_name')->setNull(false)->setLimit(255)->setComment('user name'))
            ->addColumn(Column::string('password')->setNull(false)->setDefault(0)->setComment('password'))
            ->addColumn(Column::tinyInteger('status')->setNull(false)->setDefault(1)->setComment('status：0：invalid，1：valid'))
            ->addColumn(Column::bigInteger('create_time')->setNull(false)->setDefault(0)->setLimit(20)->setComment('create time'))
            ->addColumn(Column::bigInteger('update_time')->setNull(false)->setDefault(0)->setLimit(20)->setComment('update time'))
            ->addIndex(array('id'), array('unique' => true))
            ->addIndex(array('user_name'))
            ->create();
    }

    public function down(){
        $this->dropTable('user');
    }
}
