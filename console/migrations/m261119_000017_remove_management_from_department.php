<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Class m261119_000017_remove_management_from_department
 */
class m261119_000017_remove_management_from_department extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        $this->delete('{{%department}}', ['code' => 'management']);
        $this->delete('{{%department}}', ['name' => 'ผู้บริหารสถานศึกษา']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        $now = time();
        $this->insert('{{%department}}', [
            'name' => 'ผู้บริหารสถานศึกษา',
            'code' => 'management',
            'is_active' => 1,
            'sort_order' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
