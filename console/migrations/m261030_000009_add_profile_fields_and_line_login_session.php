<?php

declare(strict_types=1);

use yii\db\Migration;

class m261030_000009_add_profile_fields_and_line_login_session extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        // 1. เพิ่มฟิลด์ข้อมูลโปรไฟล์ครูในตาราง user
        $this->addColumn('{{%user}}', 'fullname', $this->string(150)->null()->after('username'));
        $this->addColumn('{{%user}}', 'phone', $this->string(30)->null()->after('fullname'));
        $this->addColumn('{{%user}}', 'id_card', $this->string(20)->null()->after('phone'));

        $this->createIndex('idx-user-id_card', '{{%user}}', 'id_card');
        $this->createIndex('idx-user-phone', '{{%user}}', 'phone');

        // 2. สร้างตาราง line_login_session สำหรับระบบ Login ผ่าน LINE / QR Code / Magic Code
        $this->createTable('{{%line_login_session}}', [
            'id' => $this->primaryKey(),
            'session_token' => $this->string(64)->notNull(),
            'login_code' => $this->string(20)->notNull(),
            'user_id' => $this->integer()->null(),
            'ip_address' => $this->string(45)->null(),
            'user_agent' => $this->string(255)->null(),
            'status' => $this->string(20)->notNull()->defaultValue('pending'),
            'expires_at' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx-line_login_session-session_token', '{{%line_login_session}}', 'session_token', true);
        $this->createIndex('idx-line_login_session-login_code', '{{%line_login_session}}', 'login_code', true);
        $this->createIndex('idx-line_login_session-status', '{{%line_login_session}}', 'status');
        $this->createIndex('idx-line_login_session-expires_at', '{{%line_login_session}}', 'expires_at');
        $this->addForeignKey('fk-line_login_session-user_id', '{{%line_login_session}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-line_login_session-user_id', '{{%line_login_session}}');
        $this->dropTable('{{%line_login_session}}');

        $this->dropIndex('idx-user-phone', '{{%user}}');
        $this->dropIndex('idx-user-id_card', '{{%user}}');
        $this->dropColumn('{{%user}}', 'id_card');
        $this->dropColumn('{{%user}}', 'phone');
        $this->dropColumn('{{%user}}', 'fullname');
    }
}

