<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Class m261124_000018_add_deputy_title_to_user
 */
class m261124_000018_add_deputy_title_to_user extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        $userTable = $this->db->getTableSchema('{{%user}}');
        if ($userTable !== null && $userTable->getColumn('deputy_title') === null) {
            $this->addColumn(
                '{{%user}}',
                'deputy_title',
                $this->string(40)->null()->defaultValue(null)->after('role')
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        $userTable = $this->db->getTableSchema('{{%user}}');
        if ($userTable !== null && $userTable->getColumn('deputy_title') !== null) {
            $this->dropColumn('{{%user}}', 'deputy_title');
        }
    }
}
