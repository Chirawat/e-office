<?php

declare(strict_types=1);

namespace common\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $document_id
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $from_status
 * @property string|null $to_status
 * @property string|null $comment
 * @property int $created_at
 */
class WorkflowAction extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%workflow_action}}';
    }

    public function rules(): array
    {
        return [
            [['document_id', 'action'], 'required'],
            [['document_id', 'actor_id', 'created_at'], 'integer'],
            [['comment'], 'string'],
            [['action'], 'string', 'max' => 60],
            [['from_status', 'to_status'], 'string', 'max' => 40],
        ];
    }

    public function getDocument(): ActiveQuery
    {
        return $this->hasOne(Document::class, ['id' => 'document_id']);
    }

    public function getActor(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'actor_id']);
    }
}
