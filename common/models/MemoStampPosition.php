<?php

declare(strict_types=1);

namespace common\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class MemoStampPosition extends ActiveRecord
{
    public const TYPE_CREATOR = 'creator';
    public const TYPE_STAFF_REVIEW = 'staff_review';
    public const TYPE_DEPUTY_REVIEW = 'deputy_review';
    public const TYPE_DIRECTOR_APPROVE = 'director_approve';

    public static function tableName(): string
    {
        return '{{%memo_stamp_position}}';
    }

    public function rules(): array
    {
        return [
            [['memo_id', 'stamp_type'], 'required'],
            [['memo_id', 'created_by', 'created_at', 'updated_at'], 'integer'],
            [['position_x', 'position_y'], 'number'],
            [['stamp_type'], 'string', 'max' => 60],
        ];
    }

    public function getMemo(): ActiveQuery
    {
        return $this->hasOne(Memo::class, ['id' => 'memo_id']);
    }
}
