<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $keyword */
use common\models\Memo;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var string $roleLabel */

$this->title = 'งานบันทึกของฉัน';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$statusBadge = static function (Memo $model): string {
    $statusClass = 'status-badge-' . str_replace('_', '-', $model->status);
    return '<span class="status-badge ' . Html::encode($statusClass) . '">' . Html::encode(Memo::statusOptions()[$model->status] ?? $model->status) . '</span>';
};
?>

<div class="eoffice-page">
    <div class="eoffice-titlebar">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <p><?= Html::encode($roleLabel) ?> - บันทึกเสนอที่รอดำเนินการหรือรอรับทราบ</p>
        </div>
        <?= Html::a('<i class="bi bi-journal-text" aria-hidden="true"></i><span>ดูบันทึกเสนอทั้งหมด</span>', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <div class="content-card search-card table-search-card">
        <?= Html::beginForm(['my'], 'get', ['class' => 'document-search-form']) ?>
            <div>
                <label class="form-label" for="my-memo-search">ค้นหางานบันทึก</label>
                <?= Html::textInput('q', $keyword, ['id' => 'my-memo-search', 'class' => 'form-control', 'placeholder' => 'ค้นจากชื่อเรื่อง หรือเลขที่บันทึก']) ?>
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
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'tableOptions' => ['class' => 'table table-hover align-middle'],
            'summary' => 'แสดงรายการที่ {begin}-{end} จากทั้งหมด {totalCount} รายการ',
            'emptyText' => 'ยังไม่มีงานบันทึกที่ต้องดำเนินการ',
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
            ],
        ]) ?>
    </div>
</div>
?>
