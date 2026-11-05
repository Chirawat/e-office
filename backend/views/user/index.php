<?php

declare(strict_types=1);

use common\models\User;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var int|null $currentStatus */
/** @var int $pendingCount */
/** @var int $activeCount */
/** @var User[]|null $activeUsers */

$this->title = 'ผู้ใช้และบทบาท';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$roleOptions = User::roleOptions();
$csrf = Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken);
$currentUserId = (int) Yii::$app->user->id;
?>

<div class="eoffice-page">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h1 class="h5 fw-bold text-dark mb-0"><?= Html::encode($this->title) ?></h1>
            <div class="small text-muted">กำหนดสิทธิ์ผู้ใช้ อนุมัติสมาชิกใหม่ และดูแลการเข้าสู่ระบบ</div>
        </div>
        <?= Html::a('<i class="bi bi-person-plus me-1" aria-hidden="true"></i><span>สร้างผู้ใช้ใหม่</span>', ['create'], ['class' => 'btn btn-sm btn-primary fw-semibold']) ?>
    </div>

    <!-- กล่องควบคุมโหมดอนุมัติสมาชิกอัตโนมัติ (Auto Approve) -->
    <?php $isAutoApprove = \common\models\SystemSetting::isAutoApproveSignup(); ?>
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
    $currentApprover = \common\models\SystemSetting::getDocumentApprover();
    $approverUserList = $activeUsers ?? \common\models\User::find()->where(['status' => \common\models\User::STATUS_ACTIVE])->orderBy([new \yii\db\Expression("COALESCE(NULLIF(fullname, ''), username) ASC")])->all();
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

    <!-- แถบกรองสถานะผู้ใช้งาน -->
    <ul class="nav nav-pills mb-3 gap-2">
        <li class="nav-item">
            <?= Html::a(
                'ทั้งหมด',
                ['index'],
                ['class' => 'nav-link ' . ($currentStatus === null ? 'active' : 'bg-light text-dark')]
            ) ?>
        </li>
        <li class="nav-item">
            <?= Html::a(
                'รออนุมัติ ' . ($pendingCount > 0 ? "<span class='badge bg-danger ms-1'>{$pendingCount}</span>" : "<span class='badge bg-secondary ms-1'>0</span>"),
                ['index', 'status' => User::STATUS_INACTIVE],
                ['class' => 'nav-link ' . ($currentStatus === User::STATUS_INACTIVE ? 'active bg-warning text-dark' : 'bg-light text-dark')]
            ) ?>
        </li>
        <li class="nav-item">
            <?= Html::a(
                "เปิดใช้งาน <span class='badge bg-success ms-1'>{$activeCount}</span>",
                ['index', 'status' => User::STATUS_ACTIVE],
                ['class' => 'nav-link ' . ($currentStatus === User::STATUS_ACTIVE ? 'active' : 'bg-light text-dark')]
            ) ?>
        </li>
    </ul>

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
                        $html = '<div class="fw-bold text-primary">' . Html::encode($model->username) . '</div>';
                        if (!empty($model->fullname)) {
                            $html .= '<div class="small text-dark">' . Html::encode($model->fullname) . '</div>';
                        }
                        if (!empty($model->phone)) {
                            $html .= '<div class="small text-muted"><i class="bi bi-telephone me-1"></i>' . Html::encode($model->phone) . '</div>';
                        }
                        return $html;
                    },
                ],
                [
                    'attribute' => 'department_id',
                    'label' => 'กลุ่มงาน/ฝ่าย',
                    'format' => 'raw',
                    'value' => static function (User $model): string {
                        if ($model->role === User::ROLE_DEPUTY_DIRECTOR) {
                            $deptName = $model->department->name ?? '-';
                            return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"><i class="bi bi-shield-check me-1"></i>กำกับดูแล: ' . Html::encode($deptName) . '</span>';
                        }
                        if ($model->role === User::ROLE_STAFF) {
                            $deptName = $model->department->name ?? '-';
                            return '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1"><i class="bi bi-person-badge me-1"></i>หัวหน้า: ' . Html::encode($deptName) . '</span>';
                        }
                        if ($model->role === User::ROLE_GENERAL_USER) {
                            $names = array_map(static fn ($department): string => $department->name, $model->departments);
                            return $names !== [] ? implode(', ', $names) : ($model->department->name ?? '-');
                        }

                        return $model->department->name ?? '-';
                    },
                ],
                [
                    'attribute' => 'role',
                    'format' => 'raw',
                    'value' => static function (User $model) use ($roleOptions, $csrf): string {
                        $select = Html::dropDownList('role', $model->role, $roleOptions, [
                            'class' => 'form-select form-select-sm',
                        ]);
                        $button = Html::submitButton('<i class="bi bi-save" aria-hidden="true"></i><span>บันทึก</span>', ['class' => 'btn btn-sm btn-primary']);

                        return Html::beginForm(['update-role', 'id' => $model->id], 'post', ['class' => 'role-form'])
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

                            return Html::tag('span', '<i class="bi bi-chat-check-fill text-success me-1"></i><span>เชื่อมต่อแล้ว</span>', [
                                'class' => 'badge bg-success-subtle text-success border border-success',
                                'title' => $title,
                            ]);
                        }

                        return Html::tag('span', '<i class="bi bi-chat-slash text-muted me-1"></i><span>ยังไม่เชื่อมต่อ</span>', [
                            'class' => 'badge bg-light text-muted border',
                        ]);
                    },
                ],
                [
                    'label' => 'จัดการ',
                    'format' => 'raw',
                    'value' => static function (User $model) use ($currentUserId): string {
                        $items = [];

                        // 1. ปุ่มอนุมัติ (สำหรับผู้ใช้ที่สถานะ Inactive / รออนุมัติ)
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

                        // 2. ปุ่มรีเซ็ตรหัสผ่าน (Admin One-Click Reset)
                        $items[] = Html::a(
                            '<i class="bi bi-key" aria-hidden="true"></i><span>รีเซ็ตรหัส</span>',
                            ['reset-password', 'id' => $model->id],
                            [
                                'class' => 'btn btn-sm btn-outline-warning text-dark',
                                'data-method' => 'post',
                                'data-confirm' => 'ยืนยันรีเซ็ตรหัสผ่านสำหรับ ' . $model->displayName() . ' หรือไม่? ระบบจะสร้างรหัสผ่านใหม่ให้อัตโนมัติ',
                            ]
                        );

                        // 3. ปุ่มแก้ไข
                        $items[] = Html::a('<i class="bi bi-pencil-square" aria-hidden="true"></i><span>แก้ไข</span>', ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-outline-primary']);

                        // 4. ปุ่มลบ (ถ้าไม่ใช่ตัวเอง)
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
</div>
