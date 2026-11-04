<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Migration for creating document_read and memo_read tables to track per-user read/unread status.
 */
class m261104_000011_create_document_and_memo_read extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        // 1. Table document_read
        $this->createTable('{{%document_read}}', [
            'id' => $this->primaryKey(),
            'document_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'read_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx-doc_read-user_doc', '{{%document_read}}', ['user_id', 'document_id'], true);
        $this->createIndex('idx-doc_read-doc', '{{%document_read}}', 'document_id');
        $this->createIndex('idx-doc_read-user', '{{%document_read}}', 'user_id');

        $this->addForeignKey(
            'fk-doc_read-doc',
            '{{%document_read}}',
            'document_id',
            '{{%document}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-doc_read-user',
            '{{%document_read}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // 2. Table memo_read
        $this->createTable('{{%memo_read}}', [
            'id' => $this->primaryKey(),
            'memo_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'read_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx-memo_read-user_memo', '{{%memo_read}}', ['user_id', 'memo_id'], true);
        $this->createIndex('idx-memo_read-memo', '{{%memo_read}}', 'memo_id');
        $this->createIndex('idx-memo_read-user', '{{%memo_read}}', 'user_id');

        $this->addForeignKey(
            'fk-memo_read-memo',
            '{{%memo_read}}',
            'memo_id',
            '{{%memo}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-memo_read-user',
            '{{%memo_read}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-memo_read-user', '{{%memo_read}}');
        $this->dropForeignKey('fk-memo_read-memo', '{{%memo_read}}');
        $this->dropTable('{{%memo_read}}');

        $this->dropForeignKey('fk-doc_read-user', '{{%document_read}}');
        $this->dropForeignKey('fk-doc_read-doc', '{{%document_read}}');
        $this->dropTable('{{%document_read}}');
    }
}

