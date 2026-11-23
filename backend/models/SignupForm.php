<?php

declare(strict_types=1);

namespace backend\models;

use common\models\Department;
use common\models\SystemSetting;
use common\models\User;
use common\models\UserDepartment;
use Yii;
use yii\base\Model;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

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
    public ?UploadedFile $signatureFile = null;

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
            ['signatureFile', 'file', 'skipOnEmpty' => true, 'extensions' => ['png', 'jpg', 'jpeg'], 'maxSize' => 2 * 1024 * 1024],
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
            'signatureFile' => 'ภาพถ่ายหรือภาพสแกนลายมือชื่อ (ลายเซ็น)',
            'line_user_id' => 'บัญชี LINE ที่เชื่อมต่อ',
        ];
    }

    public function load($data, $formName = null): bool
    {
        $scope = $formName ?? $this->formName();
        if ($scope === '' && isset($data['signatureFile'])) {
            unset($data['signatureFile']);
        } elseif (isset($data[$scope]['signatureFile'])) {
            unset($data[$scope]['signatureFile']);
        }

        $loaded = parent::load($data, $formName);
        $this->signatureFile = UploadedFile::getInstance($this, 'signatureFile');

        return $loaded || $this->signatureFile !== null;
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

            // บันทึกไฟล์ภาพลายเซ็น (ถ้ามีอัปโหลด)
            $this->saveSignatureFile($user);

            return $user;
        }

        return null;
    }

    private function saveSignatureFile(User $user): bool
    {
        if ($this->signatureFile === null) {
            return true;
        }

        $dir = Yii::getAlias('@backend/web/uploads/signatures/' . $user->id);
        try {
            FileHelper::createDirectory($dir, 0775, true);
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            $this->addError('signatureFile', 'ไม่สามารถสร้างโฟลเดอร์สำหรับเก็บลายเซ็นได้: ' . $dir);
            return false;
        }

        if (!is_writable($dir)) {
            $this->addError('signatureFile', 'โฟลเดอร์สำหรับเก็บลายเซ็นเขียนไฟล์ไม่ได้: ' . $dir);
            return false;
        }

        $path = $dir . '/signature.jpg';
        try {
            if (!$this->writeNormalizedSignatureImage((string) $this->signatureFile->tempName, $path)) {
                $this->addError('signatureFile', 'ไม่สามารถประมวลผลไฟล์ภาพลายเซ็นได้ กรุณาตรวจสอบว่าเซิร์ฟเวอร์เปิดใช้งาน GD extension');
                return false;
            }
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            $this->addError('signatureFile', 'เกิดข้อผิดพลาดในการบันทึกภาพลายเซ็น: ' . $e->getMessage());
            return false;
        }

        $user->signature_path = 'uploads/signatures/' . $user->id . '/signature.jpg';
        $user->save(false, ['signature_path', 'updated_at']);

        return true;
    }

    private function writeNormalizedSignatureImage(string $sourcePath, string $targetPath): bool
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            return false;
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return false;
        }

        $contents = file_get_contents($sourcePath);
        if ($contents === false) {
            return false;
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            return false;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        $saved = imagejpeg($canvas, $targetPath, 95);
        imagedestroy($source);
        imagedestroy($canvas);

        return $saved;
    }
}

