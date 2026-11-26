<?php

declare(strict_types=1);

namespace backend\models;

use common\models\SystemSetting;
use yii\base\Model;

/**
 * SystemSettingForm handles system settings configuration by Admin.
 */
class SystemSettingForm extends Model
{
    public string $school_name = 'โรงเรียนกุดชุมวิทยาคม';
    public ?string $director_name = '';
    public string $director_position = 'ผู้อำนวยการโรงเรียนกุดชุมวิทยาคม';
    public int|string|null $acting_director_user_id = null;
    public ?string $acting_director_name = '';
    public string $acting_director_position = 'รองผู้อำนวยการ รักษาการในตำแหน่งผู้อำนวยการโรงเรียนกุดชุมวิทยาคม';
    public int|string $is_acting_director = 0;
    public int|string $auto_approve_signup = 0;
    public int|string $line_notify_enabled = 1;
    public int|string $line_quota_optimize = 1;
    public int|string $line_cooldown_minutes = 180;
    public int|string $line_realtime_cooldown_minutes = 30;
    public int|string $line_urgent_realtime = 1;
    public int|string $doc_receive_start_no = 1;
    public int|string $doc_receive_year = 2569;

    public function init(): void
    {
        parent::init();
        $this->loadFromDatabase();
    }

    public function rules(): array
    {
        return [
            [['doc_receive_start_no', 'doc_receive_year'], 'required'],
            [['doc_receive_start_no'], 'integer', 'min' => 1, 'max' => 999999],
            [['doc_receive_year'], 'integer', 'min' => 2500, 'max' => 2700],
            [['is_acting_director', 'auto_approve_signup', 'line_notify_enabled', 'line_quota_optimize', 'line_urgent_realtime'], 'boolean'],
            [['line_cooldown_minutes', 'line_realtime_cooldown_minutes'], 'integer', 'min' => 0, 'max' => 1440],
            [['line_cooldown_minutes'], 'default', 'value' => 180],
            [['line_realtime_cooldown_minutes'], 'default', 'value' => 30],
            [['acting_director_user_id'], 'default', 'value' => null],
            [['acting_director_user_id'], 'integer'],
            [['acting_director_user_id'], 'exist', 'skipOnError' => true, 'targetClass' => \common\models\User::class, 'targetAttribute' => ['acting_director_user_id' => 'id']],
            [['school_name', 'director_name', 'director_position', 'acting_director_name', 'acting_director_position'], 'string', 'max' => 255],
            [['school_name'], 'default', 'value' => 'โรงเรียนกุดชุมวิทยาคม'],
            [['director_position'], 'default', 'value' => 'ผู้อำนวยการโรงเรียนกุดชุมวิทยาคม'],
            [['acting_director_position'], 'default', 'value' => 'รองผู้อำนวยการ รักษาการในตำแหน่งผู้อำนวยการโรงเรียนกุดชุมวิทยาคม'],
        ];
    }

    public function load($data, $formName = null): bool
    {
        $scope = $formName ?? $this->formName();
        if ($scope !== '' && isset($data[$scope]['acting_director_user_id']) && $data[$scope]['acting_director_user_id'] === '') {
            $data[$scope]['acting_director_user_id'] = null;
        } elseif ($scope === '' && isset($data['acting_director_user_id']) && $data['acting_director_user_id'] === '') {
            $data['acting_director_user_id'] = null;
        }

        return parent::load($data, $formName);
    }

    public function attributeLabels(): array
    {
        return [
            'school_name' => 'ชื่อสถานศึกษา',
            'director_name' => 'ชื่อ-นามสกุล ผู้อำนวยการโรงเรียน',
            'director_position' => 'ตำแหน่งผู้อำนวยการโรงเรียน',
            'acting_director_user_id' => 'เลือกรองผู้อำนวยการ / ผู้ปฏิบัติหน้าที่รักษาการแทน',
            'acting_director_name' => 'ชื่อ-นามสกุล รองผู้อำนวยการ (รักษาการแทน)',
            'acting_director_position' => 'ตำแหน่งรักษาการ',
            'is_acting_director' => 'เปิดใช้งานชื่อรักษาการแทนผู้อำนวยการ (ในกรณีที่ผู้อำนวยการโรงเรียนไม่อยู่)',
            'auto_approve_signup' => 'อนุมัติผู้ใช้งานใหม่อัตโนมัติ (Auto Approve)',
            'line_notify_enabled' => 'เปิดใช้งานการแจ้งเตือนผ่าน LINE (Push Message)',
            'line_quota_optimize' => 'เปิดโหมดประหยัดโควตา LINE (รวมส่งเป็นรอบ + Cooldown)',
            'line_cooldown_minutes' => 'Cooldown กลุ่มส่งเป็นรอบ (นาที)',
            'line_realtime_cooldown_minutes' => 'Short Cooldown กลุ่มส่งทันที (นาที)',
            'line_urgent_realtime' => 'ส่งทันที (Real-time) สำหรับหนังสือด่วนมาก / ด่วนที่สุด',
            'doc_receive_start_no' => 'เลขรับหนังสือเริ่มต้น',
            'doc_receive_year' => 'ปี พ.ศ. ของเลขรับหนังสือ',
        ];
    }

    public function attributeHints(): array
    {
        return [
            'director_name' => 'ระบุคำนำหน้าและชื่อ-นามสกุล เช่น นายสมหมาย มั่นคง',
            'acting_director_user_id' => 'เลือกผู้ใช้ในระบบที่จะทำหน้าที่รักษาการแทน เพื่อให้ได้รับสิทธิ์ในการสั่งการและอนุมัติหนังสือ/บันทึกข้อความตามสายงาน',
            'acting_director_name' => 'ระบุชื่อรองผู้อำนวยการที่จะลงนามหรือปฏิบัติราชการแทนเมื่อ ผอ. ไม่อยู่',
            'is_acting_director' => 'ติ๊กเลือกเมื่อ ผอ. ติดราชการ/ไม่อยู่ เพื่อให้ระบบใช้ชื่อและตำแหน่งผู้รักษาการแทนบนเอกสารและงานสารบรรณ',
            'auto_approve_signup' => 'เมื่อเปิดใช้งาน สมาชิกใหม่ที่ลงทะเบียนและผูก LINE สำเร็จจะได้รับสิทธิ์เข้าใช้งานทันทีโดยไม่ต้องรอ Admin กดอนุมัติรายคน',
            'doc_receive_start_no' => 'เช่น เลข 1 หรือเลขเริ่มต้นตามสมุดทะเบียนรับของโรงเรียน',
            'doc_receive_year' => 'ระบุเป็นปี พ.ศ. เช่น ' . ((int) date('Y') + 543),
        ];
    }

    public function loadFromDatabase(): void
    {
        $currentYear = (int) date('Y') + 543;

        $this->school_name = (string) SystemSetting::get(SystemSetting::KEY_SCHOOL_NAME, 'โรงเรียนกุดชุมวิทยาคม');
        $this->director_name = (string) SystemSetting::get(SystemSetting::KEY_DIRECTOR_NAME, '');
        $this->director_position = (string) SystemSetting::get(SystemSetting::KEY_DIRECTOR_POSITION, 'ผู้อำนวยการโรงเรียนกุดชุมวิทยาคม');
        $actingUserId = SystemSetting::get(SystemSetting::KEY_ACTING_DIRECTOR_USER_ID);
        $this->acting_director_user_id = ($actingUserId !== null && $actingUserId !== '' && (int) $actingUserId > 0) ? (int) $actingUserId : null;
        $this->acting_director_name = (string) SystemSetting::get(SystemSetting::KEY_ACTING_DIRECTOR_NAME, '');
        $this->acting_director_position = (string) SystemSetting::get(SystemSetting::KEY_ACTING_DIRECTOR_POSITION, 'รองผู้อำนวยการ รักษาการในตำแหน่งผู้อำนวยการโรงเรียนกุดชุมวิทยาคม');
        $this->is_acting_director = (int) SystemSetting::get(SystemSetting::KEY_IS_ACTING_DIRECTOR, 0);
        $this->auto_approve_signup = (int) SystemSetting::get(SystemSetting::KEY_AUTO_APPROVE_SIGNUP, 0);
        $this->line_notify_enabled = (int) SystemSetting::get(SystemSetting::KEY_LINE_NOTIFY_ENABLED, 1);
        $this->line_quota_optimize = (int) SystemSetting::get(SystemSetting::KEY_LINE_QUOTA_OPTIMIZE, 1);
        $this->line_cooldown_minutes = (int) SystemSetting::get(SystemSetting::KEY_LINE_COOLDOWN_MINUTES, 180);
        $this->line_realtime_cooldown_minutes = (int) SystemSetting::get(SystemSetting::KEY_LINE_REALTIME_COOLDOWN_MINUTES, 30);
        $this->line_urgent_realtime = (int) SystemSetting::get(SystemSetting::KEY_LINE_URGENT_REALTIME, 1);
        $this->doc_receive_start_no = (int) SystemSetting::get(SystemSetting::KEY_DOC_RECEIVE_START_NO, 1);
        $this->doc_receive_year = (int) SystemSetting::get(SystemSetting::KEY_DOC_RECEIVE_YEAR, $currentYear);
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        if ($this->acting_director_user_id !== null && $this->acting_director_user_id > 0) {
            $actingUser = \common\models\User::findOne(['id' => $this->acting_director_user_id, 'status' => \common\models\User::STATUS_ACTIVE]);
            if ($actingUser !== null) {
                $this->acting_director_name = $actingUser->displayName();
            }
        } else {
            $this->acting_director_user_id = null;
        }

        SystemSetting::set(SystemSetting::KEY_SCHOOL_NAME, trim($this->school_name), 'general', 'ชื่อสถานศึกษา');
        SystemSetting::set(SystemSetting::KEY_DIRECTOR_NAME, trim((string) $this->director_name), 'executive', 'ชื่อผู้อำนวยการโรงเรียน');
        SystemSetting::set(SystemSetting::KEY_DIRECTOR_POSITION, trim($this->director_position), 'executive', 'ตำแหน่งผู้อำนวยการโรงเรียน');
        SystemSetting::set(SystemSetting::KEY_ACTING_DIRECTOR_USER_ID, $this->acting_director_user_id ? (string) $this->acting_director_user_id : '', 'executive', 'User ID ผู้รักษาการแทนผู้อำนวยการ');
        SystemSetting::set(SystemSetting::KEY_ACTING_DIRECTOR_NAME, trim((string) $this->acting_director_name), 'executive', 'ชื่อรองผู้อำนวยการ รักษาการในตำแหน่งผู้อำนวยการโรงเรียน');
        SystemSetting::set(SystemSetting::KEY_ACTING_DIRECTOR_POSITION, trim($this->acting_director_position), 'executive', 'ตำแหน่งรักษาการแทนผู้อำนวยการโรงเรียน');
        SystemSetting::set(SystemSetting::KEY_IS_ACTING_DIRECTOR, $this->is_acting_director ? '1' : '0', 'executive', 'ใช้ชื่อรักษาการแทนผู้อำนวยการ');
        SystemSetting::set(SystemSetting::KEY_AUTO_APPROVE_SIGNUP, $this->auto_approve_signup ? '1' : '0', 'user', 'อนุมัติการสมัครสมาชิกใหม่อัตโนมัติ');
        SystemSetting::set(SystemSetting::KEY_LINE_NOTIFY_ENABLED, $this->line_notify_enabled ? '1' : '0', 'line', 'เปิด/ปิดการส่งแจ้งเตือนผ่าน LINE (Push Message)');
        SystemSetting::set(SystemSetting::KEY_LINE_QUOTA_OPTIMIZE, $this->line_quota_optimize ? '1' : '0', 'line', 'เปิดโหมดประหยัดโควตาแจ้งเตือน LINE (Digest + Cooldown)');
        SystemSetting::set(SystemSetting::KEY_LINE_COOLDOWN_MINUTES, (string) max(0, (int) $this->line_cooldown_minutes), 'line', 'ระยะเวลา Cooldown กลุ่มส่งเป็นรอบ (นาที)');
        SystemSetting::set(SystemSetting::KEY_LINE_REALTIME_COOLDOWN_MINUTES, (string) max(0, (int) $this->line_realtime_cooldown_minutes), 'line', 'ระยะเวลา Short Cooldown กลุ่มส่งทันที (นาที)');
        SystemSetting::set(SystemSetting::KEY_LINE_URGENT_REALTIME, $this->line_urgent_realtime ? '1' : '0', 'line', 'ส่งทันทีสำหรับหนังสือด่วนมาก/ด่วนที่สุด');
        SystemSetting::set(SystemSetting::KEY_DOC_RECEIVE_START_NO, (string) $this->doc_receive_start_no, 'document', 'เลขรับหนังสือเริ่มต้น');
        SystemSetting::set(SystemSetting::KEY_DOC_RECEIVE_YEAR, (string) $this->doc_receive_year, 'document', 'ปี พ.ศ. ของเลขรับหนังสือ');

        return true;
    }
}

