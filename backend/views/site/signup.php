<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \backend\models\SignupForm $model */
/** @var \common\models\Department[] $departments */
/** @var array|null $lineData */

use common\models\User;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

$this->title = 'ลงทะเบียนเข้าใช้งาน e-Office';
$ver = time();
$this->registerCssFile("@web/css/eoffice.css?v={$ver}", ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerCssFile('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css');

$deptOptions = ArrayHelper::map($departments, 'id', 'name');
$htmlIcon = <<<HTML
{label}<div class="input-group"><span class="input-group-text" aria-hidden="true"><i class="bi %s"></i></span>{input}</div>{error}{hint}
HTML;
$labelOptions = ['class' => 'form-label fw-semibold'];

$hasLineLinked = !empty($model->line_user_id);
?>

<div class="card border-0 overflow-hidden login-split-card eoffice-login-card my-auto my-md-4" style="max-width: 980px; width: 100%;">
    <div class="row g-0">

        <!-- ฝั่งซ้าย: Theme Brand Panel -->
        <div class="col-lg-4 col-md-5 d-none d-md-flex login-brand-panel text-white">
            <div class="d-flex flex-column justify-content-between p-4 p-xl-5 w-100">
                <div>
                    <div class="login-brand-mark mb-4">
                        <i class="bi bi-building-check" aria-hidden="true"></i>
                        <span>e-Office</span>
                    </div>

                    <div class="py-2">
                        <h2 class="fw-bold mb-3 login-brand-title">
                            สมัครสมาชิก<br>ครูและบุคลากร
                        </h2>
                        <p class="opacity-75 mb-0 login-brand-text">
                            โรงเรียนกุดชุมวิทยาคม ยินดีต้อนรับคุณครูทุกท่านเข้าสู่ระบบงานสารบรรณดิจิทัล
                        </p>

                        <div class="mt-4 pt-3 border-top border-white border-opacity-25 small">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-chat-check-fill fs-5 text-success"></i>
                                <span>ผูก LINE เพื่อรับการแจ้งเตือนงาน</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-shield-check fs-5 text-info"></i>
                                <span>Admin ตรวจสอบความถูกต้อง</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-bell-fill fs-5 text-warning"></i>
                                <span>แจ้งผลการอนุมัติผ่าน LINE ทันที</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="small opacity-50 mt-4">
                    &copy; <?= date('Y') ?> โรงเรียนกุดชุมวิทยาคม
                </div>
            </div>
        </div>

        <!-- ฝั่งขวา: Signup Form -->
        <div class="col-lg-8 col-md-7 col-12 bg-white">
            <div class="p-3 p-sm-4 p-md-5">
                <div class="mb-4">
                    <div class="d-md-none mb-3">
                        <div class="login-mobile-mark">
                            <i class="bi bi-building-check" aria-hidden="true"></i>
                            <span>e-Office</span>
                        </div>
                    </div>
                    <h1 class="h3 fw-bold text-dark mb-1">ลงทะเบียนผู้ใช้งานใหม่</h1>
                    <p class="text-body-secondary small mb-0">กรอกข้อมูลและเชื่อมต่อ LINE เพื่อรับการแจ้งเตือนเอกสารราชการ</p>
                </div>

                <?php $form = ActiveForm::begin([
                    'id' => 'form-signup',
                    'options' => ['enctype' => 'multipart/form-data'],
                ]); ?>

                <!-- ================= 1. เชื่อมต่อ Line ================= -->
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <span class="badge rounded-circle d-inline-flex align-items-center justify-content-center text-white" style="width: 26px; height: 26px; font-size: 0.85rem; background: #06C755;">1</span>
                        <h6 class="fw-bold mb-0 text-dark">เชื่อมต่อ Line</h6>
                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-auto" style="font-size: 0.72rem;">ขั้นตอนจำเป็น</span>
                    </div>

                    <div class="card border p-3 rounded-3 <?= $hasLineLinked ? 'bg-success-subtle border-success' : 'bg-light border-warning' ?>">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 44px; height: 44px; background: #06C755; color: #fff; font-size: 22px;">
                                    <i class="bi bi-line"></i>
                                </div>
                                <div>
                                    <strong class="d-block text-dark">
                                        <?= $hasLineLinked ? 'เชื่อมต่อ LINE สำเร็จแล้ว' : 'กดปุ่มเพื่อเชื่อมต่อบัญชี LINE' ?>
                                    </strong>
                                    <span class="small text-secondary">
                                        <?= $hasLineLinked 
                                            ? 'บัญชี: ' . Html::encode($model->line_display_name ?: 'LINE User') . ' (ใช้รับแจ้งเตือนเอกสารและการอนุมัติ)'
                                            : 'ระบบจำเป็นต้องใช้ LINE ในการแจ้งผลอนุมัติและเตือนงานสารบรรณ' ?>
                                    </span>
                                </div>
                            </div>

                            <?php if ($hasLineLinked): ?>
                                <span class="badge bg-success px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> เชื่อมต่อแล้ว</span>
                            <?php else: ?>
                                <a href="<?= Url::to(['/site/line-auth', 'from' => 'signup']) ?>" class="btn text-white fw-bold px-3 py-2 rounded-3 shadow-sm" style="background-color: #06C755; border: none;">
                                    <i class="bi bi-link-45deg me-1"></i> กดเชื่อมต่อ LINE ที่นี่
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Hidden inputs สำหรับเก็บ line_user_id และ line_display_name -->
                    <?= Html::activeHiddenInput($model, 'line_user_id') ?>
                    <?= Html::activeHiddenInput($model, 'line_display_name') ?>

                    <?php if ($model->hasErrors('line_user_id')): ?>
                        <div class="alert alert-danger py-2 px-3 small mt-2 mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= Html::encode($model->getFirstError('line_user_id')) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ================= 2. ข้อมูลในการเข้าระบบ ================= -->
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <span class="badge rounded-circle d-inline-flex align-items-center justify-content-center text-white bg-primary" style="width: 26px; height: 26px; font-size: 0.85rem;">2</span>
                        <h6 class="fw-bold mb-0 text-dark">ข้อมูลในการเข้าระบบ</h6>
                        <span class="text-muted small ms-auto" style="font-size: 0.75rem;">Account Credentials</span>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'username', [
                            'template' => sprintf($htmlIcon, 'bi-person'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'ชื่อภาษาอังกฤษ',
                            ],
                        ])->textInput()->label('ชื่อผู้ใช้ (Username)', $labelOptions)->hint('ชื่อภาษาอังกฤษ (ไม่มีเว้นวรรคหรืออักขระพิเศษ) ใช้ในการเข้าสู่ระบบ') ?>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'email', [
                            'template' => sprintf($htmlIcon, 'bi-envelope'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'เช่น teacher@schoolkc.ac.th',
                            ],
                        ])->textInput()->label('อีเมล (Email)', $labelOptions)->hint('ใช้สำหรับรับข้อมูลข่าวสารและการกู้คืนบัญชี') ?>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'password', [
                            'template' => sprintf($htmlIcon, 'bi-lock'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'อย่างน้อย 6 ตัวอักษร',
                            ],
                        ])->passwordInput()->label('กำหนดรหัสผ่าน', $labelOptions) ?>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'password_repeat', [
                            'template' => sprintf($htmlIcon, 'bi-shield-lock'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'พิมพ์รหัสผ่านเดิมอีกครั้ง',
                            ],
                        ])->passwordInput()->label('ยืนยันรหัสผ่านอีกครั้ง', $labelOptions) ?>
                    </div>
                </div>

                <!-- ================= 3. ข้อมูลตำแหน่งงาน ================= -->
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <span class="badge rounded-circle d-inline-flex align-items-center justify-content-center text-white" style="width: 26px; height: 26px; font-size: 0.85rem; background-color: #4f46e5;">3</span>
                        <h6 class="fw-bold mb-0 text-dark">ข้อมูลตำแหน่งงาน</h6>
                        <span class="text-muted small ms-auto" style="font-size: 0.75rem;">Personnel Details</span>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'fullname', [
                            'template' => sprintf($htmlIcon, 'bi-person-badge'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'เช่น นายสมชาย ใจดี',
                            ],
                        ])->textInput()->label('ชื่อ - นามสกุลจริง (ทางการ)', $labelOptions)->hint('ระบุคำนำหน้าและชื่อ-นามสกุลจริงสำหรับระบบราชการ') ?>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'phone', [
                            'template' => sprintf($htmlIcon, 'bi-telephone'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'เช่น 0812345678',
                            ],
                        ])->textInput()->label('เบอร์โทรศัพท์มือถือ', $labelOptions) ?>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'department_id', [
                            'template' => sprintf($htmlIcon, 'bi-building'),
                            'inputOptions' => [
                                'class' => 'form-select',
                            ],
                        ])->dropDownList($deptOptions, [
                            'prompt' => '-- เลือกกลุ่มงานที่สังกัด --',
                        ])->label('กลุ่มงานที่สังกัด (ฝ่ายบริหาร)', $labelOptions)->hint('เลือกฝ่ายงานบริหารที่สังกัด เช่น วิชาการ, บุคคล, ทั่วไป ฯลฯ') ?>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'learning_area', [
                            'template' => sprintf($htmlIcon, 'bi-book'),
                            'inputOptions' => [
                                'class' => 'form-select',
                            ],
                        ])->dropDownList(User::learningAreaOptions(), [
                            'prompt' => '-- เลือกกลุ่มสาระการเรียนรู้ --',
                        ])->label('กลุ่มสาระการเรียนรู้', $labelOptions)->hint('ครูเลือกกลุ่มสาระฯ ที่สอน (เจ้าหน้าที่/บุคลากร เลือก "ไม่สังกัดกลุ่มสาระ")') ?>
                    </div>

                    <div class="mb-3">
                        <?= $form->field($model, 'signatureFile')->fileInput([
                            'accept' => 'image/png,image/jpeg',
                            'id' => 'signup-signature-input',
                            'class' => 'form-control',
                        ])->label('ภาพถ่ายหรือภาพสแกนลายมือชื่อ (ลายเซ็น)', $labelOptions)->hint('แนะนำให้อัปโหลดภาพถ่ายหรือภาพสแกนลายเซ็นบนกระดาษขาว (PNG หรือ JPG ไม่เกิน 2 MB) เพื่อใช้ประทับตราในระบบ') ?>

                        <div id="signature-preview-box" class="d-none mt-2 p-2 border rounded bg-light text-center" style="max-width: 260px;">
                            <span class="small text-muted d-block mb-1">ตัวอย่างภาพลายเซ็นที่เลือก:</span>
                            <img id="signature-preview-img" src="" alt="ตัวอย่างลายเซ็น" style="max-height: 80px; object-fit: contain;">
                        </div>
                    </div>
                </div>

                <?php $isAutoApprove = \common\models\SystemSetting::isAutoApproveSignup(); ?>
                <div class="alert <?= $isAutoApprove ? 'alert-success' : 'alert-info' ?> border-0 rounded-3 d-flex align-items-center mt-4 mb-4 py-2 px-3 small" style="<?= $isAutoApprove ? 'background-color: #ecfdf5; color: #065f46;' : 'background-color: #eff6ff; color: #1e40af;' ?>">
                    <i class="bi <?= $isAutoApprove ? 'bi-check-circle-fill text-success' : 'bi-info-circle-fill text-primary' ?> fs-5 me-2"></i>
                    <div>
                        <?php if ($isAutoApprove): ?>
                            เมื่อกดลงทะเบียน บัญชีจะได้รับการ <strong>อนุมัติและเข้าสู่ระบบทันที</strong> พร้อมส่งข้อความยืนยันเข้า LINE ของท่าน
                        <?php else: ?>
                            เมื่อกดลงทะเบียน ระบบจะนำไปยัง <strong>หน้าแสดงสถานะรอ Admin ยืนยันข้อมูล</strong> และส่งแจ้งเตือนเข้า LINE ของท่านทันที
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <?= Html::submitButton(
                        '<i class="bi bi-check2-circle me-1" aria-hidden="true"></i><span>ยืนยันการลงทะเบียน</span>',
                        [
                            'class' => 'btn login-btn btn-lg rounded-3 fw-bold text-white',
                            'name' => 'signup-button',
                            'disabled' => !$hasLineLinked,
                        ]
                    ) ?>
                    <?= Html::a(
                        '<i class="bi bi-arrow-left me-1"></i><span>มีบัญชีอยู่แล้ว? กลับไปหน้าเข้าสู่ระบบ</span>',
                        ['/site/login'],
                        ['class' => 'btn btn-outline-secondary rounded-3 text-center py-2']
                    ) ?>
                </div>

                <?php ActiveForm::end(); ?>
            </div>
        </div>

    </div>
</div>

<?php
$sigPreviewJs = <<<JS
var sigInput = document.getElementById('signup-signature-input');
var prevBox = document.getElementById('signature-preview-box');
var prevImg = document.getElementById('signature-preview-img');
if (sigInput && prevBox && prevImg) {
    sigInput.addEventListener('change', function(e) {
        var file = e.target.files && e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(evt) {
                prevImg.src = evt.target.result;
                prevBox.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        } else {
            prevBox.classList.add('d-none');
            prevImg.src = '';
        }
    });
}
JS;
$this->registerJs($sigPreviewJs);
?>
