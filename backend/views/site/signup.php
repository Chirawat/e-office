<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \backend\models\SignupForm $model */
/** @var \common\models\Department[] $departments */
/** @var array|null $lineData */

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

<div class="card border-0 overflow-hidden login-split-card eoffice-login-card my-4" style="max-width: 960px; width: 100%;">
    <div class="row g-0">

        <!-- ฝั่งซ้าย: Theme Brand Panel -->
        <div class="col-lg-4 col-md-5 d-none d-md-flex login-brand-panel text-white">
            <div class="d-flex flex-column justify-content-between p-4 p-xl-5 w-100">
                <div>
                    <div class="login-brand-mark">
                        <i class="bi bi-building-check" aria-hidden="true"></i>
                        <span>e-Office</span>
                    </div>
                </div>

                <div class="my-auto py-4">
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

                <div class="small opacity-50">
                    &copy; <?= date('Y') ?> โรงเรียนกุดชุมวิทยาคม
                </div>
            </div>
        </div>

        <!-- ฝั่งขวา: Signup Form -->
        <div class="col-lg-8 col-md-7 col-12 bg-white">
            <div class="p-4 p-sm-5">
                <div class="mb-4">
                    <div class="d-md-none mb-3">
                        <div class="login-mobile-mark">
                            <i class="bi bi-building-check" aria-hidden="true"></i>
                            <span>e-Office</span>
                        </div>
                    </div>
                    <h1 class="h3 fw-bold text-dark mb-1">ลงทะเบียนผู้ใช้งานใหม่</h1>
                    <p class="text-body-secondary small mb-0">กรอกข้อมูลและเชื่อมต่อ LINE เพื่อรับการแจ้งเตือนเอกสาร</p>
                </div>

                <!-- ส่วนบังคับผูกกับ LINE (LINE Required Step) -->
                <div class="card border mb-4 p-3 rounded-3 <?= $hasLineLinked ? 'bg-success-subtle border-success' : 'bg-light border-warning' ?>">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 44px; height: 44px; background: #06C755; color: #fff; font-size: 22px;">
                                <i class="bi bi-line"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark">
                                    <?= $hasLineLinked ? 'เชื่อมต่อ LINE สำเร็จแล้ว' : 'ขั้นตอนที่ 1: บังคับเชื่อมต่อ LINE' ?>
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

                <?php $form = ActiveForm::begin([
                    'id' => 'form-signup',
                ]); ?>

                <!-- Hidden inputs สำหรับเก็บ line_user_id และ line_display_name -->
                <?= Html::activeHiddenInput($model, 'line_user_id') ?>
                <?= Html::activeHiddenInput($model, 'line_display_name') ?>

                <?php if ($model->hasErrors('line_user_id')): ?>
                    <div class="alert alert-danger py-2 px-3 small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= Html::encode($model->getFirstError('line_user_id')) ?>
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <?= $form->field($model, 'fullname', [
                            'template' => sprintf($htmlIcon, 'bi-person-badge'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'เช่น นายสมชาย ใจดี',
                                'autofocus' => true,
                            ],
                        ])->textInput()->label('ชื่อ - นามสกุล', $labelOptions) ?>
                    </div>

                    <div class="col-md-6">
                        <?= $form->field($model, 'phone', [
                            'template' => sprintf($htmlIcon, 'bi-telephone'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'เช่น 0812345678',
                            ],
                        ])->textInput()->label('เบอร์โทรศัพท์มือถือ', $labelOptions) ?>
                    </div>

                    <div class="col-md-6">
                        <?= $form->field($model, 'username', [
                            'template' => sprintf($htmlIcon, 'bi-person'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'เลขบัตร 13 หลัก หรือภาษาอังกฤษ',
                            ],
                        ])->textInput()->label('ชื่อผู้ใช้ (Username)', $labelOptions)->hint('แนะนำให้ใช้เลขบัตรประชาชน 13 หลัก เพื่อง่ายต่อการจำ') ?>
                    </div>

                    <div class="col-md-6">
                        <?= $form->field($model, 'department_id', [
                            'template' => sprintf($htmlIcon, 'bi-building'),
                            'inputOptions' => [
                                'class' => 'form-select',
                            ],
                        ])->dropDownList($deptOptions, [
                            'prompt' => '-- เลือกกลุ่มสาระฯ / ฝ่ายงาน --',
                        ])->label('กลุ่มสาระฯ / ฝ่ายที่สังกัด', $labelOptions) ?>
                    </div>

                    <div class="col-12">
                        <?= $form->field($model, 'email', [
                            'template' => sprintf($htmlIcon, 'bi-envelope'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'เช่น teacher@kudchum.ac.th',
                            ],
                        ])->textInput()->label('อีเมล', $labelOptions) ?>
                    </div>

                    <div class="col-md-6">
                        <?= $form->field($model, 'password', [
                            'template' => sprintf($htmlIcon, 'bi-lock'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'อย่างน้อย 6 ตัวอักษร',
                            ],
                        ])->passwordInput()->label('กำหนดรหัสผ่าน', $labelOptions) ?>
                    </div>

                    <div class="col-md-6">
                        <?= $form->field($model, 'password_repeat', [
                            'template' => sprintf($htmlIcon, 'bi-shield-lock'),
                            'inputOptions' => [
                                'class' => 'form-control',
                                'placeholder' => 'พิมพ์รหัสผ่านเดิมอีกครั้ง',
                            ],
                        ])->passwordInput()->label('ยืนยันรหัสผ่าน', $labelOptions) ?>
                    </div>
                </div>

                <div class="alert alert-info border-0 rounded-3 d-flex align-items-center mt-4 mb-4 py-2 px-3 small" style="background-color: #eff6ff; color: #1e40af;">
                    <i class="bi bi-info-circle-fill fs-5 me-2 text-primary"></i>
                    <div>
                        เมื่อกดลงทะเบียน ระบบจะนำไปยัง <strong>หน้าแสดงสถานะรอ Admin ยืนยันข้อมูล</strong> และส่งแจ้งเตือนเข้า LINE ของท่านทันที
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
                        ['login'],
                        ['class' => 'btn btn-outline-secondary rounded-3 text-center py-2']
                    ) ?>
                </div>

                <?php ActiveForm::end(); ?>
            </div>
        </div>

    </div>
</div>
