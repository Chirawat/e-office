<?php

declare(strict_types=1);

use yii\db\Migration;

class m260619_000002_add_role_to_user extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%user}}', 'role', $this->string(40)->notNull()->defaultValue('officer')->after('email'));
        $this->createIndex('idx-user-role', '{{%user}}', 'role');
        $this->update('{{%user}}', ['role' => 'admin'], ['username' => 'admin']);
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx-user-role', '{{%user}}');
        $this->dropColumn('{{%user}}', 'role');
    }
}
