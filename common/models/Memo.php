<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string|null $memo_no
 * @property string $memo_date
 * @property int|null $department_id
 * @property string $subject
 * @property string $to_text
 * @property string|null $background
 * @property string|null $facts
 * @property string|null $legal
 * @property string|null $consideration
 * @property string|null $proposal
 * @property string|null $closing_text
 * @property string $status
 * @property int|null $current_actor_id
 * @property int|null $created_by
 */
class Memo extends ActiveRecord
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED_TO_STAFF = 'submitted_to_staff';
    public const STATUS_SUBMITTED_TO_DEPUTY = 'submitted_to_deputy';
    public const STATUS_SUBMITTED_TO_DIRECTOR = 'submitted_to_director';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_COMPLETED = 'completed';

    public static function tableName(): string
    {
        return '{{%memo}}';
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
            [['memo_date', 'subject', 'to_text'], 'required'],
            [['memo_date'], 'safe'],
            [['department_id', 'current_actor_id'], 'integer'],
            [['background', 'facts', 'legal', 'consideration', 'proposal'], 'string'],
            [['memo_no', 'status'], 'string', 'max' => 50],
            [['subject'], 'string', 'max' => 500],
            [['to_text', 'closing_text'], 'string', 'max' => 255],
            [['department_id'], 'exist', 'targetClass' => Department::class, 'targetAttribute' => ['department_id' => 'id']],
            [['current_actor_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['current_actor_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'memo_no' => 'เลขที่บันทึก',
            'memo_date' => 'วันที่',
            'department_id' => 'กลุ่มงาน',
            'subject' => 'เรื่อง',
            'to_text' => 'เรียน',
            'background' => 'เรื่องเดิม',
            'facts' => 'ข้อเท็จจริง',
            'legal' => 'ข้อกฎหมาย (ถ้ามี)',
            'consideration' => 'ข้อพิจารณา',
            'proposal' => 'ข้อเสนอ',
            'closing_text' => 'คำลงท้าย',
            'status' => 'สถานะ',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'ฉบับร่าง',
            self::STATUS_SUBMITTED_TO_STAFF => 'รอหัวหน้ากลุ่มงาน',
            self::STATUS_SUBMITTED_TO_DEPUTY => 'รอรอง ผอ.',
            self::STATUS_SUBMITTED_TO_DIRECTOR => 'รอ ผอ. พิจารณา',
            self::STATUS_RETURNED => 'ส่งกลับแก้ไข',
            self::STATUS_APPROVED => 'อนุมัติแล้ว',
            self::STATUS_COMPLETED => 'เสร็จสิ้น',
        ];
    }

    public function getDepartment(): ActiveQuery
    {
        return $this->hasOne(Department::class, ['id' => 'department_id']);
    }

    public function getFiles(): ActiveQuery
    {
        return $this->hasMany(MemoFile::class, ['memo_id' => 'id']);
    }

    public function getOriginalPdfFile(): ActiveQuery
    {
        return $this->hasOne(MemoFile::class, ['memo_id' => 'id'])
            ->andWhere(['{{%memo_file}}.[[description]]' => MemoFile::TYPE_ORIGINAL]);
    }

    public function getAttachmentFiles(): ActiveQuery
    {
        return $this->hasMany(MemoFile::class, ['memo_id' => 'id'])
            ->andWhere(['{{%memo_file}}.[[description]]' => MemoFile::TYPE_ATTACHMENT]);
    }

    public function getActions(): ActiveQuery
    {
        return $this->hasMany(MemoAction::class, ['memo_id' => 'id'])->orderBy(['created_at' => SORT_ASC]);
    }

    public function getStampPositions(): ActiveQuery
    {
        return $this->hasMany(MemoStampPosition::class, ['memo_id' => 'id']);
    }

    public function getCreator(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }

    public function getCurrentActor(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'current_actor_id']);
    }

    public function getMemoReads(): ActiveQuery
    {
        return $this->hasMany(MemoRead::class, ['memo_id' => 'id']);
    }
}
