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
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);

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
foreach ($model->assignments as $assignment) {
    if ($latestAssignment === null || (int) $assignment->id > (int) $latestAssignment->id) {
        $latestAssignment = $assignment;
    }
}
$canForwardAssignment = $identity instanceof User
    && $canForwardDocument
    && $latestAssignment !== null
    && (
        $identity->role === User::ROLE_ADMIN
        || (
            $identity->role === User::ROLE_STAFF
            && $identity->department_id !== null
            && $latestAssignment->department_id !== null
            && (int) $identity->department_id === (int) $latestAssignment->department_id
        )
    );
$canCompleteAssignment = $identity instanceof User
    && $canCompleteDocument
    && $latestAssignment !== null
    && (
        $identity->role === User::ROLE_ADMIN
        || (
            $identity->role === User::ROLE_DEPUTY_DIRECTOR
            && $model->status === Document::STATUS_SUBMITTED_TO_DEPUTY
        )
        || (
            $identity->role === User::ROLE_GENERAL_USER
            && $latestAssignment->assigned_to !== null
            && (int) $latestAssignment->assigned_to === (int) $identity->id
        )
        || (
            $identity->role === User::ROLE_STAFF
            && $latestAssignment->assigned_to === null
            && $identity->department_id !== null
            && $latestAssignment->department_id !== null
            && (int) $identity->department_id === (int) $latestAssignment->department_id
        )
    );
$staffItems = ArrayHelper::map($staffUsers, 'id', 'username');
$showStampPositionTools = true;
$canAdjustStampPositions = $showStampPositionTools && $model->status === Document::STATUS_REGISTERED && $canRegisterDocument;
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
    DocumentStamp::TYPE_DEPARTMENT => ['x' => 18, 'y' => 219, 'w' => 58, 'h' => 58, 'label' => 'ตรากลุ่มงาน'],
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
                                        >
                                            <span><?= Html::encode($config['label']) ?></span>
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
                    <div><dt>ความเร่งด่วน</dt><dd><?= Html::encode($urgency) ?></dd></div>
                    <div><dt>กลุ่มงาน</dt><dd><?= Html::encode($model->department->name ?? '-') ?></dd></div>
                    <div><dt>ผู้ปฏิบัติ</dt><dd><?= Html::encode($latestAssignment?->assignedTo?->username ?? '-') ?></dd></div>
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
                        <p class="muted-text">ตรารับหนังสือจะอยู่หน้าแรก ส่วนตรากลุ่มงาน/รอง ผอ./ผอ. จะอยู่หน้าสุดท้าย ปรับแล้วระบบจะสร้าง PDF ประทับตราใหม่ให้อัตโนมัติ</p>
                        <?php foreach ($stampPositionDefaults as $type => $config): ?>
                            <div class="stamp-position-row">
                                <strong><?= Html::encode($config['label']) ?></strong>
                                <div class="stamp-slider" data-stamp-control="<?= Html::encode($type) ?>">
                                    <label>
                                        <span>X <output data-output="x"><?= Html::encode((string) $stampPosition($type, 'x')) ?></output></span>
                                        <?= Html::input('range', "stamps[$type][x]", (string) $stampPosition($type, 'x'), [
                                            'class' => 'form-range',
                                            'step' => '0.5',
                                            'min' => '0',
                                            'max' => '216',
                                            'data-axis' => 'x',
                                            'data-stamp-input' => $type,
                                        ]) ?>
                                    </label>
                                    <label>
                                        <span>Y <output data-output="y"><?= Html::encode((string) $stampPosition($type, 'y')) ?></output></span>
                                        <?= Html::input('range', "stamps[$type][y]", (string) $stampPosition($type, 'y'), [
                                            'class' => 'form-range',
                                            'step' => '0.5',
                                            'min' => '0',
                                            'max' => '297',
                                            'data-axis' => 'y',
                                            'data-stamp-input' => $type,
                                        ]) ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?= Html::submitButton('<i class="bi bi-save" aria-hidden="true"></i><span>บันทึกตำแหน่งและสร้าง PDF ใหม่</span>', ['class' => 'btn btn-primary w-100 mt-2']) ?>
                    <?= Html::endForm() ?>
                </section>
            <?php endif; ?>

            <section class="content-card">
                <h2>ดำเนินการ</h2>

                <?php if ($model->status === Document::STATUS_REGISTERED && $canSubmitToDeputy): ?>
                    <?= Html::beginForm(['submit-to-deputy', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                        <?= $csrf ?>
                        <p>เสนอหนังสือให้รอง ผอ. กลุ่มงานที่เกี่ยวข้องตรวจทานและลงความเห็น</p>
                        <?= Html::submitButton('<i class="bi bi-send" aria-hidden="true"></i><span>เสนอรอง ผอ.</span>', ['class' => 'btn btn-primary w-100']) ?>
                    <?= Html::endForm() ?>

                <?php elseif ($model->status === Document::STATUS_SUBMITTED_TO_DEPUTY): ?>
                    <?php if ($canGiveDeputyComment): ?>
                        <?= Html::beginForm(['deputy-comment', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                            <?= $csrf ?>
                            <label class="form-label" for="deputy-comment">ความเห็นรอง ผอ.</label>
                            <?= Html::textarea('comment', '', [
                                'id' => 'deputy-comment',
                                'class' => 'form-control',
                                'rows' => 5,
                                'placeholder' => 'เห็นควรประชาสัมพันธ์และมอบกลุ่มงานที่เกี่ยวข้องดำเนินการ',
                            ]) ?>
                            <div class="action-buttons">
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

                <?php elseif ($model->status === Document::STATUS_RETURNED && $canSubmitToDeputy): ?>
                    <?= Html::beginForm(['submit-to-deputy', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                        <?= $csrf ?>
                        <p class="muted-text">รอง ผอ. ส่งกลับให้แก้ไข เมื่อตรวจแก้ข้อมูล/เอกสารแล้ว ให้เสนอรอง ผอ. อีกครั้ง</p>
                        <?= Html::submitButton('<i class="bi bi-send" aria-hidden="true"></i><span>แก้ไขแล้ว เสนอรอง ผอ. อีกครั้ง</span>', ['class' => 'btn btn-primary w-100']) ?>
                    <?= Html::endForm() ?>

                <?php elseif ($model->status === Document::STATUS_ASSIGNED && ($canForwardAssignment || $canCompleteAssignment)): ?>
                    <?php if ($canForwardAssignment): ?>
                        <?= Html::beginForm(['forward-to-staff', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                            <?= $csrf ?>
                            <label class="form-label" for="assigned-to">ส่งต่อให้ผู้ปฏิบัติ</label>
                            <?= Html::dropDownList('assigned_to', $latestAssignment?->assigned_to, $staffItems, [
                                'id' => 'assigned-to',
                                'class' => 'form-select',
                                'prompt' => 'เลือกผู้ปฏิบัติ',
                            ]) ?>
                            <label class="form-label mt-3" for="forward-instruction">คำสั่ง/หมายเหตุถึงผู้ปฏิบัติ</label>
                            <?= Html::textarea('instruction', $latestAssignment?->instruction ?? '', [
                                'id' => 'forward-instruction',
                                'class' => 'form-control',
                                'rows' => 3,
                                'placeholder' => 'ดำเนินการตามคำสั่งและรายงานผล',
                            ]) ?>
                            <?= Html::submitButton('<i class="bi bi-person-check" aria-hidden="true"></i><span>ส่งต่อให้ผู้ปฏิบัติ</span>', ['class' => 'btn btn-outline-primary w-100 mt-3']) ?>
                        <?= Html::endForm() ?>
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
                    <p class="muted-text">ไม่มีรายการที่ต้องดำเนินการสำหรับบทบาทของคุณในสถานะนี้</p>
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
                                    <span>โดย <?= Html::encode($action->actor->username) ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        </aside>
    </div>
</div>

<?php
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

        control.querySelector('[data-output="x"]').textContent = rounded(x);
        control.querySelector('[data-output="y"]').textContent = rounded(y);

        box.style.left = `${(x / pageWidth) * 100}%`;
        box.style.top = `${(y / pageHeight) * 100}%`;
        box.style.width = `${(width / pageWidth) * 100}%`;
        box.style.height = `${(height / pageHeight) * 100}%`;
    }

    document.querySelectorAll('[data-stamp-control]').forEach((control) => {
        const type = control.dataset.stampControl;
        control.querySelectorAll('input[type="range"]').forEach((input) => {
            input.addEventListener('input', () => {
                if (type === 'receive' && currentPageNum !== 1) {
                    goToPage(1);
                } else if (type !== 'receive' && currentPageNum !== totalPages) {
                    goToPage(totalPages);
                }
                updateStamp(type);
            });
        });
        updateStamp(type);
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
                    receiveBox.style.display = isFirstPage ? 'grid' : 'none';
                }
                if (deptBox) {
                    deptBox.style.display = isLastPage ? 'grid' : 'none';
                }
                if (deputyBox) {
                    deputyBox.style.display = isLastPage ? 'grid' : 'none';
                }
                if (directorBox) {
                    directorBox.style.display = isLastPage ? 'grid' : 'none';
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
