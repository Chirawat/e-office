<?php

declare(strict_types=1);

use yii\db\Migration;

class m260620_000005_create_user_department extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%user_department}}', [
            'user_id' => $this->integer()->notNull(),
            'department_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
            'PRIMARY KEY(user_id, department_id)',
        ]);

        $this->addForeignKey('fk-user_department-user_id', '{{%user_department}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-user_department-department_id', '{{%user_department}}', 'department_id', '{{%department}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-user_department-department_id', '{{%user_department}}');
        $this->dropForeignKey('fk-user_department-user_id', '{{%user_department}}');
        $this->dropTable('{{%user_department}}');
    }
}
