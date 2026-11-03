<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\SystemSettingForm;
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
        ]);
    }

    private function requireAdmin(): void
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User || !$identity->hasRole(User::ROLE_ADMIN)) {
            throw new ForbiddenHttpException('เฉพาะผู้ดูแลระบบ (Admin) เท่านั้นที่เข้าถึงการตั้งค่าระบบได้');
        }
    }
}

