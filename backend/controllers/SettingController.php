<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\SystemSettingForm;
use common\components\LineMessagingService;
use common\models\LineNotificationQueue;
use common\models\SystemSetting;
use common\models\User;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * SettingController manages system-wide configurations (Admin only).
 */
class SettingController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['get', 'post'],
                    'flush-line-queue' => ['post'],
                ],
            ],
        ];
    }

    /**
     * หน้าตั้งค่าระบบ
     */
    public function actionIndex(): Response|string
    {
        $this->requireAdmin();

        $form = new SystemSettingForm();

        if (Yii::$app->request->isPost && $form->load(Yii::$app->request->post())) {
            if ($form->save()) {
                Yii::$app->session->setFlash('success', 'บันทึกการตั้งค่าระบบเรียบร้อยแล้ว');
                return $this->redirect(['index']);
            }
            Yii::$app->session->setFlash('error', 'ไม่สามารถบันทึกข้อมูลได้ กรุณาตรวจสอบข้อผิดพลาด');
        }

        $nextReceiveNoPreview = SystemSetting::suggestNextReceiveNo();

        $actingUsers = User::find()
            ->where(['status' => User::STATUS_ACTIVE])
            ->andWhere(['!=', 'role', User::ROLE_ADMIN])
            ->with(['department'])
            ->orderBy(['role' => SORT_ASC, 'fullname' => SORT_ASC, 'username' => SORT_ASC])
            ->all();

        $actingUserOptions = [];
        foreach ($actingUsers as $u) {
            $label = $u->displayName() . ' (' . $u->roleLabel() . ($u->department ? ' - ' . $u->department->name : '') . ')';
            $actingUserOptions[$u->id] = $label;
        }

        return $this->render('index', [
            'model' => $form,
            'nextReceiveNoPreview' => $nextReceiveNoPreview,
            'actingUserOptions' => $actingUserOptions,
            'lineMonthlyUsed' => LineNotificationQueue::countMonthlyPushUsed(),
            'linePendingSummary' => LineNotificationQueue::pendingSummary(),
            'lineCronToken' => SystemSetting::getOrCreateLineDigestCronToken(),
        ]);
    }

    /**
     * สั่งส่งข้อความแจ้งเตือน LINE ที่ค้างอยู่ในคิวทันที (Manual Flush โดย Admin)
     */
    public function actionFlushLineQueue(): Response
    {
        $this->requireAdmin();

        $result = (new LineMessagingService())->flushDigestQueue(true);
        if ($result['sent_users'] > 0) {
            Yii::$app->session->setFlash(
                'success',
                sprintf(
                    'ส่งสรุปแจ้งเตือน LINE เรียบร้อยแล้ว (%d ผู้รับ / รวม %d รายการ, ข้ามรายการที่รับทราบแล้ว %d รายการ)',
                    $result['sent_users'],
                    $result['sent_items'],
                    $result['skipped_items']
                )
            );
        } elseif ($result['skipped_items'] > 0) {
            Yii::$app->session->setFlash('info', 'รายการในคิวทั้งหมด (' . $result['skipped_items'] . ' รายการ) ถูกรับทราบหรือดำเนินการไปแล้ว จึงไม่จำเป็นต้องส่ง LINE');
        } else {
            Yii::$app->session->setFlash('info', 'ไม่มีรายการแจ้งเตือนค้างอยู่ในคิว');
        }

        return $this->redirect(['index']);
    }

    private function requireAdmin(): void
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User || !$identity->hasRole(User::ROLE_ADMIN)) {
            throw new ForbiddenHttpException('เฉพาะผู้ดูแลระบบ (Admin) เท่านั้นที่เข้าถึงการตั้งค่าระบบได้');
        }
    }
}

