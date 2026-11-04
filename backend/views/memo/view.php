<?php

declare(strict_types=1);

use common\components\MemoPdfService;
use common\models\Memo;
use common\models\User;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var Memo $model */
/** @var string|null $previewImagePath */

$this->title = ($model->memo_no ? $model->memo_no . ' - ' : '') . $model->subject;
$this->params['breadcrumbs'][] = ['label' => 'บันทึกเสนอ', 'url' => ['index']];
$this->params['breadcrumbs'][] = $model->memo_no ?: 'รายละเอียด';
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

$identity = Yii::$app->user->identity;
$statusLabel = Memo::statusOptions()[$model->status] ?? $model->status;
$statusClass = 'status-badge-' . str_replace('_', '-', $model->status);
$latestAction = $model->actions !== [] ? $model->actions[count($model->actions) - 1] : null;
$pdfVersion = (string) Yii::$app->request->get('pdfv', (string) ($model->updated_at ?: time()));
$memoPdfUrl = Url::to(['pdf', 'id' => $model->id, 'v' => $pdfVersion]);
$previewImageUrl = $previewImagePath ? Url::to('@web/' . ltrim($previewImagePath, '/')) : null;
$csrf = Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken);

$isAdmin = $identity instanceof User && $identity->hasRole(User::ROLE_ADMIN);
$isCreator = $identity instanceof User && (int) $model->created_by === (int) $identity->id;
$canEdit = ($isCreator || $isAdmin)
    && in_array($model->status, [Memo::STATUS_DRAFT, Memo::STATUS_RETURNED], true);
$showStampPositionTools = true;
$canAdjustStampPositions = $showStampPositionTools && $canEdit;

$canReviewAsStaff = ($identity instanceof User && ($identity->role === User::ROLE_STAFF || $identity->role === User::ROLE_OFFICER || $isAdmin || (int) $identity->id === (int) $model->current_actor_id));
$canReviewAsDeputy = ($identity instanceof User && ($identity->hasRole(User::ROLE_DEPUTY_DIRECTOR) || $isAdmin));
$canReviewAsDirector = ($identity instanceof User && ($identity->hasRole(User::ROLE_DIRECTOR) || $isAdmin));
$canComplete = ($isCreator || $isAdmin) && $model->status === Memo::STATUS_APPROVED;
$stampPositionDefaults = array_map(
    static fn (array $position): array => [
        'x' => (float) $position['x'],
        'y' => (float) $position['y'],
        'w' => (float) ($position['w'] ?? MemoPdfService::SIGNATURE_WIDTH),
        'h' => (float) ($position['h'] ?? MemoPdfService::SIGNATURE_HEIGHT),
        'label' => (string) $position['label'],
    ],
    MemoPdfService::SIGNATURE_POSITIONS,
);
$stampPosition = static function (string $type, string $axis) use ($model, $stampPositionDefaults): float {
    $latest = null;
    foreach ($model->stampPositions as $position) {
        if ($position->stamp_type === $type) {
            $latest = $position;
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
    $months = [1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'];
    return (int) date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . ((int) date('Y', $timestamp) + 543) . ' ' . date('H:i', $timestamp) . ' น.';
};
$thaiDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return '-';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    $yearThai = (int) date('Y', $timestamp) + 543;
    return date('d/m/', $timestamp) . $yearThai . ' BE';
};

$this->registerJsFile('@web/js/pdf.min.js', [
    'position' => \yii\web\View::POS_HEAD,
]);
?>

<div class="eoffice-page">
    <div class="document-workspace">
        <section class="content-card pdf-card-section p-0 border-0 shadow-sm overflow-hidden">
            <!-- Compact Header / Back Bar for Mobile & Desktop -->
            <div class="pdf-viewer-header d-flex align-items-center justify-content-between p-2 px-3 bg-white border-bottom gap-2">
                <div class="d-flex align-items-center gap-2 text-truncate">
                    <?= Html::a('<i class="bi bi-arrow-left" aria-hidden="true"></i> <span class="d-none d-sm-inline">กลับ</span>', ['/memo/index'], [
                        'class' => 'btn btn-sm btn-outline-secondary py-1 px-2 text-nowrap',
                        'title' => 'กลับรายการบันทึกข้อความ',
                    ]) ?>
                    <span class="badge bg-teal-subtle text-teal border border-teal-subtle font-monospace text-nowrap" style="color: #0f766e; background-color: #ccfbf1; border-color: #99f6e4;">
                        <?= Html::encode($model->memo_no ?: '(ฉบับร่าง)') ?>
                    </span>
                    <span class="fw-semibold text-dark text-truncate small" title="<?= Html::encode($model->subject) ?>">
                        <?= Html::encode($model->subject) ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                    <?php if ($canEdit): ?>
                        <?= Html::a('<i class="bi bi-pencil-square" aria-hidden="true"></i> <span class="d-none d-sm-inline">แก้ไข</span>', ['update', 'id' => $model->id], [
                            'class' => 'btn btn-sm btn-outline-primary py-1 px-2 text-nowrap',
                            'title' => 'แก้ไขบันทึกข้อความ',
                        ]) ?>
                    <?php endif; ?>
                    <span class="status-badge <?= Html::encode($statusClass) ?>" style="font-size: 0.75rem; padding: 2px 8px; min-height: 24px;">
                        <?= Html::encode($statusLabel) ?>
                    </span>
                </div>
            </div>

            <div class="pdf-preview-frame">
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
                        <a href="<?= Html::encode($memoPdfUrl) ?>" target="_blank" class="btn btn-sm btn-primary fw-semibold px-2 py-1 ms-1 d-inline-flex align-items-center gap-1" title="เปิดเอกสารแท็บใหม่">
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
                        <i class="bi bi-file-earmark-pdf text-success fs-1 d-block mb-2"></i>
                        <h5 class="fw-bold text-dark">เปิดอ่านบันทึกข้อความ PDF</h5>
                        <p class="text-muted small">สามารถแตะปุ่มด้านล่างเพื่อเปิดอ่านบน iPad หรือมือถือได้ทันที</p>
                        <a href="<?= Html::encode($memoPdfUrl) ?>" target="_blank" class="btn btn-primary fw-semibold px-4 py-2 mt-2">
                            <i class="bi bi-box-arrow-up-right me-1"></i> แตะเพื่อเปิดอ่านไฟล์ PDF
                        </a>
                    </div>

                    <div class="pdf-preview-canvas d-none position-relative" id="pdf-page-container" 
                         data-page-width="216" data-page-height="297" 
                         data-pdf-url="<?= Html::encode($memoPdfUrl) ?>"
                         data-worker-url="<?= Url::to('@web/js/pdf.worker.min.js') ?>">
                        <canvas id="pdf-main-canvas" class="pdf-page-image" style="display: block; max-width: 100%; height: auto; background: #fff;"></canvas>

                        <?php if ($canAdjustStampPositions): ?>
                            <div class="stamp-preview-layer" aria-hidden="true">
                                <?php foreach ($stampPositionDefaults as $type => $config): ?>
                                    <div
                                        class="stamp-preview-box memo-signature-preview-box"
                                        data-stamp-box="<?= Html::encode((string) $type) ?>"
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
            </div>
        </section>

        <aside class="side-stack">
            <!-- ข้อมูลบันทึก -->
            <section class="content-card">
                <h2>ข้อมูลบันทึก</h2>
                <dl class="meta-list">
                    <div><dt>สถานะ</dt><dd><span class="status-badge <?= Html::encode($statusClass) ?>"><?= Html::encode($statusLabel) ?></span></dd></div>
                    <div><dt>เลขที่บันทึก</dt><dd class="font-monospace fw-semibold"><?= Html::encode($model->memo_no ?: '(ฉบับร่าง)') ?></dd></div>
                    <div><dt>วันที่</dt><dd><?= $thaiDate($model->memo_date) ?></dd></div>
                    <div><dt>กลุ่มงาน</dt><dd><?= Html::encode($model->department->name ?? '-') ?></dd></div>
                    <div><dt>เรียน</dt><dd><?= Html::encode($model->to_text ?: '-') ?></dd></div>
                    <div><dt>ผู้จัดทำ</dt><dd><?= Html::encode($model->creator ? $model->creator->displayName() : ($model->creator->username ?? '-')) ?></dd></div>
                    <div><dt>ผู้พิจารณาปัจจุบัน</dt><dd><?= Html::encode($model->currentActor ? $model->currentActor->displayName() : ($model->currentActor->username ?? '-')) ?></dd></div>
                </dl>
            </section>

            <!-- ไฟล์แนบ -->
            <?php if ($model->originalPdfFile !== null || $model->attachmentFiles !== []): ?>
                <section class="content-card">
                    <h2>ไฟล์แนบ</h2>
                    <div class="attachment-list">
                        <?php if ($model->originalPdfFile !== null): ?>
                            <?= Html::a('<i class="bi bi-file-earmark-pdf" aria-hidden="true"></i><span>PDF บันทึกข้อความต้นฉบับ</span>', Url::to('@web/' . ltrim($model->originalPdfFile->storage_path, '/'), true), [
                                'class' => 'attachment-link',
                                'target' => '_blank',
                            ]) ?>
                        <?php endif; ?>
                        <?php foreach ($model->attachmentFiles as $file): ?>
                            <?= Html::a('<i class="bi bi-file-earmark-pdf" aria-hidden="true"></i><span>' . Html::encode($file->original_name) . '</span>', Url::to('@web/' . ltrim($file->storage_path, '/'), true), [
                                'class' => 'attachment-link',
                                'target' => '_blank',
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- ตำแหน่งประทับลายเซ็น -->
            <?php if ($canAdjustStampPositions): ?>
                <section class="content-card">
                    <h2>ตำแหน่งประทับลายเซ็น</h2>
                    <?= Html::beginForm(['update-stamp-positions', 'id' => $model->id], 'post', ['class' => 'stamp-position-form']) ?>
                        <?= $csrf ?>
                        <p class="muted-text">หน่วยเป็นมิลลิเมตรจากซ้าย/บนของหน้าแรก ปรับแล้ว preview จะขยับทันที</p>
                        <?php foreach ($stampPositionDefaults as $type => $config): ?>
                            <div class="stamp-position-row">
                                <strong><?= Html::encode($config['label']) ?></strong>
                                <div class="stamp-slider" data-stamp-control="<?= Html::encode((string) $type) ?>">
                                    <label>
                                        <span>X <output data-output="x"><?= Html::encode((string) $stampPosition((string) $type, 'x')) ?></output></span>
                                        <?= Html::input('range', "stamps[$type][x]", (string) $stampPosition((string) $type, 'x'), [
                                            'class' => 'form-range',
                                            'step' => '0.5',
                                            'min' => '0',
                                            'max' => '216',
                                            'data-axis' => 'x',
                                            'data-stamp-input' => $type,
                                        ]) ?>
                                    </label>
                                    <label>
                                        <span>Y <output data-output="y"><?= Html::encode((string) $stampPosition((string) $type, 'y')) ?></output></span>
                                        <?= Html::input('range', "stamps[$type][y]", (string) $stampPosition((string) $type, 'y'), [
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

                        <?php if ($canEdit): ?>
                            <div class="mt-3 p-3 bg-light rounded border">
                                <label class="form-label mb-1"><strong><i class="bi bi-send me-1"></i>ส่งเสนอถึง:</strong></label>
                                <?= Html::dropDownList('target', 'staff', [
                                    'staff' => 'หัวหน้ากลุ่มงาน / เจ้าหน้าที่',
                                    'deputy' => 'รองผู้อำนวยการ',
                                    'director' => 'ผู้อำนวยการ',
                                ], ['class' => 'form-select mb-2']) ?>
                                <div class="d-grid gap-2">
                                    <button type="submit" name="submit_after_save" value="1" class="btn btn-success">
                                        <i class="bi bi-send-check-fill me-1"></i> บันทึกตำแหน่งและเสนอต่อทันที
                                    </button>
                                    <button type="submit" name="submit_after_save" value="0" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-save me-1"></i> บันทึกตำแหน่งอย่างเดียว
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <?= Html::submitButton('<i class="bi bi-save me-1" aria-hidden="true"></i><span>บันทึกตำแหน่งและสร้าง PDF ใหม่</span>', ['class' => 'btn btn-primary w-100 mt-2']) ?>
                        <?php endif; ?>
                    <?= Html::endForm() ?>
                </section>
            <?php endif; ?>

            <!-- ดำเนินการ -->
            <section class="content-card action-card">
                <h2>ดำเนินการ</h2>
                <?php if ($canEdit): ?>
                    <?= Html::beginForm(['submit', 'id' => $model->id], 'post') ?>
                        <?= $csrf ?>
                        <div class="mb-3">
                            <label class="form-label">ส่งเสนอถึง</label>
                            <?= Html::dropDownList('target', 'staff', [
                                'staff' => 'หัวหน้ากลุ่มงาน / เจ้าหน้าที่',
                                'deputy' => 'รองผู้อำนวยการ',
                                'director' => 'ผู้อำนวยการ',
                            ], ['class' => 'form-select']) ?>
                        </div>
                        <?= Html::submitButton('<i class="bi bi-send me-1" aria-hidden="true"></i><span>ส่งเสนอพิจารณา</span>', ['class' => 'btn btn-primary w-100']) ?>
                    <?= Html::endForm() ?>
                <?php elseif ($model->status === Memo::STATUS_SUBMITTED_TO_STAFF): ?>
                    <?php if ($canReviewAsStaff): ?>
                        <?= Html::beginForm(['staff-review', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                            <?= $csrf ?>
                            <label class="form-label">ความเห็นหัวหน้ากลุ่มงาน</label>
                            <?= Html::textarea('comment', '', ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'ระบุความเห็น...']) ?>
                            <?= Html::dropDownList('decision', 'submit_deputy', ['submit_deputy' => 'เสนอรอง ผอ.', 'submit_director' => 'เสนอ ผอ. โดยตรง', 'return' => 'ส่งกลับแก้ไข'], ['class' => 'form-select mt-2']) ?>
                            <?= Html::submitButton('<i class="bi bi-send-check me-1" aria-hidden="true"></i><span>บันทึกผลพิจารณา</span>', ['class' => 'btn btn-primary w-100 mt-3']) ?>
                        <?= Html::endForm() ?>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-hourglass-split me-1"></i> อยู่ระหว่างรอเจ้าหน้าที่/หัวหน้ากลุ่มงานตรวจทาน
                        </div>
                    <?php endif; ?>

                <?php elseif ($model->status === Memo::STATUS_SUBMITTED_TO_DEPUTY): ?>
                    <?php if ($canReviewAsDeputy): ?>
                        <?= Html::beginForm(['deputy-review', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                            <?= $csrf ?>
                            <label class="form-label">ความเห็นรอง ผอ.</label>
                            <?= Html::textarea('comment', '', ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'ระบุความเห็น...']) ?>
                            <?= Html::dropDownList('decision', 'submit_director', ['submit_director' => 'เสนอ ผอ.', 'return' => 'ส่งกลับแก้ไข'], ['class' => 'form-select mt-2']) ?>
                            <?= Html::submitButton('<i class="bi bi-send-check me-1" aria-hidden="true"></i><span>บันทึกผลพิจารณา</span>', ['class' => 'btn btn-primary w-100 mt-3']) ?>
                        <?= Html::endForm() ?>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-hourglass-split me-1"></i> อยู่ระหว่างรอรองผู้อำนวยการพิจารณา
                        </div>
                    <?php endif; ?>

                <?php elseif ($model->status === Memo::STATUS_SUBMITTED_TO_DIRECTOR): ?>
                    <?php if ($canReviewAsDirector): ?>
                        <?= Html::beginForm(['director-review', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                            <?= $csrf ?>
                            <label class="form-label">คำสั่ง/ความเห็น ผอ.</label>
                            <?= Html::textarea('comment', '', ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'ระบุคำสั่ง/ความเห็น...']) ?>
                            <?= Html::dropDownList('decision', 'approve', ['approve' => 'อนุมัติ/เห็นชอบ', 'return' => 'ส่งกลับแก้ไข'], ['class' => 'form-select mt-2']) ?>
                            <?= Html::submitButton('<i class="bi bi-check2-circle me-1" aria-hidden="true"></i><span>บันทึกผลพิจารณา</span>', ['class' => 'btn btn-success w-100 mt-3']) ?>
                        <?= Html::endForm() ?>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-hourglass-split me-1"></i> อยู่ระหว่างรอผู้อำนวยการอนุมัติ/สั่งการ
                        </div>
                    <?php endif; ?>

                <?php elseif ($model->status === Memo::STATUS_APPROVED): ?>
                    <?php if ($canComplete): ?>
                        <?= Html::beginForm(['complete', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                            <?= $csrf ?>
                            <div class="alert alert-success py-2 mb-2">
                                <i class="bi bi-check-circle-fill me-1"></i> ผู้อำนวยการอนุมัติเรียบร้อยแล้ว
                            </div>
                            <label class="form-label">หมายเหตุปิดเรื่อง</label>
                            <?= Html::textarea('comment', '', ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'ระบุหมายเหตุ...']) ?>
                            <?= Html::submitButton('<i class="bi bi-check-circle me-1" aria-hidden="true"></i><span>รับทราบและปิดเรื่อง</span>', ['class' => 'btn btn-success w-100 mt-3']) ?>
                        <?= Html::endForm() ?>
                    <?php else: ?>
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle-fill me-1"></i> อนุมัติ/เห็นชอบเรียบร้อยแล้ว (รอเจ้าของเรื่องปิดงาน)
                        </div>
                    <?php endif; ?>

                <?php elseif ($model->status === Memo::STATUS_COMPLETED): ?>
                    <div class="alert alert-secondary mb-0">
                        <i class="bi bi-check2-all me-1"></i> เสร็จสิ้นกระบวนการเรียบร้อยแล้ว
                    </div>

                <?php elseif ($model->status === Memo::STATUS_REJECTED): ?>
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-x-circle-fill me-1"></i> บันทึกข้อความนี้ไม่ได้รับการอนุมัติ
                    </div>
                <?php endif; ?>
                <p class="muted-text mt-3 mb-0">เมื่อบันทึกผลพิจารณาแล้ว PDF ด้านซ้ายจะสร้างใหม่พร้อมความเห็นและลายเซ็นล่าสุด</p>
            </section>

            <!-- ประวัติการดำเนินการ -->
            <section class="content-card">
                <h2>ประวัติการดำเนินการ</h2>
                <ol class="timeline">
                    <?php foreach ($model->actions as $action): ?>
                        <li class="<?= $action === $latestAction ? 'current' : '' ?>">
                            <span class="timeline-dot" aria-hidden="true"></span>
                            <div class="timeline-body">
                                <time><?= Html::encode($thaiDateTime($action->created_at)) ?></time>
                                <p><?= Html::encode($action->comment ?: $action->action) ?></p>
                                <span><?= Html::encode($action->actor ? $action->actor->displayName() : ($action->actor->username ?? 'ระบบ')) ?></span>
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
        const width = parseFloat(box.dataset.widthMm || '50');
        const height = parseFloat(box.dataset.heightMm || '23');

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
                if (currentPageNum !== 1) {
                    goToPage(1);
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

            // ป้ายลายเซ็นแสดงหน้าแรก
            if (stampLayer) {
                const isFirstPage = (num === 1);
                stampLayer.style.display = isFirstPage ? 'block' : 'none';
                if (isFirstPage) {
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
