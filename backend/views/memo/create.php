<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var backend\models\MemoForm $form */
/** @var common\models\Department[] $departments */

use yii\helpers\Html;

$this->title = 'สร้างบันทึกเสนอ';
$this->params['breadcrumbs'][] = ['label' => 'บันทึกเสนอ', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
?>

<div class="eoffice-page">
    <div class="eoffice-titlebar">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <p>จัดทำบันทึกข้อความภายในโรงเรียน แนบไฟล์ประกอบ และเสนอผู้เกี่ยวข้อง</p>
        </div>
        <?= Html::a('<i class="bi bi-arrow-left" aria-hidden="true"></i><span>กลับรายการ</span>', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?= $this->render('_form', ['form' => $form, 'departments' => $departments]) ?>
</div>
