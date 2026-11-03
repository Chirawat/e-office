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

// 1. หน้าแรก
$mainItems[] = [
    'label' => '<i class="bi bi-house-door me-1" aria-hidden="true"></i><span>หน้าแรก</span>',
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
    'label' => '<i class="bi bi-folder2-open me-1" aria-hidden="true"></i><span>หนังสือภายนอก</span>',
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
    'label' => '<i class="bi bi-pencil-square me-1" aria-hidden="true"></i><span>บันทึกข้อความ</span>',
    'items' => $memoItems,
    'visible' => !Yii::$app->user->isGuest,
];

// 4. หมวดจัดการระบบ (สำหรับ Admin)
if ($isAdmin) {
    $adminItems = [
        [
            'label' => '<i class="bi bi-sliders me-2 text-primary" aria-hidden="true"></i><span>ตั้งค่าระบบ</span>',
            'url' => ['/setting/index'],
        ],
        '-',
        [
            'label' => '<i class="bi bi-people me-2 text-info" aria-hidden="true"></i><span>จัดการผู้ใช้และบทบาท</span>',
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
    // กำหนดสี ไอคอน และสไตล์ badge ตามบทบาทหน้าที่ (Role)
    $isActingDirector = $identity instanceof User && \common\models\SystemSetting::isActingDirector() && (int) $identity->id === (int) \common\models\SystemSetting::getActingDirectorUserId();
    $rawRole = $identity instanceof User ? $identity->role : '';

    if ($isAdmin) {
        $roleTheme = [
            'badge' => 'bg-danger text-white',
            'icon' => 'bi-shield-fill-check',
            'avatarBg' => 'bg-danger text-white',
        ];
    } elseif ($isActingDirector) {
        $roleTheme = [
            'badge' => 'bg-warning text-dark',
            'icon' => 'bi-star-fill',
            'avatarBg' => 'bg-warning text-dark',
        ];
    } elseif ($rawRole === User::ROLE_DIRECTOR) {
        $roleTheme = [
            'badge' => 'bg-warning text-dark',
            'icon' => 'bi-award-fill',
            'avatarBg' => 'bg-warning text-dark',
        ];
    } elseif ($rawRole === User::ROLE_DEPUTY_DIRECTOR) {
        $roleTheme = [
            'badge' => 'bg-info text-dark',
            'icon' => 'bi-person-badge-fill',
            'avatarBg' => 'bg-info text-dark',
        ];
    } elseif ($rawRole === User::ROLE_OFFICER) {
        $roleTheme = [
            'badge' => 'bg-success text-white',
            'icon' => 'bi-pencil-square',
            'avatarBg' => 'bg-success text-white',
        ];
    } elseif ($rawRole === User::ROLE_STAFF) {
        $roleTheme = [
            'badge' => 'bg-primary text-white border border-light border-opacity-25',
            'icon' => 'bi-diagram-3-fill',
            'avatarBg' => 'bg-primary text-white',
        ];
    } else {
        $roleTheme = [
            'badge' => 'bg-light text-dark',
            'icon' => 'bi-person-fill',
            'avatarBg' => 'bg-light text-dark',
        ];
    }

    $deptName = ($identity instanceof User && $identity->department !== null) ? $identity->department->name : null;

    // Dropdown ข้อมูลผู้ใช้
    $userMenuItems = [
        [
            'label' => '<div class="px-3 py-2">'
                . '<div class="d-flex align-items-center gap-2 mb-2">'
                . '<span class="badge rounded-circle ' . $roleTheme['avatarBg'] . ' shadow-sm" style="width: 36px; height: 36px; display: inline-grid; place-items: center; font-size: 1.1rem;"><i class="bi ' . $roleTheme['icon'] . '"></i></span>'
                . '<div class="lh-sm overflow-hidden">'
                . '<div class="fw-bold text-dark text-truncate" style="max-width: 200px;">' . Html::encode($userName) . '</div>'
                . ($identity instanceof User && $identity->username ? '<div class="small text-muted text-truncate" style="max-width: 200px;">@' . Html::encode($identity->username) . '</div>' : '')
                . '</div>'
                . '</div>'
                . '<div class="d-flex flex-wrap align-items-center gap-1 pt-1 border-top">'
                . '<span class="badge ' . $roleTheme['badge'] . ' shadow-sm py-1 px-2"><i class="bi ' . $roleTheme['icon'] . ' me-1"></i>' . Html::encode($roleLabel) . '</span>'
                . ($deptName ? '<span class="badge bg-secondary-subtle text-secondary-emphasis py-1 px-2 border"><i class="bi bi-building me-1"></i>' . Html::encode($deptName) . '</span>' : '')
                . '</div>'
                . '</div>',
            'encode' => false,
            'options' => ['class' => 'bg-light bg-opacity-75 border-bottom'],
        ],
        [
            'label' => '<i class="bi bi-box-arrow-right me-2 text-danger" aria-hidden="true"></i><span>ออกจากระบบ</span>',
            'url' => ['/site/logout'],
            'linkOptions' => [
                'data-method' => 'post',
                'class' => 'text-danger fw-semibold',
            ],
        ],
    ];

    // ปุ่มแสดงข้อมูล User บนแถบ Navbar (แสดงชื่อ พร้อม Role Badge เด่นชัด)
    $userButtonHtml = '<div class="d-inline-flex align-items-center gap-2 py-1">'
        . '<span class="badge rounded-circle ' . $roleTheme['avatarBg'] . ' shadow-sm" style="width: 28px; height: 28px; display: inline-grid; place-items: center; font-size: 0.9rem;"><i class="bi ' . $roleTheme['icon'] . '"></i></span>'
        . '<div class="d-flex flex-column text-start lh-1">'
        . '<span class="fw-semibold text-white small">' . Html::encode($userName) . '</span>'
        . '<span class="badge ' . $roleTheme['badge'] . ' shadow-sm mt-1 fw-normal px-2 py-0 text-truncate" style="font-size: 0.72rem; letter-spacing: 0.01em; max-width: 160px;">' . Html::encode($roleLabel) . '</span>'
        . '</div>'
        . '</div>';

    $rightItems[] = [
        'label' => $userButtonHtml,
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
