<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Class m261126_000019_add_acknowledged_at_to_document_assignment
 */
class m261126_000019_add_acknowledged_at_to_document_assignment extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        $table = $this->db->getTableSchema('{{%document_assignment}}');
        if ($table !== null && $table->getColumn('acknowledged_at') === null) {
            $this->addColumn(
                '{{%document_assignment}}',
                'acknowledged_at',
                $this->integer()->null()->defaultValue(null)->after('status')
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        $table = $this->db->getTableSchema('{{%document_assignment}}');
        if ($table !== null && $table->getColumn('acknowledged_at') !== null) {
            $this->dropColumn('{{%document_assignment}}', 'acknowledged_at');
        }
    }
}
