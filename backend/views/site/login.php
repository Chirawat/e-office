<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \common\models\LoginForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'เข้าสู่ระบบ e-Office';
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerCssFile('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css');

$htmlIcon = <<<HTML
{label}<div class="input-group"><span class="input-group-text" aria-hidden="true"><i class="bi %s"></i></span>{input}</div>{error}{hint}
HTML;
$labelOptions = ['class' => 'form-label fw-semibold'];
?>

<div class="card border-0 overflow-hidden login-split-card eoffice-login-card" style="max-width: 920px; width: 100%;">
    <div class="row g-0">

        <!-- ฝั่งซ้าย: Brand & Visual Panel (สำหรับหน้าจอ Desktop) -->
        <div class="col-lg-5 col-md-5 d-none d-md-flex login-brand-panel text-white">
            <div class="d-flex flex-column justify-content-between p-4 p-xl-5 w-100">
                <div>
                    <div class="login-brand-mark">
                        <i class="bi bi-building-check" aria-hidden="true"></i>
                        <span>e-Office</span>
                    </div>
                </div>
                <div class="my-auto py-4">
                    <h2 class="fw-bold mb-3 login-brand-title">
                        ระบบสารบรรณ<br>อิเล็กทรอนิกส์
                    </h2>
                    <p class="opacity-75 mb-0 login-brand-text">
                        โรงเรียนกุดชุมวิทยาคม บริหารจัดการเอกสาร รวดเร็ว โปร่งใส ตรวจสอบได้
                    </p>
                </div>
                <div class="small opacity-50">
                    &copy; <?= date('Y') ?> โรงเรียนกุดชุมวิทยาคม
                </div>
            </div>
        </div>

        <!-- ฝั่งขวา: Minimal Login Form (Username, Password + LINE Login) -->
        <div class="col-lg-7 col-md-7 col-12 bg-white">
            <div class="p-4 p-sm-5 d-flex flex-column justify-content-center h-100">
                
                <div class="mb-4">
                    <div class="d-md-none mb-3">
                        <div class="login-mobile-mark">
                            <i class="bi bi-building-check" aria-hidden="true"></i>
                            <span>e-Office</span>
                        </div>
                    </div>
                    <h1 class="h3 fw-bold text-dark mb-1">เข้าสู่ระบบ</h1>
                    <p class="text-body-secondary small mb-0">กรอกชื่อผู้ใช้และรหัสผ่าน หรือเข้าสู่ระบบด้วย LINE</p>
                </div>

                <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>

                <div class="mb-3">
                    <?= $form->field($model, 'username', [
                        'options' => ['class' => 'mb-0'],
                        'template' => sprintf($htmlIcon, 'bi-person'),
                        'inputOptions' => [
                            'class' => 'form-control',
                            'placeholder' => 'ชื่อผู้ใช้ หรือ เลขบัตร ปชช.',
                            'autofocus' => true,
                        ],
                    ])->textInput()->label('ชื่อผู้ใช้', $labelOptions) ?>
                </div>

                <div class="mb-3">
                    <?= $form->field($model, 'password', [
                        'options' => ['class' => 'mb-0'],
                        'template' => sprintf($htmlIcon, 'bi-lock'),
                        'inputOptions' => [
                            'class' => 'form-control',
                            'placeholder' => 'รหัสผ่าน',
                        ],
                    ])->passwordInput()->label('รหัสผ่าน', $labelOptions) ?>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4 login-remember">
                    <?= $form->field($model, 'rememberMe', ['options' => ['class' => 'mb-0']])->checkbox([
                        'label' => 'จดจำการเข้าสู่ระบบไว้ในเครื่องนี้',
                    ]) ?>
                </div>

                <div class="d-grid mb-3">
                    <?= Html::submitButton(
                        '<i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i><span>เข้าสู่ระบบ</span>',
                        [
                            'class' => 'btn login-btn btn-lg rounded-3 text-white fw-bold',
                            'name' => 'login-button',
                        ],
                    ) ?>
                </div>

                <?php ActiveForm::end(); ?>

                <!-- เส้นคั่น หรือ -->
                <div class="position-relative text-center my-3">
                    <hr class="text-secondary opacity-25 m-0">
                    <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 small text-muted">หรือเข้าสู่ระบบด่วน</span>
                </div>

                <!-- ปุ่ม LINE Login -->
                <div class="d-grid mb-4">
                    <a href="<?= Url::to(['/site/line-auth']) ?>" class="btn btn-lg text-white fw-semibold rounded-3 d-flex align-items-center justify-content-center gap-2 shadow-sm" style="background-color: #06C755; border: none;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M24 10.304c0-5.369-5.383-9.738-12-9.738-6.616 0-12 4.369-12 9.738 0 4.814 4.269 8.846 10.019 9.572.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975 1.742-1.928 2.564-3.896 2.564-5.932zm-15.659 2.05h-1.637a.56.56 0 0 1-.559-.558v-4.092a.56.56 0 0 1 .559-.559h1.637a.56.56 0 0 1 .559.559.56.56 0 0 1-.559.558h-1.078v1.21h1.078a.56.56 0 0 1 .559.558.56.56 0 0 1-.559.558h-1.078v1.208h1.078a.56.56 0 0 1 .559.558.56.56 0 0 1-.559.558zm3.87 0a.56.56 0 0 1-.559-.558v-4.092a.56.56 0 0 1 .559-.559.56.56 0 0 1 .559.559v4.092a.56.56 0 0 1-.559.558zm4.444 0a.56.56 0 0 1-.444-.219l-2.029-2.736v2.397a.56.56 0 0 1-.559.558.56.56 0 0 1-.558-.558v-4.092a.56.56 0 0 1 .444-.22.56.56 0 0 1 .444.22l2.028 2.735v-2.397a.56.56 0 0 1 .559-.558.56.56 0 0 1 .559.558v4.092a.56.56 0 0 1-.444.455zm3.871-2.973h-1.637v1.208h1.637a.56.56 0 0 1 .559.558.56.56 0 0 1-.559.558h-2.196a.56.56 0 0 1-.559-.558v-4.092a.56.56 0 0 1 .559-.559h2.196a.56.56 0 0 1 .559.559.56.56 0 0 1-.559.558h-1.637v1.21h1.637a.56.56 0 0 1 .559.558.56.56 0 0 1-.559.558z"/>
                        </svg>
                        <span>เข้าสู่ระบบด้วย LINE</span>
                    </a>
                </div>

                <!-- ลิงก์สมัครสมาชิก -->
                <div class="text-center pt-2 border-top">
                    <span class="small text-muted">ยังไม่มีบัญชีผู้ใช้งาน?</span>
                    <?= Html::a('สมัครสมาชิกครูและบุคลากร', ['/site/signup'], ['class' => 'small fw-semibold text-primary text-decoration-none ms-1']) ?>
                </div>

            </div>
        </div>

    </div>
</div>
