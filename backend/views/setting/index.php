<?php

declare(strict_types=1);

use backend\models\SystemSettingForm;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var SystemSettingForm $model */
/** @var string $nextReceiveNoPreview */
/** @var array<int, string> $actingUserOptions */

$this->title = 'ตั้งค่าระบบ';
$this->params['breadcrumbs'][] = ['label' => 'จัดการระบบ', 'url' => ['/user/index']];
$this->params['breadcrumbs'][] = $this->title;

$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
?>

<div class="eoffice-page container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h1 class="h4 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-sliders text-primary"></i>
                <span>ตั้งค่าระบบ (System Settings)</span>
            </h1>
            <p class="text-muted small mb-0">กำหนดข้อมูลผู้บริหารสถานศึกษา และการออกเลขทะเบียนรับหนังสือของโรงเรียน</p>
        </div>
    </div>

    <?php $form = ActiveForm::begin([
        'id' => 'system-setting-form',
        'options' => ['class' => 'needs-validation'],
    ]); ?>

    <div class="row g-3">
        <!-- การ์ดที่ 1: ผู้บริหารสถานศึกษา (ผู้อำนวยการ / ผู้รักษาการแทน) -->
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary p-2 rounded-3">
                        <i class="bi bi-person-badge fs-6"></i>
                    </span>
                    <div>
                        <h2 class="h6 fw-bold mb-0 text-dark">1. ข้อมูลผู้บริหารสถานศึกษา</h2>
                        <small class="text-muted">ใช้สำหรับตราประทับคำสั่ง บันทึกข้อความ และการลงนามในระบบ</small>
                    </div>
                </div>

                <div class="card-body p-3 p-md-4">
                    <!-- ส่วน ผอ. โรงเรียน -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-semibold">
                            <i class="bi bi-award-fill"></i>
                            <span>ผู้อำนวยการโรงเรียน (ตัวจริง)</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-12 col-md-7">
                                <?= $form->field($model, 'director_name')->textInput([
                                    'class' => 'form-control',
                                    'placeholder' => 'เช่น นายสมหมาย มั่นคง',
                                ])->label('ชื่อ-นามสกุล ผู้อำนวยการ') ?>
                            </div>
                            <div class="col-12 col-md-5">
                                <?= $form->field($model, 'director_position')->textInput([
                                    'class' => 'form-control',
                                    'placeholder' => 'ผู้อำนวยการโรงเรียนกุดชุมวิทยาคม',
                                ])->label('ตำแหน่ง') ?>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วน สวิตช์รักษาการแทน -->
                    <div class="p-3 bg-warning-subtle border border-warning-subtle rounded-3 mb-3">
                        <div class="form-check form-switch mb-2">
                            <?= $form->field($model, 'is_acting_director', [
                                'template' => "{input}\n{label}\n{error}",
                                'options' => ['class' => 'mb-0'],
                            ])->checkbox([
                                'class' => 'form-check-input',
                                'role' => 'switch',
                                'id' => 'is-acting-director-switch',
                            ], false)->label('<strong>เปิดใช้งานชื่อรักษาการแทนผู้อำนวยการโรงเรียน</strong> <span class="badge bg-warning text-dark ms-1">เมื่อ ผอ. ไม่อยู่</span>') ?>
                        </div>
                        <p class="small text-muted mb-0 ps-4">
                            เมื่อเปิดใช้งาน ระบบจะใช้ชื่อและตำแหน่งผู้รักษาการแทนด้านล่างนี้บนหนังสือและเอกสารสั่งการทั้งหมดจนกว่าจะปิดสวิตช์
                        </p>

                        <hr class="my-3 opacity-25">

                        <div class="row g-2">
                            <div class="col-12 col-md-7">
                                <?= $form->field($model, 'acting_director_user_id')->dropDownList(
                                    $actingUserOptions,
                                    [
                                        'class' => 'form-select',
                                        'prompt' => '-- เลือกรองผู้อำนวยการ / ผู้ปฏิบัติหน้าที่รักษาการแทน --',
                                        'id' => 'acting-director-user-select',
                                    ]
                                )->label('ชื่อผู้รักษาการแทนผู้อำนวยการ <span class="badge bg-primary-subtle text-primary fw-normal ms-1">จากผู้ใช้ในระบบ</span>') ?>
                                <?= Html::activeHiddenInput($model, 'acting_director_name', ['id' => 'systemsettingform-acting_director_name']) ?>
                            </div>
                            <div class="col-12 col-md-5">
                                <?= $form->field($model, 'acting_director_position')->textInput([
                                    'class' => 'form-control',
                                    'placeholder' => 'รองผู้อำนวยการ รักษาการในตำแหน่งฯ',
                                ])->label('ตำแหน่งรักษาการ') ?>
                            </div>
                        </div>
                    </div>

                    <!-- Live Status Preview -->
                    <div class="alert alert-secondary d-flex align-items-center gap-3 mb-0 py-2 px-3 border-0">
                        <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                        <div class="small">
                            <strong>สถานะการลงนามปัจจุบัน:</strong>
                            <span id="acting-status-text" class="fw-semibold">
                                <?= $model->is_acting_director
                                    ? '<span class="text-warning-emphasis">กำลังปฏิบัติหน้าที่แทน โดย ' . Html::encode($model->acting_director_name ?: '(ยังไม่ได้ระบุชื่อรักษาการ)') . ' (' . Html::encode($model->acting_director_position) . ')</span>'
                                    : '<span class="text-success">ปฏิบัติหน้าที่โดยผู้อำนวยการ ' . Html::encode($model->director_name ?: '(ยังไม่ได้ระบุชื่อ ผอ.)') . '</span>'
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- การ์ดที่ 2: การออกเลขรับหนังสือภายนอก -->
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center gap-2">
                    <span class="badge bg-success-subtle text-success p-2 rounded-3">
                        <i class="bi bi-123 fs-6"></i>
                    </span>
                    <div>
                        <h2 class="h6 fw-bold mb-0 text-dark">2. เลขรับหนังสือเริ่มต้น และปี</h2>
                        <small class="text-muted">กำหนดเลขเริ่มต้นและปี พ.ศ. ทะเบียนหนังสือรับ</small>
                    </div>
                </div>

                <div class="card-body p-3 p-md-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <?= $form->field($model, 'doc_receive_start_no')->input('number', [
                                'class' => 'form-control font-monospace fs-5',
                                'min' => 1,
                                'step' => 1,
                            ])->label('เลขรับเริ่มต้น') ?>
                        </div>

                        <div class="col-6">
                            <?= $form->field($model, 'doc_receive_year')->input('number', [
                                'class' => 'form-control font-monospace fs-5',
                                'min' => 2500,
                                'max' => 2700,
                                'step' => 1,
                            ])->label('ปี พ.ศ.') ?>
                        </div>
                    </div>

                    <!-- กล่องแสดงตัวอย่างเลขรับถัดไป -->
                    <div class="p-3 bg-primary-subtle border border-primary-subtle rounded-3 text-center my-3">
                        <div class="small text-muted fw-semibold mb-1">ตัวอย่างเลขรับถัดไปที่จะออกให้:</div>
                        <div class="display-6 fw-bold font-monospace text-primary" id="preview-receive-no">
                            <?= Html::encode($nextReceiveNoPreview) ?>
                        </div>
                    </div>

                    <div class="alert alert-light border small text-muted mb-0">
                        <div class="fw-semibold text-dark mb-1"><i class="bi bi-lightbulb me-1 text-warning"></i>คำแนะนำ:</div>
                        <ul class="mb-0 ps-3">
                            <li>เมื่อเริ่มปีปฏิทินใหม่ หรือต้องการเริ่มนับสมุดเล่มใหม่ สามารถเปลี่ยนปี พ.ศ. หรือตั้งเลขเริ่มต้นได้ที่นี่</li>
                            <li>หากในระบบมีหนังสือในปีนั้นอยู่แล้ว ระบบจะรันต่อจากเลขสูงสุดอัตโนมัติ</li>
                        </ul>
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <?= $form->field($model, 'school_name')->textInput([
                            'class' => 'form-control',
                            'placeholder' => 'โรงเรียนกุดชุมวิทยาคม',
                        ])->label('ชื่อสถานศึกษา') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- แถบปุ่มบันทึก -->
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="text-muted small">
                <i class="bi bi-shield-check text-success me-1"></i>
                การบันทึกจะมีผลทันทีต่อการลงรับหนังสือใหม่และการลงนามเอกสารในระบบ
            </div>
            <div>
                <?= Html::submitButton('<i class="bi bi-save me-1" aria-hidden="true"></i> บันทึกการตั้งค่าระบบ', [
                    'class' => 'btn btn-primary px-4 fw-semibold',
                ]) ?>
            </div>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<?php
$this->registerJs(<<<'JS'
(function () {
    const switchEl = document.getElementById('is-acting-director-switch');
    const userSelectEl = document.getElementById('acting-director-user-select');
    const statusTextEl = document.getElementById('acting-status-text');
    const directorNameEl = document.getElementById('systemsettingform-director_name');
    const actingNameEl = document.getElementById('systemsettingform-acting_director_name');
    const actingPosEl = document.getElementById('systemsettingform-acting_director_position');

    function updatePreview() {
        if (!switchEl || !statusTextEl) return;
        const isActing = switchEl.checked;
        if (isActing) {
            let name = '(ยังไม่ได้เลือกรักษาการ)';
            if (userSelectEl && userSelectEl.selectedIndex > 0) {
                name = userSelectEl.options[userSelectEl.selectedIndex].text.replace(/\s*\([^)]*\)$/, '').trim();
            } else if (actingNameEl && actingNameEl.value.trim()) {
                name = actingNameEl.value.trim();
            }
            const pos = (actingPosEl && actingPosEl.value.trim()) || 'รักษาการในตำแหน่งผู้อำนวยการ';
            statusTextEl.innerHTML = '<span class="text-warning-emphasis">กำลังปฏิบัติหน้าที่แทน โดย ' + name + ' (' + pos + ')</span>';
        } else {
            const name = (directorNameEl && directorNameEl.value.trim()) || '(ยังไม่ได้ระบุชื่อ ผอ.)';
            statusTextEl.innerHTML = '<span class="text-success">ปฏิบัติหน้าที่โดยผู้อำนวยการ ' + name + '</span>';
        }
    }

    if (userSelectEl) {
        userSelectEl.addEventListener('change', function () {
            if (this.value && this.selectedIndex > 0) {
                const optText = this.options[this.selectedIndex].text;
                // ตัดส่วนในวงเล็บท้ายชื่อ เช่น "นายสมศักดิ์ รักดี (รอง ผอ. - วิชาการ)" -> "นายสมศักดิ์ รักดี"
                const cleanName = optText.replace(/\s*\([^)]*\)$/, '').trim();
                if (actingNameEl) {
                    actingNameEl.value = cleanName;
                }
            } else {
                if (actingNameEl) {
                    actingNameEl.value = '';
                }
            }
            updatePreview();
        });
    }

    if (switchEl) {
        switchEl.addEventListener('change', updatePreview);
    }
    if (directorNameEl) directorNameEl.addEventListener('input', updatePreview);
    if (actingNameEl) actingNameEl.addEventListener('input', updatePreview);
    if (actingPosEl) actingPosEl.addEventListener('input', updatePreview);
})();
JS);
?>

