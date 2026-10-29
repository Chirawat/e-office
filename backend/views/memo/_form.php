<?php

declare(strict_types=1);

use backend\models\MemoForm;
use common\models\Department;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var MemoForm $form */
/** @var Department[] $departments */

$memo = $form->memo;
?>

<?php $activeForm = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data', 'class' => 'memo-form']]) ?>
    <div class="form-grid">
        <div class="content-card">
            <div class="row">
                <div class="col-md-6">
                    <?= $activeForm->field($memo, 'memo_no')->textInput(['maxlength' => true, 'placeholder' => 'เว้นว่างได้ในระยะ MVP']) ?>
                </div>
                <div class="col-md-6">
                    <?= $activeForm->field($memo, 'memo_date')->input('date') ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <?= $activeForm->field($memo, 'department_id')->dropDownList(ArrayHelper::map($departments, 'id', 'name'), ['prompt' => 'เลือกกลุ่มงาน']) ?>
                </div>
                <div class="col-md-6">
                    <?= $activeForm->field($memo, 'to_text')->textInput(['maxlength' => true]) ?>
                </div>
            </div>

            <?= $activeForm->field($memo, 'subject')->textInput(['maxlength' => true]) ?>

            <section class="upload-section">
                <div class="upload-section-header">
                    <h2>PDF บันทึกข้อความ</h2>
                    <span>ไฟล์หลักสำหรับเสนอพิจารณา</span>
                </div>

                <?= $activeForm->field($form, 'memoPdfFile')->fileInput([
                    'accept' => 'application/pdf',
                ])->hint('แนบ PDF บันทึกข้อความที่จัดหน้าเรียบร้อยแล้ว') ?>

                <?php if (!$memo->isNewRecord && $memo->originalPdfFile !== null): ?>
                    <div class="attachment-list">
                        <?= Html::a('<i class="bi bi-file-earmark-pdf" aria-hidden="true"></i><span>PDF บันทึกข้อความต้นฉบับปัจจุบัน</span>', Url::to('@web/' . ltrim($memo->originalPdfFile->storage_path, '/'), true), [
                            'class' => 'attachment-link',
                            'target' => '_blank',
                        ]) ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="upload-section">
                <div class="upload-section-header">
                    <h2>ไฟล์แนบประกอบ</h2>
                    <span>PDF เพิ่มเติม สูงสุด 5 ไฟล์</span>
                </div>

                <?= $activeForm->field($form, 'attachmentFiles[]')->fileInput([
                    'multiple' => true,
                    'accept' => 'application/pdf',
                ])->hint('แนบไฟล์ PDF ได้สูงสุด 5 ไฟล์') ?>

                <?php if (!$memo->isNewRecord && $memo->attachmentFiles !== []): ?>
                    <div class="attachment-list">
                        <?php foreach ($memo->attachmentFiles as $file): ?>
                            <?= Html::a('<i class="bi bi-file-earmark-pdf" aria-hidden="true"></i><span>' . Html::encode($file->original_name) . '</span>', Url::to('@web/' . ltrim($file->storage_path, '/'), true), [
                                'class' => 'attachment-link',
                                'target' => '_blank',
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <div class="form-actions">
                <?= Html::a('ยกเลิก', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
                <?= Html::submitButton('บันทึกร่าง', ['class' => 'btn btn-outline-primary', 'name' => 'submit', 'value' => '0']) ?>
                <?= Html::submitButton('บันทึกและเสนอ', ['class' => 'btn btn-primary', 'name' => 'submit', 'value' => '1']) ?>
            </div>
        </div>

        <aside class="stamp-card">
            <h2>การประทับลายเซ็น</h2>
            <div class="receive-stamp memo-upload-stamp">
                <strong>PDF ต้นฉบับ</strong>
                <span>แนบไฟล์ที่จัดหน้าเรียบร้อยแล้ว</span>
                <span>ปรับตำแหน่งลายเซ็นในหน้า view</span>
                <span>ระบบสร้าง PDF ประทับตราให้อัตโนมัติ</span>
            </div>
            <p>หลังบันทึกแล้วสามารถเปิดบันทึกเพื่อปรับตำแหน่งลายเซ็น/ความเห็นด้วย slider ได้</p>
        </aside>
    </div>
<?php ActiveForm::end() ?>
