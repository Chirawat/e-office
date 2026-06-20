<?php

declare(strict_types=1);

namespace backend\models;

use common\models\AuditLog;
use common\models\Document;
use common\models\DocumentFile;
use common\models\DocumentStamp;
use common\models\WorkflowAction;
use Yii;
use yii\base\Model;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

class DocumentForm extends Model
{
    private const ATTACHMENT_UPLOAD_LIMIT = 5;

    public Document $document;
    public ?UploadedFile $pdfFile = null;
    /** @var UploadedFile[] */
    public array $attachmentFiles = [];

    public function __construct(?Document $document = null, $config = [])
    {
        $this->document = $document ?? new Document([
            'receive_date' => date('Y-m-d'),
            'receive_time' => date('H:i:s'),
            'status' => Document::STATUS_REGISTERED,
            'urgency' => Document::URGENCY_NORMAL,
            'confidentiality' => 'normal',
        ]);

        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['pdfFile'], 'file', 'skipOnEmpty' => false, 'extensions' => ['pdf'], 'maxSize' => 20 * 1024 * 1024],
            [['attachmentFiles'], 'file', 'skipOnEmpty' => true, 'extensions' => ['pdf'], 'maxSize' => 20 * 1024 * 1024, 'maxFiles' => self::ATTACHMENT_UPLOAD_LIMIT],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'pdfFile' => 'ไฟล์หนังสือต้นฉบับ',
            'attachmentFiles' => 'ไฟล์แนบเพิ่มเติม',
        ];
    }

    public function load($data, $formName = null): bool
    {
        $loaded = $this->document->load($data);
        $this->pdfFile = UploadedFile::getInstance($this, 'pdfFile');
        $this->attachmentFiles = UploadedFile::getInstances($this, 'attachmentFiles');

        return $loaded || $this->pdfFile !== null || $this->attachmentFiles !== [];
    }

    public function validate($attributeNames = null, $clearErrors = true): bool
    {
        return $this->document->validate() && parent::validate($attributeNames, $clearErrors);
    }

    public function save(): ?Document
    {
        if (!$this->validate()) {
            return null;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$this->document->save(false)) {
                $transaction->rollBack();
                return null;
            }

            $this->saveUploadedFiles($this->document);
            $this->createInitialRecords($this->document);
            $transaction->commit();

            return $this->document;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    private function saveUploadedFiles(Document $document): void
    {
        if ($this->pdfFile === null) {
            return;
        }

        $year = date('Y', strtotime($document->receive_date));
        $dir = Yii::getAlias('@backend/web/uploads/documents/' . $year . '/' . $document->id);
        FileHelper::createDirectory($dir);

        $fileName = 'original.pdf';
        $path = $dir . '/' . $fileName;
        $this->pdfFile->saveAs($path);

        $relativePath = 'uploads/documents/' . $year . '/' . $document->id . '/' . $fileName;

        $file = new DocumentFile([
            'document_id' => $document->id,
            'file_type' => DocumentFile::TYPE_ORIGINAL,
            'original_name' => $this->pdfFile->name,
            'storage_path' => $relativePath,
            'mime_type' => $this->pdfFile->type,
            'file_size' => $this->pdfFile->size,
            'created_by' => Yii::$app->user->id,
            'created_at' => time(),
        ]);
        $file->save(false);

        foreach ($this->attachmentFiles as $index => $attachmentFile) {
            $this->saveAttachmentFile($document, $attachmentFile, $dir, $year, $index + 1);
        }
    }

    private function saveAttachmentFile(Document $document, UploadedFile $uploadedFile, string $dir, string $year, int $sequence): void
    {
        $extension = strtolower($uploadedFile->extension ?: 'pdf');
        $fileName = sprintf('attachment-%02d.%s', $sequence, $extension);
        $path = $dir . '/' . $fileName;
        $uploadedFile->saveAs($path);

        $relativePath = 'uploads/documents/' . $year . '/' . $document->id . '/' . $fileName;

        $file = new DocumentFile([
            'document_id' => $document->id,
            'file_type' => DocumentFile::TYPE_ATTACHMENT,
            'original_name' => $uploadedFile->name,
            'storage_path' => $relativePath,
            'mime_type' => $uploadedFile->type,
            'file_size' => $uploadedFile->size,
            'created_by' => Yii::$app->user->id,
            'created_at' => time(),
        ]);
        $file->save(false);
    }

    private function createInitialRecords(Document $document): void
    {
        $now = time();
        $userId = Yii::$app->user->id;

        (new WorkflowAction([
            'document_id' => $document->id,
            'actor_id' => $userId,
            'action' => 'register',
            'to_status' => $document->status,
            'comment' => 'ลงทะเบียนหนังสือรับ',
            'created_at' => $now,
        ]))->save(false);

        (new DocumentStamp([
            'document_id' => $document->id,
            'stamp_type' => DocumentStamp::TYPE_RECEIVE,
            'page_no' => 1,
            'position_x' => 144,
            'position_y' => 14,
            'content' => "รับที่ {$document->receive_no}\nวันที่ {$document->receive_date}\nเวลา {$document->receive_time}",
            'created_by' => $userId,
            'created_at' => $now,
        ]))->save(false);

        (new DocumentStamp([
            'document_id' => $document->id,
            'stamp_type' => DocumentStamp::TYPE_DEPARTMENT,
            'page_no' => 1,
            'position_x' => 18,
            'position_y' => 219,
            'content' => 'ตรากลุ่มงานที่เกี่ยวข้อง',
            'created_by' => $userId,
            'created_at' => $now,
        ]))->save(false);

        (new AuditLog([
            'document_id' => $document->id,
            'user_id' => $userId,
            'event' => 'document.registered',
            'detail' => 'ลงทะเบียนหนังสือรับและอัปโหลด PDF ต้นฉบับ',
            'ip_address' => Yii::$app->request->userIP,
            'created_at' => $now,
        ]))->save(false);
    }
}
