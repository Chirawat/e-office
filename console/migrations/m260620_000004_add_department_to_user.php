<?php

declare(strict_types=1);

use yii\db\Migration;

class m260620_000004_add_department_to_user extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%user}}', 'department_id', $this->integer()->null()->after('role'));
        $this->createIndex('idx-user-department_id', '{{%user}}', 'department_id');
        $this->addForeignKey(
            'fk-user-department_id',
            '{{%user}}',
            'department_id',
            '{{%department}}',
            'id',
            'SET NULL',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-user-department_id', '{{%user}}');
        $this->dropIndex('idx-user-department_id', '{{%user}}');
        $this->dropColumn('{{%user}}', 'department_id');
    }
}
