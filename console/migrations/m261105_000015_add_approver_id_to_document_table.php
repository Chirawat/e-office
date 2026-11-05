<?php

use yii\db\Migration;

/**
 * Handles adding approver_id to table `{{%document}}`.
 */
class m261105_000015_add_approver_id_to_document_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%document}}', 'approver_id', $this->integer()->null()->after('department_id'));

        $this->createIndex(
            'idx-document-approver_id',
            '{{%document}}',
            'approver_id'
        );

        $this->addForeignKey(
            'fk-document-approver_id',
            '{{%document}}',
            'approver_id',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-document-approver_id', '{{%document}}');
        $this->dropIndex('idx-document-approver_id', '{{%document}}');
        $this->dropColumn('{{%document}}', 'approver_id');
    }
}

