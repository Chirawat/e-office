<?php

declare(strict_types=1);

namespace backend\models;

use common\models\Department;
use common\models\User;
use common\models\UserDepartment;
use Yii;
use yii\base\Model;
use yii\helpers\FileHelper;
use yii\validators\UniqueValidator;
use yii\web\UploadedFile;

class UserProfileForm extends Model
{
    public string $fullname = '';
    public string $username = '';
    public string $email = '';
    public string $phone = '';
    public string $id_card = '';
    public int|string|null $department_id = null;
    public ?string $learning_area = null;
    public string $password = '';
    public string $password_repeat = '';
    public ?UploadedFile $signatureFile = null;

    public User $user;

    public function __construct(User $user, $config = [])
    {
        $this->user = $user;
        $this->fullname = (string) ($user->fullname ?? '');
        $this->username = (string) $user->username;
        $this->email = (string) $user->email;
        $this->phone = (string) ($user->phone ?? '');
        $this->id_card = (string) ($user->id_card ?? '');
        $this->department_id = $user->department_id !== null ? (int) $user->department_id : null;
        $this->learning_area = (string) ($user->learning_area ?? '');

        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['fullname', 'username', 'email'], 'required'],
            [['fullname', 'username', 'email', 'phone', 'id_card', 'learning_area', 'password', 'password_repeat'], 'trim'],
            ['fullname', 'string', 'min' => 2, 'max' => 150],
            ['username', 'string', 'min' => 3, 'max' => 64],
            ['username', UniqueValidator::class, 'targetClass' => User::class, 'filter' => ['not', ['id' => $this->user->id]], 'message' => 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว'],
            ['email', 'email'],
            ['email', 'string', 'max' => 255],
            ['email', UniqueValidator::class, 'targetClass' => User::class, 'filter' => ['not', ['id' => $this->user->id]], 'message' => 'อีเมลนี้ถูกใช้งานแล้ว'],
            ['phone', 'string', 'max' => 20],
            ['id_card', 'string', 'max' => 20],
            ['department_id', 'integer'],
            ['department_id', 'exist', 'targetClass' => Department::class, 'targetAttribute' => ['department_id' => 'id']],
            ['learning_area', 'string', 'max' => 150],
            ['password', 'string', 'min' => 6, 'max' => 64],
            ['password_repeat', 'compare', 'compareAttribute' => 'password', 'skipOnEmpty' => true, 'message' => 'รหัสผ่านยืนยันไม่ตรงกับรหัสผ่านใหม่'],
            ['signatureFile', 'file', 'skipOnEmpty' => true, 'extensions' => ['png', 'jpg', 'jpeg'], 'maxSize' => 2 * 1024 * 1024],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'fullname' => 'ชื่อ - นามสกุล',
            'username' => 'ชื่อผู้ใช้ (Username)',
            'email' => 'อีเมล (Email)',
            'phone' => 'เบอร์โทรศัพท์มือถือ',
            'id_card' => 'เลขประจำตัวประชาชน 13 หลัก',
            'department_id' => 'กลุ่มงาน / ฝ่ายบริหาร',
            'learning_area' => 'กลุ่มสาระการเรียนรู้',
            'password' => 'รหัสผ่านใหม่ (เว้นว่างไว้หากไม่ต้องการเปลี่ยน)',
            'password_repeat' => 'ยืนยันรหัสผ่านใหม่อีกครั้ง',
            'signatureFile' => 'ไฟล์ภาพลายเซ็นสแกน (PNG หรือ JPG)',
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

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $this->user->fullname = $this->fullname;
        $this->user->username = $this->username;
        $this->user->email = $this->email;
        $this->user->phone = $this->phone;
        $this->user->id_card = $this->id_card !== '' ? $this->id_card : null;
        $this->user->learning_area = $this->learning_area !== '' ? $this->learning_area : null;
        if ($this->department_id !== null && $this->department_id !== '') {
            $this->user->department_id = (int) $this->department_id;
        }

        if ($this->password !== '') {
            $this->user->setPassword($this->password);
            $this->user->generateAuthKey();
        }

        if (!$this->user->save(false)) {
            return false;
        }

        // เชื่อมโยง UserDepartment ถ้ามีการเลือกกลุ่มงาน
        if ($this->department_id) {
            $deptId = (int) $this->department_id;
            $hasUd = UserDepartment::find()->where(['user_id' => $this->user->id, 'department_id' => $deptId])->exists();
            if (!$hasUd) {
                (new UserDepartment([
                    'user_id' => $this->user->id,
                    'department_id' => $deptId,
                    'created_at' => time(),
                ]))->save(false);
            }
        }

        if (!$this->saveSignatureFile()) {
            return false;
        }

        return true;
    }

    private function saveSignatureFile(): bool
    {
        if ($this->signatureFile === null) {
            return true;
        }

        $dir = Yii::getAlias('@backend/web/uploads/signatures/' . $this->user->id);
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

        $this->user->signature_path = 'uploads/signatures/' . $this->user->id . '/signature.jpg';
        $this->user->save(false, ['signature_path', 'updated_at']);

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
