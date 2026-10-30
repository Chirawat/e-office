<?php

declare(strict_types=1);

use common\models\Document;
use common\models\Memo;
use yii\data\ActiveDataProvider;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var ActiveDataProvider $dataProvider */
/** @var ActiveDataProvider|null $memoDataProvider */
/** @var string[] $statuses */
/** @var string $roleLabel */
/** @var array<string,int> $summary */
/** @var string $keyword */
/** @var string $memoKeyword */
/** @var int $memoTotal */
/** @var bool $showMemoWorkbench */
/** @var string $activeTab */

$this->title = 'งานของฉัน';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$statusOptions = Document::statusOptions();
$documentTotal = array_sum($summary);
$allTotal = $documentTotal + ($showMemoWorkbench ? $memoTotal : 0);
$documentStatusBadge = static function (Document $model): string {
    $statusClass = 'status-badge-' . str_replace('_', '-', $model->status);
    return '<span class="status-badge ' . Html::encode($statusClass) . '">' . Html::encode(Document::statusOptions()[$model->status] ?? $model->status) . '</span>';
};
$memoStatusBadge = static function (Memo $model): string {
    $statusClass = 'status-badge-' . str_replace('_', '-', $model->status);
    return '<span class="status-badge ' . Html::encode($statusClass) . '">' . Html::encode(Memo::statusOptions()[$model->status] ?? $model->status) . '</span>';
};
$thaiDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return '-';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    $months = [
        1 => 'ม.ค.',
        2 => 'ก.พ.',
        3 => 'มี.ค.',
        4 => 'เม.ย.',
        5 => 'พ.ค.',
        6 => 'มิ.ย.',
        7 => 'ก.ค.',
        8 => 'ส.ค.',
        9 => 'ก.ย.',
        10 => 'ต.ค.',
        11 => 'พ.ย.',
        12 => 'ธ.ค.',
    ];

    return (int) date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . ((int) date('Y', $timestamp) + 543);
};
?>

<div class="eoffice-page">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h1 class="h5 fw-bold text-dark mb-0"><?= Html::encode($this->title) ?></h1>
            <div class="small text-muted"><?= Html::encode($roleLabel) ?> - เอกสารที่รอดำเนินการ</div>
        </div>
        <div class="d-flex gap-1 flex-wrap">
            <?= Html::a('<i class="bi bi-inbox me-1" aria-hidden="true"></i><span>หนังสือรับทั้งหมด</span>', ['index'], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
            <?php if ($showMemoWorkbench): ?>
                <?= Html::a('<i class="bi bi-journal-text me-1" aria-hidden="true"></i><span>บันทึกเสนอทั้งหมด</span>', ['/memo/index'], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- แถบสรุปสถานะแบบ BADGE แนวนอน (Mobile-First แทน Card ใหญ่) -->
    <div class="p-2 bg-white rounded-3 border shadow-sm mb-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-2 px-3 d-inline-flex align-items-center gap-2">
                <i class="bi bi-list-check"></i>
                <span>รอดำเนินการทั้งหมด:</span>
                <strong class="fs-6 font-monospace"><?= Html::encode((string) $allTotal) ?></strong>
            </span>

            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 py-2 px-3 d-inline-flex align-items-center gap-2">
                <i class="bi bi-inbox-fill text-warning"></i>
                <span>หนังสือรับ:</span>
                <strong class="fs-6 font-monospace"><?= Html::encode((string) $documentTotal) ?></strong>
            </span>

            <?php if ($showMemoWorkbench): ?>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 d-inline-flex align-items-center gap-2">
                    <i class="bi bi-journal-text"></i>
                    <span>บันทึกข้อความ:</span>
                    <strong class="fs-6 font-monospace"><?= Html::encode((string) $memoTotal) ?></strong>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($showMemoWorkbench): ?>
        <nav class="workbench-tabs" role="tablist" aria-label="ประเภทงานบนโต๊ะ">
            <?= Html::a('<i class="bi bi-inbox" aria-hidden="true"></i><span>หนังสือรับ</span><strong>' . Html::encode((string) $documentTotal) . '</strong>', ['my', 'tab' => 'document', 'q_doc' => $keyword, 'q_memo' => $memoKeyword], [
                'class' => 'workbench-tab' . ($activeTab === 'document' ? ' active' : ''),
                'role' => 'tab',
                'aria-selected' => $activeTab === 'document' ? 'true' : 'false',
            ]) ?>
            <?= Html::a('<i class="bi bi-journal-text" aria-hidden="true"></i><span>บันทึกข้อความ</span><strong>' . Html::encode((string) $memoTotal) . '</strong>', ['my', 'tab' => 'memo', 'q_doc' => $keyword, 'q_memo' => $memoKeyword], [
                'class' => 'workbench-tab' . ($activeTab === 'memo' ? ' active' : ''),
                'role' => 'tab',
                'aria-selected' => $activeTab === 'memo' ? 'true' : 'false',
            ]) ?>
        </nav>
    <?php endif; ?>

    <?php if ($activeTab === 'document'): ?>
        <div class="content-card search-card table-search-card">
            <?= Html::beginForm(['my'], 'get', ['class' => 'document-search-form']) ?>
                <?= Html::hiddenInput('tab', 'document') ?>
                <?php if ($showMemoWorkbench): ?>
                    <?= Html::hiddenInput('q_memo', $memoKeyword) ?>
                <?php endif; ?>
                <div>
                    <label class="form-label" for="my-document-search">ค้นหาหนังสือรับบนโต๊ะ</label>
                    <?= Html::textInput('q_doc', $keyword, [
                        'id' => 'my-document-search',
                        'class' => 'form-control',
                        'placeholder' => 'ค้นจากชื่อเรื่อง หรือเลขที่หนังสือต้นเรื่อง',
                    ]) ?>
                </div>
                <div class="search-actions">
                    <?= Html::submitButton('<i class="bi bi-search" aria-hidden="true"></i><span>ค้นหา</span>', ['class' => 'btn btn-primary']) ?>
                    <?php if ($keyword !== ''): ?>
                        <?= Html::a('<i class="bi bi-x-circle" aria-hidden="true"></i><span>ล้าง</span>', Url::to(['my', 'tab' => 'document', 'q_memo' => $memoKeyword]), ['class' => 'btn btn-outline-secondary']) ?>
                    <?php endif; ?>
                </div>
            <?= Html::endForm() ?>
        </div>

        <div class="content-card">
            <?php if ($statuses === []): ?>
                <p class="muted-text">ยังไม่ได้กำหนดคิวงานสำหรับบทบาทนี้</p>
            <?php else: ?>
                <?= GridView::widget([
                    'dataProvider' => $dataProvider,
                    'tableOptions' => ['class' => 'table table-hover align-middle'],
                    'summary' => 'แสดงรายการที่ {begin}-{end} จากทั้งหมด {totalCount} รายการ',
                    'emptyText' => 'ยังไม่มีหนังสือรับที่ต้องดำเนินการ',
                    'columns' => [
                        'receive_no',
                        [
                            'attribute' => 'subject',
                            'format' => 'raw',
                            'value' => static fn (Document $model): string => Html::a(Html::encode($model->subject), ['view', 'id' => $model->id]),
                        ],
                        'external_no',
                        'sender',
                        [
                            'attribute' => 'department_id',
                            'value' => static fn (Document $model): string => $model->department->name ?? '-',
                        ],
                        [
                            'attribute' => 'urgency',
                            'value' => static fn (Document $model): string => Document::urgencyOptions()[$model->urgency] ?? $model->urgency,
                        ],
                        [
                            'attribute' => 'status',
                            'format' => 'raw',
                            'value' => $documentStatusBadge,
                        ],
                        [
                            'attribute' => 'receive_date',
                            'value' => static fn (Document $model): string => $thaiDate($model->receive_date),
                        ],
                    ],
                ]) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="content-card search-card table-search-card">
            <?= Html::beginForm(['my'], 'get', ['class' => 'document-search-form']) ?>
                <?= Html::hiddenInput('tab', 'memo') ?>
                <?= Html::hiddenInput('q_doc', $keyword) ?>
                <div>
                    <label class="form-label" for="my-memo-search">ค้นหาบันทึกข้อความบนโต๊ะ</label>
                    <?= Html::textInput('q_memo', $memoKeyword, [
                        'id' => 'my-memo-search',
                        'class' => 'form-control',
                        'placeholder' => 'ค้นจากชื่อเรื่อง หรือเลขที่บันทึก',
                    ]) ?>
                </div>
                <div class="search-actions">
                    <?= Html::submitButton('<i class="bi bi-search" aria-hidden="true"></i><span>ค้นหา</span>', ['class' => 'btn btn-primary']) ?>
                    <?php if ($memoKeyword !== ''): ?>
                        <?= Html::a('<i class="bi bi-x-circle" aria-hidden="true"></i><span>ล้าง</span>', Url::to(['my', 'tab' => 'memo', 'q_doc' => $keyword]), ['class' => 'btn btn-outline-secondary']) ?>
                    <?php endif; ?>
                </div>
            <?= Html::endForm() ?>
        </div>

        <div class="content-card">
            <?= GridView::widget([
                'dataProvider' => $memoDataProvider,
                'tableOptions' => ['class' => 'table table-hover align-middle'],
                'summary' => 'แสดงรายการที่ {begin}-{end} จากทั้งหมด {totalCount} รายการ',
                'emptyText' => 'ยังไม่มีบันทึกข้อความที่ต้องดำเนินการ',
                'columns' => [
                    ['attribute' => 'memo_no', 'value' => static fn (Memo $model): string => $model->memo_no ?: '-'],
                    [
                        'attribute' => 'subject',
                        'format' => 'raw',
                        'value' => static fn (Memo $model): string => Html::a(Html::encode($model->subject), ['/memo/view', 'id' => $model->id]),
                    ],
                    ['attribute' => 'department_id', 'value' => static fn (Memo $model): string => $model->department->name ?? '-'],
                    ['label' => 'ผู้จัดทำ', 'value' => static fn (Memo $model): string => $model->creator->username ?? '-'],
                    ['label' => 'ผู้รับงานปัจจุบัน', 'value' => static fn (Memo $model): string => $model->currentActor->username ?? '-'],
                    ['attribute' => 'status', 'format' => 'raw', 'value' => $memoStatusBadge],
                    ['attribute' => 'memo_date', 'value' => static fn (Memo $model): string => $thaiDate($model->memo_date)],
                ],
            ]) ?>
        </div>
    <?php endif; ?>
</div>
