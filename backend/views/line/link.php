<?php

declare(strict_types=1);

use common\models\User;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var User $user */

$this->title = 'เชื่อมต่อ LINE';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$isLinked = $user->line_user_id !== null && $user->line_user_id !== '';
$tokenActive = $user->line_link_token !== null
    && $user->line_link_token !== ''
    && $user->line_link_token_expires_at !== null
    && (int) $user->line_link_token_expires_at > time();
?>

<div class="eoffice-page">
    <div class="eoffice-titlebar">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <p>เชื่อมบัญชี e-Office กับ LINE Official Account เพื่อรับแจ้งเตือนเมื่องานถูกมอบหมาย</p>
        </div>
    </div>

    <div class="form-grid">
        <section class="content-card">
            <?php if ($isLinked): ?>
                <h2>เชื่อมต่อแล้ว</h2>
                <p class="muted-text">บัญชีนี้พร้อมรับแจ้งเตือนผ่าน LINE แล้ว</p>
                <?= Html::a('<i class="bi bi-link-45deg" aria-hidden="true"></i><span>ยกเลิกการเชื่อมต่อ</span>', ['unlink'], [
                    'class' => 'btn btn-outline-danger',
                    'data-method' => 'post',
                    'data-confirm' => 'ยืนยันยกเลิกการเชื่อมต่อ LINE หรือไม่?',
                ]) ?>
            <?php else: ?>
                <h2>ขั้นตอนเชื่อมต่อ</h2>
                <ol class="line-link-steps">
                    <li>กดปุ่ม “สร้างรหัสเชื่อมต่อ LINE”</li>
                    <li>เปิดแชท LINE Official Account ของโรงเรียน</li>
                    <li>ส่งรหัสที่ระบบแสดงให้ในแชท LINE OA ภายใน 15 นาที</li>
                </ol>

                <?php if ($tokenActive): ?>
                    <div class="line-link-token"><?= Html::encode($user->line_link_token) ?></div>
                    <p class="muted-text">รหัสนี้จะหมดอายุเวลา <?= Html::encode(date('H:i', (int) $user->line_link_token_expires_at)) ?> น.</p>
                <?php endif; ?>

                <?= Html::beginForm(['link'], 'post') ?>
                    <?= Html::submitButton('<i class="bi bi-chat-dots" aria-hidden="true"></i><span>สร้างรหัสเชื่อมต่อ LINE</span>', ['class' => 'btn btn-primary']) ?>
                <?= Html::endForm() ?>
            <?php endif; ?>
        </section>

        <aside class="stamp-card">
            <h2>Webhook URL</h2>
            <p>นำ URL นี้ไปตั้งค่าใน LINE Developers เมื่อ deploy หรือเปิด tunnel สำหรับทดสอบ</p>
            <code class="line-webhook-url"><?= Html::encode(\yii\helpers\Url::to(['/line/webhook'], true)) ?></code>
        </aside>
    </div>
</div>
