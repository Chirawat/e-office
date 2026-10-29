<?php

declare(strict_types=1);

use yii\db\Migration;

class m260620_000007_create_memo_tables extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%memo}}', [
            'id' => $this->primaryKey(),
            'memo_no' => $this->string(50)->null(),
            'memo_date' => $this->date()->notNull(),
            'department_id' => $this->integer()->null(),
            'subject' => $this->string(500)->notNull(),
            'to_text' => $this->string(255)->notNull()->defaultValue('ผู้อำนวยการโรงเรียน'),
            'background' => $this->text()->null(),
            'facts' => $this->text()->null(),
            'legal' => $this->text()->null(),
            'consideration' => $this->text()->null(),
            'proposal' => $this->text()->null(),
            'closing_text' => $this->string(255)->null(),
            'status' => $this->string(40)->notNull()->defaultValue('draft'),
            'current_actor_id' => $this->integer()->null(),
            'created_by' => $this->integer()->null(),
            'updated_by' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);
        $this->createIndex('idx-memo-memo_no', '{{%memo}}', 'memo_no');
        $this->createIndex('idx-memo-status', '{{%memo}}', 'status');
        $this->createIndex('idx-memo-department_id', '{{%memo}}', 'department_id');
        $this->createIndex('idx-memo-current_actor_id', '{{%memo}}', 'current_actor_id');
        $this->addForeignKey('fk-memo-department_id', '{{%memo}}', 'department_id', '{{%department}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk-memo-current_actor_id', '{{%memo}}', 'current_actor_id', '{{%user}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk-memo-created_by', '{{%memo}}', 'created_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk-memo-updated_by', '{{%memo}}', 'updated_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%memo_file}}', [
            'id' => $this->primaryKey(),
            'memo_id' => $this->integer()->notNull(),
            'original_name' => $this->string(255)->notNull(),
            'storage_path' => $this->string(500)->notNull(),
            'mime_type' => $this->string(120)->null(),
            'file_size' => $this->integer()->null(),
            'description' => $this->string(255)->null(),
            'created_by' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);
        $this->createIndex('idx-memo_file-memo_id', '{{%memo_file}}', 'memo_id');
        $this->addForeignKey('fk-memo_file-memo_id', '{{%memo_file}}', 'memo_id', '{{%memo}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-memo_file-created_by', '{{%memo_file}}', 'created_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%memo_action}}', [
            'id' => $this->primaryKey(),
            'memo_id' => $this->integer()->notNull(),
            'actor_id' => $this->integer()->null(),
            'action' => $this->string(60)->notNull(),
            'from_status' => $this->string(40)->null(),
            'to_status' => $this->string(40)->null(),
            'comment' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);
        $this->createIndex('idx-memo_action-memo_id', '{{%memo_action}}', 'memo_id');
        $this->addForeignKey('fk-memo_action-memo_id', '{{%memo_action}}', 'memo_id', '{{%memo}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-memo_action-actor_id', '{{%memo_action}}', 'actor_id', '{{%user}}', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%memo_action}}');
        $this->dropTable('{{%memo_file}}');
        $this->dropTable('{{%memo}}');
    }
}
