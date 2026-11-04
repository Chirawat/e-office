<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Migration for converting user table to utf8mb4_unicode_ci to support emoji in line_display_name and other fields.
 */
class m261104_000012_convert_user_table_to_utf8mb4 extends Migration
{
    public function safeUp(): void
    {
        if ($this->db->driverName === 'mysql') {
            $this->execute('ALTER TABLE {{%user}} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }
    }

    public function safeDown(): void
    {
        if ($this->db->driverName === 'mysql') {
            $this->execute('ALTER TABLE {{%user}} CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        }
    }
}

