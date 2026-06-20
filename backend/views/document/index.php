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
    <div class="eoffice-titlebar">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <p>ทะเบียนหนังสือรับจาก SMSS และสถานะการเสนอ</p>
        </div>
        <?php if ($canCreateDocument): ?>
            <?= Html::a('<i class="bi bi-plus-circle" aria-hidden="true"></i><span>ลงทะเบียนหนังสือรับ</span>', ['create'], ['class' => 'btn btn-primary']) ?>
        <?php endif; ?>
    </div>

    <div class="metric-grid">
        <div class="metric-card">
            <i class="metric-icon bi bi-inbox" aria-hidden="true"></i>
            <span>รับใหม่</span>
            <strong><?= Html::encode((string) $dataProvider->getTotalCount()) ?></strong>
            <small>รายการทั้งหมดในระบบ</small>
        </div>
        <div class="metric-card warning">
            <i class="metric-icon bi bi-hourglass-split" aria-hidden="true"></i>
            <span>รอสั่งการ</span>
            <strong>0</strong>
            <small>รอผู้บริหาร</small>
        </div>
        <div class="metric-card danger">
            <i class="metric-icon bi bi-exclamation-triangle" aria-hidden="true"></i>
            <span>ด่วน</span>
            <strong>0</strong>
            <small>ต้องติดตาม</small>
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
