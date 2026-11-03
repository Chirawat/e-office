<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Migration for creating system_setting table and initial setting rows.
 */
class m261103_000010_create_system_setting extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%system_setting}}', [
            'id' => $this->primaryKey(),
            'setting_key' => $this->string(64)->notNull()->unique(),
            'setting_value' => $this->text()->null(),
            'setting_group' => $this->string(32)->defaultValue('general'),
            'description' => $this->string(255)->null(),
            'updated_at' => $this->integer()->null(),
            'updated_by' => $this->integer()->null(),
        ], $tableOptions);

        $this->createIndex('idx-system_setting-key', '{{%system_setting}}', 'setting_key', true);
        $this->createIndex('idx-system_setting-group', '{{%system_setting}}', 'setting_group');

        $now = time();
        $currentYear = (int) date('Y') + 543;

        $initialSettings = [
            [
                'setting_key' => 'school_name',
                'setting_value' => 'โรงเรียนกุดชุมวิทยาคม',
                'setting_group' => 'general',
                'description' => 'ชื่อสถานศึกษา',
            ],
            [
                'setting_key' => 'director_name',
                'setting_value' => '',
                'setting_group' => 'executive',
                'description' => 'ชื่อผู้อำนวยการโรงเรียน',
            ],
            [
                'setting_key' => 'director_position',
                'setting_value' => 'ผู้อำนวยการโรงเรียนกุดชุมวิทยาคม',
                'setting_group' => 'executive',
                'description' => 'ตำแหน่งผู้อำนวยการโรงเรียน',
            ],
            [
                'setting_key' => 'acting_director_name',
                'setting_value' => '',
                'setting_group' => 'executive',
                'description' => 'ชื่อรองผู้อำนวยการ รักษาการในตำแหน่งผู้อำนวยการโรงเรียน',
            ],
            [
                'setting_key' => 'acting_director_position',
                'setting_value' => 'รองผู้อำนวยการ รักษาการในตำแหน่งผู้อำนวยการโรงเรียนกุดชุมวิทยาคม',
                'setting_group' => 'executive',
                'description' => 'ตำแหน่งรักษาการแทนผู้อำนวยการโรงเรียน',
            ],
            [
                'setting_key' => 'is_acting_director',
                'setting_value' => '0',
                'setting_group' => 'executive',
                'description' => 'ใช้ชื่อรักษาการแทนผู้อำนวยการ (0=ไม่ใช้, 1=ใช้)',
            ],
            [
                'setting_key' => 'doc_receive_start_no',
                'setting_value' => '1',
                'setting_group' => 'document',
                'description' => 'เลขรับหนังสือเริ่มต้น',
            ],
            [
                'setting_key' => 'doc_receive_year',
                'setting_value' => (string) $currentYear,
                'setting_group' => 'document',
                'description' => 'ปี พ.ศ. ของเลขรับหนังสือ',
            ],
        ];

        foreach ($initialSettings as $setting) {
            $this->insert('{{%system_setting}}', [
                'setting_key' => $setting['setting_key'],
                'setting_value' => $setting['setting_value'],
                'setting_group' => $setting['setting_group'],
                'description' => $setting['description'],
                'updated_at' => $now,
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%system_setting}}');
    }
}

