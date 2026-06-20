<?php

declare(strict_types=1);

namespace backend\controllers;

use common\components\LineMessagingService;
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

        $user = User::find()
            ->where(['line_link_token' => $messageText])
            ->andWhere(['>', 'line_link_token_expires_at', time()])
            ->one();
        if (!$user instanceof User) {
            if ($replyToken !== '') {
                $service->replyText($replyToken, 'ไม่พบรหัสเชื่อมต่อ หรือรหัสหมดอายุแล้ว กรุณาสร้างรหัสใหม่ในระบบ e-Office');
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
            $service->replyText($replyToken, 'เชื่อมต่อ LINE กับบัญชี e-Office ของคุณเรียบร้อยแล้ว');
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
