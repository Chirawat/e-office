<?php

declare(strict_types=1);

use backend\models\UserCreateForm;
use common\models\Department;
use common\models\User;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var UserCreateForm $model */
/** @var Department[] $departments */

$this->title = 'สร้างผู้ใช้';
$this->params['breadcrumbs'][] = ['label' => 'ผู้ใช้และบทบาท', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$departmentItems = ArrayHelper::map($departments, 'id', 'name');
?>

<div class="eoffice-page">
    <div class="eoffice-titlebar">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <p>เพิ่มบัญชีสำหรับใช้งานระบบสารบรรณอิเล็กทรอนิกส์</p>
        </div>
        <?= Html::a('<i class="bi bi-arrow-left" aria-hidden="true"></i><span>กลับหน้าผู้ใช้</span>', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <div class="form-grid">
        <section class="content-card">
            <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>
                <div class="row">
                    <div class="col-md-6">
                        <?= $form->field($model, 'username')->textInput(['maxlength' => true, 'autofocus' => true]) ?>
                    </div>
                    <div class="col-md-6">
                        <?= $form->field($model, 'email')->input('email', ['maxlength' => true]) ?>
                    </div>
                </div>

                <?= $form->field($model, 'password')->passwordInput(['maxlength' => true])->hint('อย่างน้อย 6 ตัวอักษร') ?>

                <div class="row">
                    <div class="col-md-6">
                        <?= $form->field($model, 'role')->dropDownList(User::roleOptions()) ?>
                    </div>
                    <div class="col-md-6">
                        <?= $form->field($model, 'status')->dropDownList(UserCreateForm::statusOptions()) ?>
                    </div>
                </div>

                <?= $form->field($model, 'department_id')->dropDownList($departmentItems, [
                    'prompt' => '-- เลือกกลุ่มงานที่กำกับดูแล / สังกัด --',
                ])->label('กลุ่มงานที่กำกับดูแล / สังกัดหลัก')->hint('จำเป็นสำหรับ รอง ผอ. และหัวหน้ากลุ่มงาน เพื่อกำหนดสายการบังคับบัญชาในการตรวจ/สั่งการเอกสาร') ?>

                <?= $form->field($model, 'learning_area')->dropDownList(User::learningAreaOptions(), [
                    'prompt' => '-- เลือกกลุ่มสาระการเรียนรู้ --',
                ])->label('กลุ่มสาระการเรียนรู้')->hint('สำหรับคุณครูผู้สอน (ผู้บริหาร หรือ เจ้าหน้าที่ ให้เลือก "ไม่สังกัดกลุ่มสาระ")') ?>

                <?= $form->field($model, 'department_ids')->checkboxList($departmentItems, [
                    'itemOptions' => ['class' => 'form-check-input'],
                ])->label('กลุ่มงานที่สังกัด / กำกับดูแลเพิ่มเติม')->hint('สำหรับครูผู้สอน/ผู้ใช้ทั่วไป หรือ รอง ผอ. ที่ดูแลมากกว่า 1 กลุ่มงาน') ?>

                <?= $form->field($model, 'signatureFile')->fileInput(['accept' => 'image/png,image/jpeg'])->hint('รองรับ PNG/JPG ขนาดไม่เกิน 2 MB พื้นหลังโปร่งใสจะสวยที่สุด') ?>

                <div class="form-actions">
                    <?= Html::a('<i class="bi bi-x-circle" aria-hidden="true"></i><span>ยกเลิก</span>', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
                    <?= Html::submitButton('<i class="bi bi-person-plus" aria-hidden="true"></i><span>สร้างผู้ใช้</span>', ['class' => 'btn btn-primary']) ?>
                </div>
            <?php ActiveForm::end(); ?>
        </section>

        <aside class="stamp-card">
            <h2>บทบาทในระบบ</h2>
            <dl class="meta-list">
                <div><dt>ธุรการ</dt><dd>ลงรับและเสนอรอง ผอ.</dd></div>
                <div><dt>รอง ผอ.</dt><dd>ลงความเห็นหรือส่งกลับแก้ไข</dd></div>
                <div><dt>ผอ.</dt><dd>สั่งการและมอบหมายงาน</dd></div>
                <div><dt>หัวหน้ากลุ่มงาน</dt><dd>รับงานของกลุ่มงานและส่งต่อผู้ปฏิบัติ</dd></div>
                <div><dt>ผู้ใช้ทั่วไป</dt><dd>รับงานที่ถูกส่งต่อและปิดเรื่องเมื่อดำเนินการเสร็จ</dd></div>
            </dl>
        </aside>
    </div>
</div>
