<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var common\models\User|null $user */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'รอการอนุมัติการใช้งาน - e-Office';
$ver = time();
$this->registerCssFile("@web/css/eoffice.css?v={$ver}", ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerCssFile('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css');

$name = $user !== null ? $user->displayName() : 'คุณครู';
$dept = $user?->department?->name ?? 'ทั่วไป';
$learningArea = $user?->learning_area ?: 'ไม่ระบุ';
?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden my-auto my-md-4" style="max-width: 580px; width: 100%;">
    <div class="p-3 p-sm-4 p-md-5 bg-white text-center">

            <!-- ไอคอนนาฬิกาทราย / อนุมัติ -->
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 shadow-sm" style="width: 80px; height: 80px; background: #fff7ed; color: #ea580c; font-size: 38px; border: 2px solid #fdba74;">
                <i class="bi bi-hourglass-split"></i>
            </div>

            <h1 class="h3 fw-bold text-dark mb-2">ลงทะเบียนเรียบร้อยแล้ว</h1>
            <p class="text-secondary mb-4">ข้อมูลของท่านถูกส่งเข้าสู่ระบบแล้ว และอยู่ระหว่างรอการยืนยัน</p>

            <!-- กล่องสรุปข้อมูลผู้สมัคร -->
            <div class="card bg-light border-0 rounded-3 p-3 text-start mb-4">
                <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
                    <i class="bi bi-person-circle fs-5 text-primary"></i>
                    <div>
                        <div class="small text-muted">ชื่อ-นามสกุลผู้ใช้งาน</div>
                        <strong class="text-dark"><?= Html::encode($name) ?></strong>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-sm-6">
                        <div class="small text-muted">กลุ่มงาน / ฝ่ายบริหาร</div>
                        <div class="fw-semibold text-dark"><?= Html::encode($dept) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">กลุ่มสาระการเรียนรู้</div>
                        <div class="fw-semibold text-dark"><?= Html::encode($learningArea) ?></div>
                    </div>
                    <div class="col-12 mt-2">
                        <div class="small text-muted">สถานะบัญชี</div>
                        <div><span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1"><i class="bi bi-clock me-1"></i>รอ Admin ยืนยันข้อมูล</span></div>
                    </div>
                </div>

                <?php if ($user !== null && !empty($user->line_user_id)): ?>
                    <div class="mt-3 pt-2 border-top d-flex align-items-center gap-2">
                        <i class="bi bi-chat-check-fill text-success fs-5"></i>
                        <span class="small text-success fw-semibold">เชื่อมต่อกับ LINE (<?= Html::encode($user->line_display_name ?: 'เรียบร้อย') ?>) แล้ว</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- คำแนะนำกระบวนการถัดไป -->
            <div class="alert alert-info border-0 rounded-3 text-start py-3 px-3 small mb-4" style="background-color: #eff6ff; color: #1e40af;">
                <h6 class="fw-bold mb-2"><i class="bi bi-info-circle-fill me-1 text-primary"></i> ขั้นตอนต่อไป:</h6>
                <ol class="mb-0 ps-3">
                    <li class="mb-1">ระบบได้ส่งการแจ้งเตือนไปยัง <strong>ผู้ดูแลระบบ (Admin)</strong> เพื่อตรวจสอบข้อมูลแล้ว</li>
                    <li class="mb-1">เมื่อได้รับการอนุมัติแล้ว ระบบจะส่งข้อความแจ้งเตือนเข้า <strong>LINE</strong> ของท่านทันที</li>
                    <li>ท่านสามารถกลับมาเข้าสู่ระบบด้วย LINE หรือรหัสผ่านที่กำหนดไว้ได้ทันทีหลังได้รับการอนุมัติ</li>
                </ol>
            </div>

            <!-- ปุ่มกลับหน้า Login -->
            <div class="d-grid gap-2">
                <?= Html::a(
                    '<i class="bi bi-box-arrow-in-right me-1"></i><span>กลับไปยังหน้าเข้าสู่ระบบ</span>',
                    ['login'],
                    ['class' => 'btn btn-primary btn-lg rounded-3 fw-semibold text-white shadow-sm']
                ) ?>
            </div>

        </div>
    </div>

