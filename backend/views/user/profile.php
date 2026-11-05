<?php

declare(strict_types=1);

use common\models\Department;
use common\models\User;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var backend\models\UserProfileForm $model */
/** @var User $user */
/** @var Department[] $departments */

$this->title = 'ข้อมูลผู้ใช้';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);

$departmentItems = ArrayHelper::map($departments, 'id', 'name');
$signatureUrl = $user->signature_path ? Url::to('@web/' . ltrim($user->signature_path, '/'), true) : null;
?>

<div class="eoffice-page">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h4 fw-bold text-dark mb-1">
                <i class="bi bi-person-gear text-primary me-2" aria-hidden="true"></i><?= Html::encode($this->title) ?>
            </h1>
            <div class="small text-muted">ดูและแก้ไขข้อมูลส่วนตัวตอนสมัครสมาชิก พร้อมอัปโหลดหรือแก้ไขภาพลายเซ็นสแกนสำหรับประทับตราบนเอกสาร</div>
        </div>
        <div>
            <?= Html::a('<i class="bi bi-arrow-left me-1"></i><span>กลับหน้ารายการหนังสือ</span>', ['/document/my'], ['class' => 'btn btn-outline-secondary btn-sm fw-semibold']) ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- ฝั่งซ้าย: สรุปโปรไฟล์, บัญชี LINE และลายเซ็นปัจจุบัน -->
        <div class="col-lg-4 col-md-5">
            <!-- ข้อมูลบัญชีสรุป -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body text-center p-4">
                    <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 72px; height: 72px; font-size: 2rem;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1"><?= Html::encode($user->displayName()) ?></h5>
                    <div class="text-muted small mb-2">@<?= Html::encode($user->username) ?></div>
                    <div class="d-flex justify-content-center gap-1 flex-wrap">
                        <span class="badge bg-primary px-2 py-1"><?= Html::encode($user->roleLabel()) ?></span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                            <?= $user->status === User::STATUS_ACTIVE ? 'เปิดใช้งาน' : 'รออนุมัติ' ?>
                        </span>
                    </div>
                    <?php if ($user->department): ?>
                        <div class="mt-3 pt-3 border-top small text-muted">
                            <i class="bi bi-building me-1"></i>สังกัด: <strong><?= Html::encode($user->department->name) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- บัญชี LINE ที่ผูกมัด -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-chat-dots-fill text-success fs-5"></i>
                        <strong class="text-dark">การเชื่อมต่อ LINE</strong>
                    </div>
                    <?php if (!empty($user->line_user_id)): ?>
                        <div class="p-2 rounded bg-success-subtle border border-success-subtle small">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="badge bg-success">เชื่อมต่อแล้ว</span>
                                <?php if ($user->line_linked_at): ?>
                                    <span class="text-muted" style="font-size: 0.75rem;"><?= Yii::$app->formatter->asDate($user->line_linked_at, 'short') ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-dark fw-semibold text-truncate">
                                <i class="bi bi-line text-success me-1"></i><?= Html::encode($user->line_display_name ?: 'บัญชี LINE') ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-2 rounded bg-light border text-muted small">
                            <span class="badge bg-secondary mb-1">ยังไม่เชื่อมต่อ</span>
                            <div>สามารถเข้าสู่ระบบด้วยชื่อผู้ใช้และรหัสผ่าน</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ภาพลายเซ็นสแกนปัจจุบัน -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <strong class="text-dark"><i class="bi bi-pen me-2 text-primary"></i>ลายเซ็นสแกนในระบบ</strong>
                    <?php if ($signatureUrl): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">มีลายเซ็นแล้ว</span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">ยังไม่มี</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-3 text-center">
                    <?php if ($signatureUrl): ?>
                        <div class="p-3 border rounded bg-white shadow-sm mb-3 d-inline-block w-100" style="max-width: 280px;">
                            <img src="<?= Html::encode($signatureUrl) ?>?v=<?= time() ?>" alt="ลายเซ็น <?= Html::encode($user->displayName()) ?>" class="img-fluid" style="max-height: 110px; object-fit: contain;">
                        </div>
                        <div>
                            <?= Html::a('<i class="bi bi-trash3 me-1"></i>ลบภาพลายเซ็น', ['remove-signature'], [
                                'class' => 'btn btn-outline-danger btn-sm px-3 fw-semibold',
                                'data-method' => 'post',
                                'data-confirm' => 'ต้องการลบภาพลายเซ็นนี้ใช่หรือไม่? หลังจากลบ ตราประทับที่ต้องใช้ลายเซ็นจะแสดงเป็นเส้นประว่าง',
                            ]) ?>
                        </div>
                    <?php else: ?>
                        <div class="p-4 border border-dashed rounded bg-light text-muted mb-2">
                            <i class="bi bi-image fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <div class="fw-semibold">ยังไม่มีภาพลายเซ็นสแกน</div>
                            <div class="small">สามารถอัปโหลดภาพลายเซ็นได้ที่ฟอร์มด้านขวา</div>
                        </div>
                    <?php endif; ?>

                    <div class="text-start small text-muted mt-3 pt-3 border-top">
                        <i class="bi bi-info-circle text-primary me-1"></i>
                        ลายเซ็นนี้จะถูกใช้ประทับบนเอกสารและบันทึกข้อความที่ท่านลงนามโดยอัตโนมัติ แนะนำใช้ภาพลายเซ็นที่ถ่ายหรือสแกนคมชัด
                    </div>
                </div>
            </div>
        </div>

        <!-- ฝั่งขวา: ฟอร์มแก้ไขข้อมูลและอัปโหลดลายเซ็น -->
        <div class="col-lg-8 col-md-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom">
                    <h2 class="h6 fw-bold text-dark mb-0">
                        <i class="bi bi-pencil-square text-primary me-2"></i>แก้ไขข้อมูลส่วนตัวและลายเซ็น
                    </h2>
                </div>
                <div class="card-body p-4">
                    <?php $form = ActiveForm::begin([
                        'options' => ['enctype' => 'multipart/form-data'],
                    ]); ?>

                    <!-- ส่วนที่ 1: ข้อมูลส่วนตัวตอนสมัครสมาชิก -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary text-uppercase small tracking-wide pb-2 border-bottom mb-3">
                            <i class="bi bi-card-text me-1"></i>ข้อมูลตอนสมัครสมาชิก
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <?= $form->field($model, 'fullname')->textInput([
                                    'maxlength' => true,
                                    'placeholder' => 'ระบุชื่อและนามสกุล',
                                ]) ?>
                            </div>
                            <div class="col-md-6">
                                <?= $form->field($model, 'username')->textInput([
                                    'maxlength' => true,
                                    'placeholder' => 'ระบุชื่อผู้ใช้สำหรับเข้าสู่ระบบ',
                                ]) ?>
                            </div>
                            <div class="col-md-6">
                                <?= $form->field($model, 'phone')->textInput([
                                    'maxlength' => true,
                                    'placeholder' => 'เช่น 0812345678',
                                ]) ?>
                            </div>
                            <div class="col-md-6">
                                <?= $form->field($model, 'email')->input('email', [
                                    'maxlength' => true,
                                    'placeholder' => 'เช่น teacher@school.ac.th',
                                ]) ?>
                            </div>
                            <div class="col-md-6">
                                <?= $form->field($model, 'id_card')->textInput([
                                    'maxlength' => true,
                                    'placeholder' => 'เลขบัตรประชาชน 13 หลัก',
                                ]) ?>
                            </div>
                            <div class="col-md-6">
                                <?= $form->field($model, 'department_id')->dropDownList($departmentItems, [
                                    'prompt' => '-- เลือกกลุ่มสาระฯ / ฝ่ายงาน --',
                                ]) ?>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 2: อัปโหลดหรือเปลี่ยนภาพลายเซ็นสแกน -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary text-uppercase small tracking-wide pb-2 border-bottom mb-3">
                            <i class="bi bi-pen me-1"></i>ภาพลายเซ็นสแกน (Upload / แก้ไข)
                        </h6>
                        <div class="mb-3">
                            <?= $form->field($model, 'signatureFile')->fileInput([
                                'accept' => 'image/png,image/jpeg',
                                'id' => 'profile-signature-input',
                                'class' => 'form-control',
                            ])->hint('รองรับไฟล์ PNG หรือ JPG ขนาดไม่เกิน 2 MB (สามารถถ่ายรูปหรือสแกนลายเซ็นบนกระดาษขาว)') ?>
                        </div>

                        <!-- พรีวิวแบบ Real-time เมื่อเลือกไฟล์ -->
                        <div id="new-signature-preview-box" class="d-none p-3 border border-primary rounded bg-light mb-3">
                            <div class="small fw-bold text-primary mb-2">
                                <i class="bi bi-eye me-1"></i>ตัวอย่างไฟล์ลายเซ็นที่เลือกใหม่:
                            </div>
                            <div class="p-2 border rounded bg-white d-inline-block shadow-sm" style="max-width: 260px;">
                                <img id="new-signature-preview-img" src="#" alt="ตัวอย่างลายเซ็นใหม่" class="img-fluid" style="max-height: 90px; object-fit: contain;">
                            </div>
                            <div class="small text-muted mt-2">เมื่อกดปุ่ม "บันทึกข้อมูล" ภาพนี้จะถูกปรับความคมชัดและบันทึกเป็นลายเซ็นประจำตัวของท่าน</div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 3: เปลี่ยนรหัสผ่าน (ไม่บังคับ) -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary text-uppercase small tracking-wide pb-2 border-bottom mb-3">
                            <i class="bi bi-key me-1"></i>เปลี่ยนรหัสผ่าน (หากไม่ต้องการเปลี่ยน ให้เว้นว่างไว้)
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <?= $form->field($model, 'password')->passwordInput([
                                    'autocomplete' => 'new-password',
                                    'placeholder' => 'ระบุรหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)',
                                ]) ?>
                            </div>
                            <div class="col-md-6">
                                <?= $form->field($model, 'password_repeat')->passwordInput([
                                    'autocomplete' => 'new-password',
                                    'placeholder' => 'พิมพ์รหัสผ่านใหม่อีกครั้งเพื่อยืนยัน',
                                ]) ?>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <?= Html::submitButton('<i class="bi bi-check2-circle me-1"></i>บันทึกข้อมูล', [
                            'class' => 'btn btn-primary px-4 py-2 fw-semibold shadow-sm',
                        ]) ?>
                        <?= Html::a('ยกเลิก', ['/document/my'], ['class' => 'btn btn-outline-secondary px-3 py-2']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('profile-signature-input');
    const previewBox = document.getElementById('new-signature-preview-box');
    const previewImg = document.getElementById('new-signature-preview-img');

    if (fileInput && previewBox && previewImg) {
        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewImg.src = e.target.result;
                    previewBox.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            } else {
                previewBox.classList.add('d-none');
                previewImg.src = '#';
            }
        });
    }
});
</script>

