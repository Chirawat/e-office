<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * LineLoginSession model
 *
 * @property int $id
 * @property string $session_token
 * @property string $login_code
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $status
 * @property int $expires_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read User|null $user
 */
class LineLoginSession extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_USED = 'used';
    public const STATUS_EXPIRED = 'expired';

    public static function tableName(): string
    {
        return '{{%line_login_session}}';
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
            [['session_token', 'login_code', 'expires_at'], 'required'],
            [['user_id', 'expires_at', 'created_at', 'updated_at'], 'integer'],
            [['session_token'], 'string', 'max' => 64],
            [['login_code', 'status'], 'string', 'max' => 20],
            [['ip_address'], 'string', 'max' => 45],
            [['user_agent'], 'string', 'max' => 255],
            [['session_token', 'login_code'], 'unique'],
            ['status', 'in', 'range' => [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_USED, self::STATUS_EXPIRED]],
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public static function createNewSession(?string $ipAddress = null, ?string $userAgent = null): self
    {
        // ลบ session ที่หมดอายุเกิน 1 ชั่วโมง เพื่อรักษาขนาดตาราง
        static::deleteAll(['and', ['<', 'expires_at', time() - 3600]]);

        $session = new self();
        $session->session_token = Yii::$app->security->generateRandomString(48);
        $session->login_code = self::generateLoginCode();
        $session->ip_address = $ipAddress !== null ? substr($ipAddress, 0, 45) : null;
        $session->user_agent = $userAgent !== null ? substr($userAgent, 0, 255) : null;
        $session->status = self::STATUS_PENDING;
        $session->expires_at = time() + (10 * 60); // มีอายุ 10 นาที
        $session->save(false);

        return $session;
    }

    public function isPendingAndValid(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->expires_at > time();
    }

    public function isApprovedAndValid(): bool
    {
        return $this->status === self::STATUS_APPROVED && $this->user_id !== null && $this->expires_at > time();
    }

    public function approve(User $user): bool
    {
        if (!$this->isPendingAndValid()) {
            return false;
        }

        $this->user_id = $user->id;
        $this->status = self::STATUS_APPROVED;
        return $this->save(false, ['user_id', 'status', 'updated_at']);
    }

    public function markUsed(): bool
    {
        $this->status = self::STATUS_USED;
        return $this->save(false, ['status', 'updated_at']);
    }

    private static function generateLoginCode(): string
    {
        do {
            $code = 'KC-' . random_int(100000, 999999);
        } while (static::find()->where(['login_code' => $code])->exists());

        return $code;
    }
}

