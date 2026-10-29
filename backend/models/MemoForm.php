<?php

declare(strict_types=1);

namespace backend\models;

use common\models\Memo;
use common\models\MemoAction;
use common\models\MemoFile;
use Yii;
use yii\base\Model;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

class MemoForm extends Model
{
    private const ATTACHMENT_UPLOAD_LIMIT = 5;

    public Memo $memo;
    public ?UploadedFile $memoPdfFile = null;
    /** @var UploadedFile[] */
    public array $attachmentFiles = [];

    public function __construct(?Memo $memo = null, $config = [])
    {
        $this->memo = $memo ?? new Memo([
            'memo_date' => date('Y-m-d'),
            'status' => Memo::STATUS_DRAFT,
            'to_text' => 'ผู้อำนวยการโรงเรียน',
            'closing_text' => 'จึงเรียนมาเพื่อโปรดพิจารณา',
        ]);

        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['memoPdfFile'], 'file', 'skipOnEmpty' => true, 'extensions' => ['pdf'], 'maxSize' => 20 * 1024 * 1024],
            [['memoPdfFile'], 'validateMemoPdfFile'],
            [['attachmentFiles'], 'file', 'skipOnEmpty' => true, 'extensions' => ['pdf'], 'maxSize' => 20 * 1024 * 1024, 'maxFiles' => self::ATTACHMENT_UPLOAD_LIMIT],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'memoPdfFile' => 'PDF บันทึกข้อความต้นฉบับ',
            'attachmentFiles' => 'ไฟล์แนบประกอบบันทึก',
        ];
    }

    public function load($data, $formName = null): bool
    {
        $loaded = $this->memo->load($data);
        $this->memoPdfFile = UploadedFile::getInstance($this, 'memoPdfFile');
        $this->attachmentFiles = UploadedFile::getInstances($this, 'attachmentFiles');

        return $loaded || $this->memoPdfFile !== null || $this->attachmentFiles !== [];
    }

    public function validate($attributeNames = null, $clearErrors = true): bool
    {
        return $this->memo->validate() && parent::validate($attributeNames, $clearErrors);
    }

    public function validateMemoPdfFile(string $attribute, mixed $params = null): void
    {
        if ($this->memoPdfFile !== null) {
            return;
        }

        if (!$this->memo->isNewRecord && $this->originalPdfFileExists()) {
            return;
        }

        $this->addError($attribute, 'กรุณาแนบ PDF บันทึกข้อความต้นฉบับ');
    }

    public function save(string $action): ?Memo
    {
        if (!$this->validate()) {
            return null;
        }

        $isNew = $this->memo->isNewRecord;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$this->memo->save(false)) {
                $transaction->rollBack();
                return null;
            }

            $this->saveOriginalPdfFile($this->memo);
            $this->saveUploadedFiles($this->memo);

            (new MemoAction([
                'memo_id' => $this->memo->id,
                'actor_id' => Yii::$app->user->id,
                'action' => $action,
                'to_status' => $this->memo->status,
                'comment' => $isNew ? 'สร้างบันทึกเสนอ' : 'แก้ไขบันทึกเสนอ',
                'created_at' => time(),
            ]))->save(false);

            $transaction->commit();
            return $this->memo;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    private function saveUploadedFiles(Memo $memo): void
    {
        if ($this->attachmentFiles === []) {
            return;
        }

        $year = date('Y', strtotime($memo->memo_date));
        $dir = Yii::getAlias('@backend/web/uploads/memos/' . $year . '/' . $memo->id);
        FileHelper::createDirectory($dir);

        $existingCount = (int) MemoFile::find()
            ->where(['memo_id' => $memo->id, 'description' => MemoFile::TYPE_ATTACHMENT])
            ->count();
        foreach ($this->attachmentFiles as $index => $attachmentFile) {
            $sequence = $existingCount + $index + 1;
            $extension = strtolower($attachmentFile->extension ?: 'pdf');
            $fileName = sprintf('attachment-%02d.%s', $sequence, $extension);
            $path = $dir . '/' . $fileName;
            $attachmentFile->saveAs($path);

            (new MemoFile([
                'memo_id' => $memo->id,
                'original_name' => $attachmentFile->name,
                'storage_path' => 'uploads/memos/' . $year . '/' . $memo->id . '/' . $fileName,
                'mime_type' => $attachmentFile->type,
                'file_size' => $attachmentFile->size,
                'description' => MemoFile::TYPE_ATTACHMENT,
                'created_by' => Yii::$app->user->id,
                'created_at' => time(),
            ]))->save(false);
        }
    }

    private function saveOriginalPdfFile(Memo $memo): void
    {
        if ($this->memoPdfFile === null) {
            return;
        }

        $year = date('Y', strtotime($memo->memo_date));
        $dir = Yii::getAlias('@backend/web/uploads/memos/' . $year . '/' . $memo->id);
        FileHelper::createDirectory($dir);

        $path = $dir . '/original.pdf';
        $this->memoPdfFile->saveAs($path);

        $file = MemoFile::findOne(['memo_id' => $memo->id, 'description' => MemoFile::TYPE_ORIGINAL]) ?? new MemoFile([
            'memo_id' => $memo->id,
            'description' => MemoFile::TYPE_ORIGINAL,
            'created_by' => Yii::$app->user->id,
            'created_at' => time(),
        ]);

        $file->original_name = $this->memoPdfFile->name;
        $file->storage_path = 'uploads/memos/' . $year . '/' . $memo->id . '/original.pdf';
        $file->mime_type = $this->memoPdfFile->type;
        $file->file_size = $this->memoPdfFile->size;
        $file->save(false);
    }

    private function originalPdfFileExists(): bool
    {
        return MemoFile::find()
            ->where(['memo_id' => $this->memo->id, 'description' => MemoFile::TYPE_ORIGINAL])
            ->exists();
    }
}
