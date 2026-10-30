<?php

declare(strict_types=1);

namespace backend\controllers;

use common\components\LineMessagingService;
use common\models\LineLoginSession;
use common\models\User;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class LineController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['webhook'],
                        'allow' => true,
                    ],
                    [
                        'actions' => ['link', 'unlink'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actionLink(): Response|string
    {
        $user = Yii::$app->user->identity;
        if (!$user instanceof User) {
            throw new ForbiddenHttpException('กรุณาเข้าสู่ระบบก่อนเชื่อมต่อ LINE');
        }

        if (Yii::$app->request->isPost) {
            $user->line_link_token = $this->generateLinkToken();
            $user->line_link_token_expires_at = time() + 15 * 60;
            $user->save(false, ['line_link_token', 'line_link_token_expires_at', 'updated_at']);
            Yii::$app->session->setFlash('success', 'สร้างรหัสเชื่อมต่อ LINE แล้ว กรุณาส่งรหัสนี้ในแชท LINE OA ภายใน 15 นาที');
            return $this->redirect(['link']);
        }

        return $this->render('link', [
            'user' => $user,
        ]);
    }

    public function actionUnlink(): Response
    {
        $user = Yii::$app->user->identity;
        if ($user instanceof User) {
            $user->line_user_id = null;
            $user->line_display_name = null;
            $user->line_link_token = null;
            $user->line_link_token_expires_at = null;
            $user->line_linked_at = null;
            $user->save(false, ['line_user_id', 'line_display_name', 'line_link_token', 'line_link_token_expires_at', 'line_linked_at', 'updated_at']);
            Yii::$app->session->setFlash('success', 'ยกเลิกการเชื่อมต่อ LINE แล้ว');
        }

        return $this->redirect(['link']);
    }

    public function actionWebhook(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $body = Yii::$app->request->rawBody;
        $service = new LineMessagingService();
        if (!$service->validateSignature($body, Yii::$app->request->headers->get('x-line-signature'))) {
            Yii::$app->response->statusCode = 403;
            return $this->asJson(['ok' => false]);
        }

        $payload = json_decode($body, true);
        foreach ((array) ($payload['events'] ?? []) as $event) {
            $this->handleEvent((array) $event, $service);
        }

        return $this->asJson(['ok' => true]);
    }

    private function handleEvent(array $event, LineMessagingService $service): void
    {
        $lineUserId = (string) ($event['source']['userId'] ?? '');
        $replyToken = (string) ($event['replyToken'] ?? '');
        $messageText = trim((string) ($event['message']['text'] ?? ''));
        if ($lineUserId === '' || $messageText === '') {
            return;
        }

        // 1. ตรวจสอบว่าผู้ใช้เชื่อมต่อ LINE ไว้แล้วหรือไม่
        $linkedUser = User::find()
            ->where(['line_user_id' => $lineUserId])
            ->andWhere(['status' => User::STATUS_ACTIVE])
            ->one();

        if ($linkedUser instanceof User) {
            // ดักจับรหัสยืนยันการเข้าสู่ระบบ เช่น KC-123456
            if (preg_match('/(KC-[0-9]{6})/i', $messageText, $matches)) {
                $code = strtoupper($matches[1]);
                $session = LineLoginSession::find()
                    ->where(['login_code' => $code, 'status' => LineLoginSession::STATUS_PENDING])
                    ->andWhere(['>', 'expires_at', time()])
                    ->one();

                if ($session instanceof LineLoginSession) {
                    $session->approve($linkedUser);
                    if ($replyToken !== '') {
                        $service->replyText($replyToken, "✅ ยืนยันการเข้าสู่ระบบ e-Office สำเร็จ!\nผู้ใช้งาน: " . $linkedUser->displayName() . "\nหน้าจอบนคอมพิวเตอร์ของคุณจะเข้าสู่ระบบโดยอัตโนมัติ");
                    }
                    return;
                }

                if ($replyToken !== '') {
                    $service->replyText($replyToken, "⚠️ ไม่พบคำขอเข้าสู่ระบบรหัส {$code} หรือรหัสหมดอายุแล้ว กรุณารีเฟรชหน้าจอเว็บเพื่อขอรหัสใหม่");
                }
                return;
            }

            // คำสั่งอื่นๆ สำหรับผู้ใช้ที่ผูกบัญชีแล้ว
            $lowerText = mb_strtolower($messageText);
            if (in_array($lowerText, ['เมนู', 'help', 'ช่วยเหลือ', 'เข้าสู่ระบบ', 'งานของฉัน'], true)) {
                if ($replyToken !== '') {
                    $service->replyText($replyToken, "สวัสดีครับคุณครู " . $linkedUser->displayName() . " 👋\n- ต้องการเข้าสู่ระบบบนคอมพิวเตอร์: ให้สแกน QR Code หรือส่งรหัส 6 หลัก (เช่น KC-XXXXXX) ที่ปรากฏบนหน้าจอเว็บมาที่นี่\n- บัญชี e-Office ของท่านพร้อมใช้งานตามปกติครับ");
                }
                return;
            }

            return;
        }

        // 2. กรณีผู้ใช้ยังไม่ได้ผูกบัญชี: ตรวจสอบรหัสเชื่อมโยง (Link Token)
        $user = User::find()
            ->where(['line_link_token' => $messageText])
            ->andWhere(['>', 'line_link_token_expires_at', time()])
            ->one();

        if (!$user instanceof User) {
            if ($replyToken !== '') {
                $service->replyText($replyToken, "ยินดีต้อนรับสู่ e-Office โรงเรียนกุดชุมวิทยาคม 👋\n\nบัญชี LINE นี้ยังไม่ได้ผูกกับระบบ e-Office\n- หากคุณครูมีบัญชีแล้ว: ให้เข้าสู่ระบบบนเว็บ ไปที่เมนู 'เชื่อมต่อ LINE' แล้วนำรหัสมาพิมพ์ที่นี่\n- หากยังไม่มีบัญชี: สามารถสมัครสมาชิกได้ที่หน้าเว็บ e-Office ครับ");
            }
            return;
        }

        $user->line_user_id = $lineUserId;
        $user->line_display_name = null;
        $user->line_link_token = null;
        $user->line_link_token_expires_at = null;
        $user->line_linked_at = time();
        $user->save(false, ['line_user_id', 'line_display_name', 'line_link_token', 'line_link_token_expires_at', 'line_linked_at', 'updated_at']);

        if ($replyToken !== '') {
            $service->replyText($replyToken, "🎉 เชื่อมต่อ LINE กับบัญชี e-Office (" . $user->displayName() . ") เรียบร้อยแล้ว!\nต่อไปนี้ท่านสามารถใช้ LINE แจ้งเตือนเอกสารและเป็นกุญแจเข้าสู่ระบบได้ทันที");
        }
    }

    private function generateLinkToken(): string
    {
        do {
            $token = 'KC-' . random_int(100000, 999999);
        } while (User::find()->where(['line_link_token' => $token])->exists());

        return $token;
    }
}
