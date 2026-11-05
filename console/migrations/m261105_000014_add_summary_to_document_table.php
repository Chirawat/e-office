<?php

use yii\db\Migration;

/**
 * Handles adding columns to table `{{%document}}`.
 */
class m261105_000014_add_summary_to_document_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%document}}', 'summary', $this->text()->null()->after('subject'));

        // ตั้งค่าเริ่มต้นให้กับข้อมูลหนังสือที่มีอยู่แล้ว ให้ summary = subject
        $this->execute("UPDATE {{%document}} SET summary = subject WHERE summary IS NULL OR summary = ''");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%document}}', 'summary');
    }
}

