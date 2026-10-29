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

$this->title = 'บันทึกเสนอ: ' . $model->subject;
$this->params['breadcrumbs'][] = ['label' => 'บันทึกเสนอ', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'รายละเอียด';
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$identity = Yii::$app->user->identity;
$statusLabel = Memo::statusOptions()[$model->status] ?? $model->status;
$statusClass = 'status-badge-' . str_replace('_', '-', $model->status);
$latestAction = $model->actions !== [] ? $model->actions[count($model->actions) - 1] : null;
$pdfVersion = (string) Yii::$app->request->get('pdfv', (string) ($model->updated_at ?: time()));
$memoPdfUrl = Url::to(['pdf', 'id' => $model->id, 'v' => $pdfVersion]);
$previewImageUrl = $previewImagePath ? Url::to('@web/' . ltrim($previewImagePath, '/'), true) : null;
$csrf = Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken);
$canEdit = $identity instanceof User
    && ((int) $model->created_by === (int) $identity->id || $identity->hasRole(User::ROLE_ADMIN))
    && in_array($model->status, [Memo::STATUS_DRAFT, Memo::STATUS_RETURNED], true);
$canDebugRestamp = false;
$showStampPositionTools = true;
$canAdjustStampPositions = $showStampPositionTools && $identity instanceof User
    && ((int) $model->created_by === (int) $identity->id || $identity->hasRole(User::ROLE_ADMIN))
    && !in_array($model->status, [Memo::STATUS_APPROVED, Memo::STATUS_COMPLETED], true);
$useBrowserPdfPreview = $previewImageUrl === null && $canAdjustStampPositions;
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
            <h1><?= Html::encode($model->subject) ?></h1>
            <p><span class="status-badge <?= Html::encode($statusClass) ?>"><?= Html::encode($statusLabel) ?></span></p>
        </div>
        <div class="pdf-toolbar-actions">
            <?= Html::a('<i class="bi bi-arrow-left" aria-hidden="true"></i><span>กลับรายการ</span>', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
            <?= Html::a('<i class="bi bi-file-earmark-pdf" aria-hidden="true"></i><span>เปิด PDF บันทึกข้อความ</span>', $memoPdfUrl, ['class' => 'btn btn-success stamped-pdf-button', 'target' => '_blank']) ?>
            <?php if ($canEdit): ?>
                <?= Html::a('<i class="bi bi-pencil-square" aria-hidden="true"></i><span>แก้ไข</span>', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-primary']) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="document-workspace">
        <section class="content-card">
            <div class="pdf-toolbar">
                <div>
                    <strong>PDF บันทึกข้อความ</strong>
                <span><?= $canAdjustStampPositions ? 'ปรับกรอบตำแหน่งจาก slider ด้านขวา แล้วบันทึกเพื่อสร้าง PDF ใหม่' : 'ระบบสร้าง PDF ล่าสุดพร้อมความเห็นและลายเซ็นตามขั้นตอนที่ดำเนินการแล้ว' ?></span>
                </div>
                <div class="pdf-toolbar-actions">
                    <?= Html::a('<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span>เปิดในแท็บใหม่</span>', $memoPdfUrl, ['class' => 'btn btn-outline-primary', 'target' => '_blank']) ?>
                    <?php if ($canDebugRestamp): ?>
                        <?= Html::beginForm(['debug-restamp', 'id' => $model->id], 'post', ['class' => 'inline-form']) ?>
                            <?= $csrf ?>
                            <?= Html::submitButton('<i class="bi bi-arrow-repeat" aria-hidden="true"></i><span>Debug re-stamp</span>', [
                                'class' => 'btn btn-warning debug-restamp-button',
                                'title' => 'สร้าง PDF บันทึกข้อความใหม่เพื่อเช็คตำแหน่งลายเซ็นและการปั๊มทั้งหน้า',
                            ]) ?>
                        <?= Html::endForm() ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pdf-preview-frame memo-pdf-frame">
                <div class="pdf-preview-status">
                    <span class="preview-badge stamped">PDF บันทึกข้อความ</span>
                    <span class="muted-text">แสดงเอกสารฉบับเดียวกับปุ่มเปิด PDF</span>
                </div>
                <?php if ($previewImageUrl): ?>
                    <div class="pdf-preview-canvas" data-page-width="216" data-page-height="297">
                        <img
                            class="pdf-page-image"
                            src="<?= Html::encode($previewImageUrl) ?>?v=<?= Html::encode($pdfVersion) ?>"
                            alt="<?= Html::encode('PDF บันทึกข้อความ ' . $model->subject) ?>"
                        >
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
                <?php elseif ($useBrowserPdfPreview): ?>
                    <div class="fallback-warning pdfjs-preview-status">
                        shared host ไม่สามารถสร้างภาพ preview จาก server ได้ ระบบจึง render หน้าแรกด้วย browser เพื่อปรับตำแหน่งลายเซ็น
                    </div>
                    <div class="pdf-preview-canvas pdfjs-preview-canvas" data-page-width="216" data-page-height="297" data-pdf-url="<?= Html::encode($memoPdfUrl) ?>">
                        <canvas class="pdf-page-image pdfjs-page-canvas" aria-label="<?= Html::encode('PDF บันทึกข้อความ ' . $model->subject) ?>"></canvas>
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
                    </div>
                <?php else: ?>
                    <div class="pdf-preview-fallback">
                        <iframe
                            class="pdf-preview"
                            src="<?= Html::encode($memoPdfUrl) ?>#toolbar=1&navpanes=0&view=FitH"
                            title="<?= Html::encode('PDF บันทึกข้อความ ' . $model->subject) ?>"
                        ></iframe>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <aside class="side-stack">
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
                        <?= Html::submitButton('<i class="bi bi-save" aria-hidden="true"></i><span>บันทึกตำแหน่งและสร้าง PDF ใหม่</span>', ['class' => 'btn btn-primary w-100 mt-2']) ?>
                    <?= Html::endForm() ?>
                </section>
            <?php endif; ?>

            <section class="content-card">
                <h2>ข้อมูลบันทึก</h2>
                <dl class="meta-list">
                    <div><dt>สถานะ</dt><dd><span class="status-badge <?= Html::encode($statusClass) ?>"><?= Html::encode($statusLabel) ?></span></dd></div>
                    <div><dt>เลขที่บันทึก</dt><dd><?= Html::encode($model->memo_no ?: '-') ?></dd></div>
                    <div><dt>วันที่</dt><dd><?= Html::encode($model->memo_date ?: '-') ?></dd></div>
                    <div><dt>กลุ่มงาน</dt><dd><?= Html::encode($model->department->name ?? '-') ?></dd></div>
                    <div><dt>เรียน</dt><dd><?= Html::encode($model->to_text ?: '-') ?></dd></div>
                    <div><dt>ผู้จัดทำ</dt><dd><?= Html::encode($model->creator->username ?? '-') ?></dd></div>
                    <div><dt>ผู้พิจารณาปัจจุบัน</dt><dd><?= Html::encode($model->currentActor->username ?? '-') ?></dd></div>
                </dl>
            </section>

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

            <section class="content-card action-card">
                <h2>ดำเนินการ</h2>
                <?php if ($canEdit): ?>
                    <?= Html::beginForm(['submit', 'id' => $model->id], 'post') ?>
                        <?= Html::submitButton('<i class="bi bi-send" aria-hidden="true"></i><span>เสนอหัวหน้ากลุ่มงาน</span>', ['class' => 'btn btn-primary w-100']) ?>
                    <?= Html::endForm() ?>
                <?php endif; ?>

                <?php if ($identity instanceof User && $identity->role === User::ROLE_STAFF && $model->status === Memo::STATUS_SUBMITTED_TO_STAFF): ?>
                    <?= Html::beginForm(['staff-review', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                        <label class="form-label">ความเห็นหัวหน้ากลุ่มงาน</label>
                        <?= Html::textarea('comment', '', ['class' => 'form-control', 'rows' => 4]) ?>
                        <?= Html::dropDownList('decision', 'submit_deputy', ['submit_deputy' => 'เสนอรอง ผอ.', 'submit_director' => 'เสนอ ผอ. โดยตรง', 'return' => 'ส่งกลับแก้ไข'], ['class' => 'form-select mt-2']) ?>
                        <?= Html::submitButton('<i class="bi bi-send-check" aria-hidden="true"></i><span>บันทึกผลพิจารณา</span>', ['class' => 'btn btn-primary w-100 mt-3']) ?>
                    <?= Html::endForm() ?>
                <?php endif; ?>

                <?php if ($identity instanceof User && $identity->role === User::ROLE_DEPUTY_DIRECTOR && $model->status === Memo::STATUS_SUBMITTED_TO_DEPUTY): ?>
                    <?= Html::beginForm(['deputy-review', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                        <label class="form-label">ความเห็นรอง ผอ.</label>
                        <?= Html::textarea('comment', '', ['class' => 'form-control', 'rows' => 4]) ?>
                        <?= Html::dropDownList('decision', 'submit_director', ['submit_director' => 'เสนอ ผอ.', 'return' => 'ส่งกลับแก้ไข'], ['class' => 'form-select mt-2']) ?>
                        <?= Html::submitButton('<i class="bi bi-send-check" aria-hidden="true"></i><span>บันทึกผลพิจารณา</span>', ['class' => 'btn btn-primary w-100 mt-3']) ?>
                    <?= Html::endForm() ?>
                <?php endif; ?>

                <?php if ($identity instanceof User && $identity->role === User::ROLE_DIRECTOR && $model->status === Memo::STATUS_SUBMITTED_TO_DIRECTOR): ?>
                    <?= Html::beginForm(['director-review', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                        <label class="form-label">คำสั่ง/ความเห็น ผอ.</label>
                        <?= Html::textarea('comment', '', ['class' => 'form-control', 'rows' => 4]) ?>
                        <?= Html::dropDownList('decision', 'approve', ['approve' => 'อนุมัติ/เห็นชอบ', 'return' => 'ส่งกลับแก้ไข'], ['class' => 'form-select mt-2']) ?>
                        <?= Html::submitButton('<i class="bi bi-check2-circle" aria-hidden="true"></i><span>บันทึกผลพิจารณา</span>', ['class' => 'btn btn-success w-100 mt-3']) ?>
                    <?= Html::endForm() ?>
                <?php endif; ?>

                <?php if ($identity instanceof User && (int) $model->created_by === (int) $identity->id && $model->status === Memo::STATUS_APPROVED): ?>
                    <?= Html::beginForm(['complete', 'id' => $model->id], 'post', ['class' => 'action-form']) ?>
                        <label class="form-label">หมายเหตุปิดเรื่อง</label>
                        <?= Html::textarea('comment', '', ['class' => 'form-control', 'rows' => 3]) ?>
                        <?= Html::submitButton('<i class="bi bi-check-circle" aria-hidden="true"></i><span>รับทราบและปิดเรื่อง</span>', ['class' => 'btn btn-success w-100 mt-3']) ?>
                    <?= Html::endForm() ?>
                <?php endif; ?>
                <p class="muted-text mt-3">เมื่อบันทึกผลพิจารณาแล้ว PDF ด้านซ้ายจะสร้างใหม่พร้อมความเห็นและลายเซ็นล่าสุด</p>
            </section>

            <section class="content-card">
                <h2>ประวัติการดำเนินการ</h2>
                <ol class="timeline">
                    <?php foreach ($model->actions as $action): ?>
                        <li class="<?= $action === $latestAction ? 'current' : '' ?>">
                            <span class="timeline-dot" aria-hidden="true"></span>
                            <div class="timeline-body">
                                <time><?= Html::encode($thaiDateTime($action->created_at)) ?></time>
                                <p><?= Html::encode($action->comment ?: $action->action) ?></p>
                                <span><?= Html::encode($action->actor->username ?? 'ระบบ') ?></span>
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
                status.textContent = 'แสดง preview หน้าแรกด้วย browser สำหรับปรับตำแหน่งลายเซ็น';
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
