<?php

declare(strict_types=1);

use common\models\Department;
use common\models\Document;
use common\models\User;
use yii\bootstrap5\LinkPager;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $keyword */
/** @var int|null $departmentId */
/** @var Department[] $departments */
/** @var int $countAll */
/** @var array<int, int> $deptCounts */
/** @var int[] $myAssignedDocIds */

$this->title = 'ทะเบียนหนังสือเข้า';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$identity = Yii::$app->user->identity;
$canCreateDocument = $identity instanceof User && $identity->canRegisterDocument();

$this->registerCss('
/* ป้องกันการล้นจอแนวนอนในทุกระดับ */
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
    width: 0 !important;
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

    $yearThai = (int) date('Y', $timestamp) + 543;
    return date('d/m/', $timestamp) . $yearThai . ' BE';
};

$getInitial = static function (?string $text): string {
    $text = trim((string) $text);
    if ($text === '') {
        return '?';
    }
    return mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8');
};

/** @var Document[] $models */
$models = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
?>

<div class="eoffice-page">

    <!-- Header สรุปข้อมูลหน้าจอแบบกะทัดรัด (Minimal Header) -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 pb-2 border-bottom">
        <h1 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-1">
            <i class="bi bi-folder2-open text-primary"></i>
            <span>ทะเบียนหนังสือเข้า</span>
        </h1>
        <?php if ($canCreateDocument): ?>
            <div>
                <a href="<?= Url::to(['create']) ?>" class="btn btn-sm btn-primary py-1 px-2 small fw-semibold">
                    <i class="bi bi-plus-circle me-1"></i> ลงรับหนังสือใหม่
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ส่วนค้นหาหนังสือ (Compact Search Bar) -->
    <div class="content-card p-2 mb-3 bg-white border shadow-sm">
        <?= Html::beginForm(['index'], 'get', ['class' => 'd-flex align-items-center gap-2']) ?>
            <?php if ($departmentId !== null): ?>
                <?= Html::hiddenInput('department_id', (string) $departmentId) ?>
            <?php endif; ?>
            <div class="input-group input-group-sm flex-grow-1">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <?= Html::textInput('q', $keyword, [
                    'id' => 'document-search',
                    'class' => 'form-control border-start-0 ps-0',
                    'placeholder' => 'ค้นหาจากชื่อเรื่อง, เลขที่หนังสือ, หรือเลขรับ...',
                ]) ?>
            </div>
            <button type="submit" class="btn btn-sm btn-primary py-1 px-3 fw-semibold">ค้นหา</button>
            <?php if ($keyword !== ''): ?>
                <a href="<?= Url::to(['index', 'department_id' => $departmentId]) ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="ล้างการค้นหา">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        <?= Html::endForm() ?>
    </div>

    <!-- ส่วนแท็บกลุ่มงาน (Department Tabs) -->
    <div class="content-card border shadow-sm p-0 mb-3 bg-white overflow-hidden">
        
        <div class="bg-light px-2 pt-2 border-bottom">
            <ul class="nav nav-tabs erp-nav-tabs border-0 mb-0" role="tablist">
                <!-- แท็บ: หนังสือเข้าทั้งหมด -->
                <li class="nav-item" role="presentation">
                    <a href="<?= Url::to(['index', 'q' => $keyword]) ?>" 
                       class="nav-link erp-nav-tab py-2 px-3 fw-bold d-inline-flex align-items-center gap-2 small <?= $departmentId === null ? 'active' : '' ?>">
                        <i class="bi bi-globe2 text-primary"></i>
                        <span>ทั้งหมด</span>
                        <span class="tab-badge <?= $countAll > 0 ? 'bg-primary text-white' : '' ?> font-monospace"><?= $countAll ?></span>
                    </a>
                </li>
                <!-- แท็บ: แต่ละกลุ่มงาน -->
                <?php foreach ($departments as $dept): ?>
                    <?php
                    $isActiveTab = ($departmentId === (int) $dept->id);
                    $dCount = $deptCounts[(int) $dept->id] ?? 0;
                    ?>
                    <li class="nav-item" role="presentation">
                        <a href="<?= Url::to(['index', 'department_id' => $dept->id, 'q' => $keyword]) ?>" 
                           class="nav-link erp-nav-tab py-2 px-3 fw-bold d-inline-flex align-items-center gap-2 small <?= $isActiveTab ? 'active' : '' ?>">
                            <i class="bi bi-building text-secondary"></i>
                            <span><?= Html::encode($dept->name) ?></span>
                            <span class="tab-badge <?= $dCount > 0 ? ($isActiveTab ? 'bg-primary text-white' : '') : '' ?> font-monospace"><?= $dCount ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- รายการหนังสือในรูปแบบ Apple Mail App -->
        <div>
            <?php if ($models === []): ?>
                <div class="text-center py-5 text-muted px-3">
                    <i class="bi bi-tray fs-1 text-muted opacity-50 mb-2 d-block"></i>
                    <div class="fw-semibold text-dark">ไม่พบรายการหนังสือเข้า</div>
                    <div class="text-muted small mt-1">
                        <?= $keyword !== '' ? 'ลองค้นหาด้วยคำอื่น หรือกดล้างการค้นหา' : 'ยังไม่มีหนังสือในกลุ่มงานนี้' ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($models as $doc): ?>
                        <?php
                        $viewUrl = Url::to(['/document/view', 'id' => $doc->id]);
                        $senderText = trim((string) $doc->sender) ?: 'หนังสือเข้า';
                        $initial = $getInitial($senderText);
                        $isMy = in_array((int) $doc->id, $myAssignedDocIds, true);
                        ?>
                        <a href="<?= $viewUrl ?>" class="list-group-item list-group-item-action py-3 px-3 border-bottom d-flex align-items-start gap-3 text-decoration-none position-relative apple-mail-row">
                            <!-- Blue Dot (Unread/Action/My Assigned Indicator) -->
                            <div class="pt-2 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 14px;">
                                <span class="<?= $isMy ? 'apple-blue-dot' : 'rounded-circle bg-secondary bg-opacity-25' ?>" style="width: 8px; height: 8px; display: inline-block;"></span>
                            </div>

                            <!-- Circular Avatar Badge with Initial -->
                            <div class="flex-shrink-0">
                                <div class="apple-mail-avatar shadow-sm" style="background-color: <?= $isMy ? '#0284c7' : '#94a3b8' ?>;">
                                    <?= Html::encode($initial) ?>
                                </div>
                            </div>

                            <!-- Mail Content Body -->
                            <div class="apple-mail-body">
                                <!-- Line 1: Sender Name + Date (Apple Mail header style) -->
                                <div class="apple-mail-header">
                                    <div class="apple-mail-sender">
                                        <?= Html::encode($senderText) ?>
                                        <?php if ($isMy): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal py-0 px-1 ms-1" style="font-size: 0.7rem;">มอบหมายถึงท่าน</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="apple-mail-date">
                                        <?= $thaiDate($doc->receive_date) ?>
                                    </div>
                                </div>

                                <!-- Line 2: Subject (Bold Title) -->
                                <div class="apple-mail-subject">
                                    <?php if ($doc->urgency === Document::URGENCY_MOST_URGENT || $doc->urgency === Document::URGENCY_VERY_URGENT): ?>
                                        <span class="badge bg-danger text-white py-0 px-1 me-1" style="font-size: 0.72rem;">ด่วนมาก</span>
                                    <?php elseif ($doc->urgency === Document::URGENCY_URGENT): ?>
                                        <span class="badge bg-warning text-dark py-0 px-1 me-1" style="font-size: 0.72rem;">ด่วน</span>
                                    <?php endif; ?>
                                    <?= Html::encode($doc->subject) ?>
                                </div>

                                <!-- Line 3: Snippet / Summary text (เลขรับ, เลขที่หนังสือ, กลุ่มงาน) -->
                                <div class="apple-mail-snippet">
                                    <span class="text-primary font-monospace fw-semibold me-1">เลขรับ: <?= Html::encode($doc->receive_no) ?></span>
                                    <?php if (!empty($doc->external_no)): ?>
                                        <span>• เลขที่: <?= Html::encode($doc->external_no) ?></span>
                                    <?php endif; ?>
                                    <?php if ($doc->department !== null): ?>
                                        <span>• สังกัด: <?= Html::encode($doc->department->name) ?></span>
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

        <!-- Pagination (เลขหน้า) -->
        <?php if ($pagination !== false && $pagination->pageCount > 1): ?>
            <div class="p-2 border-top bg-light d-flex justify-content-center">
                <?= LinkPager::widget([
                    'pagination' => $pagination,
                    'options' => ['class' => 'pagination pagination-sm mb-0'],
                ]) ?>
            </div>
        <?php endif; ?>

    </div>

</div>
