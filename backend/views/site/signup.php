<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \backend\models\SignupForm $model */
/** @var \common\models\Department[] $departments */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = 'สมัครสมาชิกสำหรับครูและบุคลากร - e-Office';
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerCssFile('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css');

$deptOptions = ArrayHelper::map($departments, 'id', 'name');
$htmlIcon = <<<HTML
{label}<div class="input-group"><span class="input-group-text" aria-hidden="true"><i class="bi %s"></i></span>{input}</div>{error}{hint}
HTML;
$labelOptions = ['class' => 'form-label fw-semibold'];
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="p-4 p-md-5 bg-white">
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 64px; height: 64px; font-size: 28px;">
                            <i class="bi bi-person-plus-fill"></i>
                        </div>
                        <h1 class="h3 fw-bold text-dark mb-1">ลงทะเบียนเข้าใช้งาน e-Office</h1>
                        <p class="text-muted small">สำหรับข้าราชการครูและบุคลากรทางการศึกษา โรงเรียนกุดชุมวิทยาคม</p>
                    </div>

                    <?php $form = ActiveForm::begin([
                        'id' => 'form-signup',
                        'options' => ['class' => 'needs-validation'],
                    ]); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <?= $form->field($model, 'fullname', [
                                'template' => sprintf($htmlIcon, 'bi-person-badge'),
                            ])->textInput([
                                'placeholder' => 'เช่น นายสมชาย ใจดี',
                                'autofocus' => true,
                            ])->label(null, $labelOptions) ?>
                        </div>

                        <div class="col-md-6">
                            <?= $form->field($model, 'phone', [
                                'template' => sprintf($htmlIcon, 'bi-telephone'),
                            ])->textInput([
                                'placeholder' => 'เช่น 0812345678',
                            ])->label(null, $labelOptions) ?>
                        </div>

                        <div class="col-md-6">
                            <?= $form->field($model, 'username', [
                                'template' => sprintf($htmlIcon, 'bi-person'),
                            ])->textInput([
                                'placeholder' => 'เลขบัตร 13 หลัก หรือภาษาอังกฤษ',
                            ])->label(null, $labelOptions)->hint('ใช้เป็นชื่อสำหรับเข้าสู่ระบบ (แนะนำเลขบัตร 13 หลัก)') ?>
                        </div>

                        <div class="col-md-6">
                            <?= $form->field($model, 'id_card', [
                                'template' => sprintf($htmlIcon, 'bi-card-text'),
                            ])->textInput([
                                'placeholder' => 'เลข 13 หลัก (ถ้ามี)',
                            ])->label(null, $labelOptions) ?>
                        </div>

                        <div class="col-md-6">
                            <?= $form->field($model, 'department_id', [
                                'template' => sprintf($htmlIcon, 'bi-building'),
                            ])->dropDownList($deptOptions, [
                                'prompt' => '-- เลือกกลุ่มสาระฯ / ฝ่ายงาน --',
                                'class' => 'form-select',
                            ])->label(null, $labelOptions) ?>
                        </div>

                        <div class="col-md-6">
                            <?= $form->field($model, 'email', [
                                'template' => sprintf($htmlIcon, 'bi-envelope'),
                            ])->textInput([
                                'placeholder' => 'เช่น teacher@kudchum.ac.th',
                            ])->label(null, $labelOptions) ?>
                        </div>

                        <div class="col-md-6">
                            <?= $form->field($model, 'password', [
                                'template' => sprintf($htmlIcon, 'bi-lock'),
                            ])->passwordInput([
                                'placeholder' => 'อย่างน้อย 6 ตัวอักษร',
                            ])->label(null, $labelOptions) ?>
                        </div>

                        <div class="col-md-6">
                            <?= $form->field($model, 'password_repeat', [
                                'template' => sprintf($htmlIcon, 'bi-shield-lock'),
                            ])->passwordInput([
                                'placeholder' => 'พิมพ์รหัสผ่านเดิมอีกครั้ง',
                            ])->label(null, $labelOptions) ?>
                        </div>
                    </div>

                    <div class="alert alert-info d-flex align-items-center mt-4 mb-4 py-2" role="alert">
                        <i class="bi bi-info-circle-fill fs-5 me-2"></i>
                        <div class="small">
                            เมื่อลงทะเบียนแล้ว บัญชีจะอยู่ในสถานะ <strong>"รออนุมัติ"</strong> โดยระบบจะแจ้งเตือนผู้ดูแลระบบทาง LINE เพื่อตรวจสอบและเปิดใช้งานให้ท่าน
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <?= Html::submitButton(
                            '<i class="bi bi-check2-circle me-1" aria-hidden="true"></i><span>ยืนยันการลงทะเบียน</span>',
                            ['class' => 'btn btn-primary btn-lg rounded-3 fw-bold text-white', 'name' => 'signup-button']
                        ) ?>
                        <?= Html::a(
                            '<i class="bi bi-arrow-left me-1"></i><span>กลับไปหน้าเข้าสู่ระบบ</span>',
                            ['login'],
                            ['class' => 'btn btn-outline-secondary rounded-3 text-center']
                        ) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

