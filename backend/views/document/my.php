<?php

declare(strict_types=1);

use common\models\Document;
use common\models\Memo;
use common\models\User;
use yii\data\ActiveDataProvider;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var common\models\User $identity */
/** @var ActiveDataProvider $docDataProvider */
/** @var ActiveDataProvider $memoDataProvider */
/** @var array<string, int> $stats */
/** @var string $roleLabel */

$this->title = 'โต๊ะทำงาน (งานของฉัน)';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);

$docModels = $docDataProvider->getModels();
$memoModels = $memoDataProvider->getModels();

$thaiDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return '-';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    $months = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
        7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.',
    ];

    return (int) date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . ((int) date('Y', $timestamp) + 543);
};

$memoStatusBadge = static function (Memo $model): string {
    $label = Memo::statusOptions()[$model->status] ?? $model->status;
    return match ($model->status) {
        Memo::STATUS_DRAFT => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">ฉบับร่าง</span>',
        Memo::STATUS_SUBMITTED_TO_STAFF => '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">รอหัวหน้ากลุ่มงาน</span>',
        Memo::STATUS_SUBMITTED_TO_DEPUTY => '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">รอรอง ผอ.</span>',
        Memo::STATUS_SUBMITTED_TO_DIRECTOR => '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">รอ ผอ. พิจารณา</span>',
        Memo::STATUS_RETURNED => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">ส่งกลับแก้ไข</span>',
        Memo::STATUS_APPROVED => '<span class="badge bg-success-subtle text-success border border-success-subtle">อนุมัติแล้ว</span>',
        Memo::STATUS_COMPLETED => '<span class="badge bg-success text-white">เสร็จสิ้น</span>',
        default => '<span class="badge bg-light text-dark border">' . Html::encode($label) . '</span>',
    };
};
?>

<div class="eoffice-page">

    <!-- Header สรุปข้อมูลผู้ใช้แบบกะทัดรัด (Mobile-First Minimal Header) -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h1 class="h5 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-briefcase-fill text-primary"></i>
                <span>โต๊ะทำงานของฉัน</span>
            </h1>
            <div class="small text-muted d-flex align-items-center gap-2 mt-1 flex-wrap">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= Html::encode($roleLabel) ?></span>
                <span class="fw-semibold text-dark"><?= Html::encode($identity->displayName()) ?></span>
                <?php if ($identity->department !== null): ?>
                    <span class="text-muted">• <?= Html::encode($identity->department->name) ?></span>
                <?php endif; ?>
                <span class="text-muted">• วันที่ <?= date('d/m/Y') ?></span>
            </div>
        </div>
    </div>

    <!-- แถบสรุปเฉพาะงานค้างที่ต้อง Take Action (Action-Focused Badges) -->
    <div class="p-2 bg-white rounded-3 border shadow-sm mb-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="small text-muted fw-bold me-1"><i class="bi bi-bell-fill text-warning me-1"></i>ภาระงานค้างของคุณ:</span>

            <?php if ($stats['doc_pending'] === 0 && $stats['memo_active'] === 0): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-3 d-inline-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>ยอดเยี่ยมมาก! ไม่มีงานค้างบนโต๊ะของคุณในขณะนี้</span>
                </span>
            <?php else: ?>
                <!-- Badge 1: หนังสือภายนอกที่ต้องทำ -->
                <a href="#section-doc" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-2 px-3 text-decoration-none d-inline-flex align-items-center gap-2">
                    <i class="bi bi-inbox-fill"></i>
                    <span>หนังสือภายนอกรอทำ:</span>
                    <strong class="fs-6 font-monospace"><?= Html::encode((string) $stats['doc_pending']) ?></strong>
                    <span>เรื่อง</span>
                </a>

                <!-- Badge 2: บันทึกข้อความที่กำลังเสนอ/รอการตรวจ -->
                <a href="#section-memo" class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 py-2 px-3 text-decoration-none d-inline-flex align-items-center gap-2">
                    <i class="bi bi-hourglass-split text-warning"></i>
                    <span>e-Memo กำลังเสนอ/รอพิจารณา:</span>
                    <strong class="fs-6 font-monospace"><?= Html::encode((string) $stats['memo_active']) ?></strong>
                    <span>เรื่อง</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- โครงสร้าง 2 แฟ้มงานหลักบนโต๊ะทำงาน (Dual-Panel Clean List) -->
    <div class="row g-3">

        <!-- ฝั่งซ้าย: หนังสือภายนอกที่มอบหมายถึงตนเอง -->
        <div class="col-lg-6 col-12" id="section-doc">
            <div class="content-card h-100 mb-0 d-flex flex-column border shadow-sm">
                
                <!-- หัวการ์ด: ชัดเจน ไม่เทอะทะ -->
                <div class="table-header-banner py-2 px-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-inbox-fill text-warning"></i>
                        <span class="fw-semibold">1. หนังสือภายนอก (ที่มอบหมายถึงคุณ)</span>
                        <span class="badge bg-white text-primary rounded-pill font-monospace"><?= count($docModels) ?></span>
                    </div>
                    <a href="<?= Url::to(['/document/index', 'scope' => 'my']) ?>" class="btn btn-sm btn-outline-light py-0 px-2 small">
                        ดูทั้งหมด <i class="bi bi-chevron-right"></i>
                    </a>
                </div>

                <!-- รายการหนังสือแบบ Clean List (อ่านง่าย แตะง่ายบนมือถือ) -->
                <div class="flex-grow-1 bg-white">
                    <?php if ($docModels === []): ?>
                        <div class="text-center py-5 text-muted px-3">
                            <i class="bi bi-check2-circle fs-1 text-success mb-2 d-block"></i>
                            <div class="fw-semibold text-dark">ไม่มีหนังสือภายนอกค้างดำเนินการ</div>
                            <div class="small text-muted mt-1">เมื่อมีหนังสือที่ผู้บริหารสั่งการมอบหมายถึงท่าน จะปรากฏที่นี่โดยอัตโนมัติ</div>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($docModels as $doc): ?>
                                <div class="list-group-item p-3 list-group-item-action border-bottom">
                                    
                                    <!-- บรรทัดที่ 1: ความเร่งด่วน + เลขรับ + วันที่รับ -->
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($doc->urgency === Document::URGENCY_MOST_URGENT || $doc->urgency === Document::URGENCY_VERY_URGENT): ?>
                                                <span class="badge bg-danger text-white">ด่วนมาก</span>
                                            <?php elseif ($doc->urgency === Document::URGENCY_URGENT): ?>
                                                <span class="badge bg-warning text-dark">ด่วน</span>
                                            <?php endif; ?>
                                            <span class="font-monospace fw-semibold text-primary">เลขรับ: <?= Html::encode($doc->receive_no) ?></span>
                                        </div>
                                        <span class="text-muted small"><i class="bi bi-calendar-event me-1"></i><?= $thaiDate($doc->receive_date) ?></span>
                                    </div>

                                    <!-- บรรทัดที่ 2: ชื่อเรื่อง (ตัวหนา อ่านง่าย) -->
                                    <h2 class="h6 fw-bold text-dark mb-1 lh-base">
                                        <a href="<?= Url::to(['view', 'id' => $doc->id]) ?>" class="text-dark text-decoration-none">
                                            <?= Html::encode($doc->subject) ?>
                                        </a>
                                    </h2>

                                    <!-- บรรทัดที่ 3: ต้นเรื่อง / ผู้ส่ง -->
                                    <div class="small text-muted mb-2">
                                        <i class="bi bi-send me-1"></i>จาก: <?= Html::encode($doc->sender) ?>
                                    </div>

                                    <!-- บรรทัดที่ 4: ปุ่ม Action แตะง่ายบนมือถือ -->
                                    <div class="d-flex align-items-center justify-content-between pt-1">
                                        <div>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                <i class="bi bi-clock-history me-1"></i>รอดำเนินการ
                                            </span>
                                        </div>
                                        <a href="<?= Url::to(['view', 'id' => $doc->id]) ?>" class="btn btn-sm btn-primary fw-semibold px-3 d-inline-flex align-items-center gap-1 shadow-sm">
                                            <span>เปิดอ่าน / รายงานผล</span>
                                            <i class="bi bi-arrow-right-short fs-6"></i>
                                        </a>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ท้ายการ์ด: ทางลัดไปหน้าหนังสือทั้งหมด -->
                <div class="p-2 border-top bg-light text-end">
                    <a href="<?= Url::to(['/document/index', 'scope' => 'all']) ?>" class="small text-decoration-none text-muted">
                        <i class="bi bi-globe2 me-1"></i> ทะเบียนหนังสือภายนอกทั้งหมด <i class="bi bi-arrow-right"></i>
                    </a>
                </div>

            </div>
        </div>

        <!-- ฝั่งขวา: บันทึกข้อความ (e-Memo) ของตนเอง -->
        <div class="col-lg-6 col-12" id="section-memo">
            <div class="content-card h-100 mb-0 d-flex flex-column border shadow-sm">
                
                <!-- หัวการ์ด: ชัดเจน ไม่เทอะทะ -->
                <div class="table-header-banner-memo py-2 px-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-journal-bookmark-fill text-warning"></i>
                        <span class="fw-semibold">2. บันทึกข้อความ (e-Memo ของคุณ)</span>
                        <span class="badge bg-white text-success rounded-pill font-monospace"><?= count($memoModels) ?></span>
                    </div>
                    <a href="<?= Url::to(['/memo/index']) ?>" class="btn btn-sm btn-outline-light py-0 px-2 small">
                        ดูทั้งหมด <i class="bi bi-chevron-right"></i>
                    </a>
                </div>

                <!-- รายการบันทึกข้อความแบบ Clean List -->
                <div class="flex-grow-1 bg-white">
                    <?php if ($memoModels === []): ?>
                        <div class="text-center py-5 text-muted px-3">
                            <i class="bi bi-journal-check fs-1 text-muted mb-2 d-block"></i>
                            <div class="fw-semibold text-dark">ไม่มีบันทึกข้อความที่อยู่ระหว่างดำเนินการ</div>
                            <div class="small text-muted mt-1">ท่านสามารถเริ่มร่างบันทึกข้อความเสนอ ผอ. ได้ทันที</div>
                            <div class="mt-3">
                                <a href="<?= Url::to(['/memo/create']) ?>" class="btn btn-sm btn-outline-success fw-semibold">
                                    <i class="bi bi-plus-lg me-1"></i> ร่างบันทึกข้อความใหม่
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($memoModels as $memo): ?>
                                <?php
                                $isCreator = (int) $memo->created_by === (int) $identity->id;
                                $isDraft = $memo->status === Memo::STATUS_DRAFT;
                                ?>
                                <div class="list-group-item p-3 list-group-item-action border-bottom">
                                    
                                    <!-- บรรทัดที่ 1: สถานะขั้นตอน + เลขที่บันทึก + วันที่ -->
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <?= $memoStatusBadge($memo) ?>
                                            <span class="font-monospace fw-semibold text-dark">
                                                <?= Html::encode($memo->memo_no ?: '(ฉบับร่าง)') ?>
                                            </span>
                                        </div>
                                        <span class="text-muted small"><i class="bi bi-calendar-event me-1"></i><?= $thaiDate($memo->memo_date) ?></span>
                                    </div>

                                    <!-- บรรทัดที่ 2: ชื่อเรื่องบันทึกข้อความ -->
                                    <h2 class="h6 fw-bold text-dark mb-1 lh-base">
                                        <a href="<?= Url::to(['/memo/view', 'id' => $memo->id]) ?>" class="text-dark text-decoration-none">
                                            <?= Html::encode($memo->subject) ?>
                                        </a>
                                    </h2>

                                    <!-- บรรทัดที่ 3: ผู้รับงาน / ขั้นตอนปัจจุบัน -->
                                    <div class="small text-muted mb-2">
                                        <?php if ($isCreator): ?>
                                            <span class="badge bg-light text-secondary border me-1">ร่างโดยคุณ</span>
                                        <?php endif; ?>
                                        <?php if ($memo->currentActor !== null): ?>
                                            <span><i class="bi bi-person me-1"></i>ส่งถึง: <?= Html::encode($memo->currentActor->displayName()) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- บรรทัดที่ 4: ปุ่ม Action ตรวจสอบหรือแก้ไข -->
                                    <div class="d-flex align-items-center justify-content-between pt-1">
                                        <div>
                                            <?php if ($isDraft): ?>
                                                <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>ยังไม่ได้กดส่งเสนอ</span>
                                            <?php else: ?>
                                                <span class="text-success small"><i class="bi bi-check2 me-1"></i>ส่งเสนอเรียบร้อย</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($isDraft): ?>
                                            <a href="<?= Url::to(['/memo/update', 'id' => $memo->id]) ?>" class="btn btn-sm btn-warning text-dark fw-semibold px-3 shadow-sm d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-pencil-square"></i>
                                                <span>แก้ไขฉบับร่าง</span>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= Url::to(['/memo/view', 'id' => $memo->id]) ?>" class="btn btn-sm btn-outline-success fw-semibold px-3 d-inline-flex align-items-center gap-1">
                                                <span>ติดตามสถานะ</span>
                                                <i class="bi bi-arrow-right-short fs-6"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ท้ายการ์ด: ทางลัดไปหน้ารายการบันทึกข้อความทั้งหมด -->
                <div class="p-2 border-top bg-light text-end">
                    <a href="<?= Url::to(['/memo/index']) ?>" class="small text-decoration-none text-muted">
                        <i class="bi bi-journal-text me-1"></i> รายการบันทึกข้อความทั้งหมด <i class="bi bi-arrow-right"></i>
                    </a>
                </div>

            </div>
        </div>

    </div>

</div>
