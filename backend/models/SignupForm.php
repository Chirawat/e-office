<?php

declare(strict_types=1);

namespace backend\models;

use common\models\Department;
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
    public string $password = '';
    public string $password_repeat = '';

    public function rules(): array
    {
        return [
            [['fullname', 'username', 'phone', 'email', 'department_id', 'password', 'password_repeat'], 'required'],
            [['fullname', 'username', 'phone', 'email', 'id_card'], 'trim'],
            ['fullname', 'string', 'min' => 3, 'max' => 150],
            ['username', 'string', 'min' => 3, 'max' => 64],
            ['username', 'unique', 'targetClass' => User::class, 'message' => 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว'],
            ['id_card', 'string', 'max' => 20],
            ['phone', 'string', 'min' => 9, 'max' => 20],
            ['email', 'email'],
            ['email', 'unique', 'targetClass' => User::class, 'message' => 'อีเมลนี้ถูกใช้งานแล้ว'],
            ['department_id', 'integer'],
            ['department_id', 'exist', 'targetClass' => Department::class, 'targetAttribute' => ['department_id' => 'id']],
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
            'department_id' => 'กลุ่มสาระฯ / ฝ่ายงานที่สังกัด',
            'password' => 'กำหนดรหัสผ่าน (อย่างน้อย 6 ตัวอักษร)',
            'password_repeat' => 'ยืนยันรหัสผ่านอีกครั้ง',
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
        $user->status = User::STATUS_INACTIVE; // รอการอนุมัติจาก Admin
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

