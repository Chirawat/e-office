<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\User $identity */
/** @var array $stats */
/** @var common\models\Document[] $recentDocs */
/** @var common\models\Memo[] $recentMemos */

use common\models\Document;
use common\models\Memo;
use common\models\User;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'หน้าหลัก - e-Office';
$this->params['breadcrumbs'][] = 'หน้าหลัก';

$isAdmin = $identity->hasRole(User::ROLE_ADMIN);
$isExec = $identity->hasRole([User::ROLE_DIRECTOR, User::ROLE_DEPUTY_DIRECTOR]);
$isOfficer = $identity->hasRole([User::ROLE_OFFICER, User::ROLE_ADMIN]);
?>

<div class="eoffice-page">

    <!-- Header สรุปข้อมูลผู้ใช้แบบกะทัดรัด (Mobile-First Compact Header) -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h1 class="h5 fw-bold text-dark mb-0">
                <i class="bi bi-person-circle text-primary me-1"></i><?= Html::encode($identity->displayName()) ?>
            </h1>
            <div class="small text-muted d-flex align-items-center gap-2 mt-1">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= Html::encode($identity->roleLabel()) ?></span>
                <span><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y') ?></span>
                <?php if (!empty($identity->line_user_id)): ?>
                    <span class="text-success small"><i class="bi bi-chat-check-fill me-1"></i>LINE พร้อม</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex gap-1 flex-wrap">
            <a href="<?= Url::to(['/document/my']) ?>" class="btn btn-sm btn-primary fw-semibold">
                <i class="bi bi-speedometer2 me-1"></i> โต๊ะทำงาน
            </a>
            <a href="<?= Url::to(['/memo/create']) ?>" class="btn btn-sm btn-outline-primary fw-semibold">
                <i class="bi bi-plus-lg me-1"></i> ร่าง e-Memo
            </a>
            <?php if ($isOfficer): ?>
                <a href="<?= Url::to(['/document/create']) ?>" class="btn btn-sm btn-outline-success fw-semibold">
                    <i class="bi bi-inbox-fill me-1"></i> ลงรับหนังสือ
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- แถบแจ้งเตือน Admin ถ้ามีครูรออนุมัติ -->
    <?php if ($isAdmin && $stats['pending_users'] > 0): ?>
        <div class="alert alert-warning py-2 px-3 small d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 border-warning" role="alert">
            <div>
                <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                <strong>มีคุณครูใหม่รออนุมัติ:</strong> <span class="badge bg-danger rounded-pill"><?= $stats['pending_users'] ?></span> คน
            </div>
            <a href="<?= Url::to(['/user/index', 'status' => User::STATUS_INACTIVE]) ?>" class="btn btn-sm btn-warning text-dark fw-bold py-0 px-2">
                อนุมัติทันที <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    <?php endif; ?>

    <!-- แถบแสดงสถานะสรุปเป็น BADGE แนวนอน (แทน Card ใหญ่ที่เกะกะบนมือถือ) -->
    <div class="p-2 bg-white rounded-3 border shadow-sm mb-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="small text-muted fw-bold text-uppercase d-none d-sm-inline me-1">สรุปสถานะ:</span>
            
            <!-- Badge 1: งานค้าง -->
            <a href="<?= Url::to(['/document/my']) ?>" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-2 px-3 text-decoration-none d-inline-flex align-items-center gap-2">
                <i class="bi bi-inbox-fill"></i>
                <span>งานมอบหมายที่รอทำ:</span>
                <strong class="fs-6 font-monospace"><?= $stats['my_pending_tasks'] ?></strong>
            </a>

            <!-- Badge 2: บันทึกข้อความ -->
            <a href="<?= Url::to(['/memo/my']) ?>" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-3 text-decoration-none d-inline-flex align-items-center gap-2">
                <i class="bi bi-journal-text"></i>
                <span>e-Memo ของฉัน:</span>
                <strong class="fs-6 font-monospace"><?= $stats['my_memos_count'] ?></strong>
            </a>

            <!-- Badge 3: หนังสือรับเดือนนี้ -->
            <a href="<?= Url::to(['/document/index']) ?>" class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 py-2 px-3 text-decoration-none d-inline-flex align-items-center gap-2">
                <i class="bi bi-calendar-check-fill text-warning"></i>
                <span>หนังสือรับเดือนนี้:</span>
                <strong class="fs-6 font-monospace"><?= $stats['month_docs'] ?></strong>
            </a>

            <!-- Badge 4: ตามบทบาท -->
            <?php if ($isExec): ?>
                <a href="<?= Url::to(['/document/my']) ?>" class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 py-2 px-3 text-decoration-none d-inline-flex align-items-center gap-2">
                    <i class="bi bi-pen-fill"></i>
                    <span>รอผู้บริหารสั่งการ:</span>
                    <strong class="fs-6 font-monospace"><?= $identity->hasRole(User::ROLE_DIRECTOR) ? $stats['pending_director_docs'] : $stats['pending_deputy_docs'] ?></strong>
                </a>
            <?php elseif ($isAdmin): ?>
                <a href="<?= Url::to(['/user/index']) ?>" class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 py-2 px-3 text-decoration-none d-inline-flex align-items-center gap-2">
                    <i class="bi bi-people-fill"></i>
                    <span>ผู้ใช้ทั้งหมด:</span>
                    <strong class="fs-6 font-monospace"><?= $stats['total_users'] ?></strong>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- รายการเอกสารล่าสุด 2 ส่วน (Compact & Mobile-First Tables) -->
    <div class="row g-3">
        
        <!-- ฝั่งซ้าย: หนังสือรับล่าสุด -->
        <div class="col-lg-7 col-12">
            <div class="content-card">
                <div class="table-header-banner py-2 px-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-inbox-fill"></i>
                        <span class="fw-semibold">หนังสือรับเข้าล่าสุด</span>
                    </div>
                    <a href="<?= Url::to(['/document/index']) ?>" class="btn btn-sm btn-outline-light py-0 px-2 small">
                        ดูทั้งหมด <i class="bi bi-chevron-right"></i>
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 100px;">เลขรับ</th>
                                <th>เรื่อง / ต้นเรื่อง</th>
                                <th style="width: 90px;" class="text-center">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentDocs === []): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">ยังไม่มีเอกสารในระบบ</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentDocs as $doc): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= Url::to(['/document/view', 'id' => $doc->id]) ?>" class="fw-bold text-primary font-monospace text-decoration-none d-block">
                                                <?= Html::encode($doc->receive_no) ?>
                                            </a>
                                            <span class="small text-muted" style="font-size: 0.75rem;"><?= Html::encode($doc->receive_date) ?></span>
                                        </td>
                                        <td>
                                            <a href="<?= Url::to(['/document/view', 'id' => $doc->id]) ?>" class="text-dark fw-semibold text-decoration-none d-block text-truncate" style="max-width: 320px;">
                                                <?= Html::encode($doc->subject) ?>
                                            </a>
                                            <div class="small text-muted text-truncate" style="font-size: 0.75rem; max-width: 300px;">
                                                <i class="bi bi-send me-1"></i><?= Html::encode($doc->sender) ?>
                                                <?php if ($doc->department !== null): ?>
                                                    <span class="ms-1 badge bg-light text-dark border"><?= Html::encode($doc->department->name) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?= match ($doc->status) {
                                                Document::STATUS_COMPLETED => '<span class="badge bg-success-subtle text-success border border-success">เสร็จสิ้น</span>',
                                                Document::STATUS_ASSIGNED => '<span class="badge bg-primary-subtle text-primary border border-primary">มอบหมาย</span>',
                                                Document::STATUS_SUBMITTED_TO_DIRECTOR => '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning">รอ ผอ.</span>',
                                                Document::STATUS_SUBMITTED_TO_DEPUTY => '<span class="badge bg-info-subtle text-info-emphasis border border-info">รอรอง ผอ.</span>',
                                                default => '<span class="badge bg-secondary">รับเรื่อง</span>',
                                            } ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ฝั่งขวา: บันทึกข้อความล่าสุด -->
        <div class="col-lg-5 col-12">
            <div class="content-card">
                <div class="table-header-banner py-2 px-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-journal-bookmark-fill"></i>
                        <span class="fw-semibold">บันทึกข้อความ (e-Memo)</span>
                    </div>
                    <a href="<?= Url::to(['/memo/index']) ?>" class="btn btn-sm btn-outline-light py-0 px-2 small">
                        ดูทั้งหมด <i class="bi bi-chevron-right"></i>
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ชื่อเรื่อง / ผู้ร่าง</th>
                                <th style="width: 85px;" class="text-center">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentMemos === []): ?>
                                <tr>
                                    <td colspan="2" class="text-center py-4 text-muted">ยังไม่มีบันทึกข้อความ</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentMemos as $memo): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= Url::to(['/memo/view', 'id' => $memo->id]) ?>" class="text-dark fw-semibold text-decoration-none d-block text-truncate" style="max-width: 250px;">
                                                <?= Html::encode($memo->subject) ?>
                                            </a>
                                            <div class="small text-muted" style="font-size: 0.75rem;">
                                                <i class="bi bi-person me-1"></i><?= Html::encode($memo->creator?->displayName() ?? 'ไม่ระบุ') ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?= match ($memo->status) {
                                                Memo::STATUS_COMPLETED, Memo::STATUS_APPROVED => '<span class="badge bg-success-subtle text-success border border-success">อนุมัติ</span>',
                                                Memo::STATUS_SUBMITTED_TO_DIRECTOR => '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning">รอ ผอ.</span>',
                                                Memo::STATUS_SUBMITTED_TO_DEPUTY => '<span class="badge bg-info-subtle text-info-emphasis border border-info">รอรอง ผอ.</span>',
                                                Memo::STATUS_DRAFT => '<span class="badge bg-light text-muted border">ร่าง</span>',
                                                default => '<span class="badge bg-secondary">' . Html::encode($memo->status) . '</span>',
                                            } ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>
