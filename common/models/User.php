<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * User model
 *
 * @property int $id
 * @property string $username
 * @property string|null $fullname
 * @property string|null $phone
 * @property string|null $id_card
 * @property string $password_hash
 * @property string|null $password_reset_token
 * @property string $verification_token
 * @property string $email
 * @property string $role
 * @property int|null $department_id
 * @property string|null $signature_path
 * @property string|null $line_user_id
 * @property string|null $line_display_name
 * @property string|null $line_link_token
 * @property int|null $line_link_token_expires_at
 * @property int|null $line_linked_at
 * @property string $auth_key
 * @property int $status
 * @property int $created_at
 * @property int $updated_at
 * @property string $password write-only password
 */
class User extends ActiveRecord implements IdentityInterface
{
    public const STATUS_DELETED = 0;
    public const STATUS_INACTIVE = 9;
    public const STATUS_ACTIVE = 10;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_OFFICER = 'officer';
    public const ROLE_DEPUTY_DIRECTOR = 'deputy_director';
    public const ROLE_DIRECTOR = 'director';
    public const ROLE_STAFF = 'staff';
    public const ROLE_GENERAL_USER = 'general_user';
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%user}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            ['status', 'default', 'value' => self::STATUS_INACTIVE],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_DELETED]],
            ['role', 'default', 'value' => self::ROLE_OFFICER],
            ['role', 'in', 'range' => array_keys(self::roleOptions())],
            ['department_id', 'integer'],
            ['department_id', 'exist', 'targetClass' => Department::class, 'targetAttribute' => ['department_id' => 'id']],
            [['fullname'], 'string', 'max' => 150],
            [['phone'], 'string', 'max' => 30],
            [['id_card'], 'string', 'max' => 20],
            ['signature_path', 'string', 'max' => 500],
            [['line_link_token_expires_at', 'line_linked_at'], 'integer'],
            [['line_user_id'], 'string', 'max' => 80],
            [['line_display_name'], 'string', 'max' => 120],
            [['line_link_token'], 'string', 'max' => 40],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'username' => 'ชื่อผู้ใช้',
            'fullname' => 'ชื่อ-นามสกุล',
            'phone' => 'เบอร์โทรศัพท์',
            'id_card' => 'เลขประจำตัวประชาชน',
            'role' => 'บทบาทผู้ใช้',
            'department_id' => 'กลุ่มงาน',
            'signature_path' => 'ไฟล์ลายเซ็น',
            'line_user_id' => 'LINE userId',
            'line_display_name' => 'ชื่อ LINE',
            'line_linked_at' => 'เชื่อมต่อ LINE เมื่อ',
        ];
    }

    public function getDepartment(): ActiveQuery
    {
        return $this->hasOne(Department::class, ['id' => 'department_id']);
    }

    public function getUserDepartments(): ActiveQuery
    {
        return $this->hasMany(UserDepartment::class, ['user_id' => 'id']);
    }

    public function getDepartments(): ActiveQuery
    {
        return $this->hasMany(Department::class, ['id' => 'department_id'])
            ->via('userDepartments');
    }

    public static function roleOptions(): array
    {
        return [
            self::ROLE_ADMIN => 'ผู้ดูแลระบบ',
            self::ROLE_OFFICER => 'เจ้าหน้าที่ธุรการ',
            self::ROLE_DEPUTY_DIRECTOR => 'รอง ผอ.',
            self::ROLE_DIRECTOR => 'ผอ.',
            self::ROLE_STAFF => 'หัวหน้ากลุ่มงาน',
            self::ROLE_GENERAL_USER => 'ผู้ใช้ทั่วไป',
        ];
    }

    public function departmentIds(): array
    {
        $ids = array_map(static fn (Department $department): int => (int) $department->id, $this->departments);
        if ($this->department_id !== null) {
            $ids[] = (int) $this->department_id;
        }

        return array_values(array_unique($ids));
    }

    public function belongsToDepartment(int|string|null $departmentId): bool
    {
        if ($departmentId === null || $departmentId === '') {
            return false;
        }

        return in_array((int) $departmentId, $this->departmentIds(), true);
    }

    public function displayName(): string
    {
        return !empty($this->fullname) ? $this->fullname : $this->username;
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'เปิดใช้งาน',
            self::STATUS_INACTIVE => 'รออนุมัติ / พักใช้งาน',
            self::STATUS_DELETED => 'ปิดใช้งาน',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? (string) $this->status;
    }

    public function roleLabel(): string
    {
        return self::roleOptions()[$this->role] ?? $this->role;
    }

    public function hasRole(string|array $roles): bool
    {
        if ($this->role === self::ROLE_ADMIN) {
            return true;
        }

        // หากกำลังเปิดใช้งานโหมดผู้รักษาการแทน ผอ. และผู้ใช้รายนี้คือผู้ได้รับแต่งตั้งให้รักษาการ
        if (in_array(self::ROLE_DIRECTOR, (array) $roles, true) && SystemSetting::isActingDirector()) {
            $actingUserId = SystemSetting::getActingDirectorUserId();
            if ($actingUserId !== null && (int) $this->id === $actingUserId) {
                return true;
            }
        }

        return in_array($this->role, (array) $roles, true);
    }

    public function canRegisterDocument(): bool
    {
        return $this->hasRole([self::ROLE_OFFICER]);
    }

    public function canSubmitDocumentToDeputy(): bool
    {
        return $this->hasRole([self::ROLE_OFFICER]);
    }

    public function canGiveDeputyComment(): bool
    {
        return $this->hasRole([self::ROLE_DEPUTY_DIRECTOR]);
    }

    public function canGiveDirectorOrder(): bool
    {
        return $this->hasRole([self::ROLE_DIRECTOR]);
    }

    public function canCompleteDocument(): bool
    {
        return $this->hasRole([self::ROLE_DEPUTY_DIRECTOR, self::ROLE_STAFF, self::ROLE_GENERAL_USER]);
    }

    public function canForwardDocumentToUser(): bool
    {
        return $this->hasRole([self::ROLE_STAFF]);
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id): User|null
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null): never
    {
        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    public static function findByUsername(string $username): User|null
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken(string $token): User|null
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'password_reset_token' => $token,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Finds user by verification email token
     *
     * @param string $token verify email token
     * @return static|null
     */
    public static function findByVerificationToken(string $token): User|null
    {
        return static::findOne(
            [
                'verification_token' => $token,
                'status' => self::STATUS_INACTIVE,
            ],
        );
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string|null $token password reset token
     * @return bool
     */
    public static function isPasswordResetTokenValid(string|null $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $timestamp = (int) substr($token, strrpos($token, '_') + 1);

        $expire = Yii::$app->params['user.passwordResetTokenExpire'];

        return $timestamp + $expire >= time();
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): int
    {
        return $this->getPrimaryKey();
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey(): string
    {
        return $this->auth_key;
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey): bool
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword(string $password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey(): void
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken(): void
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Generates new token for email verification
     */
    public function generateEmailVerificationToken(): void
    {
        $this->verification_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken(): void
    {
        $this->password_reset_token = null;
    }
}
