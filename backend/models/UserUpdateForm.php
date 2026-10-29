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

class UserUpdateForm extends Model
{
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $role = User::ROLE_OFFICER;
    public int|string|null $department_id = null;
    public array $department_ids = [];
    public int $status = User::STATUS_ACTIVE;
    public ?UploadedFile $signatureFile = null;

    private User $user;

    public function __construct(User $user, $config = [])
    {
        $this->user = $user;
        $this->username = $user->username;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->department_id = $user->department_id !== null ? (int) $user->department_id : null;
        $this->department_ids = $user->departmentIds();
        $this->status = (int) $user->status;

        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['username', 'email', 'role', 'status'], 'required'],
            [['username', 'email', 'password'], 'trim'],
            ['username', 'string', 'min' => 2, 'max' => 255],
            ['username', UniqueValidator::class, 'targetClass' => User::class, 'filter' => ['not', ['id' => $this->user->id]], 'message' => 'Username นี้ถูกใช้แล้ว'],
            ['email', 'email'],
            ['email', 'string', 'max' => 255],
            ['email', UniqueValidator::class, 'targetClass' => User::class, 'filter' => ['not', ['id' => $this->user->id]], 'message' => 'Email นี้ถูกใช้แล้ว'],
            ['password', 'string', 'min' => 6, 'max' => 128],
            ['role', 'in', 'range' => array_keys(User::roleOptions())],
            ['department_id', 'integer'],
            ['department_id', 'exist', 'targetClass' => Department::class, 'targetAttribute' => ['department_id' => 'id']],
            ['department_ids', 'each', 'rule' => ['integer']],
            ['department_ids', 'each', 'rule' => ['exist', 'targetClass' => Department::class, 'targetAttribute' => ['department_ids' => 'id']]],
            ['status', 'integer'],
            ['status', 'in', 'range' => array_keys(self::statusOptions())],
            ['signatureFile', 'file', 'skipOnEmpty' => true, 'extensions' => ['png', 'jpg', 'jpeg'], 'maxSize' => 2 * 1024 * 1024],
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
        if ($scope === '' && isset($data['department_ids']) && !is_array($data['department_ids'])) {
            $data['department_ids'] = $data['department_ids'] === '' ? [] : [$data['department_ids']];
        } elseif (isset($data[$scope]['department_ids']) && !is_array($data[$scope]['department_ids'])) {
            $data[$scope]['department_ids'] = $data[$scope]['department_ids'] === '' ? [] : [$data[$scope]['department_ids']];
        }

        $loaded = parent::load($data, $formName);
        $this->signatureFile = UploadedFile::getInstance($this, 'signatureFile');
        $this->normalizeDepartments();

        return $loaded || $this->signatureFile !== null;
    }

    public function attributeLabels(): array
    {
        return [
            'username' => 'Username',
            'email' => 'Email',
            'password' => 'Password ใหม่',
            'role' => 'บทบาทผู้ใช้',
            'department_id' => 'กลุ่มงาน',
            'department_ids' => 'สังกัดกลุ่มงาน',
            'status' => 'สถานะ',
            'signatureFile' => 'ไฟล์ลายเซ็น',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            User::STATUS_ACTIVE => 'ใช้งาน',
            User::STATUS_INACTIVE => 'พักใช้งาน',
            User::STATUS_DELETED => 'ลบ/ปิดใช้งาน',
        ];
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $this->user->username = $this->username;
        $this->user->email = $this->email;
        $this->user->role = $this->role;
        $this->user->department_id = $this->role === User::ROLE_STAFF && $this->department_id !== null ? (int) $this->department_id : null;
        $this->user->status = $this->status;

        if ($this->password !== '') {
            $this->user->setPassword($this->password);
            $this->user->generateAuthKey();
        }

        if (!$this->user->save(false)) {
            return false;
        }

        if (!$this->saveSignatureFile()) {
            return false;
        }

        $this->syncDepartments();

        return true;
    }

    private function syncDepartments(): void
    {
        UserDepartment::deleteAll(['user_id' => $this->user->id]);
        $departmentIds = $this->role === User::ROLE_GENERAL_USER ? $this->department_ids : [];
        foreach (array_values(array_unique(array_map('intval', $departmentIds))) as $departmentId) {
            (new UserDepartment([
                'user_id' => $this->user->id,
                'department_id' => $departmentId,
                'created_at' => time(),
            ]))->save(false);
        }
    }

    private function normalizeDepartments(): void
    {
        $this->department_id = $this->department_id !== '' && $this->department_id !== null ? (int) $this->department_id : null;
        $this->department_ids = array_values(array_filter(
            array_map(static fn ($id): int|string|null => $id !== '' && $id !== null ? (int) $id : null, (array) $this->department_ids),
            static fn ($id): bool => $id !== null,
        ));
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
            $this->addError('signatureFile', 'ไม่สามารถสร้างโฟลเดอร์ลายเซ็นได้: ' . $dir);
            return false;
        }

        if (!is_writable($dir)) {
            $this->addError('signatureFile', 'โฟลเดอร์ลายเซ็นเขียนไฟล์ไม่ได้: ' . $dir);
            return false;
        }

        $path = $dir . '/signature.jpg';
        try {
            if (!$this->writeNormalizedSignatureImage((string) $this->signatureFile->tempName, $path)) {
                $this->addError('signatureFile', 'ไม่สามารถแปลงและบันทึกไฟล์ลายเซ็นได้ กรุณาตรวจสอบว่า PHP เปิดใช้ GD extension แล้ว');
                return false;
            }
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            $this->addError('signatureFile', 'ไม่สามารถบันทึกไฟล์ลายเซ็นได้: ' . $e->getMessage());
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
