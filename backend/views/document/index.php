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
/** @var string $scope */
/** @var int $countAll */
/** @var int $countMy */
/** @var int[] $myAssignedDocIds */

$this->title = 'หนังสือรับ (หนังสือภายนอก)';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$identity = Yii::$app->user->identity;
$isGeneralStaff = $identity instanceof User && ($identity->role === User::ROLE_STAFF || $identity->role === User::ROLE_GENERAL_USER);
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
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
        7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.',
    ];

    return (int) date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . ((int) date('Y', $timestamp) + 543);
};
?>

<div class="eoffice-page">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h1 class="h5 fw-bold text-dark mb-0"><?= Html::encode($this->title) ?></h1>
            <div class="small text-muted">ทะเบียนหนังสือรับจากภายนอก/SMSS และการสั่งการ</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= Url::to(['/document/my']) ?>" class="btn btn-sm btn-outline-primary fw-semibold">
                <i class="bi bi-speedometer2 me-1"></i> โต๊ะทำงานของฉัน
            </a>
            <?php if ($canCreateDocument): ?>
                <?= Html::a('<i class="bi bi-plus-circle me-1" aria-hidden="true"></i><span>ลงทะเบียนหนังสือรับ</span>', ['create'], ['class' => 'btn btn-sm btn-primary fw-semibold']) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- แถบสลับโหมดมุมมอง: หนังสือภายนอกทั้งหมด vs เฉพาะที่เกี่ยวกับตัวเอง (Mobile-First Pill Badges) -->
    <?php if ($isGeneralStaff): ?>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
            <a href="<?= Url::to(['index', 'scope' => 'all', 'q' => $keyword]) ?>" 
               class="badge text-decoration-none py-2 px-3 d-inline-flex align-items-center gap-2 <?= $scope === 'all' ? 'bg-primary text-white shadow-sm' : 'bg-white text-secondary border' ?>"
               style="font-size: 0.85rem; font-weight: 500;">
                <i class="bi bi-globe2"></i>
                <span>1. หนังสือภายนอก (ทั้งหมด)</span>
                <span class="badge <?= $scope === 'all' ? 'bg-white text-primary' : 'bg-light text-dark border' ?> rounded-pill font-monospace"><?= $countAll ?></span>
            </a>

            <a href="<?= Url::to(['index', 'scope' => 'my', 'q' => $keyword]) ?>" 
               class="badge text-decoration-none py-2 px-3 d-inline-flex align-items-center gap-2 <?= $scope === 'my' ? 'bg-primary text-white shadow-sm' : 'bg-white text-secondary border' ?>"
               style="font-size: 0.85rem; font-weight: 500;">
                <i class="bi bi-person-check-fill text-warning"></i>
                <span>2. เฉพาะเกี่ยวกับตัวเอง</span>
                <span class="badge <?= $scope === 'my' ? 'bg-white text-primary' : 'bg-warning-subtle text-warning-emphasis border border-warning' ?> rounded-pill font-monospace"><?= $countMy ?></span>
            </a>
        </div>
    <?php else: ?>
        <!-- สรุปสถานะทั่วไปสำหรับ Admin / ผู้บริหาร -->
        <div class="p-2 bg-white rounded-3 border shadow-sm mb-3">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-2 px-3 d-inline-flex align-items-center gap-2">
                    <i class="bi bi-inbox-fill"></i>
                    <span>จำนวนหนังสือในระบบ:</span>
                    <strong class="fs-6 font-monospace"><?= Html::encode((string) $dataProvider->getTotalCount()) ?></strong>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <div class="content-card search-card table-search-card">
        <?= Html::beginForm(['index'], 'get', ['class' => 'document-search-form']) ?>
            <?php if ($isGeneralStaff): ?>
                <?= Html::hiddenInput('scope', $scope) ?>
            <?php endif; ?>
            <div>
                <label class="form-label" for="document-search">ค้นหาหนังสือ</label>
                <?= Html::textInput('q', $keyword, [
                    'id' => 'document-search',
                    'class' => 'form-control',
                    'placeholder' => 'ค้นจากชื่อเรื่อง, เลขที่ต้นเรื่อง หรือเลขรับ',
                ]) ?>
            </div>
            <div class="search-actions">
                <?= Html::submitButton('<i class="bi bi-search" aria-hidden="true"></i><span>ค้นหา</span>', ['class' => 'btn btn-primary']) ?>
                <?php if ($keyword !== ''): ?>
                    <?= Html::a('<i class="bi bi-x-circle" aria-hidden="true"></i><span>ล้าง</span>', Url::to(['index', 'scope' => $scope]), ['class' => 'btn btn-outline-secondary']) ?>
                <?php endif; ?>
            </div>
        <?= Html::endForm() ?>
    </div>

    <div class="content-card">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'tableOptions' => ['class' => 'table table-hover align-middle mb-0'],
            'summary' => 'แสดงรายการที่ {begin}-{end} จากทั้งหมด {totalCount} รายการ',
            'emptyText' => 'ไม่พบรายการหนังสือภายนอก',
            'columns' => [
                [
                    'attribute' => 'receive_no',
                    'format' => 'raw',
                    'value' => static fn (Document $model): string => '<span class="font-monospace fw-semibold text-primary">' . Html::encode($model->receive_no) . '</span>',
                ],
                [
                    'attribute' => 'subject',
                    'format' => 'raw',
                    'value' => static function (Document $model) use ($myAssignedDocIds): string {
                        $isMy = in_array((int) $model->id, $myAssignedDocIds, true);
                        $myBadge = $isMy ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size: 0.7rem;"><i class="bi bi-person-fill"></i> มอบหมายถึงท่าน</span>' : '';
                        return Html::a(Html::encode($model->subject), ['view', 'id' => $model->id], ['class' => 'text-decoration-none fw-semibold text-dark d-inline']) . ' ' . $myBadge;
                    },
                ],
                'external_no',
                'sender',
                [
                    'attribute' => 'department_id',
                    'value' => static fn (Document $model): string => $model->department->name ?? '-',
                ],
                [
                    'attribute' => 'urgency',
                    'format' => 'raw',
                    'value' => static function (Document $model): string {
                        $label = Document::urgencyOptions()[$model->urgency] ?? $model->urgency;
                        if ($model->urgency === Document::URGENCY_MOST_URGENT || $model->urgency === Document::URGENCY_VERY_URGENT) {
                            return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">' . Html::encode($label) . '</span>';
                        }
                        if ($model->urgency === Document::URGENCY_URGENT) {
                            return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">' . Html::encode($label) . '</span>';
                        }
                        return '<span class="badge bg-light text-secondary border">' . Html::encode($label) . '</span>';
                    },
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
