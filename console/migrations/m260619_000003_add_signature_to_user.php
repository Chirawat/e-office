<?php

declare(strict_types=1);

use yii\db\Migration;

class m260619_000003_add_signature_to_user extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%user}}', 'signature_path', $this->string(500)->null()->after('role'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%user}}', 'signature_path');
    }
}
