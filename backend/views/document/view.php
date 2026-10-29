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

$urgency = Document::urgencyOptions()[$model->urgency] ?? $model->urgency;
$status = Document::statusOptions()[$model->status] ?? $model->status;
$statusClass = 'status-badge-' . str_replace('_', '-', $model->status);
$pdfPath = $model->originalFile?->storage_path;
$pdfUrl = $pdfPath ? Url::to('@web/' . ltrim($pdfPath, '/'), true) : null;
$stampedPdfPath = $model->stampedFile?->storage_path;
$stampedPdfUrl = $stampedPdfPath ? Url::to('@web/' . ltrim($stampedPdfPath, '/'), true) : null;
$previewPdfUrl = $stampedPdfUrl ?: $pdfUrl;
$previewLabel = $stampedPdfUrl ? 'PDF ฉบับประทับตรา' : 'PDF ต้นฉบับ';
$previewImageUrl = $previewImagePath ? Url::to('@web/' . ltrim($previewImagePath, '/'), true) : null;
$identity = Yii::$app->user->identity;
$canRegisterDocument = $identity instanceof User && $identity->canRegisterDocument();
$canSubmitToDeputy = $identity instanceof User && $identity->canSubmitDocumentToDeputy();
$canGiveDeputyComment = $identity instanceof User && $identity->canGiveDeputyComment();
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

if ($useBrowserPdfPreview) {
    $this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs', [
        'type' => 'module',
        'position' => \yii\web\View::POS_HEAD,
    ]);
}
?>

<div class="eoffice-page">
    <div class="eoffice-titlebar">
        <div>
            <h1><?= Html::encode($model->receive_no) ?></h1>
            <p><?= Html::encode($model->subject) ?></p>
        </div>
        <?= Html::a('<i class="bi bi-arrow-left" aria-hidden="true"></i><span>กลับรายการหนังสือ</span>', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <div class="document-workspace">
        <section class="content-card">
            <div class="pdf-toolbar">
                <div>
                    <strong><?= Html::encode($previewLabel) ?></strong>
                    <span><?= $stampedPdfUrl ? 'กำลังแสดงเอกสารฉบับประทับตราแล้ว เหมือนอ่านบนกระดาษจริง' : 'ยังไม่มีฉบับประทับตรา ระบบจึงแสดง PDF ต้นฉบับก่อน' ?></span>
                </div>
                <div class="pdf-toolbar-actions">
                    <?php if ($pdfUrl): ?>
                        <?= Html::a('<i class="bi bi-file-earmark-pdf" aria-hidden="true"></i><span>เปิด PDF ต้นฉบับ</span>', $pdfUrl, ['class' => 'btn btn-outline-primary', 'target' => '_blank']) ?>
                    <?php endif; ?>
                    <?php if ($stampedPdfUrl): ?>
                        <?= Html::a('<i class="bi bi-file-earmark-check" aria-hidden="true"></i><span>เปิด PDF ตราประทับ</span>', $stampedPdfUrl, ['class' => 'btn btn-success stamped-pdf-button', 'target' => '_blank']) ?>
                    <?php endif; ?>
                    <?php if ($showDebugRestamp && $canRegisterDocument): ?>
                        <?= Html::beginForm(['generate-stamped-pdf', 'id' => $model->id], 'post', ['class' => 'inline-form']) ?>
                            <?= $csrf ?>
                            <?= Html::submitButton('<i class="bi bi-arrow-repeat" aria-hidden="true"></i><span>Debug re-stamp</span>', [
                                'class' => 'btn btn-warning debug-restamp-button',
                                'title' => 'สร้าง PDF ใหม่เพื่อจูนกรอบ debug/lายเซ็น/วันที่',
                            ]) ?>
                        <?= Html::endForm() ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pdf-preview-frame">
                <div class="pdf-preview-status">
                    <span class="<?= $stampedPdfUrl ? 'preview-badge stamped' : 'preview-badge original' ?>">
                        <?= Html::encode($previewLabel) ?>
                    </span>
                    <?php if (!$stampedPdfUrl): ?>
                        <span class="muted-text">ยังไม่พบ PDF ฉบับตราประทับ ระบบจะสร้างให้อัตโนมัติเมื่อมีการลงรับ/บันทึกตำแหน่ง</span>
                    <?php endif; ?>
                </div>

                <?php if ($previewImageUrl): ?>
                    <div class="pdf-preview-canvas" data-page-width="216" data-page-height="297">
                        <img
                            class="pdf-page-image"
                            src="<?= Html::encode($previewImageUrl) ?>?v=<?= Html::encode((string) time()) ?>"
                            alt="<?= Html::encode($previewLabel . ' ' . $model->receive_no) ?>"
                        >
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
                <?php elseif ($useBrowserPdfPreview): ?>
                    <div class="fallback-warning pdfjs-preview-status">
                        shared host ไม่สามารถสร้างภาพ preview จาก server ได้ ระบบจึง render หน้าแรกด้วย browser เพื่อปรับตำแหน่งตราประทับ
                    </div>
                    <div class="pdf-preview-canvas pdfjs-preview-canvas" data-page-width="216" data-page-height="297" data-pdf-url="<?= Html::encode($previewPdfUrl) ?>">
                        <canvas class="pdf-page-image pdfjs-page-canvas" aria-label="<?= Html::encode($previewLabel . ' ' . $model->receive_no) ?>"></canvas>
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
                    </div>
                <?php elseif ($previewPdfUrl): ?>
                    <div class="pdf-preview-fallback">
                        <iframe
                            class="pdf-preview"
                            src="<?= Html::encode($previewPdfUrl) ?>#toolbar=1&navpanes=0&view=FitH"
                            title="<?= Html::encode($previewLabel . ' ' . $model->receive_no) ?>"
                        ></iframe>
                    </div>
                <?php else: ?>
                    <div class="empty-preview">
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
                        <p class="muted-text">หน่วยเป็นมิลลิเมตรจากซ้าย/บนของหน้าแรก ปรับแล้วระบบจะสร้าง PDF ประทับตราใหม่ให้อัตโนมัติ</p>
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

                <?php elseif ($model->status === Document::STATUS_SUBMITTED_TO_DEPUTY && $canGiveDeputyComment): ?>
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
    const canvas = document.querySelector('.pdf-preview-canvas');
    if (!canvas) {
        return;
    }

    const pageWidth = parseFloat(canvas.dataset.pageWidth || '216');
    let pageHeight = parseFloat(canvas.dataset.pageHeight || '297');
    const pageImage = canvas.querySelector('.pdf-page-image');
    const pdfjsCanvas = canvas.querySelector('.pdfjs-page-canvas');

    function rounded(value) {
        return (Math.round(parseFloat(value || '0') * 10) / 10).toString();
    }

    function updateStamp(type) {
        const control = document.querySelector(`[data-stamp-control="${type}"]`);
        const box = document.querySelector(`[data-stamp-box="${type}"]`);
        if (!control || !box) {
            return;
        }

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
            input.addEventListener('input', () => updateStamp(type));
        });
        updateStamp(type);
    });

    if (pageImage && !pdfjsCanvas) {
        pageImage.addEventListener('load', () => {
            if (pageImage.naturalWidth > 0) {
                pageHeight = pageWidth * (pageImage.naturalHeight / pageImage.naturalWidth);
                document.querySelectorAll('[data-axis="y"]').forEach((input) => {
                    input.max = rounded(pageHeight);
                });
                document.querySelectorAll('[data-stamp-control]').forEach((control) => updateStamp(control.dataset.stampControl));
            }
        });
    }

    async function renderPdfPreview() {
        if (!pdfjsCanvas || !canvas.dataset.pdfUrl) {
            return;
        }

        const status = document.querySelector('.pdfjs-preview-status');
        try {
            const pdfjsLib = await import('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs');
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';

            const pdf = await pdfjsLib.getDocument(canvas.dataset.pdfUrl).promise;
            const page = await pdf.getPage(1);
            const baseViewport = page.getViewport({scale: 1});
            pageHeight = pageWidth * (baseViewport.height / baseViewport.width);

            const containerWidth = canvas.clientWidth || 780;
            const scale = containerWidth / baseViewport.width;
            const viewport = page.getViewport({scale});
            const context = pdfjsCanvas.getContext('2d');
            pdfjsCanvas.width = Math.floor(viewport.width);
            pdfjsCanvas.height = Math.floor(viewport.height);

            await page.render({canvasContext: context, viewport}).promise;
            pdfjsCanvas.style.width = '100%';
            pdfjsCanvas.style.height = 'auto';

            document.querySelectorAll('[data-axis="y"]').forEach((input) => {
                input.max = rounded(pageHeight);
            });
            document.querySelectorAll('[data-stamp-control]').forEach((control) => updateStamp(control.dataset.stampControl));

            if (status) {
                status.textContent = 'แสดง preview หน้าแรกด้วย browser สำหรับปรับตำแหน่งตราประทับ';
            }
        } catch (error) {
            if (status) {
                status.textContent = 'ไม่สามารถ render preview ด้วย browser ได้ กรุณาเปิด PDF เต็มฉบับเพื่อตรวจเอกสาร';
            }
            console.error(error);
        }
    }

    renderPdfPreview();
})();
JS);
?>
