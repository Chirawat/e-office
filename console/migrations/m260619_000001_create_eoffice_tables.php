<?php

declare(strict_types=1);

use yii\db\Migration;

class m260619_000001_create_eoffice_tables extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%department}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(120)->notNull(),
            'code' => $this->string(40)->null(),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'sort_order' => $this->integer()->notNull()->defaultValue(100),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);
        $this->createIndex('idx-department-is_active', '{{%department}}', 'is_active');

        $this->batchInsert('{{%department}}', ['name', 'code', 'sort_order', 'created_at', 'updated_at'], [
            ['วิชาการ', 'academic', 10, time(), time()],
            ['บุคคล', 'personnel', 20, time(), time()],
            ['งบประมาณ', 'budget', 30, time(), time()],
            ['บริหารทั่วไป', 'general', 40, time(), time()],
            ['กิจการนักเรียน', 'student_affairs', 50, time(), time()],
        ]);

        $this->createTable('{{%document}}', [
            'id' => $this->primaryKey(),
            'receive_no' => $this->string(50)->notNull(),
            'receive_date' => $this->date()->notNull(),
            'receive_time' => $this->time()->null(),
            'external_no' => $this->string(120)->null(),
            'external_date' => $this->date()->null(),
            'sender' => $this->string(255)->notNull(),
            'subject' => $this->string(500)->notNull(),
            'urgency' => $this->string(30)->notNull()->defaultValue('normal'),
            'confidentiality' => $this->string(30)->notNull()->defaultValue('normal'),
            'department_id' => $this->integer()->null(),
            'due_date' => $this->date()->null(),
            'status' => $this->string(40)->notNull()->defaultValue('registered'),
            'note' => $this->text()->null(),
            'created_by' => $this->integer()->null(),
            'updated_by' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);
        $this->createIndex('idx-document-receive_no', '{{%document}}', 'receive_no', true);
        $this->createIndex('idx-document-status', '{{%document}}', 'status');
        $this->createIndex('idx-document-department_id', '{{%document}}', 'department_id');
        $this->createIndex('idx-document-receive_date', '{{%document}}', 'receive_date');
        $this->addForeignKey('fk-document-department_id', '{{%document}}', 'department_id', '{{%department}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk-document-created_by', '{{%document}}', 'created_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk-document-updated_by', '{{%document}}', 'updated_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%document_file}}', [
            'id' => $this->primaryKey(),
            'document_id' => $this->integer()->notNull(),
            'file_type' => $this->string(40)->notNull()->defaultValue('original'),
            'original_name' => $this->string(255)->notNull(),
            'storage_path' => $this->string(500)->notNull(),
            'mime_type' => $this->string(120)->null(),
            'file_size' => $this->integer()->null(),
            'created_by' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);
        $this->createIndex('idx-document_file-document_id', '{{%document_file}}', 'document_id');
        $this->createIndex('idx-document_file-file_type', '{{%document_file}}', 'file_type');
        $this->addForeignKey('fk-document_file-document_id', '{{%document_file}}', 'document_id', '{{%document}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-document_file-created_by', '{{%document_file}}', 'created_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%workflow_action}}', [
            'id' => $this->primaryKey(),
            'document_id' => $this->integer()->notNull(),
            'actor_id' => $this->integer()->null(),
            'action' => $this->string(60)->notNull(),
            'from_status' => $this->string(40)->null(),
            'to_status' => $this->string(40)->null(),
            'comment' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);
        $this->createIndex('idx-workflow_action-document_id', '{{%workflow_action}}', 'document_id');
        $this->addForeignKey('fk-workflow_action-document_id', '{{%workflow_action}}', 'document_id', '{{%document}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-workflow_action-actor_id', '{{%workflow_action}}', 'actor_id', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%document_assignment}}', [
            'id' => $this->primaryKey(),
            'document_id' => $this->integer()->notNull(),
            'department_id' => $this->integer()->null(),
            'assigned_to' => $this->integer()->null(),
            'assigned_by' => $this->integer()->null(),
            'instruction' => $this->text()->null(),
            'status' => $this->string(40)->notNull()->defaultValue('pending'),
            'due_date' => $this->date()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);
        $this->createIndex('idx-document_assignment-document_id', '{{%document_assignment}}', 'document_id');
        $this->addForeignKey('fk-document_assignment-document_id', '{{%document_assignment}}', 'document_id', '{{%document}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-document_assignment-department_id', '{{%document_assignment}}', 'department_id', '{{%department}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk-document_assignment-assigned_to', '{{%document_assignment}}', 'assigned_to', '{{%user}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk-document_assignment-assigned_by', '{{%document_assignment}}', 'assigned_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%document_stamp}}', [
            'id' => $this->primaryKey(),
            'document_id' => $this->integer()->notNull(),
            'stamp_type' => $this->string(60)->notNull(),
            'page_no' => $this->integer()->notNull()->defaultValue(1),
            'position_x' => $this->decimal(8, 2)->null(),
            'position_y' => $this->decimal(8, 2)->null(),
            'content' => $this->text()->null(),
            'created_by' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);
        $this->createIndex('idx-document_stamp-document_id', '{{%document_stamp}}', 'document_id');
        $this->addForeignKey('fk-document_stamp-document_id', '{{%document_stamp}}', 'document_id', '{{%document}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-document_stamp-created_by', '{{%document_stamp}}', 'created_by', '{{%user}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%audit_log}}', [
            'id' => $this->primaryKey(),
            'document_id' => $this->integer()->null(),
            'user_id' => $this->integer()->null(),
            'event' => $this->string(120)->notNull(),
            'detail' => $this->text()->null(),
            'ip_address' => $this->string(64)->null(),
            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);
        $this->createIndex('idx-audit_log-document_id', '{{%audit_log}}', 'document_id');
        $this->addForeignKey('fk-audit_log-document_id', '{{%audit_log}}', 'document_id', '{{%document}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk-audit_log-user_id', '{{%audit_log}}', 'user_id', '{{%user}}', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%audit_log}}');
        $this->dropTable('{{%document_stamp}}');
        $this->dropTable('{{%document_assignment}}');
        $this->dropTable('{{%workflow_action}}');
        $this->dropTable('{{%document_file}}');
        $this->dropTable('{{%document}}');
        $this->dropTable('{{%department}}');
    }
}
