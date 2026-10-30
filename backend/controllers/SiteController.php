<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\SignupForm;
use common\components\LineMessagingService;
use common\models\Department;
use common\models\LineLoginSession;
use common\models\LoginForm;
use common\models\User;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\Response;

/**
 * Site controller
 */
class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'login', 'error', 'signup', 'pending-approval', 'check-line-login', 'get-line-session', 'line-auth', 'line-callback'],
                        'allow' => true,
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex(): string
    {
        return $this->render('index');
    }

    /**
     * Login action.
     *
     * @return string|Response
     */
    public function actionLogin(): string|Response
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = 'blank';

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->redirect(['/document/my']);
        }

        $model->password = '';

        // สร้าง Session สำหรับ LINE QR / Magic Code Login
        $lineSession = LineLoginSession::createNewSession(
            Yii::$app->request->userIP,
            Yii::$app->request->userAgent,
        );

        return $this->render('login', [
            'model' => $model,
            'lineSession' => $lineSession,
        ]);
    }

    /**
     * ดึงรหัส Session ใหม่เมื่อรหัสเดิมหมดอายุ (AJAX)
     */
    public function actionGetLineSession(): Response
    {
        $session = LineLoginSession::createNewSession(
            Yii::$app->request->userIP,
            Yii::$app->request->userAgent,
        );

        return $this->asJson([
            'ok' => true,
            'session_token' => $session->session_token,
            'login_code' => $session->login_code,
            'expires_in' => max(0, $session->expires_at - time()),
        ]);
    }

    /**
     * ตรวจสอบสถานะการอนุมัติการเข้าสู่ระบบผ่าน LINE (AJAX Polling)
     */
    public function actionCheckLineLogin(string $token): Response
    {
        $session = LineLoginSession::find()
            ->with(['user'])
            ->where(['session_token' => $token])
            ->one();

        if (!$session instanceof LineLoginSession) {
            return $this->asJson(['status' => 'not_found']);
        }

        if ($session->expires_at <= time()) {
            $session->status = LineLoginSession::STATUS_EXPIRED;
            $session->save(false, ['status', 'updated_at']);
            return $this->asJson(['status' => 'expired']);
        }

        if ($session->isApprovedAndValid() && $session->user instanceof User) {
            if ($session->user->status !== User::STATUS_ACTIVE) {
                return $this->asJson([
                    'status' => 'inactive',
                    'message' => 'บัญชีของท่านยังไม่ได้รับการอนุมัติจากผู้ดูแลระบบ',
                ]);
            }

            // ล็อกอินผู้ใช้เข้าสู่ระบบทันที
            Yii::$app->user->login($session->user, 3600 * 24 * 30);
            $session->markUsed();

            return $this->asJson([
                'status' => 'success',
                'redirect' => Url::to(['/document/my']),
            ]);
        }

        return $this->asJson(['status' => 'pending']);
    }

    /**
     * หน้าสมัครสมาชิกสำหรับครูและบุคลากร
     */
    public function actionSignup(): string|Response
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = 'blank';
        $model = new SignupForm();

        // ตรวจสอบว่ามีข้อมูล LINE จาก Session หรือไม่ (หลังจากกดเชื่อม LINE)
        $lineData = Yii::$app->session->get('signup_line_data');
        if (!empty($lineData['line_user_id'])) {
            $model->line_user_id = $lineData['line_user_id'];
            $model->line_display_name = $lineData['line_display_name'] ?? '';
            if (empty($model->fullname)) {
                $model->fullname = $model->line_display_name;
            }
        }

        if ($model->load(Yii::$app->request->post())) {
            $user = $model->signup();
            if ($user !== null) {
                // ล้าง session LINE ชั่วคราว
                Yii::$app->session->remove('signup_line_data');

                // แจ้งเตือนแอดมินทาง LINE ทันที
                try {
                    $lineService = new LineMessagingService();
                    $lineService->notifyAdminNewTeacherSignup($user);
                } catch (\Throwable $e) {
                    Yii::error('Failed to notify admin via LINE: ' . $e->getMessage(), __METHOD__);
                }

                // ส่งข้อความยืนยันการลงทะเบียนเข้าแชท LINE ของครูทันที
                try {
                    $lineService = new LineMessagingService();
                    $deptName = $user->department?->name ?? 'ทั่วไป';
                    $lineService->pushText($user->line_user_id, "📋 ลงทะเบียนเข้าใช้งาน e-Office สำเร็จ\n\nสวัสดีครับคุณครู " . $user->displayName() . "\nสังกัด: " . $deptName . "\n\nขณะนี้ข้อมูลของคุณครูอยู่ระหว่างรอ 'ผู้ดูแลระบบ (Admin) ตรวจสอบและอนุมัติ'\nเมื่อได้รับการอนุมัติแล้ว ระบบจะส่งข้อความแจ้งเตือนมาทาง LINE นี้ทันทีครับ");
                } catch (\Throwable $e) {
                    Yii::error('Failed to notify teacher welcome on signup: ' . $e->getMessage(), __METHOD__);
                }

                return $this->redirect(['pending-approval', 'id' => $user->id]);
            }
        }

        $departments = Department::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return $this->render('signup', [
            'model' => $model,
            'departments' => $departments,
            'lineData' => $lineData,
        ]);
    }

    /**
     * หน้ารายงานสถานะรออนุมัติ (Pending Approval)
     */
    public function actionPendingApproval(?int $id = null): string|Response
    {
        $this->layout = 'blank';
        $user = null;
        if ($id !== null) {
            $user = User::findOne($id);
        }

        return $this->render('pending-approval', [
            'user' => $user,
        ]);
    }

    /**
     * เริ่มกระบวนการ LINE Login (OAuth 2.1) ส่งต่อไปยัง LINE Authorization URL
     */
    public function actionLineAuth(): Response
    {
        $channelId = (string) Yii::$app->params['line.loginChannelId'];
        if ($channelId === '') {
            Yii::$app->session->setFlash('error', 'ระบบยังไม่ได้ตั้งค่า LINE Login Channel ID');
            return $this->redirect(['login']);
        }

        $state = Yii::$app->security->generateRandomString(32);
        Yii::$app->session->set('line_oauth_state', $state);

        $redirectUri = $this->getLineCallbackUrl();

        $params = [
            'response_type' => 'code',
            'client_id' => $channelId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'profile openid email',
            'bot_prompt' => 'aggressive', // แนะนำให้เพิ่มเพื่อน LINE OA อัตโนมัติระหว่างล็อกอิน
        ];

        $authorizeUrl = 'https://access.line.me/oauth2/v2.1/authorize?' . http_build_query($params);
        return $this->redirect($authorizeUrl);
    }

    /**
     * รับ Callback จาก LINE Login, แลก Token และล็อกอินผู้ใช้งาน
     */
    public function actionLineCallback(): Response
    {
        $error = Yii::$app->request->get('error');
        if ($error !== null) {
            $errorDesc = Yii::$app->request->get('error_description', 'ผู้ใช้ยกเลิกการเข้าสู่ระบบผ่าน LINE');
            Yii::$app->session->setFlash('error', 'การเข้าสู่ระบบด้วย LINE ไม่สำเร็จ: ' . $errorDesc);
            return $this->redirect(['login']);
        }

        $code = Yii::$app->request->get('code');
        $state = Yii::$app->request->get('state');
        $savedState = Yii::$app->session->get('line_oauth_state');
        Yii::$app->session->remove('line_oauth_state');

        if (empty($code) || empty($state) || $state !== $savedState) {
            Yii::$app->session->setFlash('error', 'ความปลอดภัยไม่ถูกต้อง (Invalid State) กรุณาลองใหม่อีกครั้ง');
            return $this->redirect(['login']);
        }

        $channelId = (string) Yii::$app->params['line.loginChannelId'];
        $channelSecret = (string) Yii::$app->params['line.loginChannelSecret'];
        $redirectUri = $this->getLineCallbackUrl();

        // 1. แลก Authorization Code เป็น Access Token & ID Token
        $tokenData = $this->exchangeLineToken($code, $channelId, $channelSecret, $redirectUri);
        if ($tokenData === null || empty($tokenData['access_token'])) {
            Yii::$app->session->setFlash('error', 'ไม่สามารถขอ Token จาก LINE ได้ กรุณาตรวจสอบ Callback URL ใน LINE Developers Console');
            return $this->redirect(['login']);
        }

        // 2. ดึงข้อมูล User Profile จาก LINE
        $profile = $this->getLineProfile($tokenData['access_token']);
        if ($profile === null || empty($profile['userId'])) {
            Yii::$app->session->setFlash('error', 'ไม่สามารถดึงข้อมูลโปรไฟล์จาก LINE ได้');
            return $this->redirect(['login']);
        }

        $lineUserId = (string) $profile['userId'];
        $displayName = (string) ($profile['displayName'] ?? '');

        // 3. ค้นหาผู้ใช้จาก line_user_id
        $user = User::findOne(['line_user_id' => $lineUserId]);

        if ($user instanceof User) {
            if ($user->status === User::STATUS_INACTIVE) {
                return $this->redirect(['pending-approval', 'id' => $user->id]);
            }
            if ($user->status === User::STATUS_DELETED) {
                Yii::$app->session->setFlash('error', 'บัญชีนี้ถูกปิดการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
                return $this->redirect(['login']);
            }

            // อัปเดตชื่อแสดงผล LINE และเวลาล็อกอิน
            $user->line_display_name = $displayName;
            $user->save(false, ['line_display_name', 'updated_at']);

            Yii::$app->user->login($user, 3600 * 24 * 30);
            Yii::$app->session->setFlash('success', 'ยินดีต้อนรับคุณครู ' . $user->displayName() . ' เข้าสู่ระบบ e-Office');
            return $this->redirect(['/document/my']);
        }

        // 4. กรณีบัญชี LINE นี้ยังไม่เคยมีในระบบ:
        // เก็บข้อมูล LINE ลง Session แล้วส่งครูไปยังหน้าลงทะเบียน พร้อมระบุ LINE อัตโนมัติ
        Yii::$app->session->set('signup_line_data', [
            'line_user_id' => $lineUserId,
            'line_display_name' => $displayName,
        ]);

        Yii::$app->session->setFlash('success', 'เชื่อมต่อ LINE สำเร็จ! กรุณากรอกข้อมูลส่วนตัวเพื่อลงทะเบียนเข้าใช้งาน');
        return $this->redirect(['signup']);
    }

    private function exchangeLineToken(string $code, string $channelId, string $channelSecret, string $redirectUri): ?array
    {
        $ch = curl_init('https://api.line.me/oauth2/v2.1/token');
        if ($ch === false) {
            return null;
        }

        $postData = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $channelId,
            'client_secret' => $channelSecret,
        ]);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false || $status < 200 || $status >= 300) {
            Yii::error("LINE exchange token failed: status={$status}, response=" . (string) $response, __METHOD__);
            return null;
        }

        return json_decode($response, true);
    }

    private function getLineProfile(string $accessToken): ?array
    {
        $ch = curl_init('https://api.line.me/v2/profile');
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false || $status !== 200) {
            Yii::error("LINE get profile failed: status={$status}, response=" . (string) $response, __METHOD__);
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * คำนวณ Callback URL ให้ถูกต้องทั้งบน Localhost และผ่าน ngrok (Reverse Proxy)
     */
    private function getLineCallbackUrl(): string
    {
        $explicitUrl = (string) (Yii::$app->params['line.loginCallbackUrl'] ?? '');
        if ($explicitUrl !== '') {
            return $explicitUrl;
        }

        $headers = Yii::$app->request->headers;
        $forwardedHost = $headers->get('x-forwarded-host');
        $forwardedProto = $headers->get('x-forwarded-proto', 'https');

        if ($forwardedHost !== null && $forwardedHost !== '') {
            $base = $forwardedProto . '://' . $forwardedHost;
            return $base . '/eoffice/backend/web/index.php?r=site/line-callback';
        }

        return Url::to(['/site/line-callback'], true);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout(): Response
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }
}
