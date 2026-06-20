<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \common\models\LoginForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'เข้าสู่ระบบ e-Office';
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerCssFile('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css');
$htmlIcon = <<<HTML
{label}<div class="input-group"><span class="input-group-text" aria-hidden="true"><i class="bi %s"></i></span>{input}</div>{error}{hint}
HTML;
$labelOptions = ['class' => 'form-label fw-semibold'];
?>
<div class="card border-0 overflow-hidden login-split-card eoffice-login-card">
    <div class="row g-0">

        <div class="col-md-5 d-none d-md-flex login-brand-panel text-white">
            <div class="d-flex flex-column justify-content-between p-4 p-lg-5 w-100">
                <div>
                    <div class="login-brand-mark">
                        <i class="bi bi-building-check" aria-hidden="true"></i>
                        <span>e-Office</span>
                    </div>
                </div>
                <div>
                    <h2 class="fw-bold mb-3 login-brand-title">
                        ระบบสารบรรณ<br>อิเล็กทรอนิกส์
                    </h2>
                    <p class="opacity-75 mb-0 login-brand-text">
                        ลงรับหนังสือ เสนอผู้บริหาร มอบหมายงาน และติดตามสถานะเอกสารในที่เดียว
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="p-4 p-lg-5">
                <div class="text-center mb-4">
                    <div class="d-md-none mb-3">
                        <div class="login-mobile-mark">
                            <i class="bi bi-building-check" aria-hidden="true"></i>
                            <span>e-Office</span>
                        </div>
                    </div>
                    <h1 class="h3 fw-bold mb-1"><?= Html::encode($this->title) ?></h1>
                    <p class="text-body-secondary small mb-0">กรอกชื่อผู้ใช้และรหัสผ่านเพื่อเข้าสู่ระบบงานสารบรรณ</p>
                </div>

                <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>

                <div class="mb-3">
                    <?= $form->field($model, 'username', [
                        'options' => ['class' => 'mb-0'],
                        'template' => sprintf($htmlIcon, 'bi-person'),
                        'inputOptions' => [
                            'class' => 'form-control',
                            'placeholder' => 'ชื่อผู้ใช้',
                            'autofocus' => true,
                        ],
                    ])->textInput()->label(null, $labelOptions) ?>
                </div>

                <div class="mb-3">
                    <?= $form->field($model, 'password', [
                        'options' => ['class' => 'mb-0'],
                        'template' => sprintf($htmlIcon, 'bi-lock'),
                        'inputOptions' => [
                            'class' => 'form-control',
                            'placeholder' => 'รหัสผ่าน',
                        ],
                    ])->passwordInput()->label(null, $labelOptions) ?>
                </div>

                <div class="mb-4 login-remember">
                    <?= $form->field($model, 'rememberMe')->checkbox(['label' => 'จดจำการเข้าสู่ระบบไว้ในเครื่องนี้']) ?>
                </div>

                <div class="d-grid">
                    <?= Html::submitButton(
                        '<i class="bi bi-box-arrow-in-right" aria-hidden="true"></i><span>เข้าสู่ระบบ</span>',
                        [
                            'class' => 'btn login-btn btn-lg rounded-3 text-white',
                            'name' => 'login-button',
                        ],
                    ) ?>
                </div>

                <?php ActiveForm::end(); ?>

                <p class="login-footnote">
                    สำหรับผู้ใช้งานภายในโรงเรียนเท่านั้น หากลืมรหัสผ่านให้ติดต่อผู้ดูแลระบบ
                </p>

            </div>
        </div>

    </div>
</div>
