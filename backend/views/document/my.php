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
/** @var int $unreadDocCount */
/** @var int $unreadMemoCount */
/** @var array<int, bool> $readDocMap */
/** @var array<int, bool> $readMemoMap */

$unreadDocCount = $unreadDocCount ?? 0;
$unreadMemoCount = $unreadMemoCount ?? 0;
$readDocMap = $readDocMap ?? [];
$readMemoMap = $readMemoMap ?? [];

$this->title = 'โต๊ะทำงาน (งานของฉัน)';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);

$docModels = $docDataProvider->getModels();
$memoModels = $memoDataProvider->getModels();

$this->registerCss('
/* ป้องกันการล้นจอแนวนอนในทุกระดับ (Full Mobile Horizontal Containment) */
html, body, .eoffice-body, main, .eoffice-main, .eoffice-container, .eoffice-page {
    max-width: 100vw !important;
    overflow-x: hidden !important;
    box-sizing: border-box !important;
}

.apple-mail-row {
    display: flex !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
    transition: background-color 0.15s ease-in-out;
    border-color: #f1f5f9 !important;
}
.apple-mail-row:hover {
    background-color: #f8fafc !important;
}
.apple-mail-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-weight: 600;
    font-size: 0.92rem;
    user-select: none;
    flex-shrink: 0;
}
.apple-blue-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #007aff;
    display: inline-block;
    flex-shrink: 0;
}
.apple-mail-body {
    flex: 1 1 0% !important;
    min-width: 0 !important;
    width: 0 !important; /* Force flex child to respect parent width and truncate properly */
    overflow: hidden !important;
}
.apple-mail-header {
    display: flex !important;
    align-items: baseline !important;
    justify-content: space-between !important;
    gap: 6px !important;
    width: 100% !important;
    min-width: 0 !important;
    margin-bottom: 2px !important;
}
.apple-mail-sender {
    font-size: 0.92rem;
    font-weight: 700;
    color: #1e293b;
    flex: 1 1 0% !important;
    min-width: 0 !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}
.apple-mail-date {
    font-size: 0.72rem;
    color: #64748b;
    font-weight: 400;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
    text-align: right;
}
.apple-mail-subject {
    font-size: 0.86rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.35;
    width: 100% !important;
    min-width: 0 !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    margin-bottom: 2px !important;
}
.apple-mail-subject.is-read {
    font-weight: 400 !important;
    color: #64748b !important;
}
.apple-mail-subject.is-unread {
    font-weight: 700 !important;
    color: #0f172a !important;
}
.apple-mail-snippet {
    font-size: 0.8rem;
    color: #64748b;
    line-height: 1.3;
    width: 100% !important;
    min-width: 0 !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}

@media (max-width: 575.98px) {
    .eoffice-container {
        padding-left: 6px !important;
        padding-right: 6px !important;
    }
    .content-card {
        border-radius: 8px !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    .apple-mail-row {
        padding-left: 8px !important;
        padding-right: 8px !important;
        gap: 8px !important;
    }
    .apple-mail-avatar {
        width: 32px;
        height: 32px;
        font-size: 0.82rem;
    }
    .apple-mail-sender {
        font-size: 0.88rem;
    }
    .apple-mail-date {
        font-size: 0.7rem;
    }
    .erp-nav-tab {
        padding: 8px 10px !important;
        font-size: 0.8rem !important;
        gap: 4px !important;
    }
    .erp-nav-tab .tab-badge {
        padding: 1px 6px !important;
        font-size: 0.7rem !important;
    }
}
');

$thaiDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return '-';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    // รูปแบบ Apple Mail: วัน/เดือน/ปี พ.ศ.
    $yearThai = (int) date('Y', $timestamp) + 543;
    return date('d/m/', $timestamp) . $yearThai . ' BE';
};

$getInitial = static function (?string $text): string {
    $text = trim((string) $text);
    if ($text === '') {
        return '?';
    }
    // ดึง 1-2 ตัวอักษรแรก
    return mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8');
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

    <!-- Header สรุปข้อมูลเฉพาะโต๊ะทำงานของฉัน (Minimal Mobile Header) -->
    <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
        <h1 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-1">
            <i class="bi bi-briefcase-fill text-primary"></i>
            <span>โต๊ะทำงานของฉัน</span>
        </h1>
    </div>

    <!-- แท็บสลับการแสดงผล หนังสือเข้า และ บันทึกข้อความ (Compact Tab Card) -->
    <div class="content-card border shadow-sm p-0 mb-3 bg-white overflow-hidden">
        
        <!-- Navigation Tabs Header -->
        <div class="bg-light px-2 pt-2 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <ul class="nav nav-tabs erp-nav-tabs border-0 mb-0" id="myWorkTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link erp-nav-tab py-2 px-3 active fw-bold d-inline-flex align-items-center gap-2 small" id="doc-tab-btn" data-bs-toggle="tab" data-bs-target="#tab-doc" type="button" role="tab" aria-controls="tab-doc" aria-selected="true">
                        <i class="bi bi-inbox-fill text-primary"></i>
                        <span>หนังสือเข้า</span>
                        <span class="tab-badge <?= $unreadDocCount > 0 ? 'bg-primary text-white' : '' ?>" title="จำนวนหนังสือเข้าที่ยังไม่ได้เปิดอ่าน"><?= $unreadDocCount ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link erp-nav-tab py-2 px-3 fw-bold d-inline-flex align-items-center gap-2 small" id="memo-tab-btn" data-bs-toggle="tab" data-bs-target="#tab-memo" type="button" role="tab" aria-controls="tab-memo" aria-selected="false">
                        <i class="bi bi-journal-bookmark-fill text-teal" style="color: #0f766e;"></i>
                        <span>บันทึกข้อความ</span>
                        <span class="tab-badge <?= $unreadMemoCount > 0 ? 'bg-success text-white' : '' ?>" title="จำนวนบันทึกข้อความที่ยังไม่ได้เปิดอ่าน"><?= $unreadMemoCount ?></span>
                    </button>
                </li>
            </ul>

            <div class="pb-1 pe-2 d-none d-sm-block">
                <a href="<?= Url::to(['/memo/create']) ?>" class="btn btn-xs btn-sm btn-outline-success py-1 px-2 small d-inline-flex align-items-center gap-1">
                    <i class="bi bi-plus-lg"></i><span>ร่างบันทึกใหม่</span>
                </a>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="myWorkTabsContent">

            <!-- TAB 1: หนังสือเข้า -->
            <div class="tab-pane fade show active" id="tab-doc" role="tabpanel" aria-labelledby="doc-tab-btn" tabindex="0">

                <!-- รายการหนังสือในสไตล์ Apple Mail App -->
                <div>
                    <?php if ($docModels === []): ?>
                        <div class="text-center py-5 text-muted px-3">
                            <i class="bi bi-tray fs-1 text-muted opacity-50 mb-2 d-block"></i>
                            <div class="fw-semibold text-dark">ไม่มีหนังสือเข้าค้างดำเนินการ</div>
                            <div class="text-muted small mt-1">เมื่อมีหนังสือมอบหมายถึงท่าน จะปรากฏที่นี่</div>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($docModels as $doc): ?>
                                <?php
                                $viewUrl = Url::to(['/document/view', 'id' => $doc->id]);
                                $senderText = trim((string) $doc->sender) ?: 'หนังสือเข้า';
                                $initial = $getInitial($senderText);
                                $isUrgent = in_array($doc->urgency, [Document::URGENCY_URGENT, Document::URGENCY_VERY_URGENT, Document::URGENCY_MOST_URGENT], true);
                                $isRead = isset($readDocMap[(int) $doc->id]);
                                ?>
                                <a href="<?= $viewUrl ?>" class="list-group-item list-group-item-action py-3 px-3 border-bottom d-flex align-items-start gap-3 text-decoration-none position-relative apple-mail-row">
                                    <!-- Blue Dot (Unread: แสดงจุดสีฟ้า / Read: ซ่อนแบบโปร่งใสเพื่อรักษาตำแหน่ง) -->
                                    <div class="pt-2 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 14px;">
                                        <span class="apple-blue-dot <?= $isRead ? 'opacity-0' : '' ?>" title="<?= $isRead ? 'เปิดอ่านแล้ว' : 'ยังไม่ได้เปิดอ่าน' ?>"></span>
                                    </div>

                                    <!-- Circular Avatar Badge with Initial / Icon -->
                                    <div class="flex-shrink-0">
                                        <div class="apple-mail-avatar shadow-sm" style="background-color: #94a3b8;">
                                            <?= Html::encode($initial) ?>
                                        </div>
                                    </div>

                                    <!-- Mail Content Body -->
                                    <div class="apple-mail-body">
                                        <!-- Line 1: Sender Name + Date (Apple Mail header style) -->
                                        <div class="apple-mail-header">
                                            <div class="apple-mail-sender">
                                                <?= Html::encode($senderText) ?>
                                            </div>
                                            <div class="apple-mail-date">
                                                <?= $thaiDate($doc->receive_date) ?>
                                            </div>
                                        </div>

                                        <!-- Line 2: Subject (Unread: ตัวหนา / Read: ตัวปกติสีเทา) -->
                                        <div class="apple-mail-subject <?= $isRead ? 'is-read fw-normal text-muted' : 'is-unread fw-bold text-dark' ?>">
                                            <?php if ($doc->urgency === Document::URGENCY_MOST_URGENT || $doc->urgency === Document::URGENCY_VERY_URGENT): ?>
                                                <span class="badge bg-danger text-white py-0 px-1 me-1" style="font-size: 0.72rem;">ด่วนมาก</span>
                                            <?php elseif ($doc->urgency === Document::URGENCY_URGENT): ?>
                                                <span class="badge bg-warning text-dark py-0 px-1 me-1" style="font-size: 0.72rem;">ด่วน</span>
                                            <?php endif; ?>
                                            <?= Html::encode($doc->subject) ?>
                                        </div>

                                        <!-- Line 3: Snippet / Summary text (เลขรับ, รายละเอียด) -->
                                        <div class="apple-mail-snippet">
                                            <span class="text-primary font-monospace fw-semibold me-1">เลขรับ: <?= Html::encode($doc->receive_no) ?></span>
                                            <?php if (!empty($doc->external_no)): ?>
                                                <span>• เลขที่หนังสือ: <?= Html::encode($doc->external_no) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($doc->note)): ?>
                                                <span>• หมายเหตุ: <?= Html::encode($doc->note) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- TAB 2: บันทึกข้อความ -->
            <div class="tab-pane fade" id="tab-memo" role="tabpanel" aria-labelledby="memo-tab-btn" tabindex="0">

                <!-- รายการบันทึกข้อความในสไตล์ Apple Mail App -->
                <div>
                    <?php if ($memoModels === []): ?>
                        <div class="text-center py-5 text-muted px-3">
                            <i class="bi bi-journal-text fs-1 text-muted opacity-50 mb-2 d-block"></i>
                            <div class="fw-semibold text-dark">ไม่มีบันทึกข้อความที่อยู่ระหว่างดำเนินการ</div>
                            <div class="text-muted small mt-1">ท่านสามารถเริ่มร่างบันทึกข้อความเสนอ ผอ. ได้ทันที</div>
                            <div class="mt-3">
                                <a href="<?= Url::to(['/memo/create']) ?>" class="btn btn-sm btn-outline-success fw-semibold py-1 px-3" style="font-size: 0.85rem;">
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
                                $targetUrl = $isDraft ? Url::to(['/memo/update', 'id' => $memo->id]) : Url::to(['/memo/view', 'id' => $memo->id]);
                                
                                // ผู้ส่ง / สายเสนอ
                                $senderName = $isCreator ? $identity->displayName() : ($memo->creator ? $memo->creator->displayName() : 'บันทึกข้อความ');
                                $initial = $getInitial($senderName);
                                $isMemoRead = isset($readMemoMap[(int) $memo->id]);
                                ?>
                                <a href="<?= $targetUrl ?>" class="list-group-item list-group-item-action py-3 px-3 border-bottom d-flex align-items-start gap-3 text-decoration-none position-relative apple-mail-row">
                                    <!-- Blue Dot (Unread: แสดงจุดสีฟ้า / Read: ซ่อนแบบโปร่งใสเพื่อรักษาตำแหน่ง) -->
                                    <div class="pt-2 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 14px;">
                                        <span class="<?= $isDraft ? 'apple-blue-dot bg-warning' : 'apple-blue-dot' ?> <?= $isMemoRead ? 'opacity-0' : '' ?>" title="<?= $isMemoRead ? 'เปิดอ่านแล้ว' : 'ยังไม่ได้เปิดอ่าน' ?>"></span>
                                    </div>

                                    <!-- Circular Avatar Badge with Initial / Icon -->
                                    <div class="flex-shrink-0">
                                        <div class="apple-mail-avatar shadow-sm" style="background-color: <?= $isDraft ? '#eab308' : '#0f766e' ?>;">
                                            <?= Html::encode($initial) ?>
                                        </div>
                                    </div>

                                    <!-- Mail Content Body -->
                                    <div class="apple-mail-body">
                                        <!-- Line 1: Sender Name + Date (Apple Mail header style) -->
                                        <div class="apple-mail-header">
                                            <div class="apple-mail-sender">
                                                <?= Html::encode($senderName) ?>
                                                <?php if ($isCreator): ?>
                                                    <span class="badge bg-light text-secondary border fw-normal py-0 px-1 ms-1" style="font-size: 0.7rem;">คุณ</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="apple-mail-date">
                                                <?= $thaiDate($memo->memo_date) ?>
                                            </div>
                                        </div>

                                        <!-- Line 2: Subject (Unread: ตัวหนา / Read: ตัวปกติสีเทา) -->
                                        <div class="apple-mail-subject <?= $isMemoRead ? 'is-read fw-normal text-muted' : 'is-unread fw-bold text-dark' ?>">
                                            <?= $memoStatusBadge($memo) ?>
                                            <span class="ms-1"><?= Html::encode($memo->subject) ?></span>
                                        </div>

                                        <!-- Line 3: Snippet / Summary text (เลขที่บันทึก, ผู้รับเรื่องปัจจุบัน) -->
                                        <div class="apple-mail-snippet">
                                            <span class="font-monospace fw-semibold text-dark me-1"><?= Html::encode($memo->memo_no ?: '(ฉบับร่าง)') ?></span>
                                            <?php if ($memo->currentActor !== null): ?>
                                                <span>• ส่งถึง: <?= Html::encode($memo->currentActor->displayName()) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($memo->to_text)): ?>
                                                <span>• เรียน: <?= Html::encode($memo->to_text) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </div>

</div>
