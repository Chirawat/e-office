<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Class m261107_000016_add_learning_area_and_management_department
 */
class m261107_000016_add_learning_area_and_management_department extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        // 1. เพิ่มฟิลด์ learning_area ในตาราง user (ถ้ายังไม่มี)
        $userTable = $this->db->getTableSchema('{{%user}}');
        if ($userTable !== null && $userTable->getColumn('learning_area') === null) {
            $this->addColumn('{{%user}}', 'learning_area', $this->string(150)->null()->defaultValue(null)->after('department_id'));
            $this->createIndex('idx-user-learning_area', '{{%user}}', 'learning_area');
        }

        // 2. เพิ่มกลุ่มงาน "ผู้บริหารสถานศึกษา" ลงในตาราง department (ถ้ายังไม่มี)
        $deptExists = (new \yii\db\Query())
            ->from('{{%department}}')
            ->where(['code' => 'management'])
            ->orWhere(['name' => 'ผู้บริหารสถานศึกษา'])
            ->exists($this->db);

        if (!$deptExists) {
            $now = time();
            $this->insert('{{%department}}', [
                'name' => 'ผู้บริหารสถานศึกษา',
                'code' => 'management',
                'is_active' => 1,
                'sort_order' => 5, // วางไว้ลำดับแรกสุดก่อน 5 กลุ่มงานบริหาร
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        $this->delete('{{%department}}', ['code' => 'management']);

        $userTable = $this->db->getTableSchema('{{%user}}');
        if ($userTable !== null && $userTable->getColumn('learning_area') !== null) {
            $this->dropIndex('idx-user-learning_area', '{{%user}}');
            $this->dropColumn('{{%user}}', 'learning_area');
        }
    }
}
