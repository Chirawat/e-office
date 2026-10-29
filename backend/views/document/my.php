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
    <div class="eoffice-titlebar">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <p><?= Html::encode($roleLabel) ?> - โต๊ะทำงานรวมสำหรับเอกสารที่รอดำเนินการ</p>
        </div>
        <div class="pdf-toolbar-actions">
            <?= Html::a('<i class="bi bi-inbox" aria-hidden="true"></i><span>หนังสือรับทั้งหมด</span>', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
            <?php if ($showMemoWorkbench): ?>
                <?= Html::a('<i class="bi bi-journal-text" aria-hidden="true"></i><span>บันทึกเสนอทั้งหมด</span>', ['/memo/index'], ['class' => 'btn btn-outline-secondary']) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="metric-grid">
        <div class="metric-card">
            <i class="metric-icon bi bi-list-check" aria-hidden="true"></i>
            <span>รอดำเนินการ</span>
            <strong><?= Html::encode((string) $allTotal) ?></strong>
            <small>รวมงานบนโต๊ะของคุณ</small>
        </div>
        <div class="metric-card warning">
            <i class="metric-icon bi bi-inbox" aria-hidden="true"></i>
            <span>หนังสือรับ</span>
            <strong><?= Html::encode((string) $documentTotal) ?></strong>
            <small>รายการ</small>
        </div>
        <?php if ($showMemoWorkbench): ?>
            <div class="metric-card">
                <i class="metric-icon bi bi-journal-text" aria-hidden="true"></i>
                <span>บันทึกข้อความ</span>
                <strong><?= Html::encode((string) $memoTotal) ?></strong>
                <small>รายการ</small>
            </div>
        <?php elseif (count($statuses) < 2): ?>
            <div class="metric-card warning">
                <i class="metric-icon bi bi-folder2-open" aria-hidden="true"></i>
                <span>สถานะอื่น</span>
                <strong>0</strong>
                <small>ไม่มีในคิวบทบาทนี้</small>
            </div>
        <?php else: ?>
            <?php $status = $statuses[0]; ?>
            <div class="metric-card <?= $status === Document::STATUS_RETURNED ? 'danger' : 'warning' ?>">
                <i class="metric-icon bi <?= $status === Document::STATUS_RETURNED ? 'bi-arrow-counterclockwise' : 'bi-clock-history' ?>" aria-hidden="true"></i>
                <span><?= Html::encode($statusOptions[$status] ?? $status) ?></span>
                <strong><?= Html::encode((string) ($summary[$status] ?? 0)) ?></strong>
                <small>รายการ</small>
            </div>
        <?php endif; ?>
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
