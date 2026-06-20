<?php

declare(strict_types=1);

use common\models\Department;
use common\models\Document;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var backend\models\DocumentForm $form */
/** @var Department[] $departments */

$this->title = 'ลงทะเบียนหนังสือรับ';
$this->params['breadcrumbs'][] = ['label' => 'หนังสือรับ', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);

$departmentItems = ArrayHelper::map($departments, 'id', 'name');
$thaiDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return '-';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    $months = [
        1 => 'ม.ค.',
        2 => 'ก.พ.',
        3 => 'มี.ค.',
        4 => 'เม.ย.',
        5 => 'พ.ค.',
        6 => 'มิ.ย.',
        7 => 'ก.ค.',
        8 => 'ส.ค.',
        9 => 'ก.ย.',
        10 => 'ต.ค.',
        11 => 'พ.ย.',
        12 => 'ธ.ค.',
    ];

    return (int) date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . ((int) date('Y', $timestamp) + 543);
};
$thaiTime = static function (?string $time): string {
    if ($time === null || $time === '') {
        return '-';
    }

    $timestamp = strtotime($time);
    if ($timestamp === false) {
        return $time;
    }

    return date('H.i', $timestamp) . ' น.';
};
?>

<div class="eoffice-page">
    <div class="eoffice-titlebar">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <p>อัปโหลด PDF จาก SMSS แล้วสร้างข้อมูลตรารับอิเล็กทรอนิกส์</p>
        </div>
    </div>

    <?php $activeForm = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>
    <div class="form-grid">
        <div class="content-card">
            <div class="row">
                <div class="col-md-6">
                    <?= $activeForm->field($form->document, 'receive_no')->textInput(['maxlength' => true])->hint('ระบบรันเลขรับให้อัตโนมัติ แต่สามารถแก้ไขได้') ?>
                </div>
                <div class="col-md-3">
                    <?= $activeForm->field($form->document, 'receive_date')->input('date') ?>
                </div>
                <div class="col-md-3">
                    <?= $activeForm->field($form->document, 'receive_time')->input('time') ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <?= $activeForm->field($form->document, 'external_no')->textInput(['maxlength' => true]) ?>
                </div>
                <div class="col-md-6">
                    <?= $activeForm->field($form->document, 'external_date')->input('date') ?>
                </div>
            </div>

            <?= $activeForm->field($form->document, 'sender')->textInput(['maxlength' => true]) ?>
            <?= $activeForm->field($form->document, 'subject')->textarea(['rows' => 3]) ?>

            <div class="row">
                <div class="col-md-4">
                    <?= $activeForm->field($form->document, 'urgency')->dropDownList(Document::urgencyOptions()) ?>
                </div>
                <div class="col-md-4">
                    <?= $activeForm->field($form->document, 'department_id')->dropDownList($departmentItems, ['prompt' => 'เลือกกลุ่มงาน']) ?>
                </div>
                <div class="col-md-4">
                    <?= $activeForm->field($form->document, 'due_date')->input('date') ?>
                </div>
            </div>

            <?= $activeForm->field($form->document, 'note')->textarea(['rows' => 3]) ?>

            <section class="upload-section">
                <div class="upload-section-header">
                    <h2>ไฟล์เอกสาร</h2>
                    <span>1 ช่องต่อ 1 ไฟล์</span>
                </div>

                <?= $activeForm->field($form, 'pdfFile')->fileInput(['accept' => 'application/pdf'])->label('ไฟล์ PDF หนังสือต้นฉบับจาก SMSS') ?>

                <div class="upload-slot-grid">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                        <div class="upload-slot">
                            <label class="form-label" for="documentform-attachmentfiles-<?= $i ?>">ไฟล์แนบเพิ่มเติม <?= $i + 1 ?></label>
                            <?= Html::fileInput(
                                Html::getInputName($form, 'attachmentFiles') . '[]',
                                null,
                                [
                                    'id' => 'documentform-attachmentfiles-' . $i,
                                    'class' => 'form-control',
                                    'accept' => 'application/pdf',
                                ],
                            ) ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <?= Html::error($form, 'attachmentFiles', ['class' => 'invalid-feedback d-block']) ?>
            </section>

            <div class="form-actions">
                <?= Html::a('ยกเลิก', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
                <?= Html::submitButton('บันทึกและดูตราประทับ', ['class' => 'btn btn-primary']) ?>
            </div>
        </div>

        <aside class="stamp-card">
            <h2>ตัวอย่างตรารับ</h2>
            <div class="receive-stamp">
                <strong>โรงเรียนกุดชุมวิทยาคม</strong>
                <span>รับที่ <em data-stamp-preview="receive-no"><?= Html::encode($form->document->receive_no ?: '-') ?></em></span>
                <span>วันที่ <em data-stamp-preview="receive-date"><?= Html::encode($thaiDate($form->document->receive_date)) ?></em></span>
                <span>เวลา <em data-stamp-preview="receive-time"><?= Html::encode($thaiTime($form->document->receive_time)) ?></em></span>
            </div>
            <p>ระบบจะเก็บ PDF ต้นฉบับไว้ และสร้างข้อมูลตราประทับสำหรับนำไปวางบน PDF ในขั้นตอนถัดไป</p>
        </aside>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<?php
$this->registerJs(<<<'JS'
(function () {
    const receiveNoInput = document.getElementById('document-receive_no');
    const receiveDateInput = document.getElementById('document-receive_date');
    const receiveTimeInput = document.getElementById('document-receive_time');
    const receiveNoOutput = document.querySelector('[data-stamp-preview="receive-no"]');
    const receiveDateOutput = document.querySelector('[data-stamp-preview="receive-date"]');
    const receiveTimeOutput = document.querySelector('[data-stamp-preview="receive-time"]');
    const thaiMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    function formatThaiDate(value) {
        if (!value) {
            return '-';
        }

        const parts = value.split('-').map((part) => parseInt(part, 10));
        if (parts.length !== 3 || parts.some((part) => Number.isNaN(part))) {
            return value;
        }

        return `${parts[2]} ${thaiMonths[parts[1] - 1] || ''} ${parts[0] + 543}`;
    }

    function formatThaiTime(value) {
        if (!value) {
            return '-';
        }

        const parts = value.split(':');
        if (parts.length < 2) {
            return value;
        }

        return `${parts[0]}.${parts[1]} น.`;
    }

    function refreshStampPreview() {
        if (receiveNoOutput) {
            receiveNoOutput.textContent = receiveNoInput && receiveNoInput.value.trim() !== '' ? receiveNoInput.value.trim() : '-';
        }
        if (receiveDateOutput) {
            receiveDateOutput.textContent = formatThaiDate(receiveDateInput ? receiveDateInput.value : '');
        }
        if (receiveTimeOutput) {
            receiveTimeOutput.textContent = formatThaiTime(receiveTimeInput ? receiveTimeInput.value : '');
        }
    }

    [receiveNoInput, receiveDateInput, receiveTimeInput].forEach((input) => {
        if (input) {
            input.addEventListener('input', refreshStampPreview);
            input.addEventListener('change', refreshStampPreview);
        }
    });

    refreshStampPreview();
})();
JS);
?>
