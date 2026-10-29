<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var backend\models\MemoForm $form */
/** @var common\models\Department[] $departments */

use yii\helpers\Html;

$this->title = 'แก้ไขบันทึกเสนอ';
$this->params['breadcrumbs'][] = ['label' => 'บันทึกเสนอ', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
?>

<div class="eoffice-page">
    <div class="eoffice-titlebar">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <p><?= Html::encode($form->memo->subject) ?></p>
        </div>
        <?= Html::a('<i class="bi bi-arrow-left" aria-hidden="true"></i><span>กลับรายละเอียด</span>', ['view', 'id' => $form->memo->id], ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?= $this->render('_form', ['form' => $form, 'departments' => $departments]) ?>
</div>
