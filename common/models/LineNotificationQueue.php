<?php

declare(strict_types=1);

namespace common\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $user_id
 * @property string $event_type
 * @property string $ref_type
 * @property int $ref_id
 * @property string $title
 * @property string $subject
 * @property string|null $detail
 * @property int|bool $is_urgent
 * @property string $status
 * @property string|null $batch_key
 * @property int $created_at
 * @property int|null $sent_at
 *
 * @property-read User $user
 */
class LineNotificationQueue extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_SKIPPED = 'skipped';

    public const REF_DOCUMENT = 'document';
    public const REF_MEMO = 'memo';

    public const EVENT_DOCUMENT_WORKFLOW = 'document_workflow';
    public const EVENT_DOCUMENT_ASSIGNED = 'document_assigned';
    public const EVENT_MEMO = 'memo';

    public static function tableName(): string
    {
        return '{{%line_notification_queue}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'event_type', 'ref_type', 'ref_id', 'title', 'subject', 'created_at'], 'required'],
            [['user_id', 'ref_id', 'created_at', 'sent_at'], 'integer'],
            [['is_urgent'], 'boolean'],
            [['detail'], 'string'],
            [['event_type'], 'string', 'max' => 40],
            [['ref_type', 'status'], 'string', 'max' => 20],
            [['batch_key'], 'string', 'max' => 64],
            [['title'], 'string', 'max' => 255],
            [['subject'], 'string', 'max' => 500],
            [['status'], 'default', 'value' => self::STATUS_PENDING],
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * นับจำนวน Push Message ที่ส่งออกไปแล้วในเดือนปัจจุบัน (นับตาม distinct batch_key เพื่อสะท้อนโควตา LINE จริง)
     */
    public static function countMonthlyPushUsed(): int
    {
        try {
            $monthStart = strtotime(date('Y-m-01 00:00:00'));
            return (int) static::find()
                ->select('batch_key')
                ->distinct()
                ->where(['status' => self::STATUS_SENT])
                ->andWhere(['>=', 'sent_at', $monthStart])
                ->andWhere(['is not', 'batch_key', null])
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * นับจำนวนรายการค้างในคิว และจำนวนผู้รับที่รอส่ง
     *
     * @return array{items: int, users: int}
     */
    public static function pendingSummary(): array
    {
        try {
            $items = (int) static::find()->where(['status' => self::STATUS_PENDING])->count();
            $users = (int) static::find()->select('user_id')->distinct()->where(['status' => self::STATUS_PENDING])->count();
            return ['items' => $items, 'users' => $users];
        } catch (\Throwable $e) {
            return ['items' => 0, 'users' => 0];
        }
    }

    /**
     * เวลาที่ส่ง LINE Push ล่าสุดให้ผู้ใช้คนนี้ (timestamp หรือ null)
     */
    public static function lastSentAtForUser(int $userId): ?int
    {
        try {
            $val = static::find()
                ->select('MAX([[sent_at]])')
                ->where(['user_id' => $userId, 'status' => self::STATUS_SENT])
                ->scalar();
            return ($val !== false && $val !== null) ? (int) $val : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
