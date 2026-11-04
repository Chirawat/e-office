<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $document_id
 * @property int $user_id
 * @property int $read_at
 *
 * @property-read Document $document
 * @property-read User $user
 */
class DocumentRead extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%document_read}}';
    }

    public function rules(): array
    {
        return [
            [['document_id', 'user_id', 'read_at'], 'required'],
            [['document_id', 'user_id', 'read_at'], 'integer'],
            [['user_id', 'document_id'], 'unique', 'targetAttribute' => ['user_id', 'document_id']],
        ];
    }

    public function getDocument(): ActiveQuery
    {
        return $this->hasOne(Document::class, ['id' => 'document_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * บันทึกสถานะว่าผู้ใช้เปิดอ่านเอกสารนี้แล้ว (Atomic upsert)
     */
    public static function markAsRead(int $documentId, int $userId): void
    {
        if ($documentId <= 0 || $userId <= 0) {
            return;
        }

        try {
            Yii::$app->db->createCommand()->upsert(
                static::tableName(),
                [
                    'document_id' => $documentId,
                    'user_id' => $userId,
                    'read_at' => time(),
                ],
                [
                    'read_at' => time(),
                ]
            )->execute();
        } catch (\Throwable $e) {
            Yii::warning('Failed to mark document as read: ' . $e->getMessage(), __METHOD__);
        }
    }
}

