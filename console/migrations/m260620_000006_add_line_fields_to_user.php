<?php

declare(strict_types=1);

use yii\db\Migration;

class m260620_000006_add_line_fields_to_user extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%user}}', 'line_user_id', $this->string(80)->null()->after('signature_path'));
        $this->addColumn('{{%user}}', 'line_display_name', $this->string(120)->null()->after('line_user_id'));
        $this->addColumn('{{%user}}', 'line_link_token', $this->string(40)->null()->after('line_display_name'));
        $this->addColumn('{{%user}}', 'line_link_token_expires_at', $this->integer()->null()->after('line_link_token'));
        $this->addColumn('{{%user}}', 'line_linked_at', $this->integer()->null()->after('line_link_token_expires_at'));

        $this->createIndex('idx-user-line_user_id', '{{%user}}', 'line_user_id', true);
        $this->createIndex('idx-user-line_link_token', '{{%user}}', 'line_link_token', true);
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx-user-line_link_token', '{{%user}}');
        $this->dropIndex('idx-user-line_user_id', '{{%user}}');
        $this->dropColumn('{{%user}}', 'line_linked_at');
        $this->dropColumn('{{%user}}', 'line_link_token_expires_at');
        $this->dropColumn('{{%user}}', 'line_link_token');
        $this->dropColumn('{{%user}}', 'line_display_name');
        $this->dropColumn('{{%user}}', 'line_user_id');
    }
}
