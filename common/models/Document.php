<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $receive_no
 * @property string $receive_date
 * @property string|null $receive_time
 * @property string|null $external_no
 * @property string|null $external_date
 * @property string $sender
 * @property string $subject
 * @property string|null $summary
 * @property string $urgency
 * @property string $confidentiality
 * @property int|null $department_id
 * @property int|null $approver_id
 * @property string|null $due_date
 * @property string $status
 * @property string|null $note
 */
class Document extends ActiveRecord
{
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_SUBMITTED_TO_APPROVER = 'submitted_to_approver';
    public const STATUS_SUBMITTED_TO_DEPUTY = 'submitted_to_deputy';
    public const STATUS_SUBMITTED_TO_DIRECTOR = 'submitted_to_director';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_COMPLETED = 'completed';

    public const URGENCY_NORMAL = 'normal';
    public const URGENCY_URGENT = 'urgent';
    public const URGENCY_VERY_URGENT = 'very_urgent';
    public const URGENCY_MOST_URGENT = 'most_urgent';

    public static function tableName(): string
    {
        return '{{%document}}';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
            [
                'class' => BlameableBehavior::class,
                'createdByAttribute' => 'created_by',
                'updatedByAttribute' => 'updated_by',
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['receive_no', 'receive_date', 'sender', 'subject', 'department_id'], 'required'],
            [['receive_date', 'receive_time', 'external_date', 'due_date'], 'safe'],
            [['department_id', 'approver_id'], 'integer'],
            [['note', 'summary'], 'string'],
            [['receive_no'], 'string', 'max' => 50],
            [['external_no'], 'string', 'max' => 120],
            [['sender'], 'string', 'max' => 255],
            [['subject'], 'string', 'max' => 500],
            [['urgency', 'confidentiality', 'status'], 'string', 'max' => 40],
            [['receive_no'], 'unique'],
            [['department_id'], 'exist', 'targetClass' => Department::class, 'targetAttribute' => ['department_id' => 'id']],
            [['approver_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['approver_id' => 'id']],
        ];
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        // หากสรุปเนื้อหาเป็นค่าว่าง ให้ใช้ชื่อเรื่องหนังสือเป็นข้อมูลตั้งต้น
        if ($this->summary === null || trim($this->summary) === '') {
            $this->summary = $this->subject;
        }

        return true;
    }

    public function attributeLabels(): array
    {
        return [
            'receive_no' => 'เลขรับ',
            'receive_date' => 'วันที่รับ',
            'receive_time' => 'เวลา',
            'external_no' => 'เลขที่หนังสือต้นทาง',
            'external_date' => 'ลงวันที่',
            'sender' => 'จาก',
            'subject' => 'เรื่อง',
            'summary' => 'สรุปเนื้อหาในหนังสือโดยย่อ',
            'urgency' => 'ความเร่งด่วน',
            'confidentiality' => 'ชั้นความลับ',
            'department_id' => 'กลุ่มงานที่เกี่ยวข้อง',
            'approver_id' => 'ผู้ตรวจสอบ/อนุมัติ',
            'due_date' => 'วันครบกำหนด',
            'status' => 'สถานะ',
            'note' => 'หมายเหตุ',
        ];
    }

    public static function urgencyOptions(): array
    {
        return [
            self::URGENCY_NORMAL => 'ปกติ',
            self::URGENCY_URGENT => 'ด่วน',
            self::URGENCY_VERY_URGENT => 'ด่วนมาก',
            self::URGENCY_MOST_URGENT => 'ด่วนที่สุด',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_REGISTERED => 'ลงทะเบียนรับแล้ว',
            self::STATUS_SUBMITTED_TO_APPROVER => 'รอตรวจสอบ/อนุมัติ',
            self::STATUS_SUBMITTED_TO_DEPUTY => 'รอรอง ผอ.',
            self::STATUS_SUBMITTED_TO_DIRECTOR => 'รอ ผอ. สั่งการ',
            self::STATUS_ASSIGNED => 'มอบหมายแล้ว',
            self::STATUS_RETURNED => 'ส่งกลับแก้ไข',
            self::STATUS_COMPLETED => 'เสร็จสิ้น',
        ];
    }

    public function getApprover(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'approver_id']);
    }

    public function getDepartment(): ActiveQuery
    {
        return $this->hasOne(Department::class, ['id' => 'department_id']);
    }

    public function getFiles(): ActiveQuery
    {
        return $this->hasMany(DocumentFile::class, ['document_id' => 'id']);
    }

    public function getOriginalFile(): ActiveQuery
    {
        return $this->hasOne(DocumentFile::class, ['document_id' => 'id'])->andOnCondition(['file_type' => DocumentFile::TYPE_ORIGINAL]);
    }

    public function getStampedFile(): ActiveQuery
    {
        return $this->hasOne(DocumentFile::class, ['document_id' => 'id'])->andOnCondition(['file_type' => DocumentFile::TYPE_STAMPED]);
    }

    public function getWorkflowActions(): ActiveQuery
    {
        return $this->hasMany(WorkflowAction::class, ['document_id' => 'id'])->orderBy(['created_at' => SORT_ASC]);
    }

    public function getDocumentReads(): ActiveQuery
    {
        return $this->hasMany(DocumentRead::class, ['document_id' => 'id']);
    }

    public function getAssignments(): ActiveQuery
    {
        return $this->hasMany(DocumentAssignment::class, ['document_id' => 'id']);
    }

    public function getStamps(): ActiveQuery
    {
        return $this->hasMany(DocumentStamp::class, ['document_id' => 'id']);
    }
}
