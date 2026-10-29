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
            <p class="landing-tagline">โปรแกรมงานสารบรรณที่เข้าใจระบบเอกสารในโรงเรียน</p>
            <p class="landing-copy">รับหนังสือจาก SMSS เสนอหนังสือภายใน ทำ e-Memo ปั๊มตราและลายเซ็นบน PDF พร้อมติดตามสถานะงานได้ในที่เดียว</p>
            <div class="landing-actions">
                <?= Html::a('<i class="bi bi-play-circle" aria-hidden="true"></i><span>เริ่มการใช้งาน</span>', ['/document/my'], ['class' => 'btn btn-primary btn-lg landing-button']) ?>
                <?= Html::a('<i class="bi bi-box-seam" aria-hidden="true"></i><span>ดูแพ็กเกจ</span>', '#packages', ['class' => 'btn btn-outline-primary btn-lg landing-button']) ?>
            </div>

            <div class="landing-features" aria-label="จุดเด่นของระบบ">
                <span><i class="bi bi-file-earmark-check" aria-hidden="true"></i>ประทับตรา PDF</span>
                <span><i class="bi bi-send-check" aria-hidden="true"></i>เสนอหนังสือ</span>
                <span><i class="bi bi-clock-history" aria-hidden="true"></i>ติดตามสถานะ</span>
                <span><i class="bi bi-chat-dots" aria-hidden="true"></i>แจ้งเตือน LINE</span>
            </div>
        </div>
        <p class="landing-help">หากพบปัญหาการใช้งาน กรุณาติดต่อผู้ดูแลระบบ</p>
    </section>

    <section class="landing-section landing-values" aria-labelledby="landing-values-title">
        <div class="landing-section-head">
            <p>คุณค่าที่โรงเรียนจะได้รับ</p>
            <h2 id="landing-values-title">ลดภาระงานเอกสาร โดยยังคงความรู้สึกเหมือนอ่านบนกระดาษจริง</h2>
        </div>
        <div class="landing-value-grid">
            <article>
                <i class="bi bi-archive" aria-hidden="true"></i>
                <h3>เอกสารไม่สูญหาย</h3>
                <p>จัดเก็บ PDF ต้นฉบับและฉบับประทับตราเป็นระบบ ตรวจสอบย้อนหลังได้</p>
            </article>
            <article>
                <i class="bi bi-search" aria-hidden="true"></i>
                <h3>ค้นหาเอกสารได้ทุกเมื่อ</h3>
                <p>ค้นจากเลขที่หนังสือ ชื่อเรื่อง หรือเลขบันทึก ลดเวลารื้อแฟ้มเอกสาร</p>
            </article>
            <article>
                <i class="bi bi-diagram-3" aria-hidden="true"></i>
                <h3>เห็นสถานะทุกขั้นตอน</h3>
                <p>รู้ว่าเอกสารอยู่ที่เจ้าหน้าที่ หัวหน้ากลุ่ม รอง ผอ. ผอ. หรือผู้ปฏิบัติ</p>
            </article>
            <article>
                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                <h3>PDF พร้อมตราประทับ</h3>
                <p>อ่านเอกสารที่มีตรารับ ความเห็น คำสั่ง และลายเซ็นเหมือนเอกสารจริง</p>
            </article>
        </div>
    </section>

    <section id="packages" class="landing-section landing-packages" aria-labelledby="landing-packages-title">
        <div class="landing-section-head">
            <p>แพ็กเกจการใช้งาน</p>
            <h2 id="landing-packages-title">เลือกขนาดระบบให้เหมาะกับโรงเรียน</h2>
        </div>
        <div class="landing-package-grid">
            <article class="package-card">
                <span>Starter</span>
                <h3>เริ่มต้นใช้งาน</h3>
                <p>สำหรับโรงเรียนที่ต้องการเริ่มลดกระดาษและจัดระบบหนังสือรับ</p>
                <ul>
                    <li>หนังสือรับและ e-Memo</li>
                    <li>ประทับตราและลายเซ็น PDF</li>
                    <li>ค้นหาและติดตามสถานะ</li>
                    <li>ผู้ใช้และพื้นที่ไฟล์เริ่มต้น</li>
                </ul>
            </article>
            <article class="package-card recommended">
                <span>School</span>
                <h3>แพ็กเกจแนะนำ</h3>
                <p>สำหรับโรงเรียนที่ต้องการ workflow ครบตั้งแต่ธุรการถึงผู้บริหาร</p>
                <ul>
                    <li>ทุกอย่างใน Starter</li>
                    <li>แจ้งเตือนผ่าน LINE OA</li>
                    <li>ปรับตำแหน่งตราและลายเซ็น</li>
                    <li>จัดการบทบาทและกลุ่มงาน</li>
                </ul>
            </article>
            <article class="package-card">
                <span>School Plus</span>
                <h3>ขยายทั้งโรงเรียน</h3>
                <p>สำหรับโรงเรียนขนาดใหญ่หรือกลุ่มโรงเรียนที่ต้องการดูแลพิเศษ</p>
                <ul>
                    <li>พื้นที่ไฟล์และผู้ใช้มากขึ้น</li>
                    <li>ปรับ workflow เพิ่มเติม</li>
                    <li>อบรมและวางระบบการใช้งาน</li>
                    <li>Priority support</li>
                </ul>
            </article>
        </div>
    </section>

    <section class="landing-section landing-contact" aria-labelledby="landing-contact-title">
        <div>
            <p class="landing-contact-eyebrow">N Academy</p>
            <h2 id="landing-contact-title">ให้คำปรึกษา วางระบบ และอบรมการใช้งาน e-Office สำหรับโรงเรียน</h2>
            <p>ติดต่อ N Academy เพื่อประเมินรูปแบบการใช้งาน แพ็กเกจ และแนวทางนำระบบสารบรรณอิเล็กทรอนิกส์ไปใช้จริงในโรงเรียน</p>
        </div>
        <div class="landing-contact-card">
            <strong>ติดต่อ N Academy</strong>
            <span><i class="bi bi-line" aria-hidden="true"></i> Line OA: <?= Html::a('@341ffebo', 'https://lin.ee/341ffebo', ['target' => '_blank', 'rel' => 'noopener']) ?></span>
            <span><i class="bi bi-telephone" aria-hidden="true"></i> โทรศัพท์: <?= Html::a('084-8313126', 'tel:0848313126') ?></span>
            <span><i class="bi bi-envelope" aria-hidden="true"></i> อีเมล: <?= Html::a('nacademyth@gmail.com', 'mailto:nacademyth@gmail.com') ?></span>
        </div>
    </section>
</div>
