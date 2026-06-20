<?php

declare(strict_types=1);

namespace common\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $document_id
 * @property string $file_type
 * @property string $original_name
 * @property string $storage_path
 * @property string|null $mime_type
 * @property int|null $file_size
 */
class DocumentFile extends ActiveRecord
{
    public const TYPE_ORIGINAL = 'original';
    public const TYPE_STAMPED = 'stamped';
    public const TYPE_ATTACHMENT = 'attachment';

    public static function tableName(): string
    {
        return '{{%document_file}}';
    }

    public function rules(): array
    {
        return [
            [['document_id', 'file_type', 'original_name', 'storage_path'], 'required'],
            [['document_id', 'file_size', 'created_by', 'created_at'], 'integer'],
            [['file_type'], 'string', 'max' => 40],
            [['original_name'], 'string', 'max' => 255],
            [['storage_path'], 'string', 'max' => 500],
            [['mime_type'], 'string', 'max' => 120],
        ];
    }

    public function getDocument(): ActiveQuery
    {
        return $this->hasOne(Document::class, ['id' => 'document_id']);
    }
}
