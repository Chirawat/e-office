<?php

declare(strict_types=1);

return [
    'timeZone' => 'Asia/Bangkok',
    'bootstrap' => [
        \common\bootstrap\MailerBootstrap::class,
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'components' => [
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'formatter' => [
            'class' => \yii\i18n\Formatter::class,
            'defaultTimeZone' => 'Asia/Bangkok',
            'timeZone' => 'Asia/Bangkok',
        ],
    ],
];
