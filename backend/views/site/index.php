<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\web\View $this */

$this->title = 'e-Office';
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
?>

<div class="landing-page">
    <section class="landing-hero" aria-labelledby="landing-title">
        <div class="landing-hero-copy">
            <p class="landing-eyebrow">
                <i class="bi bi-building-check" aria-hidden="true"></i>
                <span>ระบบสารบรรณอิเล็กทรอนิกส์</span>
            </p>
            <h1 id="landing-title">e-Office</h1>
            <p class="landing-copy">รับ เสนอ สั่งการ และติดตามหนังสือราชการภายในโรงเรียนด้วยเอกสาร PDF ที่มีตราประทับอิเล็กทรอนิกส์</p>
            <?= Html::a('<i class="bi bi-play-circle" aria-hidden="true"></i><span>เริ่มการใช้งาน</span>', ['/document/my'], ['class' => 'btn btn-primary btn-lg landing-button']) ?>

            <div class="landing-features" aria-label="จุดเด่นของระบบ">
                <span><i class="bi bi-file-earmark-check" aria-hidden="true"></i>ประทับตรา PDF</span>
                <span><i class="bi bi-send-check" aria-hidden="true"></i>เสนอหนังสือ</span>
                <span><i class="bi bi-clock-history" aria-hidden="true"></i>ติดตามสถานะ</span>
                <span><i class="bi bi-chat-dots" aria-hidden="true"></i>แจ้งเตือน LINE</span>
            </div>
        </div>
        <p class="landing-help">หากพบปัญหาการใช้งาน กรุณาติดต่อผู้ดูแลระบบ</p>
    </section>
</div>
