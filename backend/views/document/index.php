<?php

declare(strict_types=1);

use common\models\Document;
use common\models\User;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $keyword */

$this->title = 'หนังสือรับ';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$identity = Yii::$app->user->identity;
$canCreateDocument = $identity instanceof User && $identity->canRegisterDocument();
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
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h1 class="h5 fw-bold text-dark mb-0"><?= Html::encode($this->title) ?></h1>
            <div class="small text-muted">ทะเบียนหนังสือรับจาก SMSS และสถานะการเสนอ</div>
        </div>
        <?php if ($canCreateDocument): ?>
            <?= Html::a('<i class="bi bi-plus-circle me-1" aria-hidden="true"></i><span>ลงทะเบียนหนังสือรับ</span>', ['create'], ['class' => 'btn btn-sm btn-primary fw-semibold']) ?>
        <?php endif; ?>
    </div>

    <!-- สรุปสถานะเป็น BADGE กะทัดรัด -->
    <div class="p-2 bg-white rounded-3 border shadow-sm mb-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-2 px-3 d-inline-flex align-items-center gap-2">
                <i class="bi bi-inbox-fill"></i>
                <span>รับทั้งหมด:</span>
                <strong class="fs-6 font-monospace"><?= Html::encode((string) $dataProvider->getTotalCount()) ?></strong>
            </span>
        </div>
    </div>

    <div class="content-card search-card table-search-card">
        <?= Html::beginForm(['index'], 'get', ['class' => 'document-search-form']) ?>
            <div>
                <label class="form-label" for="document-search">ค้นหาหนังสือ</label>
                <?= Html::textInput('q', $keyword, [
                    'id' => 'document-search',
                    'class' => 'form-control',
                    'placeholder' => 'ค้นจากชื่อเรื่อง หรือเลขที่หนังสือต้นเรื่อง',
                ]) ?>
            </div>
            <div class="search-actions">
                <?= Html::submitButton('<i class="bi bi-search" aria-hidden="true"></i><span>ค้นหา</span>', ['class' => 'btn btn-primary']) ?>
                <?php if ($keyword !== ''): ?>
                    <?= Html::a('<i class="bi bi-x-circle" aria-hidden="true"></i><span>ล้าง</span>', Url::to(['index']), ['class' => 'btn btn-outline-secondary']) ?>
                <?php endif; ?>
            </div>
        <?= Html::endForm() ?>
    </div>

    <div class="content-card">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'tableOptions' => ['class' => 'table table-hover align-middle'],
            'summary' => 'แสดงรายการที่ {begin}-{end} จากทั้งหมด {totalCount} รายการ',
            'emptyText' => 'ไม่พบหนังสือรับ',
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
    </div>
</div>
