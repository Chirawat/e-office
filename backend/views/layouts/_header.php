<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use common\models\User;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\helpers\Html;

$identity = Yii::$app->user->identity;
$roleLabel = $identity instanceof User ? $identity->roleLabel() : '';
$isAdmin = $identity instanceof User && $identity->hasRole(User::ROLE_ADMIN);
$mainItems = [
    [
        'label' => '<i class="bi bi-house-door" aria-hidden="true"></i><span>หน้าหลัก</span>',
        'url' => ['/site/index'],
    ],
    [
        'label' => '<i class="bi bi-inbox" aria-hidden="true"></i><span>หนังสือรับ</span>',
        'url' => ['/document/index'],
        'visible' => !Yii::$app->user->isGuest,
    ],
    [
        'label' => '<i class="bi bi-journal-text" aria-hidden="true"></i><span>บันทึกเสนอ</span>',
        'url' => ['/memo/index'],
        'visible' => !Yii::$app->user->isGuest,
    ],
    [
        'label' => '<i class="bi bi-list-check" aria-hidden="true"></i><span>งานของฉัน</span>',
        'url' => ['/document/my'],
        'visible' => !Yii::$app->user->isGuest,
    ],
    [
        'label' => '<i class="bi bi-chat-dots" aria-hidden="true"></i><span>เชื่อมต่อ LINE</span>',
        'url' => ['/line/link'],
        'visible' => !Yii::$app->user->isGuest,
    ],
    [
        'label' => '<i class="bi bi-people" aria-hidden="true"></i><span>ผู้ใช้</span>',
        'url' => ['/user/index'],
        'visible' => $isAdmin,
    ],
];
$rightItems = [
    [
        'label' => '<i class="bi bi-box-arrow-in-right" aria-hidden="true"></i><span>Login</span>',
        'url' => ['/site/login'],
        'visible' => Yii::$app->user->isGuest,
    ],
    [
        'label' => '<i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Logout (' . Html::encode(Yii::$app->user->identity?->username) . ($roleLabel !== '' ? ' - ' . Html::encode($roleLabel) : '') . ')</span>',
        'url' => ['/site/logout'],
        'linkOptions' => [
            'data-method' => 'post',
            'class' => 'logout',
        ],
        'visible' => !Yii::$app->user->isGuest,
    ],
];
?>
<header id="header">
    <?php NavBar::begin(
        [
            'brandLabel' => Yii::$app->name,
            'brandUrl' => Yii::$app->homeUrl,
            'options' => ['class' => 'navbar navbar-expand-md navbar-dark fixed-top eoffice-navbar']
        ],
    ) ?>
    <?= Nav::widget(
        [
            'options' => ['class' => 'navbar-nav me-auto'],
            'encodeLabels' => false,
            'items' => $mainItems,
        ],
    ) ?>
    <?= Nav::widget(
        [
            'options' => ['class' => 'navbar-nav ms-auto'],
            'encodeLabels' => false,
            'items' => $rightItems,
        ],
    ) ?>
    <?php NavBar::end() ?>
</header>
