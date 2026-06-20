<?php

declare(strict_types=1);

use common\models\Document;
use yii\data\ActiveDataProvider;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var ActiveDataProvider $dataProvider */
/** @var string[] $statuses */
/** @var string $roleLabel */
/** @var array<string,int> $summary */
/** @var string $keyword */

$this->title = 'งานของฉัน';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$statusOptions = Document::statusOptions();
$total = array_sum($summary);
$statusBadge = static function (Document $model): string {
    $statusClass = 'status-badge-' . str_replace('_', '-', $model->status);
    return '<span class="status-badge ' . Html::encode($statusClass) . '">' . Html::encode(Document::statusOptions()[$model->status] ?? $model->status) . '</span>';
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
            <p><?= Html::encode($roleLabel) ?> - งานที่รอดำเนินการตามบทบาทของคุณ</p>
        </div>
        <?= Html::a('<i class="bi bi-inbox" aria-hidden="true"></i><span>ดูหนังสือรับทั้งหมด</span>', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <div class="metric-grid">
        <div class="metric-card">
            <i class="metric-icon bi bi-list-check" aria-hidden="true"></i>
            <span>รอดำเนินการ</span>
            <strong><?= Html::encode((string) $total) ?></strong>
            <small>รวมงานในคิวของคุณ</small>
        </div>
        <?php foreach (array_slice($statuses, 0, 2) as $status): ?>
            <div class="metric-card <?= $status === Document::STATUS_RETURNED ? 'danger' : 'warning' ?>">
                <i class="metric-icon bi <?= $status === Document::STATUS_RETURNED ? 'bi-arrow-counterclockwise' : 'bi-clock-history' ?>" aria-hidden="true"></i>
                <span><?= Html::encode($statusOptions[$status] ?? $status) ?></span>
                <strong><?= Html::encode((string) ($summary[$status] ?? 0)) ?></strong>
                <small>รายการ</small>
            </div>
        <?php endforeach; ?>
        <?php if (count($statuses) < 2): ?>
            <div class="metric-card warning">
                <i class="metric-icon bi bi-folder2-open" aria-hidden="true"></i>
                <span>สถานะอื่น</span>
                <strong>0</strong>
                <small>ไม่มีในคิวบทบาทนี้</small>
            </div>
        <?php endif; ?>
    </div>

    <div class="content-card search-card table-search-card">
        <?= Html::beginForm(['my'], 'get', ['class' => 'document-search-form']) ?>
            <div>
                <label class="form-label" for="my-document-search">ค้นหางานของฉัน</label>
                <?= Html::textInput('q', $keyword, [
                    'id' => 'my-document-search',
                    'class' => 'form-control',
                    'placeholder' => 'ค้นจากชื่อเรื่อง หรือเลขที่หนังสือต้นเรื่อง',
                ]) ?>
            </div>
            <div class="search-actions">
                <?= Html::submitButton('<i class="bi bi-search" aria-hidden="true"></i><span>ค้นหา</span>', ['class' => 'btn btn-primary']) ?>
                <?php if ($keyword !== ''): ?>
                    <?= Html::a('<i class="bi bi-x-circle" aria-hidden="true"></i><span>ล้าง</span>', Url::to(['my']), ['class' => 'btn btn-outline-secondary']) ?>
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
                'emptyText' => 'ยังไม่มีงานที่ต้องดำเนินการ',
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
                        'value' => $statusBadge,
                    ],
                    [
                        'attribute' => 'receive_date',
                        'value' => static fn (Document $model): string => $thaiDate($model->receive_date),
                    ],
                ],
            ]) ?>
        <?php endif; ?>
    </div>
</div>
