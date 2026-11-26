<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class DocumentAssignment extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public static function tableName(): string
    {
        return '{{%document_assignment}}';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules(): array
    {
        return [
            [['document_id'], 'required'],
            [['document_id', 'department_id', 'assigned_to', 'assigned_by', 'acknowledged_at'], 'integer'],
            [['instruction'], 'string'],
            [['due_date'], 'safe'],
            [['status'], 'string', 'max' => 40],
        ];
    }

    public function isAcknowledged(): bool
    {
        return $this->status === self::STATUS_ACKNOWLEDGED || $this->acknowledged_at !== null;
    }

    public function getDocument(): ActiveQuery
    {
        return $this->hasOne(Document::class, ['id' => 'document_id']);
    }

    public function getAssignedTo(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'assigned_to']);
    }

    public function getAssignedBy(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'assigned_by']);
    }
}
