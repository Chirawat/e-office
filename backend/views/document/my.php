<?php

declare(strict_types=1);

use common\models\Document;
use common\models\Memo;
use common\models\User;
use yii\data\ActiveDataProvider;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var common\models\User $identity */
/** @var ActiveDataProvider $docDataProvider */
/** @var ActiveDataProvider $memoDataProvider */
/** @var array<string, int> $stats */
/** @var string $keyword */
/** @var string $memoKeyword */
/** @var string $roleLabel */

$this->title = 'โต๊ะทำงาน (งานของฉัน)';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);

$docStatusBadge = static function (Document $model): string {
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
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
        7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.',
    ];

    return (int) date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . ((int) date('Y', $timestamp) + 543);
};
?>

<div class="eoffice-page">

    <!-- Header สรุปข้อมูลผู้ใช้แบบกะทัดรัด (Mobile-First Header) -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h1 class="h5 fw-bold text-dark mb-0">
                <i class="bi bi-speedometer2 text-primary me-1"></i><?= Html::encode($this->title) ?>
            </h1>
            <div class="small text-muted d-flex align-items-center gap-2 mt-1 flex-wrap">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= Html::encode($roleLabel) ?></span>
                <span class="fw-semibold text-dark"><?= Html::encode($identity->displayName()) ?></span>
                <span><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y') ?></span>
                <?php if (!empty($identity->line_user_id)): ?>
                    <span class="text-success small"><i class="bi bi-chat-check-fill me-1"></i>LINE พร้อมแจ้งเตือน</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= Url::to(['/memo/create']) ?>" class="btn btn-sm btn-primary fw-semibold">
                <i class="bi bi-plus-lg me-1"></i> ร่าง e-Memo ใหม่
            </a>
            <a href="<?= Url::to(['/document/index']) ?>" class="btn btn-sm btn-outline-secondary fw-semibold">
                <i class="bi bi-inbox me-1"></i> ทะเบียนหนังสือภายนอก
            </a>
        </div>
    </div>

    <!-- แถบสรุปสถานะแบบ BADGE แนวนอน (Mobile-First Compact Badges แทน Card ใหญ่) -->
    <div class="p-2 bg-white rounded-3 border shadow-sm mb-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="small text-muted fw-bold text-uppercase d-none d-sm-inline me-1">สรุปงานส่วนตัว:</span>

            <!-- Badge 1: หนังสือภายนอกมอบหมายรอทำ -->
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-2 px-3 d-inline-flex align-items-center gap-2">
                <i class="bi bi-inbox-fill"></i>
                <span>หนังสือภายนอกรอทำ:</span>
                <strong class="fs-6 font-monospace"><?= Html::encode((string) $stats['doc_pending']) ?></strong>
            </span>

            <!-- Badge 2: หนังสือภายนอกเสร็จสิ้น -->
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 py-2 px-3 d-inline-flex align-items-center gap-2">
                <i class="bi bi-check2-all"></i>
                <span>หนังสือรับเสร็จสิ้น:</span>
                <strong class="fs-6 font-monospace"><?= Html::encode((string) $stats['doc_completed']) ?></strong>
            </span>

            <!-- Badge 3: บันทึกข้อความรอเสนอ/รอพิจารณา -->
            <a href="<?= Url::to(['/memo/my']) ?>" class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 py-2 px-3 text-decoration-none d-inline-flex align-items-center gap-2">
                <i class="bi bi-hourglass-split text-warning"></i>
                <span>e-Memo รอพิจารณา/อนุมัติ:</span>
                <strong class="fs-6 font-monospace"><?= Html::encode((string) $stats['memo_pending']) ?></strong>
            </a>

            <!-- Badge 4: บันทึกข้อความของฉันทั้งหมด -->
            <a href="<?= Url::to(['/memo/index']) ?>" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 text-decoration-none d-inline-flex align-items-center gap-2">
                <i class="bi bi-journal-text"></i>
                <span>e-Memo ของฉัน:</span>
                <strong class="fs-6 font-monospace"><?= Html::encode((string) $stats['memo_total']) ?></strong>
            </a>
        </div>
    </div>

    <!-- แดชบอร์ดโต๊ะทำงาน 2 คอลัมน์ (Desktop: 2 ฝั่งคู่กัน, Mobile: เรียงต่อกันแนวดิ่ง) -->
    <div class="row g-3">

        <!-- คอลัมน์ซ้าย: หนังสือภายนอกที่มอบหมายถึงตนเอง -->
        <div class="col-lg-6 col-12">
            <div class="content-card h-100 mb-0 d-flex flex-column">
                <div class="table-header-banner py-2 px-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-inbox-fill text-warning"></i>
                        <span class="fw-semibold">1. หนังสือภายนอก (ที่มอบหมายถึงคุณ)</span>
                    </div>
                    <a href="<?= Url::to(['/document/index', 'scope' => 'my']) ?>" class="btn btn-sm btn-outline-light py-0 px-2 small">
                        ดูทั้งหมด <i class="bi bi-chevron-right"></i>
                    </a>
                </div>

                <div class="p-2 border-bottom bg-light">
                    <?= Html::beginForm(['my'], 'get', ['class' => 'd-flex gap-2']) ?>
                        <?= Html::hiddenInput('q_memo', $memoKeyword) ?>
                        <?= Html::textInput('q_doc', $keyword, [
                            'class' => 'form-control form-control-sm',
                            'placeholder' => 'ค้นหาชื่อเรื่อง หรือเลขหนังสือรับ...',
                        ]) ?>
                        <?= Html::submitButton('<i class="bi bi-search"></i>', ['class' => 'btn btn-sm btn-primary px-3']) ?>
                        <?php if ($keyword !== ''): ?>
                            <?= Html::a('<i class="bi bi-x"></i>', Url::to(['my', 'q_memo' => $memoKeyword]), ['class' => 'btn btn-sm btn-outline-secondary']) ?>
                        <?php endif; ?>
                    <?= Html::endForm() ?>
                </div>

                <div class="table-responsive flex-grow-1">
                    <?= GridView::widget([
                        'dataProvider' => $docDataProvider,
                        'tableOptions' => ['class' => 'table table-hover align-middle mb-0'],
                        'summary' => '<div class="small text-muted px-2 py-1">แสดง {begin}-{end} จาก {totalCount} รายการ</div>',
                        'emptyText' => '<div class="text-center py-4 text-muted"><i class="bi bi-check-circle fs-3 d-block text-success mb-2"></i>ไม่มีหนังสือภายนอกค้างดำเนินการ</div>',
                        'columns' => [
                            [
                                'attribute' => 'receive_no',
                                'label' => 'เลขรับ',
                                'format' => 'raw',
                                'value' => static fn (Document $model): string => '<a href="' . Url::to(['view', 'id' => $model->id]) . '" class="fw-bold font-monospace text-primary text-decoration-none">' . Html::encode($model->receive_no) . '</a><br><span class="text-muted" style="font-size:0.7rem;">' . Html::encode($model->receive_date) . '</span>',
                            ],
                            [
                                'attribute' => 'subject',
                                'label' => 'เรื่อง / ต้นทาง',
                                'format' => 'raw',
                                'value' => static function (Document $model): string {
                                    $urgencyBadge = '';
                                    if ($model->urgency === Document::URGENCY_MOST_URGENT || $model->urgency === Document::URGENCY_VERY_URGENT) {
                                        $urgencyBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1" style="font-size:0.65rem;">ด่วนมาก</span>';
                                    } elseif ($model->urgency === Document::URGENCY_URGENT) {
                                        $urgencyBadge = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle me-1" style="font-size:0.65rem;">ด่วน</span>';
                                    }
                                    return $urgencyBadge . Html::a(Html::encode($model->subject), ['view', 'id' => $model->id], ['class' => 'text-dark fw-semibold text-decoration-none'])
                                        . '<div class="text-muted small text-truncate" style="font-size:0.75rem; max-width:280px;"><i class="bi bi-send me-1"></i>' . Html::encode($model->sender) . '</div>';
                                },
                            ],
                            [
                                'attribute' => 'status',
                                'label' => 'สถานะ',
                                'format' => 'raw',
                                'value' => $docStatusBadge,
                            ],
                            [
                                'label' => 'จัดการ',
                                'format' => 'raw',
                                'value' => static fn (Document $model): string => Html::a('<i class="bi bi-eye"></i> ดูงาน', ['view', 'id' => $model->id], ['class' => 'btn btn-xs btn-outline-primary py-0 px-2 fw-semibold']),
                            ],
                        ],
                    ]) ?>
                </div>

                <div class="p-2 border-top bg-light text-end">
                    <a href="<?= Url::to(['/document/index', 'scope' => 'all']) ?>" class="small text-decoration-none text-muted">
                        <i class="bi bi-globe2 me-1"></i> เข้าสู่หน้าหนังสือภายนอกทั้งหมด <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- คอลัมน์ขวา: บันทึกข้อความ (e-Memo) ที่เกี่ยวข้องกับตนเอง -->
        <div class="col-lg-6 col-12">
            <div class="content-card h-100 mb-0 d-flex flex-column">
                <div class="table-header-banner-memo py-2 px-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-journal-bookmark-fill text-warning"></i>
                        <span class="fw-semibold">2. บันทึกข้อความ (e-Memo ของคุณ)</span>
                    </div>
                    <a href="<?= Url::to(['/memo/index']) ?>" class="btn btn-sm btn-outline-light py-0 px-2 small">
                        ดูทั้งหมด <i class="bi bi-chevron-right"></i>
                    </a>
                </div>

                <div class="p-2 border-bottom bg-light">
                    <?= Html::beginForm(['my'], 'get', ['class' => 'd-flex gap-2']) ?>
                        <?= Html::hiddenInput('q_doc', $keyword) ?>
                        <?= Html::textInput('q_memo', $memoKeyword, [
                            'class' => 'form-control form-control-sm',
                            'placeholder' => 'ค้นหาชื่อเรื่อง หรือเลขที่บันทึกข้อความ...',
                        ]) ?>
                        <?= Html::submitButton('<i class="bi bi-search"></i>', ['class' => 'btn btn-sm btn-success px-3']) ?>
                        <?php if ($memoKeyword !== ''): ?>
                            <?= Html::a('<i class="bi bi-x"></i>', Url::to(['my', 'q_doc' => $keyword]), ['class' => 'btn btn-sm btn-outline-secondary']) ?>
                        <?php endif; ?>
                    <?= Html::endForm() ?>
                </div>

                <div class="table-responsive flex-grow-1">
                    <?= GridView::widget([
                        'dataProvider' => $memoDataProvider,
                        'tableOptions' => ['class' => 'table table-hover align-middle mb-0'],
                        'summary' => '<div class="small text-muted px-2 py-1">แสดง {begin}-{end} จาก {totalCount} รายการ</div>',
                        'emptyText' => '<div class="text-center py-4 text-muted"><i class="bi bi-journal-text fs-3 d-block text-muted mb-2"></i>ยังไม่มีบันทึกข้อความของคุณ <br><a href="' . Url::to(['/memo/create']) . '" class="btn btn-sm btn-outline-success mt-2"><i class="bi bi-plus-lg me-1"></i>ร่างบันทึกข้อความใหม่</a></div>',
                        'columns' => [
                            [
                                'attribute' => 'memo_no',
                                'label' => 'เลขที่บันทึก',
                                'format' => 'raw',
                                'value' => static fn (Memo $model): string => '<a href="' . Url::to(['/memo/view', 'id' => $model->id]) . '" class="fw-bold font-monospace text-success text-decoration-none">' . Html::encode($model->memo_no ?: '(ฉบับร่าง)') . '</a><br><span class="text-muted" style="font-size:0.7rem;">' . Html::encode($model->memo_date) . '</span>',
                            ],
                            [
                                'attribute' => 'subject',
                                'label' => 'ชื่อเรื่อง / ผู้รับงานปัจจุบัน',
                                'format' => 'raw',
                                'value' => static function (Memo $model) use ($identity): string {
                                    $isCreator = (int) $model->created_by === (int) $identity->id;
                                    $creatorTag = $isCreator ? '<span class="badge bg-light text-dark border me-1" style="font-size:0.65rem;">ร่างโดยคุณ</span>' : '';
                                    return $creatorTag . Html::a(Html::encode($model->subject), ['/memo/view', 'id' => $model->id], ['class' => 'text-dark fw-semibold text-decoration-none'])
                                        . '<div class="text-muted small text-truncate" style="font-size:0.75rem; max-width:260px;"><i class="bi bi-person me-1"></i>ส่งถึง: ' . Html::encode($model->currentActor?->displayName() ?? 'ไม่ระบุ') . '</div>';
                                },
                            ],
                            [
                                'attribute' => 'status',
                                'label' => 'สถานะ',
                                'format' => 'raw',
                                'value' => $memoStatusBadge,
                            ],
                            [
                                'label' => 'จัดการ',
                                'format' => 'raw',
                                'value' => static fn (Memo $model): string => Html::a('<i class="bi bi-arrow-right-circle"></i> ดู', ['/memo/view', 'id' => $model->id], ['class' => 'btn btn-xs btn-outline-success py-0 px-2 fw-semibold']),
                            ],
                        ],
                    ]) ?>
                </div>

                <div class="p-2 border-top bg-light text-end">
                    <a href="<?= Url::to(['/memo/create']) ?>" class="btn btn-xs btn-success text-white fw-semibold me-2">
                        <i class="bi bi-plus-lg me-1"></i> ร่างบันทึกข้อความ
                    </a>
                    <a href="<?= Url::to(['/memo/index']) ?>" class="small text-decoration-none text-muted">
                        ดูบันทึกข้อความทั้งหมด <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>
