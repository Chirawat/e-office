<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $memo_id
 * @property int $user_id
 * @property int $read_at
 *
 * @property-read Memo $memo
 * @property-read User $user
 */
class MemoRead extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%memo_read}}';
    }

    public function rules(): array
    {
        return [
            [['memo_id', 'user_id', 'read_at'], 'required'],
            [['memo_id', 'user_id', 'read_at'], 'integer'],
            [['user_id', 'memo_id'], 'unique', 'targetAttribute' => ['user_id', 'memo_id']],
        ];
    }

    public function getMemo(): ActiveQuery
    {
        return $this->hasOne(Memo::class, ['id' => 'memo_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * บันทึกสถานะว่าผู้ใช้เปิดอ่านบันทึกข้อความนี้แล้ว (Atomic upsert)
     */
    public static function markAsRead(int $memoId, int $userId): void
    {
        if ($memoId <= 0 || $userId <= 0) {
            return;
        }

        try {
            Yii::$app->db->createCommand()->upsert(
                static::tableName(),
                [
                    'memo_id' => $memoId,
                    'user_id' => $userId,
                    'read_at' => time(),
                ],
                [
                    'read_at' => time(),
                ]
            )->execute();
        } catch (\Throwable $e) {
            Yii::warning('Failed to mark memo as read: ' . $e->getMessage(), __METHOD__);
        }
    }
}

