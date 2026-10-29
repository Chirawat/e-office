<?php

declare(strict_types=1);

namespace common\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class MemoAction extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%memo_action}}';
    }

    public function rules(): array
    {
        return [
            [['memo_id', 'action'], 'required'],
            [['memo_id', 'actor_id', 'created_at'], 'integer'],
            [['comment'], 'string'],
            [['action'], 'string', 'max' => 60],
            [['from_status', 'to_status'], 'string', 'max' => 40],
        ];
    }

    public function getMemo(): ActiveQuery
    {
        return $this->hasOne(Memo::class, ['id' => 'memo_id']);
    }

    public function getActor(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'actor_id']);
    }
}
