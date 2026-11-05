<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * SystemSetting model
 *
 * @property int $id
 * @property string $setting_key
 * @property string|null $setting_value
 * @property string $setting_group
 * @property string|null $description
 * @property int|null $updated_at
 * @property int|null $updated_by
 */
class SystemSetting extends ActiveRecord
{
    public const KEY_SCHOOL_NAME = 'school_name';
    public const KEY_DIRECTOR_NAME = 'director_name';
    public const KEY_DIRECTOR_POSITION = 'director_position';
    public const KEY_ACTING_DIRECTOR_NAME = 'acting_director_name';
    public const KEY_ACTING_DIRECTOR_USER_ID = 'acting_director_user_id';
    public const KEY_ACTING_DIRECTOR_POSITION = 'acting_director_position';
    public const KEY_IS_ACTING_DIRECTOR = 'is_acting_director';
    public const KEY_DOC_RECEIVE_START_NO = 'doc_receive_start_no';
    public const KEY_DOC_RECEIVE_YEAR = 'doc_receive_year';
    public const KEY_AUTO_APPROVE_SIGNUP = 'auto_approve_signup';
    public const KEY_DOCUMENT_APPROVER_ID = 'document_approver_id';

    /** @var array<string, mixed>|null Local memory cache for request */
    private static ?array $cache = null;

    public static function tableName(): string
    {
        return '{{%system_setting}}';
    }

    public function rules(): array
    {
        return [
            [['setting_key'], 'required'],
            [['setting_value'], 'string'],
            [['setting_key'], 'string', 'max' => 64],
            [['setting_group'], 'string', 'max' => 32],
            [['description'], 'string', 'max' => 255],
            [['updated_at', 'updated_by'], 'integer'],
            [['setting_key'], 'unique'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'setting_key' => 'รหัสการตั้งค่า',
            'setting_value' => 'ค่าการตั้งค่า',
            'setting_group' => 'หมวดหมู่',
            'description' => 'คำอธิบาย',
            'updated_at' => 'แก้ไขเมื่อ',
            'updated_by' => 'แก้ไขโดย',
        ];
    }

    /**
     * ดึงค่าการตั้งค่าตาม key
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$cache === null) {
            self::warmUpCache();
        }

        if (array_key_exists($key, self::$cache ?? [])) {
            $val = self::$cache[$key];
            return ($val !== null && $val !== '') ? $val : $default;
        }

        return $default;
    }

    /**
     * บันทึกหรืออัปเดตค่าการตั้งค่า
     */
    public static function set(string $key, mixed $value, ?string $group = null, ?string $description = null): bool
    {
        $model = static::findOne(['setting_key' => $key]);
        if ($model === null) {
            $model = new static();
            $model->setting_key = $key;
            $model->setting_group = $group ?? 'general';
            if ($description !== null) {
                $model->description = $description;
            }
        }

        $model->setting_value = (string) $value;
        $model->updated_at = time();
        if (Yii::$app->has('user') && !Yii::$app->user->isGuest) {
            $model->updated_by = (int) Yii::$app->user->id;
        }

        $saved = $model->save();
        if ($saved) {
            if (self::$cache === null) {
                self::$cache = [];
            }
            self::$cache[$key] = (string) $value;
        }

        return $saved;
    }

    /**
     * ดึงค่าทั้งหมดเป็น map [key => value]
     *
     * @return array<string, string|null>
     */
    public static function getAllAsMap(): array
    {
        if (self::$cache === null) {
            self::warmUpCache();
        }

        return self::$cache ?? [];
    }

    /**
     * ตรวจสอบว่าปัจจุบันอยู่ในสถานะ "ใช้ชื่อรักษาการแทน ผอ." หรือไม่
     */
    public static function isActingDirector(): bool
    {
        return (string) static::get(self::KEY_IS_ACTING_DIRECTOR, '0') === '1';
    }

    /**
     * คืนค่า ID ของผู้ใช้ (User ID) ที่ทำหน้าที่รักษาการแทนผู้อำนวยการ
     */
    public static function getActingDirectorUserId(): ?int
    {
        $val = static::get(self::KEY_ACTING_DIRECTOR_USER_ID);
        return ($val !== null && $val !== '' && (int) $val > 0) ? (int) $val : null;
    }

    /**
     * คืนค่าโมเดล User ของผู้รักษาการแทนผู้อำนวยการ
     */
    public static function getActingDirectorUser(): ?User
    {
        $userId = static::getActingDirectorUserId();
        return $userId !== null ? User::findOne(['id' => $userId, 'status' => User::STATUS_ACTIVE]) : null;
    }

    /**
     * คืนค่าชื่อผู้อำนวยการ หรือ ผู้รักษาการในตำแหน่งผู้อำนวยการ ตามการตั้งค่าปัจจุบัน
     */
    public static function getCurrentDirectorName(): string
    {
        if (static::isActingDirector()) {
            $actingUser = static::getActingDirectorUser();
            if ($actingUser !== null) {
                return $actingUser->displayName();
            }
            $actingName = trim((string) static::get(self::KEY_ACTING_DIRECTOR_NAME, ''));
            if ($actingName !== '') {
                return $actingName;
            }
        }

        return trim((string) static::get(self::KEY_DIRECTOR_NAME, ''));
    }

    /**
     * คืนค่าตำแหน่งผู้อำนวยการ หรือ ตำแหน่งผู้รักษาการ ตามการตั้งค่าปัจจุบัน
     */
    public static function getCurrentDirectorPosition(): string
    {
        if (static::isActingDirector()) {
            $actingPos = trim((string) static::get(self::KEY_ACTING_DIRECTOR_POSITION, ''));
            if ($actingPos !== '') {
                return $actingPos;
            }
        }

        return trim((string) static::get(self::KEY_DIRECTOR_POSITION, 'ผู้อำนวยการโรงเรียน'));
    }

    /**
     * ตรวจสอบว่าเปิดโหมด Auto Approve สำหรับผู้สมัครสมาชิกใหม่หรือไม่
     */
    public static function isAutoApproveSignup(): bool
    {
        return (string) static::get(self::KEY_AUTO_APPROVE_SIGNUP, '0') === '1';
    }

    /**
     * ดึง ID ของผู้ตรวจสอบ/อนุมัติหนังสือรับที่ Admin กำหนดไว้
     */
    public static function getDocumentApproverId(): ?int
    {
        $val = static::get(self::KEY_DOCUMENT_APPROVER_ID);
        if ($val === null || $val === '' || (int) $val <= 0) {
            return null;
        }

        return (int) $val;
    }

    /**
     * ดึงโมเดล User ของผู้ตรวจสอบ/อนุมัติหนังสือรับที่ Admin กำหนดไว้
     */
    public static function getDocumentApprover(): ?User
    {
        $id = static::getDocumentApproverId();
        if ($id === null) {
            return null;
        }

        return User::findOne(['id' => $id, 'status' => User::STATUS_ACTIVE]);
    }

    /**
     * แนะนำเลขรับหนังสือถัดไปตามการตั้งค่า "เลขเริ่มต้น" และ "ปี พ.ศ."
     */
    public static function suggestNextReceiveNo(): string
    {
        $currentBuddhistYear = (int) date('Y') + 543;
        $configuredYear = (int) static::get(self::KEY_DOC_RECEIVE_YEAR, $currentBuddhistYear);
        if ($configuredYear <= 0) {
            $configuredYear = $currentBuddhistYear;
        }

        $startNo = (int) static::get(self::KEY_DOC_RECEIVE_START_NO, 1);
        if ($startNo <= 0) {
            $startNo = 1;
        }

        $suffix = '/' . $configuredYear;

        $receiveNumbers = Document::find()
            ->select('receive_no')
            ->where(['like', 'receive_no', '%' . $suffix, false])
            ->column();

        $max = 0;
        foreach ($receiveNumbers as $receiveNo) {
            if (preg_match('/^(\d+)\/' . preg_quote((string) $configuredYear, '/') . '$/', (string) $receiveNo, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        if ($max === 0) {
            // ยังไม่มีหนังสือในปีนี้ ให้เริ่มที่เลขเริ่มต้นตามที่ตั้งค่าไว้
            return $startNo . $suffix;
        }

        // ถ้ามีหนังสือแล้ว แต่ max ยังน้อยกว่า startNo ให้กระโดดไปเริ่มที่ startNo
        $nextNo = max($max + 1, $startNo);

        return $nextNo . $suffix;
    }

    /**
     * โหลดค่าลง cache หน่วยความจำ
     */
    private static function warmUpCache(): void
    {
        self::$cache = [];
        try {
            $settings = static::find()->asArray()->all();
            foreach ($settings as $setting) {
                self::$cache[$setting['setting_key']] = $setting['setting_value'];
            }
        } catch (\Throwable $e) {
            // หากตารางยังไม่พร้อม
        }
    }
}

