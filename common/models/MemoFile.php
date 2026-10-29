<?php

declare(strict_types=1);

namespace common\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class MemoFile extends ActiveRecord
{
    public const TYPE_ORIGINAL = 'memo_pdf';
    public const TYPE_ATTACHMENT = 'attachment';

    public static function tableName(): string
    {
        return '{{%memo_file}}';
    }

    public function rules(): array
    {
        return [
            [['memo_id', 'original_name', 'storage_path'], 'required'],
            [['memo_id', 'file_size', 'created_by', 'created_at'], 'integer'],
            [['original_name', 'description'], 'string', 'max' => 255],
            [['storage_path'], 'string', 'max' => 500],
            [['mime_type'], 'string', 'max' => 120],
        ];
    }

    public function getMemo(): ActiveQuery
    {
        return $this->hasOne(Memo::class, ['id' => 'memo_id']);
    }
}
