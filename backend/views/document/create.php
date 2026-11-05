<?php

declare(strict_types=1);

use common\models\Department;
use common\models\Document;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var backend\models\DocumentForm $form */
/** @var Department[] $departments */
/** @var bool $isActingDirector */
/** @var string $effectiveDirectorName */
/** @var string $effectiveDirectorPosition */
/** @var array<int, string|null> $deputyByDept */
/** @var string[] $allDeputyNames */

$this->title = 'ลงทะเบียนหนังสือรับ';
$this->params['breadcrumbs'][] = ['label' => 'หนังสือรับ', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);

$departmentItems = ArrayHelper::map($departments, 'id', 'name');
$allUserItems = ArrayHelper::map($allUsers ?? [], 'id', static fn (\common\models\User $u): string => $u->displayName() . ' (' . $u->roleLabel() . ')');
$thaiDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return '-';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
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

    return (int) date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . ((int) date('Y', $timestamp) + 543);
};
$thaiTime = static function (?string $time): string {
    if ($time === null || $time === '') {
        return '-';
    }

    $timestamp = strtotime($time);
    if ($timestamp === false) {
        return $time;
    }

    return date('H.i', $timestamp) . ' น.';
};
?>

<div class="eoffice-page">
    <div class="eoffice-titlebar">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <p>อัปโหลด PDF จาก SMSS แล้วสร้างข้อมูลตรารับอิเล็กทรอนิกส์</p>
        </div>
    </div>

    <?php $activeForm = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>
    <?= $activeForm->errorSummary([$form, $form->document], ['class' => 'alert alert-danger mb-3', 'header' => '<h5 class="alert-heading mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> พบข้อผิดพลาดในการลงทะเบียนเอกสาร</h5>']) ?>
    <div class="form-grid">
        <div class="content-card">
            <div class="row">
                <div class="col-md-6">
                    <?= $activeForm->field($form->document, 'receive_no')->textInput(['maxlength' => true])->hint('ระบบรันเลขรับให้อัตโนมัติ แต่สามารถแก้ไขได้') ?>
                </div>
                <div class="col-md-3">
                    <?= $activeForm->field($form->document, 'receive_date')->input('date') ?>
                </div>
                <div class="col-md-3">
                    <?= $activeForm->field($form->document, 'receive_time')->input('time') ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <?= $activeForm->field($form->document, 'external_no')->textInput(['maxlength' => true]) ?>
                </div>
                <div class="col-md-6">
                    <?= $activeForm->field($form->document, 'external_date')->input('date') ?>
                </div>
            </div>

            <?= $activeForm->field($form->document, 'sender')->textInput(['maxlength' => true]) ?>
            <?= $activeForm->field($form->document, 'subject')->textarea(['rows' => 3]) ?>
            <?= $activeForm->field($form->document, 'summary')->textarea([
                'rows' => 3,
                'style' => 'text-indent: 0;',
                'placeholder' => 'หากเว้นว่างไว้ ระบบจะยกเอาชื่อเรื่องหนังสือมาเป็นข้อมูลตั้งต้นให้อัตโนมัติ',
            ])->hint('สรุปเนื้อหาในหนังสือโดยย่อ (หากเว้นว่างไว้ ระบบจะยกชื่อเรื่องมาเป็นข้อมูลสรุปตั้งต้น และสามารถแก้ไขได้ในภายหลัง)') ?>

            <div class="row">
                <div class="col-md-6">
                    <?= $activeForm->field($form->document, 'department_id')
                        ->dropDownList($departmentItems, ['prompt' => 'เลือกกลุ่มงาน (กำหนดสายบังคับบัญชา)'])
                        ->hint('สายการเสนอเอกสารจะวิ่งไปหารอง ผอ. ประจำกลุ่มงานนี้โดยอัตโนมัติ') ?>
                </div>
                <div class="col-md-6">
                    <?php $systemApprover = \common\models\SystemSetting::getDocumentApprover(); ?>
                    <div class="mb-3">
                        <label class="form-label text-muted">ผู้ตรวจสอบ/อนุมัติ (ก่อนเสนอรอง ผอ.)</label>
                        <?php if ($systemApprover !== null): ?>
                            <div class="p-2 border rounded bg-light d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-shield-check text-primary fs-5"></i>
                                    <div>
                                        <strong class="text-dark"><?= Html::encode($systemApprover->displayName()) ?></strong>
                                        <span class="badge bg-secondary ms-1"><?= Html::encode($systemApprover->roleLabel()) ?></span>
                                    </div>
                                </div>
                                <span class="badge bg-info-subtle text-info border border-info-subtle">กำหนดโดย Admin</span>
                            </div>
                            <div class="form-text text-muted">หนังสือจะถูกส่งให้ผู้ตรวจท่านนี้พิจารณาก่อนเสนอต่อรอง ผอ. โดยอัตโนมัติ</div>
                        <?php else: ?>
                            <div class="p-2 border border-warning rounded bg-warning-subtle text-dark small">
                                <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                                <strong>ยังไม่ได้กำหนดผู้ตรวจในระบบ</strong> กรุณาแจ้ง Admin ให้ตั้งค่าในหน้าผู้ใช้และบทบาท
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <?= $activeForm->field($form->document, 'urgency')->dropDownList(Document::urgencyOptions()) ?>
                </div>
                <div class="col-md-6">
                    <?= $activeForm->field($form->document, 'due_date')->input('date') ?>
                </div>
            </div>

            <?= $activeForm->field($form->document, 'note')->textarea(['rows' => 3]) ?>

            <section class="upload-section">
                <div class="upload-section-header">
                    <h2>ไฟล์เอกสาร</h2>
                    <span>1 ช่องต่อ 1 ไฟล์</span>
                </div>

                <?= $activeForm->field($form, 'pdfFile')->fileInput(['accept' => 'application/pdf'])->label('ไฟล์ PDF หนังสือต้นฉบับจาก SMSS') ?>

                <div class="upload-slot-grid">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                        <div class="upload-slot">
                            <label class="form-label" for="documentform-attachmentfiles-<?= $i ?>">ไฟล์แนบเพิ่มเติม <?= $i + 1 ?></label>
                            <?= Html::fileInput(
                                Html::getInputName($form, 'attachmentFiles') . '[]',
                                null,
                                [
                                    'id' => 'documentform-attachmentfiles-' . $i,
                                    'class' => 'form-control',
                                    'accept' => 'application/pdf',
                                ],
                            ) ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <?= Html::error($form, 'attachmentFiles', ['class' => 'invalid-feedback d-block']) ?>
            </section>

            <div class="form-actions">
                <?= Html::a('ยกเลิก', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
                <?= Html::submitButton('<i class="bi bi-cloud-arrow-up-fill me-1"></i> <span>บันทึกและดูตราประทับ</span>', [
                    'class' => 'btn btn-primary',
                    'id' => 'btn-submit-doc',
                ]) ?>
            </div>
        </div>

        <aside class="stamp-card">
            <h2 class="h6 fw-bold mb-2 text-dark"><i class="bi bi-stamp text-danger me-1"></i> ตัวอย่างตรารับ</h2>
            <div class="receive-stamp mb-3">
                <strong>โรงเรียนกุดชุมวิทยาคม</strong>
                <span>รับที่ <em data-stamp-preview="receive-no"><?= Html::encode($form->document->receive_no ?: '-') ?></em></span>
                <span>วันที่ <em data-stamp-preview="receive-date"><?= Html::encode($thaiDate($form->document->receive_date)) ?></em></span>
                <span>เวลา <em data-stamp-preview="receive-time"><?= Html::encode($thaiTime($form->document->receive_time)) ?></em></span>
            </div>

            <!-- กล่อง Preview ผู้ลงนามตามสายงาน (Signers Preview) -->
            <div class="p-3 bg-white border rounded-3 shadow-sm mb-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-bold small text-dark d-flex align-items-center gap-1">
                        <i class="bi bi-diagram-3-fill text-primary"></i>
                        <span>สายบังคับบัญชาและผู้ลงนาม</span>
                    </span>
                    <span class="badge bg-light text-muted border fw-normal" style="font-size: 0.65rem;">Flow เอกสาร</span>
                </div>

                <!-- 1. ผู้ลงรับหนังสือ (สารบรรณ) -->
                <div class="p-2 bg-light border rounded-2 mb-2">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="fw-semibold text-secondary d-flex align-items-center gap-1" style="font-size: 0.8rem;">
                            <i class="bi bi-1-circle-fill text-secondary"></i> สารบรรณ / ผู้ลงรับ
                        </span>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle" style="font-size: 0.65rem;">ลงรับระบบ</span>
                    </div>
                    <div class="fw-bold text-dark small">
                        <?= Html::encode(Yii::$app->user->identity->name ?? 'เจ้าหน้าที่สารบรรณ') ?>
                    </div>
                    <div class="text-muted" style="font-size: 0.72rem;">
                        เจ้าหน้าที่ผู้ลงทะเบียนรับหนังสือ
                    </div>
                </div>

                <!-- 2. รองผู้อำนวยการ -->
                <div class="p-2 bg-light border rounded-2 mb-2">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="fw-semibold text-primary d-flex align-items-center gap-1" style="font-size: 0.8rem;">
                            <i class="bi bi-2-circle-fill text-primary"></i> รองผู้อำนวยการ
                        </span>
                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size: 0.65rem;">ลงความเห็น</span>
                    </div>
                    <div class="fw-bold text-dark small" id="preview-deputy-name">
                        <?= Html::encode($allDeputyNames !== [] ? implode(', ', $allDeputyNames) : '(ยังไม่มีผู้ใช้บทบาท รอง ผอ. ในระบบ)') ?>
                    </div>
                    <div class="text-muted" style="font-size: 0.72rem;" id="preview-deputy-dept">
                        (เลือกกลุ่มงานด้านซ้ายเพื่อระบุ รอง ผอ.)
                    </div>
                </div>

                <!-- 3. ผู้อำนวยการ หรือ ผู้รักษาการแทน -->
                <div class="p-2 rounded-2 <?= $isActingDirector ? 'bg-warning-subtle border border-warning' : 'bg-light border' ?>">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="fw-semibold <?= $isActingDirector ? 'text-warning-emphasis' : 'text-primary' ?> d-flex align-items-center gap-1" style="font-size: 0.8rem;">
                            <i class="bi bi-3-circle-fill <?= $isActingDirector ? 'text-warning-emphasis' : 'text-primary' ?>"></i> <?= $isActingDirector ? 'ผู้รักษาการแทน ผอ.' : 'ผู้อำนวยการโรงเรียน' ?>
                        </span>
                        <span class="badge <?= $isActingDirector ? 'bg-warning text-dark' : 'bg-success-subtle text-success border border-success-subtle' ?>" style="font-size: 0.65rem;">
                            <?= $isActingDirector ? 'รักษาการแทน' : 'สั่งการขั้นสุดท้าย' ?>
                        </span>
                    </div>
                    <div class="fw-bold text-dark small">
                        <?= Html::encode($effectiveDirectorName ?: '(ยังไม่ได้ระบุชื่อในการตั้งค่าระบบ)') ?>
                    </div>
                    <div class="text-muted" style="font-size: 0.72rem;">
                        <?= Html::encode($effectiveDirectorPosition) ?>
                    </div>
                    <?php if ($isActingDirector): ?>
                        <div class="mt-1 pt-1 border-top border-warning-subtle text-warning-emphasis fw-semibold" style="font-size: 0.7rem;">
                            <i class="bi bi-exclamation-circle-fill me-1"></i> ปัจจุบันใช้ชื่อรักษาการแทน ผอ.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-2 pt-2 border-top text-center">
                    <?php if (Yii::$app->user->identity instanceof \common\models\User && Yii::$app->user->identity->hasRole(\common\models\User::ROLE_ADMIN)): ?>
                        <?= Html::a('<i class="bi bi-gear-fill me-1"></i> ตรวจสอบการตั้งค่าระบบ', ['/setting/index'], [
                            'class' => 'btn btn-sm btn-outline-secondary py-0 px-2',
                            'style' => 'font-size: 0.72rem;',
                            'target' => '_blank',
                        ]) ?>
                    <?php else: ?>
                        <span class="text-muted" style="font-size: 0.7rem;">
                            <i class="bi bi-shield-lock me-1"></i> ข้อมูลผู้ลงนามอ้างอิงจากการตั้งค่าระบบ
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <p class="small text-muted mb-0">ระบบจะเก็บ PDF ต้นฉบับไว้ และสร้างข้อมูลตราประทับสำหรับนำไปวางบน PDF ในขั้นตอนถัดไป</p>
        </aside>
    </div>
    <?php ActiveForm::end(); ?>

    <script type="application/json" id="deputy-dept-json"><?= json_encode($deputyByDept, JSON_UNESCAPED_UNICODE) ?></script>
    <script type="application/json" id="default-deputy-json"><?= json_encode($allDeputyNames !== [] ? implode(', ', $allDeputyNames) : '(ยังไม่มีผู้ใช้บทบาท รอง ผอ. ในระบบ)', JSON_UNESCAPED_UNICODE) ?></script>
</div>

<?php
$this->registerJs(<<<'JS'
(function () {
    const receiveNoInput = document.getElementById('document-receive_no');
    const receiveDateInput = document.getElementById('document-receive_date');
    const receiveTimeInput = document.getElementById('document-receive_time');
    const receiveNoOutput = document.querySelector('[data-stamp-preview="receive-no"]');
    const receiveDateOutput = document.querySelector('[data-stamp-preview="receive-date"]');
    const receiveTimeOutput = document.querySelector('[data-stamp-preview="receive-time"]');
    const departmentInput = document.getElementById('document-department_id');
    const deputyNameOutput = document.getElementById('preview-deputy-name');
    const deputyDeptOutput = document.getElementById('preview-deputy-dept');
    const thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    let deputyByDeptMap = {};
    let defaultDeputyText = '(ยังไม่มีผู้ใช้บทบาท รอง ผอ. ในระบบ)';
    try {
        const jsonEl = document.getElementById('deputy-dept-json');
        if (jsonEl && jsonEl.textContent) {
            deputyByDeptMap = JSON.parse(jsonEl.textContent);
        }
        const defaultEl = document.getElementById('default-deputy-json');
        if (defaultEl && defaultEl.textContent) {
            defaultDeputyText = JSON.parse(defaultEl.textContent);
        }
    } catch (e) {
        console.error('Error parsing deputy JSON', e);
    }

    function formatThaiDate(value) {
        if (!value) {
            return '-';
        }

        const parts = value.split('-').map((part) => parseInt(part, 10));
        if (parts.length !== 3 || parts.some((part) => Number.isNaN(part))) {
            return value;
        }

        return `${parts[2]} ${thaiMonths[parts[1] - 1] || ''} ${parts[0] + 543}`;
    }

    function formatThaiTime(value) {
        if (!value) {
            return '-';
        }

        const parts = value.split(':');
        if (parts.length < 2) {
            return value;
        }

        return `${parts[0]}.${parts[1]} น.`;
    }

    function refreshStampPreview() {
        if (receiveNoOutput) {
            receiveNoOutput.textContent = receiveNoInput && receiveNoInput.value.trim() !== '' ? receiveNoInput.value.trim() : '-';
        }
        if (receiveDateOutput) {
            receiveDateOutput.textContent = formatThaiDate(receiveDateInput ? receiveDateInput.value : '');
        }
        if (receiveTimeOutput) {
            receiveTimeOutput.textContent = formatThaiTime(receiveTimeInput ? receiveTimeInput.value : '');
        }
    }

    function refreshDeputyPreview() {
        if (!deputyNameOutput) return;
        const deptId = departmentInput ? departmentInput.value : '';
        if (deptId && deputyByDeptMap[deptId]) {
            deputyNameOutput.textContent = deputyByDeptMap[deptId];
            if (deputyDeptOutput && departmentInput.selectedIndex > 0) {
                deputyDeptOutput.textContent = 'ประจำกลุ่มงาน' + departmentInput.options[departmentInput.selectedIndex].text;
            }
        } else if (deptId) {
            deputyNameOutput.textContent = defaultDeputyText;
            if (deputyDeptOutput && departmentInput.selectedIndex > 0) {
                deputyDeptOutput.textContent = 'กำกับดูแลกลุ่มงาน' + departmentInput.options[departmentInput.selectedIndex].text;
            }
        } else {
            deputyNameOutput.textContent = defaultDeputyText;
            if (deputyDeptOutput) {
                deputyDeptOutput.textContent = '(เลือกกลุ่มงานด้านซ้ายเพื่อระบุ รอง ผอ.)';
            }
        }
    }

    [receiveNoInput, receiveDateInput, receiveTimeInput].forEach((input) => {
        if (input) {
            input.addEventListener('input', refreshStampPreview);
            input.addEventListener('change', refreshStampPreview);
        }
    });

    if (departmentInput) {
        departmentInput.addEventListener('change', refreshDeputyPreview);
    }

    const formEl = document.getElementById('w0');
    const submitBtn = document.getElementById('btn-submit-doc');
    if (formEl && submitBtn) {
        formEl.addEventListener('submit', function () {
            setTimeout(function () {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> กำลังอัปโหลดและประมวลผล PDF...';
            }, 10);
        });
    }

    refreshStampPreview();
    refreshDeputyPreview();
})();
JS);
?>
