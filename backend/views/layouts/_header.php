<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use common\models\User;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\helpers\Html;

$identity = Yii::$app->user->identity;
$roleLabel = $identity instanceof User ? $identity->roleLabel() : '';
$userName = $identity instanceof User ? $identity->displayName() : '';
$isAdmin = $identity instanceof User && $identity->hasRole(User::ROLE_ADMIN);
$isOfficer = $identity instanceof User && ($identity->hasRole(User::ROLE_OFFICER) || $isAdmin);

$mainItems = [];

// 1. หน้าหลัก
$mainItems[] = [
    'label' => '<i class="bi bi-speedometer2 me-1" aria-hidden="true"></i><span>โต๊ะทำงาน (งานของฉัน)</span>',
    'url' => ['/document/my'],
    'visible' => !Yii::$app->user->isGuest,
];

// 2. หมวดหนังสือรับ (Document Group)
$docItems = [
    [
        'label' => '<i class="bi bi-inbox me-2 text-primary" aria-hidden="true"></i><span>ทะเบียนหนังสือรับทั้งหมด</span>',
        'url' => ['/document/index'],
    ],
];
if ($isOfficer) {
    $docItems[] = '-'; // เส้นคั่น divider
    $docItems[] = [
        'label' => '<i class="bi bi-plus-circle me-2 text-success" aria-hidden="true"></i><span>ลงรับหนังสือใหม่</span>',
        'url' => ['/document/create'],
    ];
}
$mainItems[] = [
    'label' => '<i class="bi bi-folder2-open me-1" aria-hidden="true"></i><span>งานหนังสือรับ</span>',
    'items' => $docItems,
    'visible' => !Yii::$app->user->isGuest,
];

// 3. หมวดบันทึกข้อความ (Memo Group)
$memoItems = [
    [
        'label' => '<i class="bi bi-journal-text me-2 text-primary" aria-hidden="true"></i><span>รายการบันทึกเสนอทั้งหมด</span>',
        'url' => ['/memo/index'],
    ],
    [
        'label' => '<i class="bi bi-person-lines-fill me-2 text-info" aria-hidden="true"></i><span>บันทึกข้อความของฉัน</span>',
        'url' => ['/memo/my'],
    ],
    '-',
    [
        'label' => '<i class="bi bi-file-earmark-plus me-2 text-success" aria-hidden="true"></i><span>สร้างบันทึกเสนอใหม่</span>',
        'url' => ['/memo/create'],
    ],
];
$mainItems[] = [
    'label' => '<i class="bi bi-pencil-square me-1" aria-hidden="true"></i><span>บันทึกข้อความ (e-Memo)</span>',
    'items' => $memoItems,
    'visible' => !Yii::$app->user->isGuest,
];

// 4. หมวดจัดการระบบ (สำหรับ Admin)
if ($isAdmin) {
    $adminItems = [
        [
            'label' => '<i class="bi bi-people me-2 text-primary" aria-hidden="true"></i><span>จัดการผู้ใช้และบทบาท</span>',
            'url' => ['/user/index'],
        ],
        [
            'label' => '<i class="bi bi-person-plus me-2 text-success" aria-hidden="true"></i><span>สร้างผู้ใช้ใหม่</span>',
            'url' => ['/user/create'],
        ],
        '-',
        [
            'label' => '<i class="bi bi-chat-dots me-2 text-success" aria-hidden="true"></i><span>ตั้งค่า LINE OA & Token</span>',
            'url' => ['/line/link'],
        ],
    ];
    $mainItems[] = [
        'label' => '<i class="bi bi-gear me-1" aria-hidden="true"></i><span>จัดการระบบ</span>',
        'items' => $adminItems,
    ];
}

// ฝั่งขวา: โปรไฟล์ผู้ใช้ และการตั้งค่าส่วนตัว
$rightItems = [];

if (Yii::$app->user->isGuest) {
    $rightItems[] = [
        'label' => '<i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i><span>เข้าสู่ระบบ</span>',
        'url' => ['/site/login'],
    ];
    $rightItems[] = [
        'label' => '<i class="bi bi-person-plus me-1" aria-hidden="true"></i><span>สมัครสมาชิก</span>',
        'url' => ['/site/signup'],
    ];
} else {
    // Dropdown ข้อมูลผู้ใช้
    $userMenuItems = [
        [
            'label' => '<div class="px-2 py-1"><div class="fw-bold text-dark">' . Html::encode($userName) . '</div><div class="small text-muted">' . Html::encode($roleLabel) . '</div></div>',
            'encode' => false,
            'options' => ['class' => 'bg-light border-bottom'],
        ],
        [
            'label' => '<i class="bi bi-chat-dots me-2 text-success" aria-hidden="true"></i><span>เชื่อมต่อ LINE ของฉัน</span>',
            'url' => ['/line/link'],
        ],
        '-',
        [
            'label' => '<i class="bi bi-box-arrow-right me-2 text-danger" aria-hidden="true"></i><span>ออกจากระบบ</span>',
            'url' => ['/site/logout'],
            'linkOptions' => [
                'data-method' => 'post',
                'class' => 'text-danger fw-semibold',
            ],
        ],
    ];

    $rightItems[] = [
        'label' => '<div class="d-inline-flex align-items-center gap-2"><span class="badge rounded-circle bg-white text-primary p-1" style="width: 24px; height: 24px; display: inline-grid; place-items: center;"><i class="bi bi-person-fill"></i></span><span>' . Html::encode($userName) . '</span><span class="badge bg-white bg-opacity-20 text-white ms-1 fw-normal">' . Html::encode($roleLabel) . '</span></div>',
        'items' => $userMenuItems,
        'encode' => false,
    ];
}
?>
<header id="header">
    <?php NavBar::begin(
        [
            'brandLabel' => '<div class="d-inline-flex align-items-center gap-2"><span class="brand-badge-icon shadow-sm"><i class="bi bi-building-check text-primary"></i></span><span class="fw-bold tracking-wide">e-Office</span></div>',
            'brandUrl' => Yii::$app->user->isGuest ? ['/site/index'] : ['/document/my'],
            'options' => ['class' => 'navbar navbar-expand-lg navbar-dark fixed-top eoffice-navbar']
        ],
    ) ?>
    <?= Nav::widget(
        [
            'options' => ['class' => 'navbar-nav me-auto align-items-lg-center'],
            'encodeLabels' => false,
            'items' => $mainItems,
        ],
    ) ?>
    <?= Nav::widget(
        [
            'options' => ['class' => 'navbar-nav ms-auto align-items-lg-center'],
            'encodeLabels' => false,
            'items' => $rightItems,
        ],
    ) ?>
    <?php NavBar::end() ?>
</header>
