<?php

declare(strict_types=1);

namespace backend\models;

use common\models\Department;
use common\models\SystemSetting;
use common\models\User;
use common\models\UserDepartment;
use Yii;
use yii\base\Model;

/**
 * SignupForm for teachers and school staff
 */
class SignupForm extends Model
{
    public string $fullname = '';
    public string $username = '';
    public string $id_card = '';
    public string $phone = '';
    public string $email = '';
    public int|string|null $department_id = null;
    public ?string $learning_area = null;
    public string $password = '';
    public string $password_repeat = '';

    // ฟิลด์สำหรับข้อมูล LINE ที่ผูกมัด
    public string $line_user_id = '';
    public string $line_display_name = '';

    public function rules(): array
    {
        return [
            [['fullname', 'username', 'phone', 'email', 'department_id', 'learning_area', 'password', 'password_repeat'], 'required'],
            [['department_id'], 'required', 'message' => 'กรุณาเลือกกลุ่มงาน/ฝ่ายที่สังกัด'],
            [['learning_area'], 'required', 'message' => 'กรุณาเลือกกลุ่มสาระการเรียนรู้'],
            [['line_user_id'], 'required', 'message' => 'กรุณายืนยันการเชื่อมต่อ LINE ก่อนลงทะเบียน'],
            [['fullname', 'username', 'phone', 'email', 'id_card', 'learning_area', 'line_user_id', 'line_display_name'], 'trim'],
            ['fullname', 'string', 'min' => 3, 'max' => 150],
            ['username', 'string', 'min' => 3, 'max' => 64],
            ['username', 'unique', 'targetClass' => User::class, 'filter' => ['!=', 'status', User::STATUS_DELETED], 'message' => 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว'],
            ['id_card', 'string', 'max' => 20],
            ['phone', 'string', 'min' => 9, 'max' => 20],
            ['email', 'email'],
            ['email', 'unique', 'targetClass' => User::class, 'filter' => ['!=', 'status', User::STATUS_DELETED], 'message' => 'อีเมลนี้ถูกใช้งานแล้ว'],
            ['line_user_id', 'unique', 'targetClass' => User::class, 'targetAttribute' => 'line_user_id', 'filter' => ['!=', 'status', User::STATUS_DELETED], 'message' => 'บัญชี LINE นี้ถูกผูกกับผู้ใช้อื่นในระบบแล้ว'],
            ['department_id', 'integer'],
            ['department_id', 'exist', 'targetClass' => Department::class, 'targetAttribute' => ['department_id' => 'id']],
            ['learning_area', 'string', 'max' => 150],
            ['password', 'string', 'min' => 6, 'max' => 64],
            ['password_repeat', 'compare', 'compareAttribute' => 'password', 'message' => 'รหัสผ่านยืนยันไม่ตรงกัน'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'fullname' => 'ชื่อ - นามสกุล',
            'username' => 'ชื่อผู้ใช้ (หรือเลขบัตร ปชช.)',
            'id_card' => 'เลขประจำตัวประชาชน 13 หลัก (ถ้ามี)',
            'phone' => 'เบอร์โทรศัพท์มือถือ',
            'email' => 'อีเมล',
            'department_id' => 'กลุ่มงาน / ฝ่ายบริหารที่สังกัด',
            'learning_area' => 'กลุ่มสาระการเรียนรู้',
            'password' => 'กำหนดรหัสผ่าน (อย่างน้อย 6 ตัวอักษร)',
            'password_repeat' => 'ยืนยันรหัสผ่านอีกครั้ง',
            'line_user_id' => 'บัญชี LINE ที่เชื่อมต่อ',
        ];
    }

    public function signup(): ?User
    {
        if (!$this->validate()) {
            return null;
        }

        $user = new User();
        $user->username = $this->username;
        $user->fullname = $this->fullname;
        $user->id_card = $this->id_card !== '' ? $this->id_card : null;
        $user->phone = $this->phone;
        $user->email = $this->email;
        $user->role = User::ROLE_GENERAL_USER; // ค่าเริ่มต้นเป็นผู้ใช้ทั่วไป/ครูผู้ปฏิบัติงาน
        $user->department_id = (int) $this->department_id;
        $user->learning_area = $this->learning_area !== '' ? $this->learning_area : null;
        $user->status = SystemSetting::isAutoApproveSignup() ? User::STATUS_ACTIVE : User::STATUS_INACTIVE;
        $user->line_user_id = $this->line_user_id;
        $user->line_display_name = $this->line_display_name !== '' ? $this->line_display_name : null;
        $user->line_linked_at = time();
        $user->setPassword($this->password);
        $user->generateAuthKey();
        $user->verification_token = Yii::$app->security->generateRandomString();

        if ($user->save(false)) {
            // บันทึกสังกัดกลุ่มงาน
            $ud = new UserDepartment([
                'user_id' => $user->id,
                'department_id' => (int) $this->department_id,
                'created_at' => time(),
            ]);
            $ud->save(false);

            return $user;
        }

        return null;
    }
}

