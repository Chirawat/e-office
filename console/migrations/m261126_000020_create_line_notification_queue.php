<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Class m261126_000020_create_line_notification_queue
 */
class m261126_000020_create_line_notification_queue extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        if ($this->db->getTableSchema('{{%line_notification_queue}}') === null) {
            $tableOptions = null;
            if ($this->db->driverName === 'mysql') {
                $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
            }

            $this->createTable('{{%line_notification_queue}}', [
                'id' => $this->primaryKey(),
                'user_id' => $this->integer()->notNull(),
                'event_type' => $this->string(40)->notNull(),
                'ref_type' => $this->string(20)->notNull(),
                'ref_id' => $this->integer()->notNull(),
                'title' => $this->string(255)->notNull(),
                'subject' => $this->string(500)->notNull(),
                'detail' => $this->text()->null(),
                'is_urgent' => $this->boolean()->notNull()->defaultValue(false),
                'status' => $this->string(20)->notNull()->defaultValue('pending'),
                'batch_key' => $this->string(64)->null(),
                'created_at' => $this->integer()->notNull(),
                'sent_at' => $this->integer()->null(),
            ], $tableOptions);

            $this->createIndex('idx-line_queue-user_status', '{{%line_notification_queue}}', ['user_id', 'status']);
            $this->createIndex('idx-line_queue-status_created', '{{%line_notification_queue}}', ['status', 'created_at']);
            $this->createIndex('idx-line_queue-sent_at', '{{%line_notification_queue}}', ['sent_at']);
            $this->createIndex('idx-line_queue-batch_key', '{{%line_notification_queue}}', ['batch_key']);
            $this->addForeignKey(
                'fk-line_queue-user_id',
                '{{%line_notification_queue}}',
                'user_id',
                '{{%user}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        if ($this->db->getTableSchema('{{%line_notification_queue}}') !== null) {
            $this->dropForeignKey('fk-line_queue-user_id', '{{%line_notification_queue}}');
            $this->dropTable('{{%line_notification_queue}}');
        }
    }
}
