<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $content */

use backend\assets\AppAsset;
use yii\helpers\Html;

AppAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="min-vh-100" data-bs-theme="light">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
    <?= $this->render('_analytics') ?>
</head>
<body class="d-flex flex-column align-items-center min-vh-100 py-3 py-md-4 px-2" style="background-color: #f1f5f9; min-height: 100vh; min-height: 100dvh; overflow-x: hidden; overflow-y: auto; -webkit-overflow-scrolling: touch;">
<?php $this->beginBody() ?>

<?= $content ?>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage();
