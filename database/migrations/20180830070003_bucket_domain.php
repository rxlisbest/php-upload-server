<?php

use think\migration\Migrator;
use think\migration\db\Column;

class BucketDomain extends Migrator
{
    public function up(){
        $table = $this->table('bucket_domain', array('engine'=>'InnoDB'));
        $table->addColumn(Column::string('domain')->setNullable()->setLimit(255)->setComment('domain'))
            ->addColumn(Column::string('cname')->setNullable()->setLimit(255)->setComment('cname'))
            ->addColumn(Column::integer('bucket_id')->setNull(false)->setDefault(0)->setComment('bucket id'))
            ->addColumn(Column::tinyInteger('status')->setNull(false)->setDefault(1)->setComment('status：0：invalid，1：valid'))
            ->addColumn(Column::bigInteger('create_time')->setNull(false)->setDefault(0)->setLimit(20)->setComment('create time'))
            ->addColumn(Column::bigInteger('update_time')->setNull(false)->setDefault(0)->setLimit(20)->setComment('update time'))
            ->addIndex(array('id'), array('unique' => true))
            ->addIndex(array('domain'))
            ->create();
    }

    public function down(){
        $this->dropTable('bucket_domain');
    }
}
