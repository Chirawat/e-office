<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\UserCreateForm;
use backend\models\UserUpdateForm;
use common\models\Department;
use common\models\User;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class UserController extends Controller
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
                    'update-role' => ['post'],
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $this->requireAdmin();
        $roleOrder = sprintf(
            "CASE [[role]] WHEN '%s' THEN 10 WHEN '%s' THEN 20 WHEN '%s' THEN 30 WHEN '%s' THEN 40 WHEN '%s' THEN 50 WHEN '%s' THEN 60 ELSE 99 END",
            User::ROLE_ADMIN,
            User::ROLE_DIRECTOR,
            User::ROLE_DEPUTY_DIRECTOR,
            User::ROLE_STAFF,
            User::ROLE_OFFICER,
            User::ROLE_GENERAL_USER,
        );
        $query = User::find()
            ->with(['department', 'departments'])
            ->where(['<>', 'status', User::STATUS_DELETED])
            ->orderBy(new Expression($roleOrder))
            ->addOrderBy(['username' => SORT_ASC, 'id' => SORT_ASC]);

        return $this->render('index', [
            'dataProvider' => new ActiveDataProvider([
                'query' => $query,
                'pagination' => ['pageSize' => 20],
            ]),
        ]);
    }

    public function actionCreate(): Response|string
    {
        $this->requireAdmin();
        $model = new UserCreateForm();

        if ($model->load(Yii::$app->request->post())) {
            $user = $model->save();
            if ($user !== null) {
                Yii::$app->session->setFlash('success', 'สร้างผู้ใช้ ' . $user->username . ' เรียบร้อยแล้ว');
                return $this->redirect(['index']);
            }
        }

        return $this->render('create', [
            'model' => $model,
            'departments' => $this->activeDepartments(),
        ]);
    }

    public function actionUpdateRole(int $id): Response
    {
        $this->requireAdmin();
        $model = $this->findModel($id);
        $role = (string) Yii::$app->request->post('role', '');

        if (!array_key_exists($role, User::roleOptions())) {
            Yii::$app->session->setFlash('error', 'บทบาทผู้ใช้ไม่ถูกต้อง');
            return $this->redirect(['index']);
        }

        $model->role = $role;
        if ($model->save(false, ['role', 'updated_at'])) {
            Yii::$app->session->setFlash('success', 'ปรับบทบาทผู้ใช้เรียบร้อยแล้ว');
        }

        return $this->redirect(['index']);
    }

    public function actionUpdate(int $id): Response|string
    {
        $this->requireAdmin();
        $user = $this->findModel($id);
        $model = new UserUpdateForm($user);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'แก้ไขผู้ใช้เรียบร้อยแล้ว');
            return $this->redirect(['index']);
        }

        return $this->render('update', [
            'model' => $model,
            'user' => $user,
            'departments' => $this->activeDepartments(),
        ]);
    }

    public function actionDelete(int $id): Response
    {
        $this->requireAdmin();
        $model = $this->findModel($id);

        if ((int) Yii::$app->user->id === (int) $model->id) {
            Yii::$app->session->setFlash('error', 'ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้');
            return $this->redirect(['index']);
        }

        $this->markUserDeleted($model);
        if ($model->save(false, ['username', 'email', 'status', 'updated_at'])) {
            Yii::$app->session->setFlash('success', 'ลบผู้ใช้เรียบร้อยแล้ว');
        }

        return $this->redirect(['index']);
    }

    private function findModel(int $id): User
    {
        $model = User::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('ไม่พบผู้ใช้ที่ต้องการ');
        }

        return $model;
    }

    private function requireAdmin(): void
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User || !$identity->hasRole(User::ROLE_ADMIN)) {
            throw new ForbiddenHttpException('เฉพาะผู้ดูแลระบบเท่านั้นที่จัดการผู้ใช้ได้');
        }
    }

    private function markUserDeleted(User $model): void
    {
        $safeUsername = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $model->username) ?: 'user';
        $model->username = substr($safeUsername, 0, 180) . '__deleted_' . $model->id;
        $model->email = 'deleted_' . $model->id . '_' . time() . '@example.invalid';
        $model->status = User::STATUS_DELETED;
    }

    private function activeDepartments(): array
    {
        return Department::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }
}
