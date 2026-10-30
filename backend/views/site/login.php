<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \common\models\LoginForm $model */
/** @var \common\models\LineLoginSession $lineSession */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'เข้าสู่ระบบ e-Office';
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerCssFile('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css');

$htmlIcon = <<<HTML
{label}<div class="input-group"><span class="input-group-text" aria-hidden="true"><i class="bi %s"></i></span>{input}</div>{error}{hint}
HTML;
$labelOptions = ['class' => 'form-label fw-semibold'];
$demoAccounts = [
    ['role' => 'ผู้ดูแลระบบ', 'username' => 'admin_demo'],
    ['role' => 'เจ้าหน้าที่ธุรการ', 'username' => 'officer_demo'],
    ['role' => 'หัวหน้ากลุ่มงาน', 'username' => 'staff_academic_demo'],
    ['role' => 'รอง ผอ.', 'username' => 'deputy_demo'],
    ['role' => 'ผอ.', 'username' => 'director_demo'],
    ['role' => 'ผู้ใช้ทั่วไป', 'username' => 'teacher_demo'],
];

$checkLoginUrl = Url::to(['/site/check-line-login']);
$refreshSessionUrl = Url::to(['/site/get-line-session']);
$qrData = 'เข้าสู่ระบบ ' . $lineSession->login_code;
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($qrData);
?>
<div class="card border-0 overflow-hidden login-split-card eoffice-login-card">
    <div class="row g-0">

        <div class="col-md-5 d-none d-md-flex login-brand-panel text-white">
            <div class="d-flex flex-column justify-content-between p-4 p-lg-5 w-100">
                <div>
                    <div class="login-brand-mark">
                        <i class="bi bi-building-check" aria-hidden="true"></i>
                        <span>e-Office</span>
                    </div>
                </div>
                <div>
                    <h2 class="fw-bold mb-3 login-brand-title">
                        ระบบสารบรรณ<br>อิเล็กทรอนิกส์
                    </h2>
                    <p class="opacity-75 mb-0 login-brand-text">
                        ลงรับหนังสือ เสนอผู้บริหาร มอบหมายงาน และติดตามสถานะเอกสารในที่เดียว
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="p-4 p-lg-5">
                <div class="text-center mb-4">
                    <div class="d-md-none mb-3">
                        <div class="login-mobile-mark">
                            <i class="bi bi-building-check" aria-hidden="true"></i>
                            <span>e-Office</span>
                        </div>
                    </div>
                    <h1 class="h3 fw-bold mb-1"><?= Html::encode($this->title) ?></h1>
                    <p class="text-body-secondary small mb-0">เลือกวิธีการเข้าสู่ระบบที่สะดวกสำหรับท่าน</p>
                </div>

                <!-- แท็บเลือกวิธีเข้าสู่ระบบ -->
                <ul class="nav nav-tabs nav-fill mb-4" id="loginTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold text-success" id="line-tab" data-bs-toggle="tab" data-bs-target="#line-tab-pane" type="button" role="tab">
                            <i class="bi bi-chat-dots-fill me-1"></i> เข้าสู่ระบบด้วย LINE
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold text-secondary" id="password-tab" data-bs-toggle="tab" data-bs-target="#password-tab-pane" type="button" role="tab">
                            <i class="bi bi-key-fill me-1"></i> ใช้รหัสผ่าน
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="loginTabsContent">

                    <!-- TAB 1: LINE LOGIN (SMART KEY) -->
                    <div class="tab-pane fade show active text-center" id="line-tab-pane" role="tabpanel">
                        <!-- ปุ่มมาตรฐาน LINE LOGIN 1-Click -->
                        <div class="mb-4">
                            <a href="<?= Url::to(['/site/line-auth']) ?>" class="btn btn-lg w-100 text-white fw-bold py-3 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2" style="background-color: #06C755; border: none; font-size: 1.1rem;">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M24 10.304c0-5.369-5.383-9.738-12-9.738-6.616 0-12 4.369-12 9.738 0 4.814 4.269 8.846 10.019 9.572.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975 1.742-1.928 2.564-3.896 2.564-5.932zm-15.659 2.05h-1.637a.56.56 0 0 1-.559-.558v-4.092a.56.56 0 0 1 .559-.559h1.637a.56.56 0 0 1 .559.559.56.56 0 0 1-.559.558h-1.078v1.21h1.078a.56.56 0 0 1 .559.558.56.56 0 0 1-.559.558h-1.078v1.208h1.078a.56.56 0 0 1 .559.558.56.56 0 0 1-.559.558zm3.87 0a.56.56 0 0 1-.559-.558v-4.092a.56.56 0 0 1 .559-.559.56.56 0 0 1 .559.559v4.092a.56.56 0 0 1-.559.558zm4.444 0a.56.56 0 0 1-.444-.219l-2.029-2.736v2.397a.56.56 0 0 1-.559.558.56.56 0 0 1-.558-.558v-4.092a.56.56 0 0 1 .444-.22.56.56 0 0 1 .444.22l2.028 2.735v-2.397a.56.56 0 0 1 .559-.558.56.56 0 0 1 .559.558v4.092a.56.56 0 0 1-.444.455zm3.871-2.973h-1.637v1.208h1.637a.56.56 0 0 1 .559.558.56.56 0 0 1-.559.558h-2.196a.56.56 0 0 1-.559-.558v-4.092a.56.56 0 0 1 .559-.559h2.196a.56.56 0 0 1 .559.559.56.56 0 0 1-.559.558h-1.637v1.21h1.637a.56.56 0 0 1 .559.558.56.56 0 0 1-.559.558z"/>
                                </svg>
                                <span>เข้าสู่ระบบด้วย LINE (คลิกเดียวจบ)</span>
                            </a>
                            <div class="text-muted small mt-2">
                                <i class="bi bi-shield-check text-success me-1"></i> เชื่อมต่อความปลอดภัยผ่าน LINE Official
                            </div>
                        </div>

                        <div class="position-relative my-4">
                            <hr class="text-muted">
                            <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted small">หรือ สแกน QR Code / ส่งรหัสเข้าแชท</span>
                        </div>

                        <div class="p-3 bg-light rounded-4 border mb-3">
                            <div class="d-flex justify-content-center mb-3">
                                <div class="p-2 bg-white rounded-3 shadow-sm border">
                                    <img id="line-qr-image" src="<?= Html::encode($qrUrl) ?>" alt="LINE Login QR Code" width="160" height="160" class="d-block">
                                </div>
                            </div>

                            <div class="mb-2">
                                <span class="text-muted small">หรือส่งรหัสนี้ในแชท LINE OA ของโรงเรียน:</span>
                                <div class="display-6 fw-bold text-primary tracking-wide font-monospace mt-1 mb-1" id="line-login-code">
                                    <?= Html::encode($lineSession->login_code) ?>
                                </div>
                            </div>

                            <div id="line-login-status" class="small text-muted d-flex align-items-center justify-content-center gap-2">
                                <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                                <span>กำลังรอการยืนยันผ่าน LINE...</span>
                            </div>

                            <button id="btn-refresh-session" class="btn btn-sm btn-outline-secondary mt-3 d-none">
                                <i class="bi bi-arrow-clockwise me-1"></i> ขอรหัสเข้าสู่ระบบใหม่
                            </button>
                        </div>

                        <div class="alert alert-success bg-success bg-opacity-10 border-success border-opacity-25 text-start py-2 px-3 small mb-3">
                            <i class="bi bi-lightbulb-fill text-success me-1"></i>
                            <strong>วิธีเข้าสู่ระบบสำหรับครู (ไม่ต้องจำรหัสผ่าน):</strong>
                            <ol class="mb-0 ps-3 mt-1">
                                <li>เปิดกล้องมือถือ หรือแอป LINE สแกน QR Code ด้านบน</li>
                                <li>หรือพิมพ์รหัส <code id="line-code-hint"><?= Html::encode($lineSession->login_code) ?></code> ส่งในแชท LINE OA</li>
                                <li>หน้าจอนี้จะล็อกอินเข้าสู่ระบบโดยอัตโนมัติทันที</li>
                            </ol>
                        </div>
                    </div>

                    <!-- TAB 2: USERNAME / PASSWORD -->
                    <div class="tab-pane fade" id="password-tab-pane" role="tabpanel">
                        <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>

                        <div class="mb-3">
                            <?= $form->field($model, 'username', [
                                'options' => ['class' => 'mb-0'],
                                'template' => sprintf($htmlIcon, 'bi-person'),
                                'inputOptions' => [
                                    'class' => 'form-control',
                                    'placeholder' => 'ชื่อผู้ใช้ หรือ เลขบัตร 13 หลัก',
                                ],
                            ])->textInput()->label(null, $labelOptions) ?>
                        </div>

                        <div class="mb-3">
                            <?= $form->field($model, 'password', [
                                'options' => ['class' => 'mb-0'],
                                'template' => sprintf($htmlIcon, 'bi-lock'),
                                'inputOptions' => [
                                    'class' => 'form-control',
                                    'placeholder' => 'รหัสผ่าน',
                                ],
                            ])->passwordInput()->label(null, $labelOptions) ?>
                        </div>

                        <div class="mb-4 login-remember">
                            <?= $form->field($model, 'rememberMe')->checkbox(['label' => 'จดจำการเข้าสู่ระบบไว้ในเครื่องนี้']) ?>
                        </div>

                        <div class="d-grid">
                            <?= Html::submitButton(
                                '<i class="bi bi-box-arrow-in-right" aria-hidden="true"></i><span>เข้าสู่ระบบด้วยรหัสผ่าน</span>',
                                [
                                    'class' => 'btn login-btn btn-lg rounded-3 text-white',
                                    'name' => 'login-button',
                                ],
                            ) ?>
                        </div>

                        <?php ActiveForm::end(); ?>
                    </div>

                </div>

                <!-- ปุ่มสมัครสมาชิกสำหรับครูใหม่ -->
                <div class="d-grid mt-4 pt-3 border-top">
                    <?= Html::a(
                        '<i class="bi bi-person-plus-fill me-1"></i><span>ยังไม่มีบัญชี? สมัครสมาชิกครูและบุคลากร</span>',
                        ['/site/signup'],
                        ['class' => 'btn btn-outline-primary py-2 fw-semibold rounded-3']
                    ) ?>
                </div>

                <!-- บัญชีสำหรับ Demo -->
                <div class="demo-login-box mt-4">
                    <div class="demo-login-head">
                        <i class="bi bi-key" aria-hidden="true"></i>
                        <strong>บัญชีทดลองสำหรับ Demo</strong>
                    </div>
                    <div class="demo-login-password">
                        รหัสผ่านทุกบัญชี: <code>Demo@1234</code>
                    </div>
                    <div class="demo-login-list">
                        <?php foreach ($demoAccounts as $account): ?>
                            <div>
                                <span><?= Html::encode($account['role']) ?></span>
                                <code><?= Html::encode($account['username']) ?></code>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <p class="login-footnote text-center mt-3">
                    ระบบสารบรรณอิเล็กทรอนิกส์ โรงเรียนกุดชุมวิทยาคม
                </p>

            </div>
        </div>

    </div>
</div>

<script>
(function() {
    let currentToken = <?= json_encode($lineSession->session_token) ?>;
    let pollInterval = null;
    const checkUrl = <?= json_encode($checkLoginUrl) ?>;
    const refreshUrl = <?= json_encode($refreshSessionUrl) ?>;
    const statusEl = document.getElementById('line-login-status');
    const refreshBtn = document.getElementById('btn-refresh-session');
    const codeEl = document.getElementById('line-login-code');
    const codeHint = document.getElementById('line-code-hint');
    const qrImg = document.getElementById('line-qr-image');

    function checkLoginStatus() {
        if (!currentToken) return;

        fetch(checkUrl + '?token=' + encodeURIComponent(currentToken))
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.redirect) {
                    clearInterval(pollInterval);
                    statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> ยืนยันสำเร็จ! กำลังเข้าสู่ระบบ...</span>';
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 500);
                } else if (data.status === 'expired') {
                    clearInterval(pollInterval);
                    statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> รหัสหมดอายุแล้ว กรุณากดขอรหัสใหม่</span>';
                    refreshBtn.classList.remove('d-none');
                } else if (data.status === 'inactive') {
                    clearInterval(pollInterval);
                    statusEl.innerHTML = '<span class="text-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> ' + (data.message || 'บัญชีรออนุมัติ') + '</span>';
                }
            })
            .catch(err => {
                console.error('Polling error:', err);
            });
    }

    function startPolling() {
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(checkLoginStatus, 2500);
    }

    refreshBtn.addEventListener('click', function() {
        statusEl.innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> กำลังสร้างรหัสใหม่...';
        refreshBtn.classList.add('d-none');

        fetch(refreshUrl)
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    currentToken = data.session_token;
                    codeEl.textContent = data.login_code;
                    codeHint.textContent = data.login_code;
                    qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent('เข้าสู่ระบบ ' + data.login_code);
                    statusEl.innerHTML = '<div class="spinner-border spinner-border-sm text-success" role="status"></div> <span>กำลังรอการยืนยันผ่าน LINE...</span>';
                    startPolling();
                }
            })
            .catch(err => {
                statusEl.innerHTML = '<span class="text-danger">เกิดข้อผิดพลาดในการขอรหัสใหม่</span>';
                refreshBtn.classList.remove('d-none');
            });
    });

    // เริ่มต้นตรวจสถานะ
    startPolling();
})();
</script>
