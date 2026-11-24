<?php

declare(strict_types=1);

use common\models\SystemSetting;
use common\models\User;
use yii\bootstrap5\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var User[] $allUsers */
/** @var array<string, User[]> $groupedUsers */
/** @var array<string, int> $countsByArea */
/** @var array<string, string> $learningAreaOptions */
/** @var int|null $currentStatus */
/** @var string|null $currentArea */
/** @var string|null $currentQ */
/** @var string $currentView */
/** @var int $pendingCount */
/** @var int $activeCount */
/** @var int $totalCount */
/** @var User[]|null $activeUsers */

$this->title = 'ผู้ใช้และบทบาท';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);

$roleOptions = User::roleOptions();
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->csrfToken;
$csrf = Html::hiddenInput($csrfParam, $csrfToken);
$currentUserId = (int) Yii::$app->user->id;

// กำหนดไอคอน สี และข้อความย่อสำหรับแต่ละกลุ่มสาระการเรียนรู้
$areaMeta = [
    'กลุ่มสาระการเรียนรู้ภาษาไทย' => [
        'icon' => 'bi-book-half',
        'color' => '#1d4ed8',
        'bg' => '#eff6ff',
        'border' => '#bfdbfe',
        'badge' => 'bg-primary',
        'short' => 'ภาษาไทย',
    ],
    'กลุ่มสาระการเรียนรู้คณิตศาสตร์' => [
        'icon' => 'bi-calculator-fill',
        'color' => '#b45309',
        'bg' => '#fffbeb',
        'border' => '#fde68a',
        'badge' => 'bg-warning text-dark',
        'short' => 'คณิตศาสตร์',
    ],
    'กลุ่มสาระการเรียนรู้วิทยาศาสตร์และเทคโนโลยี' => [
        'icon' => 'bi-cpu-fill',
        'color' => '#0369a1',
        'bg' => '#f0f9ff',
        'border' => '#bae6fd',
        'badge' => 'bg-info text-white',
        'short' => 'วิทย์-เทคโนฯ',
    ],
    'กลุ่มสาระการเรียนรู้สังคมศึกษา ศาสนา และวัฒนธรรม' => [
        'icon' => 'bi-globe-americas',
        'color' => '#15803d',
        'bg' => '#f0fdf4',
        'border' => '#bbf7d0',
        'badge' => 'bg-success',
        'short' => 'สังคมศึกษาฯ',
    ],
    'กลุ่มสาระการเรียนรู้สุขศึกษาและพลศึกษา' => [
        'icon' => 'bi-heart-pulse-fill',
        'color' => '#b91c1c',
        'bg' => '#fef2f2',
        'border' => '#fecaca',
        'badge' => 'bg-danger',
        'short' => 'สุขศึกษา-พลศึกษา',
    ],
    'กลุ่มสาระการเรียนรู้ศิลปะ' => [
        'icon' => 'bi-palette-fill',
        'color' => '#6d28d9',
        'bg' => '#f5f3ff',
        'border' => '#ddd6fe',
        'badge' => 'bg-purple text-white',
        'short' => 'ศิลปะ',
    ],
    'กลุ่มสาระการเรียนรู้การงานอาชีพ' => [
        'icon' => 'bi-tools',
        'color' => '#475569',
        'bg' => '#f8fafc',
        'border' => '#cbd5e1',
        'badge' => 'bg-secondary',
        'short' => 'การงานอาชีพ',
    ],
    'กลุ่มสาระการเรียนรู้ภาษาต่างประเทศ' => [
        'icon' => 'bi-translate',
        'color' => '#0284c7',
        'bg' => '#f0fdfa',
        'border' => '#99f6e4',
        'badge' => 'bg-info text-dark',
        'short' => 'ภาษาต่างประเทศ',
    ],
    'กิจกรรมพัฒนาผู้เรียน' => [
        'icon' => 'bi-stars',
        'color' => '#0f766e',
        'bg' => '#ecfdf5',
        'border' => '#a7f3d0',
        'badge' => 'bg-teal text-white',
        'short' => 'กิจกรรมพัฒนาผู้เรียน',
    ],
    'ไม่สังกัดกลุ่มสาระ (ผู้บริหาร/บุคลากร)' => [
        'icon' => 'bi-building-fill-check',
        'color' => '#334155',
        'bg' => '#f1f5f9',
        'border' => '#cbd5e1',
        'badge' => 'bg-dark text-white',
        'short' => 'ผู้บริหาร / บุคลากร',
    ],
    '_unassigned' => [
        'icon' => 'bi-exclamation-triangle-fill',
        'color' => '#c2410c',
        'bg' => '#fff7ed',
        'border' => '#fed7aa',
        'badge' => 'bg-warning text-dark',
        'short' => 'ยังไม่ระบุกลุ่มสาระ',
    ],
];

/**
 * ฟังก์ชันช่วยสร้าง HTML สำหรับแถวข้อมูลผู้ใช้งานในตาราง
 */
$renderUserRow = function (User $model, int $index, bool $showLearningAreaSelect = false) use ($roleOptions, $learningAreaOptions, $csrf, $currentUserId): string {
    $out = '<tr>';
    
    // 1. ลำดับ
    $out .= '<td class="text-center text-muted small fw-semibold" style="width: 45px;">' . ($index + 1) . '</td>';

    // 2. ชื่อผู้ใช้ / ชื่อนามสกุล
    $out .= '<td>';
    $out .= '<div class="d-flex align-items-center gap-2">';
    $out .= '<div class="rounded-circle d-flex align-items-center justify-content-center bg-primary-subtle text-primary fw-bold" style="width: 36px; height: 36px; flex-shrink: 0; font-size: 0.85rem;">';
    $displayName = $model->displayName();
    $out .= Html::encode(mb_substr($displayName, 0, 1, 'UTF-8'));
    $out .= '</div>';
    $out .= '<div>';
    $out .= '<div class="fw-bold text-dark d-flex align-items-center gap-1 flex-wrap">';
    $out .= Html::encode($displayName);
    if (!empty($model->signature_path)) {
        $out .= ' <span class="badge bg-success-subtle text-success border border-success px-1 py-0" title="มีไฟล์ลายเซ็นดิจิทัลแล้ว" style="font-size: 0.7rem;"><i class="bi bi-pen me-1"></i>มีลายเซ็น</span>';
    }
    $out .= '</div>';
    $out .= '<div class="small text-muted font-monospace"><i class="bi bi-person me-1"></i>' . Html::encode($model->username) . '</div>';
    if (!empty($model->phone)) {
        $out .= '<div class="small text-muted"><i class="bi bi-telephone me-1"></i>' . Html::encode($model->phone) . '</div>';
    }
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</td>';

    // 3. กลุ่มงาน / สังกัด
    $out .= '<td style="min-width: 150px;">';
    if ($model->role === User::ROLE_DEPUTY_DIRECTOR) {
        $deptName = $model->department->name ?? '-';
        $isAssistant = $model->deputy_title === User::DEPUTY_TITLE_ASSISTANT;
        $badgeStyle = $isAssistant
            ? 'background-color: #f3e8ff; color: #6d28d9; border: 1px solid #d8b4fe;'
            : '';
        $badgeClass = $isAssistant
            ? 'badge px-2 py-1'
            : 'badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1';
        $out .= '<span class="' . $badgeClass . '" style="' . $badgeStyle . '"><i class="bi bi-shield-check me-1"></i>' . Html::encode($model->deputyTitleShort()) . ' · กำกับ: ' . Html::encode($deptName) . '</span>';
        $out .= '<div class="mt-1">';
        $out .= Html::beginForm(['update-deputy-title', 'id' => $model->id], 'post', ['class' => 'd-flex align-items-center gap-1'])
            . $csrf
            . Html::dropDownList('deputy_title', $model->deputy_title ?: User::DEPUTY_TITLE_DEPUTY, User::deputyTitleOptions(), [
                'class' => 'form-select form-select-sm',
                'style' => 'font-size: 0.76rem; max-width: 170px;',
                'aria-label' => 'เลือกตำแหน่งผู้บริหาร',
            ])
            . Html::submitButton('<i class="bi bi-check-lg"></i>', ['class' => 'btn btn-sm btn-outline-primary py-0 px-2', 'title' => 'บันทึกตำแหน่ง'])
            . Html::endForm();
        $out .= '</div>';
    } elseif ($model->role === User::ROLE_STAFF) {
        $deptName = $model->department->name ?? '-';
        $out .= '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1"><i class="bi bi-person-badge me-1"></i>หัวหน้า: ' . Html::encode($deptName) . '</span>';
    } elseif ($model->role === User::ROLE_GENERAL_USER) {
        $names = array_map(static fn ($department): string => $department->name, $model->departments);
        $out .= $names !== [] ? '<span class="small text-dark">' . Html::encode(implode(', ', $names)) . '</span>' : '<span class="small text-muted">' . Html::encode($model->department->name ?? '-') . '</span>';
    } else {
        $out .= '<span class="small text-muted">' . Html::encode($model->department->name ?? '-') . '</span>';
    }
    
    // ถ้าแสดงช่องปรับกลุ่มสาระฯ ด่วน
    if ($showLearningAreaSelect) {
        $out .= '<div class="mt-1">';
        $out .= Html::beginForm(['update-learning-area', 'id' => $model->id], 'post', ['class' => 'd-flex align-items-center gap-1'])
            . $csrf
            . Html::dropDownList('learning_area', $model->learning_area, ['' => '-- เลือกกลุ่มสาระ --'] + $learningAreaOptions, [
                'class' => 'form-select form-select-sm',
                'style' => 'font-size: 0.78rem; max-width: 220px;',
            ])
            . Html::submitButton('<i class="bi bi-check-lg"></i>', ['class' => 'btn btn-sm btn-outline-primary py-0 px-2', 'title' => 'บันทึกกลุ่มสาระ'])
            . Html::endForm();
        $out .= '</div>';
    }
    $out .= '</td>';

    // 4. บทบาทผู้ใช้ (Role Dropdown Form)
    $out .= '<td style="min-width: 170px;">';
    $select = Html::dropDownList('role', $model->role, $roleOptions, [
        'class' => 'form-select form-select-sm',
        'style' => 'font-size: 0.8rem;',
    ]);
    $button = Html::submitButton('<i class="bi bi-save me-1" aria-hidden="true"></i><span>บันทึก</span>', [
        'class' => 'btn btn-sm btn-primary py-1 px-2 text-nowrap',
        'style' => 'font-size: 0.75rem;',
    ]);
    $out .= Html::beginForm(['update-role', 'id' => $model->id], 'post', ['class' => 'd-flex align-items-center gap-1'])
        . $csrf
        . $select
        . $button
        . Html::endForm();
    $out .= '</td>';

    // 5. สถานะ
    $out .= '<td class="text-center" style="width: 110px;">';
    $out .= match ($model->status) {
        User::STATUS_ACTIVE => '<span class="badge bg-success-subtle text-success border border-success px-2 py-1"><i class="bi bi-check-circle me-1"></i>เปิดใช้งาน</span>',
        User::STATUS_INACTIVE => '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1"><i class="bi bi-hourglass-split me-1"></i>รออนุมัติ</span>',
        User::STATUS_DELETED => '<span class="badge bg-secondary px-2 py-1">ปิดใช้งาน</span>',
        default => (string) $model->status,
    };
    $out .= '</td>';

    // 6. LINE Login
    $out .= '<td class="text-center" style="width: 110px;">';
    if ($model->line_user_id !== null && $model->line_user_id !== '') {
        $title = $model->line_display_name !== null && $model->line_display_name !== ''
            ? 'เชื่อมต่อกับ ' . $model->line_display_name
            : 'บัญชีนี้เชื่อมต่อ LINE แล้ว (เข้าสู่ระบบด้วย LINE ได้)';
        $out .= Html::tag('span', '<i class="bi bi-chat-check-fill text-success me-1"></i><span>เชื่อมแล้ว</span>', [
            'class' => 'badge bg-success-subtle text-success border border-success px-2 py-1',
            'title' => $title,
        ]);
    } else {
        $out .= Html::tag('span', '<i class="bi bi-chat-slash text-muted me-1"></i><span>ยังไม่เชื่อม</span>', [
            'class' => 'badge bg-light text-muted border px-2 py-1',
        ]);
    }
    $out .= '</td>';

    // 7. ปุ่มจัดการ
    $out .= '<td class="text-end" style="min-width: 160px;">';
    $items = [];
    if ($model->status === User::STATUS_INACTIVE) {
        $items[] = Html::a(
            '<i class="bi bi-check-lg me-1" aria-hidden="true"></i>อนุมัติ',
            ['approve', 'id' => $model->id],
            [
                'class' => 'btn btn-sm btn-success text-white fw-semibold py-1 px-2',
                'style' => 'font-size: 0.78rem;',
                'data-method' => 'post',
                'data-confirm' => 'ยืนยันอนุมัติการใช้งานสำหรับ ' . $model->displayName() . ' หรือไม่?',
            ]
        );
    }
    $items[] = Html::a(
        '<i class="bi bi-key me-1" aria-hidden="true"></i>รีเซ็ต',
        ['reset-password', 'id' => $model->id],
        [
            'class' => 'btn btn-sm btn-outline-warning text-dark py-1 px-2',
            'style' => 'font-size: 0.78rem;',
            'data-method' => 'post',
            'data-confirm' => 'ยืนยันรีเซ็ตรหัสผ่านสำหรับ ' . $model->displayName() . ' หรือไม่? ระบบจะสร้างรหัสผ่านใหม่ให้อัตโนมัติ',
            'title' => 'รีเซ็ตรหัสผ่านเริ่มต้น',
        ]
    );
    $items[] = Html::a(
        '<i class="bi bi-pencil-square" aria-hidden="true"></i>',
        ['update', 'id' => $model->id],
        [
            'class' => 'btn btn-sm btn-outline-primary py-1 px-2',
            'style' => 'font-size: 0.78rem;',
            'title' => 'แก้ไขข้อมูลผู้ใช้',
        ]
    );
    if ((int) $model->id !== $currentUserId && (int) $model->status !== User::STATUS_DELETED) {
        $items[] = Html::a(
            '<i class="bi bi-trash" aria-hidden="true"></i>',
            ['delete', 'id' => $model->id],
            [
                'class' => 'btn btn-sm btn-outline-danger py-1 px-2',
                'style' => 'font-size: 0.78rem;',
                'data-method' => 'post',
                'data-confirm' => 'ยืนยันลบผู้ใช้นี้หรือไม่? ระบบจะปิดใช้งานบัญชีแทนการลบข้อมูลถาวร',
                'title' => 'ปิดใช้งานผู้ใช้',
            ]
        );
    }
    $out .= '<div class="d-flex gap-1 justify-content-end flex-wrap">' . implode('', $items) . '</div>';
    $out .= '</td>';

    $out .= '</tr>';
    return $out;
};
?>

<div class="eoffice-page">
    <!-- ส่วนหัวหน้าจอ -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h1 class="h5 fw-bold text-dark mb-0">
                <i class="bi bi-people-fill text-primary me-2"></i><?= Html::encode($this->title) ?>
            </h1>
            <div class="small text-muted">จัดการบัญชีผู้ใช้งาน กำหนดสิทธิ์ และแสดงรายชื่อจำแนกตามกลุ่มสาระการเรียนรู้</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?= Html::a('<i class="bi bi-person-plus me-1" aria-hidden="true"></i><span>สร้างผู้ใช้ใหม่</span>', ['create'], ['class' => 'btn btn-sm btn-primary fw-semibold shadow-sm']) ?>
        </div>
    </div>

    <!-- กล่องควบคุมโหมดอนุมัติสมาชิกอัตโนมัติ (Auto Approve) -->
    <?php $isAutoApprove = SystemSetting::isAutoApproveSignup(); ?>
    <div class="card border-0 shadow-sm mb-3 <?= $isAutoApprove ? 'bg-success-subtle border-start border-4 border-success' : 'bg-light border-start border-4 border-secondary' ?>">
        <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center <?= $isAutoApprove ? 'bg-success text-white' : 'bg-secondary text-white' ?>" style="width: 38px; height: 38px; flex-shrink: 0;">
                    <i class="bi bi-magic fs-5"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <strong class="text-dark">โหมดอนุมัติสมาชิกอัตโนมัติ (Auto Approve)</strong>
                        <span class="badge <?= $isAutoApprove ? 'bg-success text-white' : 'bg-secondary text-white' ?> px-2 py-0">
                            <?= $isAutoApprove ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?>
                        </span>
                    </div>
                    <div class="small text-muted">
                        <?= $isAutoApprove
                            ? 'ครู/บุคลากรที่สมัครใหม่พร้อมเชื่อม LINE จะได้รับสิทธิ์ใช้งานทันที (Active) โดยไม่ต้องรอ Admin อนุมัติรายคน'
                            : 'เมื่อมีผู้สมัครใหม่ สมาชิกจะอยู่ในสถานะ "รออนุมัติ" และต้องให้ Admin กดปุ่มอนุมัติในหน้านี้' ?>
                    </div>
                </div>
            </div>
            <div>
                <?= Html::a(
                    $isAutoApprove
                        ? '<i class="bi bi-toggle-on text-success fs-5 me-1"></i><span>คลิกเพื่อปิดโหมดอัตโนมัติ</span>'
                        : '<i class="bi bi-toggle-off text-secondary fs-5 me-1"></i><span>คลิกเพื่อเปิดโหมดอัตโนมัติ</span>',
                    ['toggle-auto-approve'],
                    [
                        'class' => 'btn btn-sm ' . ($isAutoApprove ? 'btn-outline-danger' : 'btn-outline-success') . ' fw-semibold px-3 py-1 d-inline-flex align-items-center shadow-sm',
                        'data-method' => 'post',
                        'data-confirm' => $isAutoApprove
                            ? 'ต้องการปิดโหมดอนุมัติอัตโนมัติใช่หรือไม่? (ผู้สมัครใหม่จะต้องรอ Admin อนุมัติรายคน)'
                            : 'ต้องการเปิดโหมดอนุมัติอัตโนมัติใช่หรือไม่? (ผู้สมัครใหม่จะได้รับสิทธิ์เข้าใช้งานทันที)',
                    ]
                ) ?>
            </div>
        </div>
    </div>

    <!-- กล่องกำหนดผู้ตรวจสอบ/อนุมัติหนังสือรับ (ก่อนเสนอรอง ผอ.) -->
    <?php
    $currentApprover = SystemSetting::getDocumentApprover();
    $approverUserList = $activeUsers ?? User::find()->where(['status' => User::STATUS_ACTIVE])->orderBy([new \yii\db\Expression("COALESCE(NULLIF(fullname, ''), username) ASC")])->all();
    $approverOptions = ['' => '-- ยังไม่กำหนดผู้ตรวจสอบ --'];
    foreach ($approverUserList as $u) {
        $deptSuffix = $u->department ? ' - ' . $u->department->name : '';
        $approverOptions[$u->id] = $u->displayName() . ' (' . $u->roleLabel() . $deptSuffix . ')';
    }
    ?>
    <div class="card border-0 shadow-sm mb-3 <?= $currentApprover !== null ? 'bg-primary-subtle border-start border-4 border-primary' : 'bg-light border-start border-4 border-warning' ?>">
        <div class="card-body p-3">
            <div class="row align-items-center g-3">
                <div class="col-lg-6">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center <?= $currentApprover !== null ? 'bg-primary text-white' : 'bg-warning text-dark' ?>" style="width: 38px; height: 38px; flex-shrink: 0;">
                            <i class="bi bi-shield-check fs-5"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <strong class="text-dark">ผู้ตรวจสอบ/อนุมัติหนังสือรับ (ก่อนเสนอรอง ผอ.)</strong>
                                <?php if ($currentApprover !== null): ?>
                                    <span class="badge bg-primary text-white px-2 py-0">กำหนดแล้ว</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark px-2 py-0">ยังไม่กำหนด</span>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted mt-1">
                                <?php if ($currentApprover !== null): ?>
                                    ปัจจุบันคือ: <strong class="text-primary"><?= Html::encode($currentApprover->displayName()) ?></strong>
                                    (<?= Html::encode($currentApprover->roleLabel()) ?><?= $currentApprover->department ? ' - ' . Html::encode($currentApprover->department->name) : '' ?>)
                                    <span class="text-secondary ms-1">| ธุรการจะไม่สามารถเปลี่ยนผู้ตรวจเองได้</span>
                                <?php else: ?>
                                    <span class="text-danger">ยังไม่ได้ระบุผู้ตรวจสอบ — เจ้าหน้าที่ธุรการจะไม่สามารถส่งเสนอตรวจสอบหนังสือได้</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <?= Html::beginForm(['set-document-approver'], 'post', ['class' => 'd-flex align-items-center gap-2 justify-content-lg-end']) ?>
                        <?= $csrf ?>
                        <div class="flex-grow-1" style="max-width: 340px;">
                            <?= Html::dropDownList('approver_id', $currentApprover?->id, $approverOptions, [
                                'class' => 'form-select form-select-sm',
                                'aria-label' => 'เลือกผู้ตรวจสอบหนังสือรับ',
                            ]) ?>
                        </div>
                        <?= Html::submitButton('<i class="bi bi-check2-circle me-1"></i>บันทึก', [
                            'class' => 'btn btn-sm btn-primary fw-semibold px-3 py-1 text-nowrap shadow-sm',
                        ]) ?>
                    <?= Html::endForm() ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ส่วนแถบกรองสถานะ และสลับรูปแบบการแสดงผล -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <!-- แถบกรองสถานะผู้ใช้งาน -->
        <ul class="nav nav-pills gap-1 mb-0">
            <li class="nav-item">
                <?= Html::a(
                    'ทั้งหมด <span class="badge bg-secondary ms-1">' . $totalCount . '</span>',
                    ['index', 'status' => null, 'learning_area' => $currentArea, 'q' => $currentQ, 'view' => $currentView],
                    ['class' => 'nav-link py-1 px-3 ' . ($currentStatus === null ? 'active' : 'bg-white border text-dark')]
                ) ?>
            </li>
            <li class="nav-item">
                <?= Html::a(
                    'รออนุมัติ ' . ($pendingCount > 0 ? "<span class='badge bg-danger ms-1'>{$pendingCount}</span>" : "<span class='badge bg-secondary ms-1'>0</span>"),
                    ['index', 'status' => User::STATUS_INACTIVE, 'learning_area' => $currentArea, 'q' => $currentQ, 'view' => $currentView],
                    ['class' => 'nav-link py-1 px-3 ' . ($currentStatus === User::STATUS_INACTIVE ? 'active bg-warning text-dark' : 'bg-white border text-dark')]
                ) ?>
            </li>
            <li class="nav-item">
                <?= Html::a(
                    "เปิดใช้งาน <span class='badge bg-success ms-1'>{$activeCount}</span>",
                    ['index', 'status' => User::STATUS_ACTIVE, 'learning_area' => $currentArea, 'q' => $currentQ, 'view' => $currentView],
                    ['class' => 'nav-link py-1 px-3 ' . ($currentStatus === User::STATUS_ACTIVE ? 'active' : 'bg-white border text-dark')]
                ) ?>
            </li>
        </ul>

        <!-- ปุ่มสลับโหมดการแสดงผล (แยกตามกลุ่มสาระ vs ตารางรวม) -->
        <div class="d-flex align-items-center gap-2">
            <div class="btn-group btn-group-sm shadow-sm" role="group">
                <?= Html::a(
                    '<i class="bi bi-grid-3x2-gap-fill me-1"></i>จำแนกตามกลุ่มสาระ',
                    ['index', 'status' => $currentStatus, 'learning_area' => $currentArea, 'q' => $currentQ, 'view' => 'grouped'],
                    ['class' => 'btn ' . ($currentView === 'grouped' ? 'btn-primary' : 'btn-outline-secondary')]
                ) ?>
                <?= Html::a(
                    '<i class="bi bi-table me-1"></i>ตารางรวมทั้งหมด',
                    ['index', 'status' => $currentStatus, 'learning_area' => $currentArea, 'q' => $currentQ, 'view' => 'table'],
                    ['class' => 'btn ' . ($currentView === 'table' ? 'btn-primary' : 'btn-outline-secondary')]
                ) ?>
            </div>
        </div>
    </div>

    <!-- แถบค้นหาและกรองกลุ่มสาระการเรียนรู้ -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <!-- ช่องค้นหาด่วน -->
            <form method="get" action="<?= Url::to(['index']) ?>" class="row g-2 align-items-center mb-3">
                <?php if ($currentStatus !== null): ?>
                    <input type="hidden" name="status" value="<?= Html::encode((string) $currentStatus) ?>">
                <?php endif; ?>
                <?php if ($currentArea !== null && $currentArea !== ''): ?>
                    <input type="hidden" name="learning_area" value="<?= Html::encode($currentArea) ?>">
                <?php endif; ?>
                <input type="hidden" name="view" value="<?= Html::encode($currentView) ?>">

                <div class="col-sm-8 col-md-6 col-lg-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="ค้นหาชื่อ, นามสกุล, เบอร์โทร..." value="<?= Html::encode((string) $currentQ) ?>">
                        <?php if (!empty($currentQ)): ?>
                            <a href="<?= Url::to(['index', 'status' => $currentStatus, 'learning_area' => $currentArea, 'view' => $currentView]) ?>" class="btn btn-outline-secondary" title="ล้างคำค้นหา"><i class="bi bi-x"></i></a>
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit">ค้นหา</button>
                    </div>
                </div>
                <div class="col-auto small text-muted">
                    แสดงผลทั้งหมด <strong><?= count($allUsers) ?></strong> รายการ
                </div>
            </form>

            <!-- แถบปุ่มกลุ่มสาระการเรียนรู้ (Learning Area Badges Navigation) -->
            <div class="d-flex align-items-center gap-1 flex-wrap pt-2 border-top">
                <span class="small fw-bold text-muted me-2"><i class="bi bi-funnel me-1"></i>กลุ่มสาระฯ:</span>
                
                <!-- ปุ่มทุกกลุ่มสาระ -->
                <?= Html::a(
                    'ทั้งหมด (' . count($allUsers) . ')',
                    ['index', 'status' => $currentStatus, 'learning_area' => null, 'q' => $currentQ, 'view' => $currentView],
                    [
                        'class' => 'badge text-decoration-none py-2 px-2 ' . ($currentArea === null || $currentArea === '' ? 'bg-primary text-white shadow-sm' : 'bg-light text-dark border'),
                        'style' => 'font-size: 0.8rem;',
                    ]
                ) ?>

                <!-- ปุ่มแต่ละกลุ่มสาระ -->
                <?php foreach ($learningAreaOptions as $areaKey => $areaLabel): ?>
                    <?php
                    $areaCount = $countsByArea[$areaKey] ?? 0;
                    $meta = $areaMeta[$areaKey] ?? ['short' => $areaLabel, 'icon' => 'bi-book', 'color' => '#333'];
                    $isActive = ($currentArea === $areaKey);
                    ?>
                    <?= Html::a(
                        '<i class="bi ' . $meta['icon'] . ' me-1"></i>' . Html::encode($meta['short']) . ' <span class="badge rounded-pill ' . ($isActive ? 'bg-white text-dark' : 'bg-secondary') . ' ms-1">' . $areaCount . '</span>',
                        ['index', 'status' => $currentStatus, 'learning_area' => $areaKey, 'q' => $currentQ, 'view' => $currentView],
                        [
                            'class' => 'badge text-decoration-none py-2 px-2 ' . ($isActive ? 'bg-dark text-white shadow-sm' : 'bg-light text-dark border'),
                            'style' => 'font-size: 0.8rem;',
                        ]
                    ) ?>
                <?php endforeach; ?>

                <!-- ปุ่มกลุ่มยังไม่ระบุกลุ่มสาระ -->
                <?php
                $unassignedCount = $countsByArea['_unassigned'] ?? 0;
                $isUnassignedActive = ($currentArea === '_unassigned' || $currentArea === 'unassigned');
                ?>
                <?= Html::a(
                    '<i class="bi bi-question-circle me-1"></i>ยังไม่ระบุสาระฯ <span class="badge rounded-pill ' . ($unassignedCount > 0 ? 'bg-warning text-dark' : 'bg-secondary') . ' ms-1">' . $unassignedCount . '</span>',
                    ['index', 'status' => $currentStatus, 'learning_area' => '_unassigned', 'q' => $currentQ, 'view' => $currentView],
                    [
                        'class' => 'badge text-decoration-none py-2 px-2 ' . ($isUnassignedActive ? 'bg-warning text-dark shadow-sm border border-warning' : 'bg-light text-dark border'),
                        'style' => 'font-size: 0.8rem;',
                    ]
                ) ?>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- มุมมองที่ 1: จำแนกตามกลุ่มสาระการเรียนรู้ (Grouped View) -->
    <!-- ========================================== -->
    <?php if ($currentView === 'grouped'): ?>

        <!-- กล่องสำหรับ "ยังไม่ระบุกลุ่มสาระการเรียนรู้" (ถ้ามีสมาชิกที่ยังไม่ได้ระบุกลุ่มสาระ) -->
        <?php
        $unassignedUsers = $groupedUsers['_unassigned'] ?? [];
        $showUnassignedSection = ($currentArea === null || $currentArea === '' || $currentArea === '_unassigned' || $currentArea === 'unassigned') && count($unassignedUsers) > 0;
        ?>
        <?php if ($showUnassignedSection): ?>
            <div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
                <div class="card-header bg-warning-subtle py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning text-dark" style="width: 32px; height: 32px;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">ยังไม่ระบุกลุ่มสาระการเรียนรู้</h6>
                            <div class="small text-muted">สมาชิกที่ยังไม่ได้กำหนดกลุ่มสาระฯ (เช่น บัญชีเดิม) ท่านสามารถเลือกกลุ่มสาระฯ และกดบันทึกได้ทันที</div>
                        </div>
                    </div>
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold shadow-sm">
                        <?= count($unassignedUsers) ?> คน
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 45px;">#</th>
                                    <th>ชื่อผู้ใช้ / ชื่อนามสกุล</th>
                                    <th>กำหนดกลุ่มสาระฯ ด่วน / กลุ่มงาน</th>
                                    <th>บทบาทผู้ใช้</th>
                                    <th class="text-center">สถานะ</th>
                                    <th class="text-center">LINE</th>
                                    <th class="text-end">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($unassignedUsers as $idx => $user): ?>
                                    <?= $renderUserRow($user, $idx, true) ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- วนลูปแสดงการ์ดสำหรับแต่ละกลุ่มสาระการเรียนรู้ -->
        <?php
        $renderedAreasCount = 0;
        foreach ($learningAreaOptions as $areaKey => $areaTitle):
            // ถ้าเลือกกรองเฉพาะกลุ่มสาระใดสาระหนึ่ง ให้แสดงเฉพาะกลุ่มนั้น
            if ($currentArea !== null && $currentArea !== '' && $currentArea !== '_unassigned' && $currentArea !== $areaKey) {
                continue;
            }
            if ($currentArea === '_unassigned' && $areaKey !== '_unassigned') {
                continue;
            }

            $usersInGroup = $groupedUsers[$areaKey] ?? [];
            $groupCount = count($usersInGroup);
            $meta = $areaMeta[$areaKey] ?? [
                'icon' => 'bi-book',
                'color' => '#1d4ed8',
                'bg' => '#eff6ff',
                'border' => '#bfdbfe',
                'badge' => 'bg-primary',
                'short' => $areaTitle,
            ];
            $collapseId = 'collapse_area_' . md5($areaKey);
            $renderedAreasCount++;
        ?>
            <div class="card border-0 shadow-sm mb-4" style="border-top: 3px solid <?= $meta['color'] ?> !important;">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px; background-color: <?= $meta['bg'] ?>; color: <?= $meta['color'] ?>; border: 1px solid <?= $meta['border'] ?>;">
                            <i class="bi <?= $meta['icon'] ?> fs-5"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h6 class="mb-0 fw-bold" style="color: <?= $meta['color'] ?>;">
                                    <?= Html::encode($areaTitle) ?>
                                </h6>
                                <span class="badge <?= $meta['badge'] ?> px-2 py-1 small rounded-pill">
                                    <?= $groupCount ?> คน
                                </span>
                            </div>
                            <div class="small text-muted mt-0">
                                รายชื่อคุณครูและบุคลากรสังกัดกลุ่มสาระการเรียนรู้<?= Html::encode($meta['short']) ?>
                            </div>
                        </div>
                    </div>

                    <div>
                        <button class="btn btn-sm btn-outline-secondary py-1 px-3 d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="true" aria-controls="<?= $collapseId ?>">
                            <i class="bi bi-chevron-down"></i>
                            <span class="d-none d-sm-inline">ย่อ/ขยาย</span>
                        </button>
                    </div>
                </div>

                <div class="collapse show" id="<?= $collapseId ?>">
                    <div class="card-body p-0">
                        <?php if ($groupCount > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width: 45px;">#</th>
                                            <th>ชื่อผู้ใช้ / ชื่อนามสกุล</th>
                                            <th>กลุ่มงานที่สังกัด</th>
                                            <th>บทบาทผู้ใช้</th>
                                            <th class="text-center">สถานะ</th>
                                            <th class="text-center">LINE</th>
                                            <th class="text-end">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usersInGroup as $idx => $user): ?>
                                            <?= $renderUserRow($user, $idx, false) ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi <?= $meta['icon'] ?> fs-3 d-block mb-2 opacity-50"></i>
                                <div class="small">ยังไม่มีผู้ใช้งานในกลุ่มสาระนี้</div>
                                <?php if (!empty($unassignedUsers)): ?>
                                    <div class="small text-secondary mt-1">
                                        (สามารถจัดผู้ใช้จากหมวด "ยังไม่ระบุกลุ่มสาระการเรียนรู้" เข้ามาในกลุ่มสาระนี้ได้)
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($renderedAreasCount === 0 && !$showUnassignedSection): ?>
            <div class="card border-0 shadow-sm p-5 text-center text-muted">
                <i class="bi bi-inbox fs-1 mb-2 text-secondary opacity-50"></i>
                <h5>ไม่พบข้อมูลผู้ใช้งาน</h5>
                <p class="small text-muted mb-0">ไม่พบสมาชิกตามเงื่อนไขการค้นหาหรือกลุ่มสาระที่เลือก</p>
                <div class="mt-3">
                    <?= Html::a('ล้างตัวกรองทั้งหมด', ['index'], ['class' => 'btn btn-sm btn-outline-primary']) ?>
                </div>
            </div>
        <?php endif; ?>

    <!-- ========================================== -->
    <!-- มุมมองที่ 2: ตารางรวมทั้งหมด (Flat Table View) -->
    <!-- ========================================== -->
    <?php else: ?>
        <div class="content-card">
            <div class="table-responsive">
                <?= GridView::widget([
                    'dataProvider' => $dataProvider,
                    'tableOptions' => ['class' => 'table table-hover align-middle mb-0'],
                    'columns' => [
                        'id',
                        [
                            'label' => 'ชื่อผู้ใช้ / ชื่อนามสกุล',
                            'format' => 'raw',
                            'value' => static function (User $model): string {
                                $html = '<div class="fw-bold text-primary">' . Html::encode($model->displayName()) . '</div>';
                                $html .= '<div class="small text-muted font-monospace"><i class="bi bi-person me-1"></i>' . Html::encode($model->username) . '</div>';
                                if (!empty($model->phone)) {
                                    $html .= '<div class="small text-muted"><i class="bi bi-telephone me-1"></i>' . Html::encode($model->phone) . '</div>';
                                }
                                if (!empty($model->signature_path)) {
                                    $html .= '<span class="badge bg-success-subtle text-success border border-success px-1 py-0 mt-1" style="font-size: 0.7rem;"><i class="bi bi-pen me-1"></i>มีลายเซ็น</span>';
                                }
                                return $html;
                            },
                        ],
                        [
                            'attribute' => 'learning_area',
                            'label' => 'กลุ่มสาระการเรียนรู้',
                            'format' => 'raw',
                            'value' => static function (User $model) use ($learningAreaOptions, $csrf): string {
                                if (empty($model->learning_area)) {
                                    return Html::beginForm(['update-learning-area', 'id' => $model->id], 'post', ['class' => 'd-flex align-items-center gap-1'])
                                        . $csrf
                                        . Html::dropDownList('learning_area', '', ['' => '-- เลือกกลุ่มสาระ --'] + $learningAreaOptions, [
                                            'class' => 'form-select form-select-sm',
                                            'style' => 'font-size: 0.78rem; max-width: 180px;',
                                        ])
                                        . Html::submitButton('<i class="bi bi-check-lg"></i>', ['class' => 'btn btn-sm btn-outline-primary py-0 px-2'])
                                        . Html::endForm();
                                }
                                return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"><i class="bi bi-book me-1"></i>' . Html::encode($model->learningAreaShort()) . '</span>';
                            },
                        ],
                        [
                            'attribute' => 'department_id',
                            'label' => 'กลุ่มงาน',
                            'format' => 'raw',
                            'value' => static function (User $model) use ($csrf): string {
                                if ($model->role === User::ROLE_DEPUTY_DIRECTOR) {
                                    $deptName = $model->department->name ?? '-';
                                    $isAssistant = $model->deputy_title === User::DEPUTY_TITLE_ASSISTANT;
                                    $badgeStyle = $isAssistant
                                        ? 'background-color: #f3e8ff; color: #6d28d9; border: 1px solid #d8b4fe;'
                                        : '';
                                    $badgeClass = $isAssistant
                                        ? 'badge px-2 py-1'
                                        : 'badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1';
                                    $html = '<span class="' . $badgeClass . '" style="' . $badgeStyle . '"><i class="bi bi-shield-check me-1"></i>' . Html::encode($model->deputyTitleShort()) . ' · กำกับ: ' . Html::encode($deptName) . '</span>';
                                    $html .= '<div class="mt-1">'
                                        . Html::beginForm(['update-deputy-title', 'id' => $model->id], 'post', ['class' => 'd-flex align-items-center gap-1'])
                                        . $csrf
                                        . Html::dropDownList('deputy_title', $model->deputy_title ?: User::DEPUTY_TITLE_DEPUTY, User::deputyTitleOptions(), [
                                            'class' => 'form-select form-select-sm',
                                            'style' => 'font-size: 0.76rem; max-width: 170px;',
                                        ])
                                        . Html::submitButton('<i class="bi bi-check-lg"></i>', ['class' => 'btn btn-sm btn-outline-primary py-0 px-2', 'title' => 'บันทึกตำแหน่ง'])
                                        . Html::endForm()
                                        . '</div>';
                                    return $html;
                                }
                                if ($model->role === User::ROLE_STAFF) {
                                    $deptName = $model->department->name ?? '-';
                                    return '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1"><i class="bi bi-person-badge me-1"></i>หัวหน้า: ' . Html::encode($deptName) . '</span>';
                                }
                                if ($model->role === User::ROLE_GENERAL_USER) {
                                    $names = array_map(static fn ($department): string => $department->name, $model->departments);
                                    return $names !== [] ? Html::encode(implode(', ', $names)) : Html::encode($model->department->name ?? '-');
                                }
                                return Html::encode($model->department->name ?? '-');
                            },
                        ],
                        [
                            'attribute' => 'role',
                            'format' => 'raw',
                            'value' => static function (User $model) use ($roleOptions, $csrf): string {
                                $select = Html::dropDownList('role', $model->role, $roleOptions, [
                                    'class' => 'form-select form-select-sm',
                                ]);
                                $button = Html::submitButton('<i class="bi bi-save me-1" aria-hidden="true"></i><span>บันทึก</span>', ['class' => 'btn btn-sm btn-primary']);

                                return Html::beginForm(['update-role', 'id' => $model->id], 'post', ['class' => 'd-flex align-items-center gap-1'])
                                    . $csrf
                                    . $select
                                    . $button
                                    . Html::endForm();
                            },
                        ],
                        [
                            'attribute' => 'status',
                            'format' => 'raw',
                            'value' => static function (User $model): string {
                                return match ($model->status) {
                                    User::STATUS_ACTIVE => '<span class="badge bg-success-subtle text-success border border-success px-2 py-1"><i class="bi bi-check-circle me-1"></i>เปิดใช้งาน</span>',
                                    User::STATUS_INACTIVE => '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1"><i class="bi bi-hourglass-split me-1"></i>รออนุมัติ</span>',
                                    User::STATUS_DELETED => '<span class="badge bg-secondary px-2 py-1">ปิดใช้งาน</span>',
                                    default => (string) $model->status,
                                };
                            },
                        ],
                        [
                            'label' => 'LINE Login',
                            'format' => 'raw',
                            'value' => static function (User $model): string {
                                if ($model->line_user_id !== null && $model->line_user_id !== '') {
                                    $title = $model->line_display_name !== null && $model->line_display_name !== ''
                                        ? 'เชื่อมต่อกับ ' . $model->line_display_name
                                        : 'บัญชีนี้เชื่อมต่อ LINE แล้ว (เข้าสู่ระบบด้วย LINE ได้)';

                                    return Html::tag('span', '<i class="bi bi-chat-check-fill text-success me-1"></i><span>เชื่อมแล้ว</span>', [
                                        'class' => 'badge bg-success-subtle text-success border border-success px-2 py-1',
                                        'title' => $title,
                                    ]);
                                }

                                return Html::tag('span', '<i class="bi bi-chat-slash text-muted me-1"></i><span>ยังไม่เชื่อม</span>', [
                                    'class' => 'badge bg-light text-muted border px-2 py-1',
                                ]);
                            },
                        ],
                        [
                            'label' => 'จัดการ',
                            'format' => 'raw',
                            'value' => static function (User $model) use ($currentUserId): string {
                                $items = [];

                                if ($model->status === User::STATUS_INACTIVE) {
                                    $items[] = Html::a(
                                        '<i class="bi bi-check-lg" aria-hidden="true"></i><span>อนุมัติ</span>',
                                        ['approve', 'id' => $model->id],
                                        [
                                            'class' => 'btn btn-sm btn-success text-white fw-bold',
                                            'data-method' => 'post',
                                            'data-confirm' => 'ยืนยันอนุมัติการใช้งานสำหรับ ' . $model->displayName() . ' หรือไม่?',
                                        ]
                                    );
                                }

                                $items[] = Html::a(
                                    '<i class="bi bi-key" aria-hidden="true"></i><span>รีเซ็ตรหัส</span>',
                                    ['reset-password', 'id' => $model->id],
                                    [
                                        'class' => 'btn btn-sm btn-outline-warning text-dark',
                                        'data-method' => 'post',
                                        'data-confirm' => 'ยืนยันรีเซ็ตรหัสผ่านสำหรับ ' . $model->displayName() . ' หรือไม่? ระบบจะสร้างรหัสผ่านใหม่ให้อัตโนมัติ',
                                    ]
                                );

                                $items[] = Html::a('<i class="bi bi-pencil-square" aria-hidden="true"></i><span>แก้ไข</span>', ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-outline-primary']);

                                if ((int) $model->id !== $currentUserId && (int) $model->status !== User::STATUS_DELETED) {
                                    $items[] = Html::a('<i class="bi bi-trash" aria-hidden="true"></i>', ['delete', 'id' => $model->id], [
                                        'class' => 'btn btn-sm btn-outline-danger',
                                        'data-method' => 'post',
                                        'data-confirm' => 'ยืนยันลบผู้ใช้นี้หรือไม่? ระบบจะปิดใช้งานบัญชีแทนการลบข้อมูลถาวร',
                                        'title' => 'ปิดใช้งานผู้ใช้',
                                    ]);
                                }

                                return '<div class="d-flex gap-1 flex-wrap">' . implode('', $items) . '</div>';
                            },
                        ],
                    ],
                ]) ?>
            </div>
        </div>
    <?php endif; ?>
</div>
