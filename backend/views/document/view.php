<?php

declare(strict_types=1);

use common\models\Document;
use common\models\Department;
use common\models\DocumentFile;
use common\models\DocumentStamp;
use common\models\User;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var Document $model */
/** @var Department[] $departments */
/** @var User[] $staffUsers */
/** @var string|null $previewImagePath */

$this->title = $model->receive_no . ' - ' . $model->subject;
$this->params['breadcrumbs'][] = ['label' => 'หนังสือรับ', 'url' => ['index']];
$this->params['breadcrumbs'][] = $model->receive_no;
$cssVer = file_exists(Yii::getAlias('@backend/web/css/eoffice.css')) ? filemtime(Yii::getAlias('@backend/web/css/eoffice.css')) : time();
$this->registerCssFile("@web/css/eoffice.css?v={$cssVer}", ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);

$this->registerCss('
/* ป้องกันการล้นจอแนวนอนและขยายมุมมอง PDF ให้เต็มความกว้างของจอมือถือ */
html, body, .eoffice-body, main, .eoffice-main, .eoffice-container, .eoffice-page {
    max-width: 100vw !important;
    overflow-x: hidden !important;
    box-sizing: border-box !important;
}

@media (max-width: 991.98px) {
    .eoffice-main {
        padding: 0 0 24px 0 !important;
    }
    .eoffice-page {
        padding: 0 0 24px 0 !important;
    }
    .eoffice-container {
        padding-left: 0 !important;
        padding-right: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
    }
    .document-workspace {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        width: 100% !important;
        gap: 12px !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .document-workspace > * {
        width: 100% !important;
        max-width: 100% !important;
    }
    .pdf-card-section {
        width: 100% !important;
        max-width: 100% !important;
        border-radius: 0 !important;
        border: 0 !important;
        margin: 0 !important;
        box-shadow: none !important;
    }
    .pdf-preview-frame {
        border-radius: 0 !important;
        border: 0 !important;
        width: 100% !important;
    }
    .pdf-canvas-wrapper {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        background: #e2e8f0 !important;
        min-height: auto !important;
    }
    .pdf-viewer-controls {
        padding: 6px 12px !important;
        background-color: #f8fafc !important;
        border-radius: 0 !important;
    }
    .pdf-viewer-header {
        padding: 8px 12px !important;
        border-radius: 0 !important;
    }
    .pdf-preview-canvas {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 auto !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }
    #pdf-main-canvas {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        height: auto !important;
        border-radius: 0 !important;
        margin: 0 auto !important;
    }
    .side-stack {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
}
');

$urgency = Document::urgencyOptions()[$model->urgency] ?? $model->urgency;
$status = Document::statusOptions()[$model->status] ?? $model->status;
$statusClass = 'status-badge-' . str_replace('_', '-', $model->status);
$pdfPath = $model->originalFile?->storage_path;
$pdfUrl = $pdfPath ? Url::to('@web/' . ltrim($pdfPath, '/')) : null;
$stampedPdfPath = $model->stampedFile?->storage_path;
$stampedPdfUrl = $stampedPdfPath ? Url::to('@web/' . ltrim($stampedPdfPath, '/')) : null;
$previewPdfUrl = $stampedPdfUrl ?: $pdfUrl;
$previewLabel = $stampedPdfUrl ? 'PDF ฉบับประทับตรา' : 'PDF ต้นฉบับ';
$previewImageUrl = $previewImagePath ? Url::to('@web/' . ltrim($previewImagePath, '/')) : null;
$identity = Yii::$app->user->identity;
$canRegisterDocument = $identity instanceof User && $identity->canRegisterDocument();
$canSubmitToDeputy = $identity instanceof User && $identity->canSubmitDocumentToDeputy();
$canGiveDeputyComment = $identity instanceof User && $identity->canGiveDeputyComment($model->department_id);
$canGiveDirectorOrder = $identity instanceof User && $identity->canGiveDirectorOrder();
$canCompleteDocument = $identity instanceof User && $identity->canCompleteDocument();
$canForwardDocument = $identity instanceof User && $identity->canForwardDocumentToUser();
$latestAssignment = null;
$assignedUserIds = [];
$assignedAssignments = [];
$myAssignment = null;
$acknowledgedCount = 0;
foreach ($model->assignments as $assignment) {
    if ($latestAssignment === null || (int) $assignment->id > (int) $latestAssignment->id) {
        $latestAssignment = $assignment;
    }
    if ($assignment->assigned_to !== null) {
        $uid = (int) $assignment->assigned_to;
        if (!in_array($uid, $assignedUserIds, true)) {
            $assignedUserIds[] = $uid;
            $assignedAssignments[] = $assignment;
            if ($assignment->isAcknowledged()) {
                $acknowledgedCount++;
            }
        }
        if ($identity instanceof User && $uid === (int) $identity->id) {
            $myAssignment = $assignment;
        }
    }
}
$totalAssignedCount = count($assignedAssignments);

$formatThaiDateTime = static function (?int $timestamp): string {
    if ($timestamp === null || $timestamp <= 0) {
        return '-';
    }
    $months = [1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'];
    $day = (int) date('j', $timestamp);
    $month = $months[(int) date('n', $timestamp)] ?? '';
    $year = (int) date('Y', $timestamp) + 543;
    return "{$day} {$month} {$year} เวลา " . date('H:i', $timestamp) . ' น.';
};

$hasSignedByMe = false;
if ($identity instanceof User) {
    foreach ($model->workflowActions as $wfAction) {
        if ((int) $wfAction->actor_id === (int) $identity->id) {
            $hasSignedByMe = true;
            break;
        }
    }
}

$canForwardAssignment = false;
if ($identity instanceof User && $canForwardDocument && $model->status === Document::STATUS_ASSIGNED) {
    if ($identity->role === User::ROLE_ADMIN) {
        $canForwardAssignment = true;
    } elseif ($identity->role === User::ROLE_DEPUTY_DIRECTOR) {
        $canForwardAssignment = $identity->canGiveDeputyComment($model->department_id) || $hasSignedByMe;
    } elseif ($identity->role === User::ROLE_STAFF && $identity->department_id !== null) {
        if ($model->department_id !== null && (int) $model->department_id === (int) $identity->department_id) {
            $canForwardAssignment = true;
        } else {
            foreach ($model->assignments as $assignment) {
                if (
                    ($assignment->department_id !== null && (int) $assignment->department_id === (int) $identity->department_id)
                    || ($assignment->assigned_by !== null && (int) $assignment->assigned_by === (int) $identity->id)
                ) {
                    $canForwardAssignment = true;
                    break;
                }
            }
        }
    }
}
$canCompleteAssignment = false;
if ($identity instanceof User && $canCompleteDocument && $model->status !== Document::STATUS_COMPLETED) {
    if ($identity->role === User::ROLE_ADMIN || $identity->hasRole(User::ROLE_DIRECTOR)) {
        $canCompleteAssignment = in_array($model->status, [
            Document::STATUS_SUBMITTED_TO_DEPUTY,
            Document::STATUS_SUBMITTED_TO_DIRECTOR,
            Document::STATUS_ASSIGNED,
        ], true);
    } elseif ($identity->role === User::ROLE_DEPUTY_DIRECTOR) {
        if ($model->status === Document::STATUS_SUBMITTED_TO_DEPUTY) {
            $canCompleteAssignment = $identity->canGiveDeputyComment($model->department_id);
        } elseif ($model->status === Document::STATUS_ASSIGNED) {
            $canCompleteAssignment = $identity->canGiveDeputyComment($model->department_id) || $hasSignedByMe;
        }
    } elseif ($model->status === Document::STATUS_ASSIGNED) {
        if ($identity->role === User::ROLE_STAFF && $identity->department_id !== null) {
            foreach ($model->assignments as $assignment) {
                if (
                    $assignment->assigned_to === null
                    && $assignment->department_id !== null
                    && (int) $assignment->department_id === (int) $identity->department_id
                ) {
                    $canCompleteAssignment = true;
                    break;
                }
            }
        }
    }
}

// จัดกลุ่มบุคลากรตามกลุ่มสาระการเรียนรู้สำหรับการเลือกมอบหมาย (รองรับข้ามกลุ่มงานและเลือกหลายคน)
$staffGroupedByArea = [];
foreach (array_keys(User::learningAreaOptions()) as $areaKey) {
    $staffGroupedByArea[$areaKey] = [];
}
$staffGroupedByArea['ยังไม่ระบุกลุ่มสาระ'] = [];
foreach ($staffUsers as $staffUser) {
    $areaKey = !empty($staffUser->learning_area) && isset($staffGroupedByArea[$staffUser->learning_area])
        ? $staffUser->learning_area
        : 'ยังไม่ระบุกลุ่มสาระ';
    $staffGroupedByArea[$areaKey][] = $staffUser;
}
$staffGroupedByArea = array_filter($staffGroupedByArea, static fn (array $list): bool => $list !== []);
$canEditSummary = $identity instanceof User && (
    $identity->hasRole([User::ROLE_ADMIN, User::ROLE_OFFICER, User::ROLE_DIRECTOR])
    || ($identity->role === User::ROLE_DEPUTY_DIRECTOR && $identity->canGiveDeputyComment($model->department_id))
    || ($identity->role === User::ROLE_STAFF && $identity->belongsToDepartment($model->department_id))
    || $canCompleteAssignment
);
$canApproveDocument = $identity instanceof User && ($identity->hasRole(User::ROLE_ADMIN) || ((int) $identity->id === (int) $model->approver_id));
$canSubmitToApprover = $identity instanceof User && ($identity->hasRole([User::ROLE_ADMIN, User::ROLE_OFFICER]));
$canDeleteDocument = $identity instanceof User && $identity->canDeleteDocument($model);
$allUserItems = ArrayHelper::map($allUsers ?? [], 'id', static fn (User $user): string => $user->displayName() . ' (' . $user->roleLabel() . ')');
$staffItems = ArrayHelper::map($staffUsers, 'id', static fn (User $user): string => $user->displayName());
$showStampPositionTools = true;
$canAdjustStampPositions = $showStampPositionTools && in_array($model->status, [Document::STATUS_REGISTERED, Document::STATUS_RETURNED], true) && $canRegisterDocument;
$useBrowserPdfPreview = $previewImageUrl === null && $previewPdfUrl !== null && $canAdjustStampPositions;
$departmentItems = ArrayHelper::map($departments, 'id', 'name');
$attachmentFiles = array_values(array_filter($model->files, static fn (DocumentFile $file): bool => $file->file_type === DocumentFile::TYPE_ATTACHMENT));
$csrf = Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken);
$showDebugRestamp = false;
$latestDeputyStamp = null;
$latestDirectorStamp = null;
foreach ($model->stamps as $stamp) {
    if ($stamp->stamp_type === DocumentStamp::TYPE_DEPUTY_COMMENT) {
        $latestDeputyStamp = $stamp;
    }
    if ($stamp->stamp_type === DocumentStamp::TYPE_DIRECTOR_ORDER) {
        $latestDirectorStamp = $stamp;
    }
}
$stampPositionDefaults = [
    DocumentStamp::TYPE_RECEIVE => ['x' => 144, 'y' => 14, 'w' => 58, 'h' => 27, 'label' => 'ตรารับหนังสือ'],
    DocumentStamp::TYPE_DEPARTMENT => ['x' => 18, 'y' => 210, 'w' => 60, 'h' => 60, 'label' => 'ตรากลุ่มงาน'],
    DocumentStamp::TYPE_DEPUTY_COMMENT => ['x' => 128, 'y' => 170, 'w' => 68, 'h' => 42, 'label' => 'ความเห็นรอง ผอ.'],
    DocumentStamp::TYPE_DIRECTOR_ORDER => ['x' => 132, 'y' => 230, 'w' => 64, 'h' => 38, 'label' => 'คำสั่ง ผอ.'],
];
$stampPosition = static function (string $type, string $axis) use ($model, $stampPositionDefaults): float {
    $latest = null;
    foreach ($model->stamps as $stamp) {
        if ($stamp->stamp_type === $type) {
            $latest = $stamp;
        }
    }

    $value = $axis === 'x' ? $latest?->position_x : $latest?->position_y;
    $value = $value !== null ? (float) $value : (float) $stampPositionDefaults[$type][$axis];

    if ($value < 0 || $value > 300) {
        return (float) $stampPositionDefaults[$type][$axis];
    }

    return $value;
};
$thaiDateTime = static function (int|string|null $value): string {
    if ($value === null || $value === '') {
        return '-';
    }

    $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
    if ($timestamp === false) {
        return (string) $value;
    }

    $months = [
        1 => 'ม.ค.',
        2 => 'ก.พ.',
        3 => 'มี.ค.',
        4 => 'เม.ย.',
        5 => 'พ.ค.',
        6 => 'มิ.ย.',
        7 => 'ก.ค.',
        8 => 'ส.ค.',
        9 => 'ก.ย.',
        10 => 'ต.ค.',
        11 => 'พ.ย.',
        12 => 'ธ.ค.',
    ];

    return (int) date('j', $timestamp)
        . ' ' . $months[(int) date('n', $timestamp)]
        . ' ' . ((int) date('Y', $timestamp) + 543)
        . ' เวลา ' . date('H:i', $timestamp) . ' น.';
};

if ($previewPdfUrl) {
    $this->registerJsFile('@web/js/pdf.min.js', [
        'position' => \yii\web\View::POS_HEAD,
    ]);
}
?>

<div class="eoffice-page">
    <div class="document-workspace">
        <section class="content-card pdf-card-section p-0 border-0 shadow-sm overflow-hidden">
            <!-- Compact Header / Back Bar for Mobile & Desktop -->
            <div class="pdf-viewer-header d-flex align-items-center justify-content-between p-2 px-3 bg-white border-bottom gap-2">
                <div class="d-flex align-items-center gap-2 text-truncate">
                    <?= Html::a('<i class="bi bi-arrow-left" aria-hidden="true"></i> <span class="d-none d-sm-inline">กลับ</span>', ['/document/index'], [
                        'class' => 'btn btn-sm btn-outline-secondary py-1 px-2 text-nowrap',
                        'title' => 'กลับรายการหนังสือ',
                    ]) ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace text-nowrap">
                        <?= Html::encode($model->receive_no) ?>
                    </span>
                    <span class="fw-semibold text-dark text-truncate small" title="<?= Html::encode($model->subject) ?>">
                        <?= Html::encode($model->subject) ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                    <span class="status-badge <?= Html::encode($statusClass) ?>" style="font-size: 0.75rem; padding: 2px 8px; min-height: 24px;">
                        <?= Html::encode($status) ?>
                    </span>
                    <?php if ($canDeleteDocument): ?>
                        <?= Html::a('<i class="bi bi-trash3 me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">ลบหนังสือ</span>', ['delete', 'id' => $model->id], [
                            'class' => 'btn btn-sm btn-outline-danger py-1 px-2 text-nowrap ms-1',
                            'data-confirm' => 'คุณแน่ใจหรือไม่ว่าต้องการลบหนังสือเลขรับ ' . Html::encode($model->receive_no) . ' เรื่อง "' . Html::encode($model->subject) . '" นี้? เอกสารและไฟล์แนบทั้งหมดจะถูกลบออกจากระบบอย่างถาวร',
                            'data-method' => 'post',
                            'title' => 'ลบหนังสือที่ลงรับผิดพลาด',
                        ]) ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pdf-preview-frame">
                <?php if ($previewPdfUrl): ?>
                    <!-- PDF Controls Toolbar (Sticky, Mobile-Optimized) -->
                    <div class="pdf-viewer-controls p-2 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-1 sticky-top" style="z-index: 10;">
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1" id="pdf-prev-page" title="หน้าก่อนหน้า">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <span class="small font-monospace px-1 text-dark text-nowrap" id="pdf-page-num-display">
                                <strong id="pdf-current-page">1</strong> / <span id="pdf-total-pages">-</span>
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1" id="pdf-next-page" title="หน้าถัดไป">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1" id="pdf-zoom-out" title="ย่อ">
                                <i class="bi bi-zoom-out"></i>
                            </button>
                            <span class="small font-monospace px-1 text-muted d-none d-sm-inline" id="pdf-zoom-level">100%</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1" id="pdf-zoom-in" title="ขยาย">
                                <i class="bi bi-zoom-in"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1" id="pdf-zoom-fit" title="พอดีหน้าจอ">
                                <i class="bi bi-arrows-fullscreen"></i>
                            </button>
                            <a href="<?= Html::encode($previewPdfUrl) ?>" target="_blank" class="btn btn-sm btn-primary fw-semibold px-2 py-1 ms-1 d-inline-flex align-items-center gap-1" title="เปิดเอกสารแท็บใหม่">
                                <i class="bi bi-box-arrow-up-right"></i>
                                <span class="d-none d-sm-inline">เต็มจอ</span>
                            </a>
                        </div>
                    </div>

                    <!-- Canvas Area -->
                    <div class="pdf-canvas-wrapper text-center p-0" id="pdf-canvas-wrapper" style="overflow-x: auto; background: #e2e8f0; min-height: auto; -webkit-overflow-scrolling: touch;">
                        <div id="pdf-loading-indicator" class="py-5 text-dark">
                            <div class="spinner-border text-primary mb-2" role="status"></div>
                            <div class="small fw-semibold">กำลังโหลดเอกสาร PDF คมชัดสูง...</div>
                        </div>

                        <div id="pdf-error-fallback" class="d-none py-5 bg-white rounded p-4 text-center my-3 shadow-sm mx-auto" style="max-width: 500px;">
                            <i class="bi bi-file-earmark-pdf text-primary fs-1 d-block mb-2"></i>
                            <h5 class="fw-bold text-dark">เปิดอ่านเอกสาร PDF</h5>
                            <p class="text-muted small">สามารถแตะปุ่มด้านล่างเพื่อเปิดอ่านบน iPad หรือมือถือได้ทันที</p>
                            <a href="<?= Html::encode($previewPdfUrl) ?>" target="_blank" class="btn btn-primary fw-semibold px-4 py-2 mt-2">
                                <i class="bi bi-box-arrow-up-right me-1"></i> แตะเพื่อเปิดอ่านไฟล์ PDF
                            </a>
                        </div>

                        <div class="pdf-preview-canvas d-none position-relative" id="pdf-page-container" 
                             data-page-width="216" data-page-height="297" 
                             data-pdf-url="<?= Html::encode($previewPdfUrl) ?>"
                             data-worker-url="<?= Url::to('@web/js/pdf.worker.min.js') ?>">
                            <canvas id="pdf-main-canvas" class="pdf-page-image" style="display: block; max-width: 100%; height: auto; background: #fff;"></canvas>

                            <?php if ($canAdjustStampPositions): ?>
                                <div class="stamp-preview-layer" aria-hidden="true">
                                    <?php foreach ($stampPositionDefaults as $type => $config): ?>
                                        <div
                                            class="stamp-preview-box stamp-preview-<?= Html::encode($type) ?>"
                                            data-stamp-box="<?= Html::encode($type) ?>"
                                            data-width-mm="<?= Html::encode((string) $config['w']) ?>"
                                            data-height-mm="<?= Html::encode((string) $config['h']) ?>"
                                            title="คลิกแล้วลากเพื่อปรับตำแหน่งตราประทับ"
                                        >
                                            <span class="stamp-drag-handle"><i class="bi bi-arrows-move me-1"></i>ลากย้าย</span>
                                            <span class="stamp-box-title"><?= Html::encode($config['label']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-preview py-5 text-center text-muted">
                        <i class="bi bi-file-earmark-x fs-1 d-block mb-2"></i>
                        ยังไม่มีไฟล์ PDF สำหรับแสดงตัวอย่าง
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <aside class="side-stack">
            <section class="content-card">
                <h2>ข้อมูลหนังสือ</h2>
                <dl class="meta-list">
                    <div><dt>สถานะ</dt><dd><span class="status-badge <?= Html::encode($statusClass) ?>"><?= Html::encode($status) ?></span></dd></div>
                    <div><dt>เลขหนังสือต้นทาง</dt><dd><?= Html::encode($model->external_no ?: '-') ?></dd></div>
                    <div><dt>จาก</dt><dd><?= Html::encode($model->sender) ?></dd></div>
                    <div class="meta-item-stacked">
                        <dt class="d-flex justify-content-between align-items-center">
                            <span>สรุปเนื้อหาในหนังสือโดยย่อ</span>
                            <?php if ($canEditSummary): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#modal-edit-summary" title="แก้ไขสรุปเนื้อหา">
                                    <i class="bi bi-pencil me-1"></i>แก้ไข
                                </button>
                            <?php endif; ?>
                        </dt>
                        <dd class="p-2 bg-light rounded border text-dark small mt-1" style="word-break: break-word; line-height: 1.5; white-space: pre-line; text-indent: 0;"><?= Html::encode(trim((string) ($model->summary ?: $model->subject))) ?></dd>
                    </div>
                    <div><dt>ความเร่งด่วน</dt><dd><?= Html::encode($urgency) ?></dd></div>
                    <div><dt>กลุ่มงาน</dt><dd><?= Html::encode($model->department->name ?? '-') ?></dd></div>
                    <div><dt>ผู้ตรวจ/อนุมัติ</dt><dd><?= Html::encode($model->approver?->displayName() ?? \common\models\SystemSetting::getDocumentApprover()?->displayName() ?? '-') ?></dd></div>
                    <div class="meta-item-stacked">
                        <dt class="d-flex justify-content-between align-items-center">
                            <span>ผู้ปฏิบัติ</span>
                            <?php if ($totalAssignedCount > 0): ?>
                                <span class="badge <?= $acknowledgedCount === $totalAssignedCount ? 'bg-success' : 'bg-info text-dark' ?> fw-normal" style="font-size: 0.72rem;">
                                    รับทราบแล้ว <?= $acknowledgedCount ?>/<?= $totalAssignedCount ?> คน
                                </span>
                            <?php endif; ?>
                        </dt>
                        <dd class="mt-1 mb-0">
                            <?php if ($assignedAssignments !== []): ?>
                                <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                                    <?php foreach ($assignedAssignments as $asg): ?>
                                        <?php $assigneeName = $asg->assignedTo?->displayName() ?? ('ผู้ใช้ #' . $asg->assigned_to); ?>
                                        <li class="py-2 px-2 rounded-2 border <?= $asg->isAcknowledged() ? 'bg-success-subtle border-success-subtle' : 'bg-light' ?>">
                                            <span class="d-flex align-items-start gap-2">
                                                <i class="bi <?= $asg->isAcknowledged() ? 'bi-check-circle-fill text-success' : 'bi-hourglass-split text-warning' ?> flex-shrink-0" style="margin-top: 2px;"></i>
                                                <span class="d-block flex-grow-1" style="min-width: 0;">
                                                    <span class="d-block fw-semibold <?= $asg->isAcknowledged() ? 'text-success' : 'text-dark' ?>" style="font-size: 0.84rem; line-height: 1.35;">
                                                        <?= Html::encode($assigneeName) ?>
                                                    </span>
                                                    <span class="d-block text-muted mt-1" style="font-size: 0.74rem; line-height: 1.25;">
                                                        <?php if ($asg->isAcknowledged()): ?>
                                                            <i class="bi bi-clock me-1"></i>รับทราบเมื่อ <?= Html::encode($formatThaiDateTime($asg->acknowledged_at ? (int) $asg->acknowledged_at : null)) ?>
                                                        <?php else: ?>
                                                            <i class="bi bi-clock-history me-1"></i>รอรับทราบ
                                                        <?php endif; ?>
                                                    </span>
                                                </span>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div><dt>ครบกำหนด</dt><dd><?= $model->due_date ? Yii::$app->formatter->asDate($model->due_date) : '-' ?></dd></div>
                </dl>
            </section>

            <?php if ($attachmentFiles !== []): ?>
                <section class="content-card">
                    <h2>ไฟล์แนบเพิ่มเติม</h2>
                    <div class="attachment-list">
                        <?php foreach ($attachmentFiles as $attachmentFile): ?>
                            <?= Html::a(
                                Html::encode($attachmentFile->original_name),
                                Url::to('@web/' . ltrim($attachmentFile->storage_path, '/'), true),
                                ['class' => 'attachment-link', 'target' => '_blank'],
                            ) ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($canAdjustStampPositions): ?>
                <section class="content-card">
                    <h2>ตำแหน่งตราประทับ</h2>
                    <?= Html::beginForm(['update-stamp-positions', 'id' => $model->id], 'post', ['class' => 'stamp-position-form']) ?>
                        <?= $csrf ?>
                        <?php foreach ($stampPositionDefaults as $type => $config): ?>
                            <div data-stamp-control="<?= Html::encode($type) ?>" class="d-none">
                                <?= Html::hiddenInput("stamps[$type][x]", (string) $stampPosition($type, 'x'), [
                                    'data-axis' => 'x',
                                    'data-stamp-input' => $type,
                                ]) ?>
                                <?= Html::hiddenInput("stamps[$type][y]", (string) $stampPosition($type, 'y'), [
                                    'data-axis' => 'y',
                                    'data-stamp-input' => $type,
                                ]) ?>
                            </div>
                        <?php endforeach; ?>
                        <?= Html::submitButton('<i class="bi bi-save me-1" aria-hidden="true"></i><span>บันทึกตำแหน่งใหม่</span>', ['class' => 'btn btn-primary w-100']) ?>
                    <?= Html::endForm() ?>
                </section>
            <?php endif; ?>

            <section class="content-card">
                <h2>ดำเนินการ</h2>

                <?php if ($model->status === Document::STATUS_REGISTERED && $canSubmitToApprover): ?>
                    <?php $systemApprover = \common\models\SystemSetting::getDocumentApprover(); ?>
                    <?= Html::beginForm(['submit-to-approver', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                        <?= $csrf ?>
                        <p class="small text-muted mb-2">เสนอหนังสือให้ผู้ตรวจพิจารณาอนุมัติความถูกต้องก่อนเสนอต่อรอง ผอ.</p>
                        <?php if ($systemApprover !== null): ?>
                            <div class="alert alert-light border py-2 px-3 small mb-3">
                                <div class="text-muted small">ผู้ตรวจสอบ/อนุมัติ (กำหนดโดย Admin):</div>
                                <div class="fw-bold text-dark fs-6 mt-1">
                                    <i class="bi bi-shield-check text-primary me-1"></i><?= Html::encode($systemApprover->displayName()) ?>
                                    <span class="badge bg-secondary ms-1"><?= Html::encode($systemApprover->roleLabel()) ?></span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted" for="approver-comment">หมายเหตุถึงผู้ตรวจสอบ (ถ้ามี)</label>
                                <?= Html::textarea('comment', '', [
                                    'id' => 'approver-comment',
                                    'class' => 'form-control',
                                    'rows' => 2,
                                    'placeholder' => 'ระบุข้อความหรือหมายเหตุ...',
                                ]) ?>
                            </div>
                            <?= Html::submitButton('<i class="bi bi-send-check me-1" aria-hidden="true"></i><span>เสนอผู้ตรวจสอบ/อนุมัติ</span>', ['class' => 'btn btn-primary w-100']) ?>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 px-3 small mb-0">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                <strong>ยังไม่ได้กำหนดผู้ตรวจสอบในระบบ</strong><br>
                                กรุณาแจ้งผู้ดูแลระบบ (Admin) เพื่อตั้งค่าผู้ตรวจสอบ/อนุมัติในหน้า "ผู้ใช้และบทบาท"
                            </div>
                        <?php endif; ?>
                    <?= Html::endForm() ?>

                <?php elseif ($model->status === Document::STATUS_SUBMITTED_TO_APPROVER): ?>
                    <?php if ($canApproveDocument): ?>
                        <div class="alert alert-info py-2 px-3 small mb-3">
                            <i class="bi bi-shield-check me-1"></i>ท่านได้รับมอบหมายให้ตรวจสอบและอนุมัติหนังสือฉบับนี้
                        </div>

                        <!-- ตรวจสอบ/แก้ไขกลุ่มงานที่มอบหมาย (ติ๊กใหม่ได้หากระบุผิด) -->
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label fw-semibold mb-0">เห็นสมควรมอบกลุ่มงาน (ตรากลุ่มงาน)</label>
                                <span class="badge bg-secondary-subtle text-secondary border fw-normal" style="font-size: 0.75rem;">ติ๊กใหม่ได้หากธุรการระบุผิด</span>
                            </div>
                            <div class="border rounded p-2 bg-light">
                                <?php foreach ($departments as $dept): ?>
                                    <div class="form-check my-1">
                                        <input class="form-check-input review-dept-radio" type="radio" name="review_department_id" id="dept-radio-<?= $dept->id ?>" value="<?= $dept->id ?>" <?= ((int)$model->department_id === (int)$dept->id) ? 'checked' : '' ?> onchange="
                                            document.querySelectorAll('input[name=\'review_department_id\']').forEach(function(r) {
                                                const lbl = document.querySelector('label[for=\'' + r.id + '\']');
                                                if (lbl) {
                                                    if (r.checked) {
                                                        lbl.classList.add('fw-bold', 'text-primary');
                                                        lbl.classList.remove('text-dark');
                                                    } else {
                                                        lbl.classList.remove('fw-bold', 'text-primary');
                                                        lbl.classList.add('text-dark');
                                                    }
                                                }
                                            });
                                        ">
                                        <label class="form-check-label small <?= ((int)$model->department_id === (int)$dept->id) ? 'fw-bold text-primary' : 'text-dark' ?>" for="dept-radio-<?= $dept->id ?>">
                                            <?= Html::encode($dept->name) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text small text-muted">หากพบว่าธุรการติ๊กกลุ่มงานผิด สามารถติ๊กเลือกกลุ่มงานใหม่ที่นี่แล้วกดอนุมัติได้ทันที ตราประทับจะปรับเป็นกลุ่มงานใหม่โดยอัตโนมัติ</div>
                        </div>

                        <label class="form-label fw-semibold" for="review-comment">ความเห็นการตรวจสอบ/อนุมัติ (ถ้ามี)</label>
                        <?= Html::textarea('comment', '', [
                            'id' => 'review-comment',
                            'class' => 'form-control mb-3',
                            'rows' => 2,
                            'placeholder' => 'ระบุความเห็นหรือข้อเสนอแนะ (ถ้ามี)...',
                        ]) ?>
                        <div class="d-grid gap-2">
                            <?= Html::beginForm(['approver-approve', 'id' => $model->id], 'post', ['class' => 'mb-0', 'id' => 'form-approver-approve']) ?>
                                <?= $csrf ?>
                                <?= Html::hiddenInput('comment', '', ['id' => 'hidden-approve-comment']) ?>
                                <?= Html::hiddenInput('department_id', (string) $model->department_id, ['id' => 'hidden-approve-department-id']) ?>
                                <?= Html::submitButton('<i class="bi bi-check-circle me-1"></i><span>อนุมัติและเสนอรอง ผอ.</span>', [
                                    'class' => 'btn btn-success w-100',
                                    'onclick' => "
                                        document.getElementById('hidden-approve-comment').value = document.getElementById('review-comment').value;
                                        const checkedDept = document.querySelector('input[name=\"review_department_id\"]:checked');
                                        if (checkedDept) {
                                            document.getElementById('hidden-approve-department-id').value = checkedDept.value;
                                        }
                                    ",
                                ]) ?>
                            <?= Html::endForm() ?>

                            <?= Html::beginForm(['approver-return', 'id' => $model->id], 'post', ['class' => 'mb-0', 'id' => 'form-approver-return']) ?>
                                <?= $csrf ?>
                                <?= Html::hiddenInput('comment', '', ['id' => 'hidden-return-comment']) ?>
                                <?= Html::submitButton('<i class="bi bi-arrow-return-left me-1"></i><span>ส่งกลับให้ธุรการแก้ไข</span>', [
                                    'class' => 'btn btn-outline-danger w-100',
                                    'onclick' => "document.getElementById('hidden-return-comment').value = document.getElementById('review-comment').value;",
                                ]) ?>
                            <?= Html::endForm() ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning py-3 px-3 mb-0 small">
                            <i class="bi bi-hourglass-split me-1"></i><strong>รอการตรวจสอบ/อนุมัติ</strong><br>
                            อยู่ระหว่างรอ <strong><?= Html::encode($model->approver?->displayName() ?? '-') ?></strong> ตรวจสอบความถูกต้องก่อนเสนอต่อรอง ผอ.
                        </div>
                    <?php endif; ?>

                <?php elseif ($model->status === Document::STATUS_SUBMITTED_TO_DEPUTY): ?>
                    <?php if ($canGiveDeputyComment): ?>
                        <?= Html::beginForm(['deputy-comment', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                            <?= $csrf ?>
                            <label class="form-label" for="deputy-comment">ความเห็นรอง ผอ.</label>
                            <?= Html::textarea('comment', '', [
                                'id' => 'deputy-comment',
                                'class' => 'form-control',
                                'rows' => 4,
                                'placeholder' => 'เห็นควรประชาสัมพันธ์และมอบกลุ่มงานที่เกี่ยวข้องดำเนินการ',
                            ]) ?>

                            <!-- ตัวเลือกกำหนดผู้ปฏิบัติงานล่วงหน้า (ไม่บังคับ) -->
                            <div class="mt-3 p-2 border rounded-2 bg-light">
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <span class="small fw-semibold text-primary">
                                        <i class="bi bi-person-plus-fill me-1"></i>กำหนดผู้ปฏิบัติงานล่วงหน้า (ไม่บังคับ)
                                    </span>
                                    <span class="badge bg-primary rounded-pill" id="deputy-selected-staff-count">เลือกแล้ว <?= count($assignedUserIds) ?> คน</span>
                                </div>
                                <div class="text-muted mt-1" style="font-size: 0.74rem;">
                                    เว้นว่างไว้เพื่อให้หัวหน้ากลุ่มงานมอบหมาย หรือเลือกผู้ปฏิบัติไว้ล่วงหน้าเพื่อให้หนังสือส่งตรงถึงผู้ปฏิบัติทันทีหลัง ผอ. สั่งการ
                                </div>

                                <div class="mt-2" id="deputy-preassign-box">
                                    <div class="input-group input-group-sm mb-2">
                                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                                        <input type="text" class="form-control" id="deputy-staff-search-input" placeholder="ค้นหาชื่อบุคลากร หรือกลุ่มสาระฯ...">
                                        <button type="button" class="btn btn-outline-secondary" id="btn-clear-deputy-staff">ล้าง</button>
                                    </div>
                                    <div class="border rounded-2 bg-white p-2" id="deputy-staff-area-picker" style="max-height: 260px; overflow-y: auto;">
                                        <?php foreach ($staffGroupedByArea as $areaTitle => $members): ?>
                                            <?php $groupKey = 'deputy_area_' . md5($areaTitle); ?>
                                            <div class="staff-area-group mb-2 pb-1 border-bottom">
                                                <div class="d-flex align-items-center justify-content-between bg-light px-2 py-1 rounded-1 mb-1">
                                                    <span class="small fw-bold text-primary">
                                                        <i class="bi bi-book me-1"></i><?= Html::encode(str_replace('กลุ่มสาระการเรียนรู้', 'กลุ่มสาระฯ ', $areaTitle)) ?>
                                                        <span class="text-muted fw-normal">(<?= count($members) ?>)</span>
                                                    </span>
                                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none btn-toggle-area-group" data-group="<?= $groupKey ?>" style="font-size: 0.72rem;">เลือกทั้งกลุ่ม</button>
                                                </div>
                                                <div class="ps-1">
                                                    <?php foreach ($members as $staffUser): ?>
                                                        <?php
                                                        $isChecked = in_array((int) $staffUser->id, $assignedUserIds, true);
                                                        $deptSuffix = $staffUser->department ? $staffUser->department->name : '';
                                                        $searchText = mb_strtolower($staffUser->displayName() . ' ' . $staffUser->username . ' ' . $deptSuffix . ' ' . $areaTitle, 'UTF-8');
                                                        ?>
                                                        <label class="form-check d-flex align-items-center gap-2 py-1 px-1 mb-0 rounded staff-member-item" data-search="<?= Html::encode($searchText) ?>" style="cursor: pointer; font-size: 0.84rem;">
                                                            <input class="form-check-input mt-0 staff-checkbox" type="checkbox" name="assigned_to[]" value="<?= (int) $staffUser->id ?>" data-group="<?= $groupKey ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                            <span class="text-dark flex-grow-1">
                                                                <?= Html::encode($staffUser->displayName()) ?>
                                                                <?php if ($deptSuffix !== ''): ?>
                                                                    <span class="text-muted" style="font-size: 0.74rem;">(<?= Html::encode($deptSuffix) ?>)</span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $this->registerJs(<<<'JS'
                            (function() {
                                var picker = document.getElementById('deputy-staff-area-picker');
                                if (!picker) return;
                                var searchInput = document.getElementById('deputy-staff-search-input');
                                var countBadge = document.getElementById('deputy-selected-staff-count');
                                var clearBtn = document.getElementById('btn-clear-deputy-staff');

                                function updateCount() {
                                    var checked = picker.querySelectorAll('.staff-checkbox:checked').length;
                                    if (countBadge) {
                                        countBadge.textContent = 'เลือกแล้ว ' + checked + ' คน';
                                    }
                                }

                                picker.addEventListener('change', function(e) {
                                    if (e.target && e.target.classList.contains('staff-checkbox')) {
                                        updateCount();
                                    }
                                });

                                picker.querySelectorAll('.btn-toggle-area-group').forEach(function(btn) {
                                    btn.addEventListener('click', function() {
                                        var grp = btn.getAttribute('data-group');
                                        var boxes = Array.prototype.slice.call(picker.querySelectorAll('.staff-checkbox[data-group="' + grp + '"]'))
                                            .filter(function(cb) { return cb.closest('.staff-member-item').style.display !== 'none'; });
                                        var allChecked = boxes.length > 0 && boxes.every(function(cb) { return cb.checked; });
                                        boxes.forEach(function(cb) { cb.checked = !allChecked; });
                                        updateCount();
                                    });
                                });

                                if (clearBtn) {
                                    clearBtn.addEventListener('click', function() {
                                        picker.querySelectorAll('.staff-checkbox:checked').forEach(function(cb) { cb.checked = false; });
                                        updateCount();
                                    });
                                }

                                if (searchInput) {
                                    searchInput.addEventListener('input', function() {
                                        var q = searchInput.value.trim().toLowerCase();
                                        picker.querySelectorAll('.staff-area-group').forEach(function(groupEl) {
                                            var anyVisible = false;
                                            groupEl.querySelectorAll('.staff-member-item').forEach(function(itemEl) {
                                                var hay = itemEl.getAttribute('data-search') || '';
                                                var show = (q === '' || hay.indexOf(q) !== -1);
                                                itemEl.style.display = show ? '' : 'none';
                                                if (show) anyVisible = true;
                                            });
                                            groupEl.style.display = anyVisible ? '' : 'none';
                                        });
                                    });
                                }

                                updateCount();
                            })();
                            JS);
                            ?>

                            <div class="action-buttons mt-3">
                                <?= Html::submitButton('<i class="bi bi-send-check" aria-hidden="true"></i><span>เสนอ ผอ.</span>', [
                                    'class' => 'btn btn-primary',
                                    'name' => 'decision',
                                    'value' => 'submit',
                                ]) ?>
                                <?= Html::submitButton('<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span>ส่งกลับแก้ไข</span>', [
                                    'class' => 'btn btn-outline-danger',
                                    'name' => 'decision',
                                    'value' => 'return',
                                ]) ?>
                            </div>
                        <?= Html::endForm() ?>
                    <?php else: ?>
                        <div class="alert alert-info d-flex align-items-center gap-2 mb-0" style="font-size: 0.85rem;">
                            <i class="bi bi-info-circle-fill fs-5 text-info"></i>
                            <div>
                                <strong>รอการพิจารณาจากรองผู้อำนวยการ</strong><br>
                                เอกสารนี้อยู่ในสายบังคับบัญชาของ <strong><?= Html::encode($model->department ? $model->department->name : 'กลุ่มงานที่เกี่ยวข้อง') ?></strong>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($canCompleteAssignment): ?>
                        <?= Html::beginForm(['complete', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                            <?= $csrf ?>
                            <label class="form-label" for="complete-comment">ผลการดำเนินงาน</label>
                            <?= Html::textarea('comment', '', [
                                'id' => 'complete-comment',
                                'class' => 'form-control',
                                'rows' => 4,
                                'placeholder' => 'ดำเนินการเรียบร้อยแล้ว',
                            ]) ?>
                            <?= Html::submitButton('<i class="bi bi-check-circle" aria-hidden="true"></i><span>ปิดเรื่อง</span>', ['class' => 'btn btn-success w-100 mt-3']) ?>
                        <?= Html::endForm() ?>
                    <?php endif; ?>

                <?php elseif ($model->status === Document::STATUS_SUBMITTED_TO_DIRECTOR && $canGiveDirectorOrder): ?>
                    <?= Html::beginForm(['director-order', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                        <?= $csrf ?>
                        <label class="form-label" for="director-comment">คำสั่ง ผอ.</label>
                        <?= Html::textarea('comment', '', [
                            'id' => 'director-comment',
                            'class' => 'form-control',
                            'rows' => 5,
                            'placeholder' => 'มอบงานบริหารทั่วไปแจ้งผู้เกี่ยวข้องดำเนินการและรายงานผล',
                        ]) ?>
                        <label class="form-label mt-3" for="department-id">มอบหมายให้กลุ่มงาน</label>
                        <?= Html::dropDownList('department_id', $model->department_id, $departmentItems, [
                            'id' => 'department-id',
                            'class' => 'form-select',
                            'prompt' => 'เลือกกลุ่มงาน',
                        ]) ?>
                        <label class="form-label mt-3" for="due-date">วันครบกำหนด</label>
                        <?= Html::input('date', 'due_date', $model->due_date, [
                            'id' => 'due-date',
                            'class' => 'form-control',
                        ]) ?>
                        <?= Html::submitButton('<i class="bi bi-check2-square" aria-hidden="true"></i><span>บันทึกคำสั่งและมอบหมาย</span>', ['class' => 'btn btn-primary w-100 mt-3']) ?>
                    <?= Html::endForm() ?>

                    <?php if ($canCompleteAssignment): ?>
                        <hr class="my-3">
                        <?= Html::beginForm(['complete', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                            <?= $csrf ?>
                            <label class="form-label" for="complete-comment-director">หรือปิดเรื่องทันที (เช่น หนังสือเพื่อทราบ)</label>
                            <?= Html::textarea('comment', '', [
                                'id' => 'complete-comment-director',
                                'class' => 'form-control',
                                'rows' => 2,
                                'placeholder' => 'ทราบ / ดำเนินการเรียบร้อยแล้ว',
                            ]) ?>
                            <?= Html::submitButton('<i class="bi bi-check-circle me-1" aria-hidden="true"></i><span>ปิดเรื่อง</span>', [
                                'class' => 'btn btn-success w-100 mt-2',
                                'data-confirm' => 'ยืนยันปิดเรื่องหนังสือฉบับนี้ทันทีหรือไม่?',
                            ]) ?>
                        <?= Html::endForm() ?>
                    <?php endif; ?>

                <?php elseif ($model->status === Document::STATUS_RETURNED && $canSubmitToApprover): ?>
                    <?php $systemApprover = \common\models\SystemSetting::getDocumentApprover(); ?>
                    <?= Html::beginForm(['submit-to-approver', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                        <?= $csrf ?>
                        <div class="alert alert-danger py-2 px-3 small mb-3">
                            <i class="bi bi-exclamation-triangle me-1"></i>หนังสือถูกส่งกลับให้แก้ไข เมื่อตรวจแก้ข้อมูลแล้ว ให้เสนอผู้ตรวจสอบอีกครั้ง
                        </div>
                        <?php if ($systemApprover !== null): ?>
                            <div class="alert alert-light border py-2 px-3 small mb-3">
                                <div class="text-muted small">ผู้ตรวจสอบ/อนุมัติ (กำหนดโดย Admin):</div>
                                <div class="fw-bold text-dark fs-6 mt-1">
                                    <i class="bi bi-shield-check text-primary me-1"></i><?= Html::encode($systemApprover->displayName()) ?>
                                    <span class="badge bg-secondary ms-1"><?= Html::encode($systemApprover->roleLabel()) ?></span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted" for="re-submit-comment">หมายเหตุการแก้ไข (ถ้ามี)</label>
                                <?= Html::textarea('comment', '', [
                                    'id' => 're-submit-comment',
                                    'class' => 'form-control',
                                    'rows' => 2,
                                    'placeholder' => 'แก้ไขตามคำแนะนำแล้ว...',
                                ]) ?>
                            </div>
                            <?= Html::submitButton('<i class="bi bi-send-check me-1" aria-hidden="true"></i><span>แก้ไขแล้ว เสนอผู้ตรวจสอบอีกครั้ง</span>', ['class' => 'btn btn-primary w-100']) ?>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 px-3 small mb-0">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                <strong>ยังไม่ได้กำหนดผู้ตรวจสอบในระบบ</strong><br>
                                กรุณาแจ้ง Admin เพื่อตั้งค่าผู้ตรวจสอบ/อนุมัติในหน้า "ผู้ใช้และบทบาท"
                            </div>
                        <?php endif; ?>
                    <?= Html::endForm() ?>

                <?php elseif ($model->status === Document::STATUS_ASSIGNED && ($canForwardAssignment || $canCompleteAssignment)): ?>
                    <?php if ($canForwardAssignment): ?>
                        <?= Html::beginForm(['forward-to-staff', 'id' => $model->id], 'post', ['class' => 'action-form', 'id' => 'forward-staff-form']) ?>
                            <?= $csrf ?>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label mb-0 fw-semibold">ส่งต่อให้ผู้ปฏิบัติ (เลือกได้หลายคน / ข้ามกลุ่มงานได้)</label>
                                <span class="badge bg-primary rounded-pill" id="selected-staff-count">เลือกแล้ว <?= count($assignedUserIds) ?> คน</span>
                            </div>

                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control" id="staff-search-input" placeholder="ค้นหาชื่อบุคลากร หรือกลุ่มสาระฯ...">
                                <button type="button" class="btn btn-outline-secondary" id="btn-clear-staff-selection" title="ล้างที่เลือกทั้งหมด">ล้าง</button>
                            </div>

                            <div class="border rounded-2 bg-white p-2" id="staff-area-picker" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($staffGroupedByArea as $areaTitle => $members): ?>
                                    <?php $groupKey = 'area_' . md5($areaTitle); ?>
                                    <div class="staff-area-group mb-2 pb-1 border-bottom" data-area-name="<?= Html::encode(mb_strtolower($areaTitle, 'UTF-8')) ?>">
                                        <div class="d-flex align-items-center justify-content-between bg-light px-2 py-1 rounded-1 mb-1">
                                            <span class="small fw-bold text-primary">
                                                <i class="bi bi-book me-1"></i><?= Html::encode(str_replace('กลุ่มสาระการเรียนรู้', 'กลุ่มสาระฯ ', $areaTitle)) ?>
                                                <span class="text-muted fw-normal">(<?= count($members) ?>)</span>
                                            </span>
                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none btn-toggle-area-group" data-group="<?= $groupKey ?>" style="font-size: 0.72rem;">เลือกทั้งกลุ่ม</button>
                                        </div>
                                        <div class="ps-1">
                                            <?php foreach ($members as $staffUser): ?>
                                                <?php
                                                $isChecked = in_array((int) $staffUser->id, $assignedUserIds, true);
                                                $deptSuffix = $staffUser->department ? $staffUser->department->name : '';
                                                $searchText = mb_strtolower($staffUser->displayName() . ' ' . $staffUser->username . ' ' . $deptSuffix . ' ' . $areaTitle, 'UTF-8');
                                                ?>
                                                <label class="form-check d-flex align-items-center gap-2 py-1 px-1 mb-0 rounded staff-member-item" data-search="<?= Html::encode($searchText) ?>" style="cursor: pointer; font-size: 0.84rem;">
                                                    <input class="form-check-input mt-0 staff-checkbox" type="checkbox" name="assigned_to[]" value="<?= (int) $staffUser->id ?>" data-group="<?= $groupKey ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                    <span class="text-dark flex-grow-1">
                                                        <?= Html::encode($staffUser->displayName()) ?>
                                                        <?php if ($deptSuffix !== ''): ?>
                                                            <span class="text-muted" style="font-size: 0.74rem;">(<?= Html::encode($deptSuffix) ?>)</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <label class="form-label mt-3" for="forward-instruction">คำสั่ง/หมายเหตุถึงผู้ปฏิบัติ</label>
                            <?= Html::textarea('instruction', $latestAssignment?->instruction ?? '', [
                                'id' => 'forward-instruction',
                                'class' => 'form-control',
                                'rows' => 3,
                                'placeholder' => 'ดำเนินการตามคำสั่งและรายงานผล',
                            ]) ?>
                            <?= Html::submitButton('<i class="bi bi-person-check me-1" aria-hidden="true"></i><span>ส่งต่อให้ผู้ปฏิบัติที่เลือก</span>', ['class' => 'btn btn-outline-primary w-100 mt-3']) ?>
                        <?= Html::endForm() ?>
                        <?php
                        $this->registerJs(<<<'JS'
                        (function() {
                            var picker = document.getElementById('staff-area-picker');
                            if (!picker) return;
                            var searchInput = document.getElementById('staff-search-input');
                            var countBadge = document.getElementById('selected-staff-count');
                            var clearBtn = document.getElementById('btn-clear-staff-selection');

                            function updateCount() {
                                var checked = picker.querySelectorAll('.staff-checkbox:checked').length;
                                if (countBadge) {
                                    countBadge.textContent = 'เลือกแล้ว ' + checked + ' คน';
                                }
                            }

                            picker.addEventListener('change', function(e) {
                                if (e.target && e.target.classList.contains('staff-checkbox')) {
                                    updateCount();
                                }
                            });

                            picker.querySelectorAll('.btn-toggle-area-group').forEach(function(btn) {
                                btn.addEventListener('click', function() {
                                    var grp = btn.getAttribute('data-group');
                                    var boxes = Array.prototype.slice.call(picker.querySelectorAll('.staff-checkbox[data-group="' + grp + '"]'))
                                        .filter(function(cb) { return cb.closest('.staff-member-item').style.display !== 'none'; });
                                    var allChecked = boxes.length > 0 && boxes.every(function(cb) { return cb.checked; });
                                    boxes.forEach(function(cb) { cb.checked = !allChecked; });
                                    updateCount();
                                });
                            });

                            if (clearBtn) {
                                clearBtn.addEventListener('click', function() {
                                    picker.querySelectorAll('.staff-checkbox:checked').forEach(function(cb) { cb.checked = false; });
                                    updateCount();
                                });
                            }

                            if (searchInput) {
                                searchInput.addEventListener('input', function() {
                                    var q = searchInput.value.trim().toLowerCase();
                                    picker.querySelectorAll('.staff-area-group').forEach(function(groupEl) {
                                        var anyVisible = false;
                                        groupEl.querySelectorAll('.staff-member-item').forEach(function(itemEl) {
                                            var hay = itemEl.getAttribute('data-search') || '';
                                            var show = (q === '' || hay.indexOf(q) !== -1);
                                            itemEl.style.display = show ? '' : 'none';
                                            if (show) anyVisible = true;
                                        });
                                        groupEl.style.display = anyVisible ? '' : 'none';
                                    });
                                });
                            }

                            updateCount();
                        })();
                        JS);
                        ?>
                    <?php endif; ?>

                    <?php if ($canCompleteAssignment): ?>
                        <?= Html::beginForm(['complete', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                            <?= $csrf ?>
                            <label class="form-label" for="complete-comment">ผลการดำเนินงาน</label>
                            <?= Html::textarea('comment', '', [
                                'id' => 'complete-comment',
                                'class' => 'form-control',
                                'rows' => 4,
                                'placeholder' => 'ดำเนินการเรียบร้อยแล้ว',
                            ]) ?>
                            <?= Html::submitButton('<i class="bi bi-check-circle" aria-hidden="true"></i><span>ปิดเรื่อง</span>', ['class' => 'btn btn-success w-100 mt-3']) ?>
                        <?= Html::endForm() ?>
                    <?php endif; ?>

                <?php else: ?>
                    <?php if ($myAssignment !== null && $myAssignment->isAcknowledged()): ?>
                        <div class="alert alert-success d-flex align-items-start gap-2 mb-0" style="font-size: 0.88rem;">
                            <i class="bi bi-check-circle-fill fs-5 text-success mt-1"></i>
                            <div>
                                <strong>ท่านรับทราบเอกสารนี้แล้ว</strong><br>
                                <span class="small text-muted">บันทึกเวลารับทราบอัตโนมัติเมื่อ: <?= Html::encode($formatThaiDateTime($myAssignment->acknowledged_at ? (int) $myAssignment->acknowledged_at : null)) ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="muted-text">ไม่มีรายการที่ต้องดำเนินการสำหรับบทบาทของคุณในสถานะนี้</p>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($canDeleteDocument): ?>
                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small text-danger fw-semibold"><i class="bi bi-trash3 me-1"></i>จัดการหนังสือที่ลงรับผิดพลาด</span>
                        </div>
                        <p class="small text-muted mb-2">หากลงรับหนังสือผิดพลาด (เช่น เลขรับซ้ำ, อัปโหลดเอกสารผิดฉบับ) เจ้าหน้าที่ธุรการสามารถลบรายการนี้ออกจากระบบได้</p>
                        <?= Html::a('<i class="bi bi-trash3 me-1" aria-hidden="true"></i><span>ลบหนังสือฉบับนี้</span>', ['delete', 'id' => $model->id], [
                            'class' => 'btn btn-outline-danger btn-sm w-100',
                            'data-confirm' => 'คุณแน่ใจหรือไม่ว่าต้องการลบหนังสือเลขรับ ' . Html::encode($model->receive_no) . ' เรื่อง "' . Html::encode($model->subject) . '" นี้? เอกสารและไฟล์แนบทั้งหมดจะถูกลบออกจากระบบอย่างถาวร ไม่สามารถกู้คืนได้',
                            'data-method' => 'post',
                        ]) ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="content-card">
                <h2>ประวัติการดำเนินการ</h2>
                <ol class="timeline">
                    <?php $workflowActionCount = count($model->workflowActions); ?>
                    <?php foreach ($model->workflowActions as $index => $action): ?>
                        <li class="<?= $index === $workflowActionCount - 1 ? 'current' : '' ?>">
                            <span class="timeline-dot" aria-hidden="true"></span>
                            <div class="timeline-body">
                                <time><?= Html::encode($thaiDateTime($action->created_at)) ?></time>
                                <p><?= Html::encode($action->comment ?: $action->action) ?></p>
                                <?php if ($action->actor): ?>
                                    <span>โดย <?= Html::encode($action->actor->displayName()) ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        </aside>
    </div>
</div>

<?php if ($canEditSummary): ?>
    <div class="modal fade" id="modal-edit-summary" tabindex="-1" aria-labelledby="modalEditSummaryLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <?= Html::beginForm(['update-summary', 'id' => $model->id], 'post') ?>
                <?= $csrf ?>
                <div class="modal-header bg-light">
                    <h5 class="modal-title fs-6 fw-bold text-dark" id="modalEditSummaryLabel">
                        <i class="bi bi-card-text me-1 text-primary"></i>แก้ไขสรุปเนื้อหาในหนังสือโดยย่อ
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">ชื่อเรื่องหนังสือ (ค่าตั้งต้น):</label>
                        <div class="p-2 bg-light border rounded small text-secondary" style="line-height: 1.5; white-space: pre-line; text-indent: 0;"><?= Html::encode(trim((string) $model->subject)) ?></div>
                    </div>
                    <div class="mb-2">
                        <label for="document-summary-input" class="form-label fw-semibold">สรุปเนื้อหาในหนังสือโดยย่อ:</label>
                        <?= Html::textarea('summary', trim((string) ($model->summary ?: $model->subject)), [
                            'id' => 'document-summary-input',
                            'class' => 'form-control',
                            'rows' => 4,
                            'style' => 'text-indent: 0;',
                            'placeholder' => 'ระบุสรุปเนื้อหาในหนังสือโดยย่อ...',
                        ]) ?>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none p-0" id="btn-reset-summary-to-subject">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>คืนค่าตามชื่อเรื่อง
                        </button>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2 me-1"></i>บันทึก</button>
                </div>
                <?= Html::endForm() ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
if ($canEditSummary) {
    $subjectJson = json_encode($model->subject, JSON_UNESCAPED_UNICODE);
    $this->registerJs(<<<JS
    const btnResetSummary = document.getElementById('btn-reset-summary-to-subject');
    const summaryInput = document.getElementById('document-summary-input');
    if (btnResetSummary && summaryInput) {
        btnResetSummary.addEventListener('click', () => {
            summaryInput.value = {$subjectJson};
            summaryInput.focus();
        });
    }
JS);
}

$this->registerJs(<<<'JS'
(function () {
    const canvasContainer = document.getElementById('pdf-page-container');
    if (!canvasContainer || !canvasContainer.dataset.pdfUrl) {
        return;
    }

    const pdfUrl = canvasContainer.dataset.pdfUrl;
    const pageWidth = parseFloat(canvasContainer.dataset.pageWidth || '216');
    let pageHeight = parseFloat(canvasContainer.dataset.pageHeight || '297');
    const mainCanvas = document.getElementById('pdf-main-canvas');
    if (!mainCanvas) return;
    const ctx = mainCanvas.getContext('2d');

    const loadingIndicator = document.getElementById('pdf-loading-indicator');
    const errorFallback = document.getElementById('pdf-error-fallback');
    const pageNumDisplay = document.getElementById('pdf-current-page');
    const totalPagesDisplay = document.getElementById('pdf-total-pages');
    const prevBtn = document.getElementById('pdf-prev-page');
    const nextBtn = document.getElementById('pdf-next-page');
    const zoomInBtn = document.getElementById('pdf-zoom-in');
    const zoomOutBtn = document.getElementById('pdf-zoom-out');
    const zoomFitBtn = document.getElementById('pdf-zoom-fit');
    const zoomLevelDisplay = document.getElementById('pdf-zoom-level');
    const stampLayer = canvasContainer.querySelector('.stamp-preview-layer');

    let pdfDoc = null;
    let currentPageNum = 1;
    let totalPages = 1;
    let currentScale = 1.0;
    let fitScale = 1.0;
    let isRendering = false;
    let pendingPageNum = null;
    let renderTask = null;

    function rounded(value) {
        return (Math.round(parseFloat(value || '0') * 10) / 10).toString();
    }

    function updateStamp(type) {
        const control = document.querySelector(`[data-stamp-control="${type}"]`);
        const box = document.querySelector(`[data-stamp-box="${type}"]`);
        if (!control || !box) return;

        const xInput = control.querySelector('[data-axis="x"]');
        const yInput = control.querySelector('[data-axis="y"]');
        const x = parseFloat(xInput.value || '0');
        const y = parseFloat(yInput.value || '0');
        const width = parseFloat(box.dataset.widthMm || '58');
        const height = parseFloat(box.dataset.heightMm || '30');

        const xOut = control.querySelector('[data-output="x"]');
        const yOut = control.querySelector('[data-output="y"]');
        if (xOut) xOut.textContent = rounded(x);
        if (yOut) yOut.textContent = rounded(y);

        box.style.left = `${(x / pageWidth) * 100}%`;
        box.style.top = `${(y / pageHeight) * 100}%`;
        box.style.width = `${(width / pageWidth) * 100}%`;
        box.style.height = `${(height / pageHeight) * 100}%`;
    }

    function setStampCoords(type, xMm, yMm) {
        const control = document.querySelector(`[data-stamp-control="${type}"]`);
        const box = document.querySelector(`[data-stamp-box="${type}"]`);
        if (!box) return;

        const widthMm = parseFloat(box.dataset.widthMm || '58');
        const heightMm = parseFloat(box.dataset.heightMm || '30');

        // Clamp inside paper boundaries
        const maxX = Math.max(0, pageWidth - widthMm);
        const maxY = Math.max(0, pageHeight - heightMm);
        const clampedX = Math.max(0, Math.min(maxX, xMm));
        const clampedY = Math.max(0, Math.min(maxY, yMm));

        const roundedX = Math.round(clampedX * 10) / 10;
        const roundedY = Math.round(clampedY * 10) / 10;

        if (control) {
            const xInput = control.querySelector('[data-axis="x"]');
            const yInput = control.querySelector('[data-axis="y"]');
            if (xInput) xInput.value = roundedX;
            if (yInput) yInput.value = roundedY;
            const xOut = control.querySelector('[data-output="x"]');
            const yOut = control.querySelector('[data-output="y"]');
            if (xOut) xOut.textContent = roundedX.toString();
            if (yOut) yOut.textContent = roundedY.toString();
        }

        box.style.left = `${(clampedX / pageWidth) * 100}%`;
        box.style.top = `${(clampedY / pageHeight) * 100}%`;
        box.style.width = `${(widthMm / pageWidth) * 100}%`;
        box.style.height = `${(heightMm / pageHeight) * 100}%`;
    }

    // Interactive Drag & Drop for Stamp Boxes (MS Word-like feel)
    function initDraggableStampBox(box) {
        const type = box.dataset.stampBox;
        let isDragging = false;
        let startPointerX = 0;
        let startPointerY = 0;
        let startLeftPx = 0;
        let startTopPx = 0;
        let containerRect = null;

        function onPointerDown(e) {
            if (e.type === 'mousedown' && e.button !== 0) return;

            // Make sure we're on the right page for this stamp
            if (type === 'receive' && currentPageNum !== 1) {
                goToPage(1);
            } else if (type !== 'receive' && currentPageNum !== totalPages) {
                goToPage(totalPages);
            }

            isDragging = true;
            box.classList.add('is-dragging');

            const clientX = e.type.startsWith('touch') ? e.touches[0].clientX : e.clientX;
            const clientY = e.type.startsWith('touch') ? e.touches[0].clientY : e.clientY;

            startPointerX = clientX;
            startPointerY = clientY;

            const container = document.getElementById('pdf-page-container');
            containerRect = container.getBoundingClientRect();

            const boxRect = box.getBoundingClientRect();
            startLeftPx = boxRect.left - containerRect.left;
            startTopPx = boxRect.top - containerRect.top;

            if (e.cancelable) {
                e.preventDefault();
            }

            window.addEventListener('mousemove', onPointerMove, { passive: false });
            window.addEventListener('mouseup', onPointerUp);
            window.addEventListener('touchmove', onPointerMove, { passive: false });
            window.addEventListener('touchend', onPointerUp);
        }

        function onPointerMove(e) {
            if (!isDragging || !containerRect) return;
            if (e.cancelable) {
                e.preventDefault();
            }

            const clientX = e.type.startsWith('touch') ? e.touches[0].clientX : e.clientX;
            const clientY = e.type.startsWith('touch') ? e.touches[0].clientY : e.clientY;

            const deltaX = clientX - startPointerX;
            const deltaY = clientY - startPointerY;

            const newLeftPx = startLeftPx + deltaX;
            const newTopPx = startTopPx + deltaY;

            const currentContainerWidth = containerRect.width;
            const currentContainerHeight = containerRect.height;
            if (currentContainerWidth <= 0 || currentContainerHeight <= 0) return;

            const newX_mm = (newLeftPx / currentContainerWidth) * pageWidth;
            const newY_mm = (newTopPx / currentContainerHeight) * pageHeight;

            setStampCoords(type, newX_mm, newY_mm);
        }

        function onPointerUp(e) {
            if (!isDragging) return;
            isDragging = false;
            box.classList.remove('is-dragging');

            window.removeEventListener('mousemove', onPointerMove);
            window.removeEventListener('mouseup', onPointerUp);
            window.removeEventListener('touchmove', onPointerMove);
            window.removeEventListener('touchend', onPointerUp);
        }

        box.addEventListener('mousedown', onPointerDown);
        box.addEventListener('touchstart', onPointerDown, { passive: false });
    }

    document.querySelectorAll('[data-stamp-control]').forEach((control) => {
        const type = control.dataset.stampControl;
        control.addEventListener('click', () => {
            if (type === 'receive' && currentPageNum !== 1) {
                goToPage(1);
            } else if (type !== 'receive' && currentPageNum !== totalPages) {
                goToPage(totalPages);
            }
            const box = document.querySelector(`[data-stamp-box="${type}"]`);
            if (box) {
                box.classList.add('is-dragging');
                setTimeout(() => box.classList.remove('is-dragging'), 800);
            }
        });
        updateStamp(type);
    });

    document.querySelectorAll('.stamp-preview-box').forEach((box) => {
        initDraggableStampBox(box);
    });

    async function renderPage(num) {
        if (!pdfDoc) return;
        isRendering = true;

        try {
            const page = await pdfDoc.getPage(num);
            const wrapper = document.getElementById('pdf-canvas-wrapper');
            const isMobile = window.innerWidth < 992;
            let availableWidth = (wrapper && wrapper.clientWidth > 100)
                ? wrapper.clientWidth
                : (window.innerWidth || document.documentElement.clientWidth || 360);

            if (!isMobile) {
                availableWidth = Math.max(availableWidth - 16, 280);
            }
            const unscaledViewport = page.getViewport({ scale: 1 });
            
            fitScale = availableWidth / unscaledViewport.width;
            const effectiveScale = currentScale * fitScale;

            // Ultra-sharp Retina / High-DPI rendering (2x to 3x DPR buffer)
            const dpr = Math.min(Math.max(window.devicePixelRatio || 1, 2), 3);
            const viewport = page.getViewport({ scale: effectiveScale * dpr });

            mainCanvas.width = Math.floor(viewport.width);
            mainCanvas.height = Math.floor(viewport.height);
            const cssWidth = Math.floor(viewport.width / dpr);
            const cssHeight = Math.floor(viewport.height / dpr);

            if (currentScale > 1.0) {
                // Allow panning / scroll when zoomed in
                mainCanvas.style.width = cssWidth + 'px';
                mainCanvas.style.height = cssHeight + 'px';
                mainCanvas.style.maxWidth = 'none';
                if (canvasContainer) {
                    canvasContainer.style.width = cssWidth + 'px';
                    canvasContainer.style.height = cssHeight + 'px';
                    canvasContainer.style.maxWidth = 'none';
                }
            } else {
                if (isMobile) {
                    mainCanvas.style.width = '100%';
                    mainCanvas.style.height = 'auto';
                    mainCanvas.style.maxWidth = '100%';
                    if (canvasContainer) {
                        canvasContainer.style.width = '100%';
                        canvasContainer.style.height = 'auto';
                        canvasContainer.style.maxWidth = '100%';
                    }
                } else {
                    mainCanvas.style.width = cssWidth + 'px';
                    mainCanvas.style.height = cssHeight + 'px';
                    mainCanvas.style.maxWidth = '100%';
                    if (canvasContainer) {
                        canvasContainer.style.width = cssWidth + 'px';
                        canvasContainer.style.height = cssHeight + 'px';
                        canvasContainer.style.maxWidth = '100%';
                    }
                }
            }

            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';

            if (renderTask) {
                try {
                    renderTask.cancel();
                } catch (e) {}
            }

            const renderContext = {
                canvasContext: ctx,
                viewport: viewport,
            };

            renderTask = page.render(renderContext);
            await renderTask.promise;

            // Calculate height aspect ratio for stamp overlay
            pageHeight = pageWidth * (unscaledViewport.height / unscaledViewport.width);
            document.querySelectorAll('[data-axis="y"]').forEach((input) => {
                input.max = rounded(pageHeight);
            });

            // ตรารับหนังสือแสดงหน้าแรก ส่วนตรากลุ่มงาน/รอง ผอ./ผอ. แสดงหน้าสุดท้าย
            if (stampLayer) {
                const isFirstPage = (num === 1);
                const isLastPage = (num === totalPages);

                const receiveBox = stampLayer.querySelector('[data-stamp-box="receive"]');
                const deptBox = stampLayer.querySelector('[data-stamp-box="department"]');
                const deputyBox = stampLayer.querySelector('[data-stamp-box="deputy_comment"]');
                const directorBox = stampLayer.querySelector('[data-stamp-box="director_order"]');

                if (receiveBox) {
                    receiveBox.style.display = isFirstPage ? 'flex' : 'none';
                }
                if (deptBox) {
                    deptBox.style.display = isLastPage ? 'flex' : 'none';
                }
                if (deputyBox) {
                    deputyBox.style.display = isLastPage ? 'flex' : 'none';
                }
                if (directorBox) {
                    directorBox.style.display = isLastPage ? 'flex' : 'none';
                }

                stampLayer.style.display = (isFirstPage || isLastPage) ? 'block' : 'none';
                if (isFirstPage || isLastPage) {
                    document.querySelectorAll('[data-stamp-control]').forEach((control) => updateStamp(control.dataset.stampControl));
                }
            }

            if (pageNumDisplay) pageNumDisplay.textContent = num;
            if (prevBtn) prevBtn.disabled = (num <= 1);
            if (nextBtn) nextBtn.disabled = (num >= totalPages);

            if (loadingIndicator) loadingIndicator.classList.add('d-none');
            canvasContainer.classList.remove('d-none');
        } catch (err) {
            if (err && err.name === 'RenderingCancelledException') {
                return;
            }
            console.error('Error rendering page:', err);
        } finally {
            isRendering = false;
            renderTask = null;
            if (pendingPageNum !== null) {
                const nextNum = pendingPageNum;
                pendingPageNum = null;
                renderPage(nextNum);
            }
        }
    }

    function queueRenderPage(num) {
        if (isRendering) {
            pendingPageNum = num;
        } else {
            renderPage(num);
        }
    }

    function goToPage(num) {
        if (num < 1 || num > totalPages) return;
        currentPageNum = num;
        queueRenderPage(currentPageNum);
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPageNum > 1) goToPage(currentPageNum - 1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (currentPageNum < totalPages) goToPage(currentPageNum + 1);
        });
    }

    if (zoomInBtn) {
        zoomInBtn.addEventListener('click', () => {
            if (currentScale < 2.5) {
                currentScale = Math.round((currentScale + 0.25) * 100) / 100;
                if (zoomLevelDisplay) zoomLevelDisplay.textContent = Math.round(currentScale * 100) + '%';
                queueRenderPage(currentPageNum);
            }
        });
    }

    if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', () => {
            if (currentScale > 0.5) {
                currentScale = Math.round((currentScale - 0.25) * 100) / 100;
                if (zoomLevelDisplay) zoomLevelDisplay.textContent = Math.round(currentScale * 100) + '%';
                queueRenderPage(currentPageNum);
            }
        });
    }

    if (zoomFitBtn) {
        zoomFitBtn.addEventListener('click', () => {
            currentScale = 1.0;
            if (zoomLevelDisplay) zoomLevelDisplay.textContent = '100%';
            queueRenderPage(currentPageNum);
        });
    }

    async function initPdf() {
        if (typeof window.pdfjsLib === 'undefined') {
            console.error('pdfjsLib is not loaded');
            if (loadingIndicator) loadingIndicator.classList.add('d-none');
            if (errorFallback) errorFallback.classList.remove('d-none');
            return;
        }

        try {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = canvasContainer.dataset.workerUrl;
            pdfDoc = await window.pdfjsLib.getDocument(pdfUrl).promise;
            totalPages = pdfDoc.numPages;
            if (totalPagesDisplay) totalPagesDisplay.textContent = totalPages;
            requestAnimationFrame(() => {
                renderPage(1);
            });
        } catch (err) {
            console.error('Error loading PDF:', err);
            if (loadingIndicator) loadingIndicator.classList.add('d-none');
            if (errorFallback) errorFallback.classList.remove('d-none');
        }
    }

    initPdf();

    let resizeTimer = null;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            queueRenderPage(currentPageNum);
        }, 200);
    });
})();
JS);
?>
