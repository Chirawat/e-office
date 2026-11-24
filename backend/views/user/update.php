<?php

declare(strict_types=1);

use backend\models\UserUpdateForm;
use common\models\Department;
use common\models\User;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var UserUpdateForm $model */
/** @var User $user */
/** @var Department[] $departments */

$this->title = 'แก้ไขผู้ใช้: ' . $user->username;
$this->params['breadcrumbs'][] = ['label' => 'ผู้ใช้และบทบาท', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'แก้ไขผู้ใช้';
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$signatureUrl = $user->signature_path ? Url::to('@web/' . ltrim($user->signature_path, '/'), true) : null;
$departmentItems = ArrayHelper::map($departments, 'id', 'name');
?>

<div class="eoffice-page">
    <div class="eoffice-titlebar">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <p>แก้ไขข้อมูลบัญชี บทบาท สถานะ และรหัสผ่าน</p>
        </div>
        <?= Html::a('กลับหน้าผู้ใช้', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
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

                <?= $form->field($model, 'fullname')->textInput(['maxlength' => true, 'placeholder' => 'เช่น นายสมชาย ใจดี'])->hint('ชื่อ-นามสกุลจริงพร้อมคำนำหน้า สำหรับแสดงในระบบและใต้ลายเซ็นเอกสาร PDF') ?>

                <?= $form->field($model, 'password')->passwordInput(['maxlength' => true])->hint('เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยนรหัสผ่าน') ?>

                <div class="row">
                    <div class="col-md-6">
                        <?= $form->field($model, 'role')->dropDownList(User::roleOptions()) ?>
                    </div>
                    <div class="col-md-6">
                        <?= $form->field($model, 'status')->dropDownList(UserUpdateForm::statusOptions()) ?>
                    </div>
                </div>

                <div id="deputy-title-wrapper" style="<?= $model->role === User::ROLE_DEPUTY_DIRECTOR ? '' : 'display: none;' ?>">
                    <?= $form->field($model, 'deputy_title')->dropDownList(User::deputyTitleOptions())
                        ->label('ตำแหน่งผู้บริหาร (รองผู้อำนวยการ / ผู้ช่วยผู้อำนวยการ)')
                        ->hint('เลือกระบุว่าเป็น "รองผู้อำนวยการ" หรือ "ผู้ช่วยผู้อำนวยการ" เพื่อแสดงผลในระบบและใต้ลายเซ็นเอกสาร PDF') ?>
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

                <?php if ($signatureUrl): ?>
                    <div class="signature-preview">
                        <span>ลายเซ็นปัจจุบัน</span>
                        <img src="<?= Html::encode($signatureUrl) ?>?v=<?= Html::encode((string) time()) ?>" alt="ลายเซ็น <?= Html::encode($user->username) ?>">
                    </div>
                <?php endif; ?>

                <?= $form->field($model, 'signatureFile')->fileInput(['accept' => 'image/png,image/jpeg'])->hint('อัปโหลดไฟล์ใหม่เมื่อต้องการเปลี่ยนลายเซ็น') ?>

                <div class="form-actions">
                    <?= Html::a('ยกเลิก', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
                    <?= Html::submitButton('บันทึกการแก้ไข', ['class' => 'btn btn-primary']) ?>
                </div>
            <?php ActiveForm::end(); ?>
        </section>

        <aside class="stamp-card">
            <h2>การลบผู้ใช้</h2>
            <p>ระบบจะปิดใช้งานบัญชีแทนการลบข้อมูลออกจากฐานข้อมูล เพื่อเก็บประวัติเอกสารและ audit log ให้ยังอ่านได้ครบ</p>
        </aside>
    </div>
</div>
<?php
$this->registerJs(<<<JS
(function() {
    var roleSelect = document.getElementById('userupdateform-role');
    var wrapper = document.getElementById('deputy-title-wrapper');
    if (!roleSelect || !wrapper) return;
    function toggleDeputyTitle() {
        wrapper.style.display = (roleSelect.value === 'deputy_director') ? '' : 'none';
    }
    roleSelect.addEventListener('change', toggleDeputyTitle);
    toggleDeputyTitle();
})();
JS);
?>
