<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $content */

use common\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\helpers\Html;

$this->render('_head');
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100" data-bs-theme="light">
<head>
    <meta name="color-scheme" content="light">
    <script>
        try {
            localStorage.removeItem('theme');
            document.documentElement.setAttribute('data-bs-theme', 'light');
        } catch (e) {}
    </script>
    <?php $this->head() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?= $this->render('_analytics') ?>
</head>
<body class="d-flex flex-column h-100 eoffice-body">
<?php $this->beginBody() ?>

<?= $this->render('_header') ?>

<main id="main" class="flex-grow-1 eoffice-main" role="main">
    <div class="container eoffice-container">
        <?= Alert::widget() ?>
        <?= $content ?>
    </div>
</main>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage();
