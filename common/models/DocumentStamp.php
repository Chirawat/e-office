<?php

declare(strict_types=1);

namespace common\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class DocumentStamp extends ActiveRecord
{
    public const TYPE_RECEIVE = 'receive';
    public const TYPE_DEPARTMENT = 'department';
    public const TYPE_DEPUTY_COMMENT = 'deputy_comment';
    public const TYPE_DIRECTOR_ORDER = 'director_order';

    public static function tableName(): string
    {
        return '{{%document_stamp}}';
    }

    public function rules(): array
    {
        return [
            [['document_id', 'stamp_type'], 'required'],
            [['document_id', 'page_no', 'created_by', 'created_at'], 'integer'],
            [['position_x', 'position_y'], 'number'],
            [['content'], 'string'],
            [['stamp_type'], 'string', 'max' => 60],
        ];
    }

    public function getDocument(): ActiveQuery
    {
        return $this->hasOne(Document::class, ['id' => 'document_id']);
    }
}
