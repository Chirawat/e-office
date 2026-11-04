<?php

declare(strict_types=1);

use common\models\Memo;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $keyword */

$this->title = 'บันทึกเสนอ';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$statusBadge = static function (Memo $model): string {
    $statusClass = 'status-badge-' . str_replace('_', '-', $model->status);
    return '<span class="status-badge ' . Html::encode($statusClass) . '">' . Html::encode(Memo::statusOptions()[$model->status] ?? $model->status) . '</span>';
};
$thaiDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return '-';
    }
    $timestamp = strtotime($date);
    $months = [1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'];
    return (int) date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . ((int) date('Y', $timestamp) + 543);
};
?>

<div class="eoffice-page">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h1 class="h5 fw-bold text-dark mb-0"><?= Html::encode($this->title) ?></h1>
            <div class="small text-muted">บันทึกข้อความภายในและสถานะการเสนอ</div>
        </div>
    </div>

    <div class="content-card search-card table-search-card">
        <?= Html::beginForm(['index'], 'get', ['class' => 'document-search-form']) ?>
            <div>
                <label class="form-label" for="memo-search">ค้นหาบันทึก</label>
                <?= Html::textInput('q', $keyword, ['id' => 'memo-search', 'class' => 'form-control', 'placeholder' => 'ค้นจากชื่อเรื่อง หรือเลขที่บันทึก']) ?>
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
            'emptyText' => 'ยังไม่มีบันทึกเสนอ',
            'columns' => [
                ['attribute' => 'memo_no', 'value' => static fn (Memo $model): string => $model->memo_no ?: '-'],
                [
                    'attribute' => 'subject',
                    'format' => 'raw',
                    'value' => static fn (Memo $model): string => Html::a(Html::encode($model->subject), ['view', 'id' => $model->id]),
                ],
                ['attribute' => 'department_id', 'value' => static fn (Memo $model): string => $model->department->name ?? '-'],
                ['label' => 'ผู้จัดทำ', 'value' => static fn (Memo $model): string => $model->creator->username ?? '-'],
                ['label' => 'ผู้รับงานปัจจุบัน', 'value' => static fn (Memo $model): string => $model->currentActor->username ?? '-'],
                ['attribute' => 'status', 'format' => 'raw', 'value' => $statusBadge],
                ['attribute' => 'memo_date', 'value' => static fn (Memo $model): string => $thaiDate($model->memo_date)],
            ],
        ]) ?>
    </div>
</div>
