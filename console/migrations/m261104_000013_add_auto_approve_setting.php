<?php

use yii\db\Migration;

/**
 * Class m261104_000013_add_auto_approve_setting
 */
class m261104_000013_add_auto_approve_setting extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $existing = (new \yii\db\Query())
            ->from('{{%system_setting}}')
            ->where(['setting_key' => 'auto_approve_signup'])
            ->exists();

        if (!$existing) {
            $this->insert('{{%system_setting}}', [
                'setting_key' => 'auto_approve_signup',
                'setting_value' => '0',
                'setting_group' => 'user',
                'description' => 'อนุมัติการสมัครสมาชิกใหม่อัตโนมัติ (1 = เปิดใช้งาน, 0 = ปิดใช้งาน/ต้องรอ Admin อนุมัติ)',
                'updated_at' => time(),
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->delete('{{%system_setting}}', ['setting_key' => 'auto_approve_signup']);
    }
}

