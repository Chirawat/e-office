<?php

use yii\db\Migration;

class m260621_000008_create_memo_stamp_position extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%memo_stamp_position}}', [
            'id' => $this->primaryKey(),
            'memo_id' => $this->integer()->notNull(),
            'stamp_type' => $this->string(60)->notNull(),
            'position_x' => $this->decimal(8, 2),
            'position_y' => $this->decimal(8, 2),
            'created_by' => $this->integer(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);

        $this->createIndex('idx-memo_stamp_position-memo_id-stamp_type', '{{%memo_stamp_position}}', ['memo_id', 'stamp_type'], true);
        $this->addForeignKey(
            'fk-memo_stamp_position-memo_id',
            '{{%memo_stamp_position}}',
            'memo_id',
            '{{%memo}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-memo_stamp_position-memo_id', '{{%memo_stamp_position}}');
        $this->dropTable('{{%memo_stamp_position}}');
    }
}
