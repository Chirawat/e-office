<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\MemoForm;
use common\components\LineMessagingService;
use common\components\MemoPdfService;
use common\models\Department;
use common\models\Memo;
use common\models\MemoAction;
use common\models\MemoStampPosition;
use common\models\SystemSetting;
use common\models\User;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class MemoController extends Controller
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
                    'submit' => ['post'],
                    'staff-review' => ['post'],
                    'deputy-review' => ['post'],
                    'director-review' => ['post'],
                    'complete' => ['post'],
                    'debug-restamp' => ['post'],
                    'update-stamp-positions' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $identity = $this->identity();
        $keyword = trim((string) Yii::$app->request->get('q', ''));
        $query = Memo::find()->with(['department', 'creator', 'currentActor'])->orderBy(['updated_at' => SORT_DESC, 'id' => SORT_DESC]);
        $this->applyVisibilityScope($query, $identity);
        $this->applyKeyword($query, $keyword);

        return $this->render('index', [
            'dataProvider' => new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 20]]),
            'keyword' => $keyword,
        ]);
    }

    public function actionMy(): string
    {
        $identity = $this->identity();
        $keyword = trim((string) Yii::$app->request->get('q', ''));
        $query = Memo::find()
            ->with(['department', 'creator', 'currentActor'])
            ->orderBy(['updated_at' => SORT_DESC, 'id' => SORT_DESC]);
        $this->applyMyScope($query, $identity);
        $this->applyKeyword($query, $keyword);

        return $this->render('my', [
            'dataProvider' => new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 20]]),
            'keyword' => $keyword,
            'roleLabel' => $identity->roleLabel(),
        ]);
    }

    public function actionCreate(): Response|string
    {
        $identity = $this->identity();
        $form = new MemoForm();
        if ($identity->department_id !== null) {
            $form->memo->department_id = $identity->department_id;
        }

        if (Yii::$app->request->isPost && $form->load(Yii::$app->request->post())) {
            $submit = Yii::$app->request->post('submit') === '1';
            if ($submit) {
                $this->prepareSubmitToStaff($form->memo);
            }
            $memo = $form->save($submit ? 'create_and_submit' : 'create_draft');
            if ($memo !== null) {
                if ($submit) {
                    $this->notifyCurrentActor($memo, 'มีบันทึกเสนอใหม่รอพิจารณา');
                }
                Yii::$app->session->setFlash('success', $submit ? 'สร้างและเสนอบันทึกเรียบร้อยแล้ว' : 'บันทึกร่างเรียบร้อยแล้ว');
                return $this->redirect(['view', 'id' => $memo->id]);
            }
        }

        return $this->render('create', [
            'form' => $form,
            'departments' => Department::find()->where(['is_active' => true])->orderBy(['sort_order' => SORT_ASC])->all(),
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $memo = $this->findModel($id);
        $identity = $this->identity();
        if (!$this->canEdit($memo, $identity)) {
            throw new ForbiddenHttpException('แก้ไขได้เฉพาะเจ้าของเรื่องในสถานะร่างหรือส่งกลับแก้ไข');
        }

        $form = new MemoForm($memo);
        if (Yii::$app->request->isPost && $form->load(Yii::$app->request->post())) {
            $submit = Yii::$app->request->post('submit') === '1';
            if ($submit) {
                $this->prepareSubmitToStaff($form->memo);
            }
            $saved = $form->save($submit ? 'edit_and_submit' : 'edit_draft');
            if ($saved !== null) {
                if ($submit) {
                    $this->notifyCurrentActor($saved, 'มีบันทึกเสนอที่แก้ไขแล้วรอพิจารณา');
                }
                Yii::$app->session->setFlash('success', $submit ? 'แก้ไขและเสนอบันทึกเรียบร้อยแล้ว' : 'บันทึกการแก้ไขเรียบร้อยแล้ว');
                return $this->redirect(['view', 'id' => $saved->id]);
            }
        }

        return $this->render('update', [
            'form' => $form,
            'departments' => Department::find()->where(['is_active' => true])->orderBy(['sort_order' => SORT_ASC])->all(),
        ]);
    }

    public function actionView(int $id): string
    {
        $memo = $this->findModel($id);
        if (!$this->canView($memo, $this->identity())) {
            throw new ForbiddenHttpException('คุณไม่มีสิทธิ์ดูบันทึกนี้');
        }

        $service = new MemoPdfService();
        try {
            $service->generate($memo);
        } catch (\Throwable $e) {
            Yii::warning($e, __METHOD__);
        }

        return $this->render('view', [
            'model' => $memo,
            'previewImagePath' => $service->generatePreviewImage($memo),
        ]);
    }

    public function actionPdf(int $id): Response
    {
        $memo = $this->findModel($id);
        if (!$this->canView($memo, $this->identity())) {
            throw new ForbiddenHttpException('คุณไม่มีสิทธิ์ดูบันทึกนี้');
        }

        $relativePath = (new MemoPdfService())->generate($memo);
        $path = Yii::getAlias('@backend/web/' . ltrim($relativePath, '/'));

        return Yii::$app->response->sendFile($path, 'memo-' . $memo->id . '.pdf', [
            'mimeType' => 'application/pdf',
            'inline' => true,
        ]);
    }

    public function actionDebugRestamp(int $id): Response
    {
        $memo = $this->findModel($id);
        $identity = $this->identity();
        if (!$this->canView($memo, $identity) || !$identity->hasRole(User::ROLE_ADMIN)) {
            throw new ForbiddenHttpException('เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถ debug re-stamp ได้');
        }

        (new MemoPdfService())->generate($memo);
        Yii::$app->session->setFlash('success', 'สร้าง PDF บันทึกข้อความใหม่เรียบร้อยแล้ว');

        return $this->redirect(['view', 'id' => $memo->id, 'pdfv' => time()]);
    }

    public function actionUpdateStampPositions(int $id): Response
    {
        $memo = $this->findModel($id);
        $identity = $this->identity();
        if (!$this->canAdjustStampPositions($memo, $identity)) {
            throw new ForbiddenHttpException('ปรับตำแหน่งประทับลายเซ็นได้เฉพาะเจ้าของเรื่องหรือผู้ดูแลระบบ');
        }

        $positions = (array) Yii::$app->request->post('stamps', []);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach (MemoPdfService::SIGNATURE_POSITIONS as $type => $default) {
                $input = (array) ($positions[$type] ?? []);
                $x = isset($input['x']) && $input['x'] !== '' ? (float) $input['x'] : (float) $default['x'];
                $y = isset($input['y']) && $input['y'] !== '' ? (float) $input['y'] : (float) $default['y'];
                $this->saveStampPosition($memo, (string) $type, $x, $y);
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        try {
            (new MemoPdfService())->generate($memo);
            Yii::$app->session->setFlash('success', 'บันทึกตำแหน่งประทับลายเซ็นและสร้าง PDF ใหม่แล้ว');
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            Yii::$app->session->setFlash('warning', 'บันทึกตำแหน่งแล้ว แต่สร้าง PDF ใหม่ไม่สำเร็จ: ' . $e->getMessage());
        }

        return $this->redirect(['view', 'id' => $memo->id, 'pdfv' => time()]);
    }

    public function actionSubmit(int $id): Response
    {
        $memo = $this->findModel($id);
        $identity = $this->identity();
        if (!$this->canEdit($memo, $identity)) {
            throw new ForbiddenHttpException('ไม่สามารถเสนอบันทึกนี้ได้');
        }

        $this->prepareSubmitToStaff($memo);
        $this->changeStatus($memo, Memo::STATUS_SUBMITTED_TO_STAFF, 'submit_to_staff', 'เสนอหัวหน้ากลุ่มงาน');
        $this->notifyCurrentActor($memo, 'มีบันทึกเสนอใหม่รอพิจารณา');

        Yii::$app->session->setFlash('success', 'เสนอบันทึกเรียบร้อยแล้ว');
        return $this->redirect(['view', 'id' => $memo->id]);
    }

    public function actionStaffReview(int $id): Response
    {
        $this->requireRole(User::ROLE_STAFF, 'เฉพาะหัวหน้ากลุ่มงานเท่านั้นที่พิจารณาขั้นนี้ได้');
        $memo = $this->findModel($id);
        if ($memo->status !== Memo::STATUS_SUBMITTED_TO_STAFF) {
            Yii::$app->session->setFlash('warning', 'บันทึกไม่ได้อยู่ในสถานะรอหัวหน้ากลุ่มงาน');
            return $this->redirect(['view', 'id' => $memo->id]);
        }

        $decision = (string) Yii::$app->request->post('decision', 'submit_deputy');
        $comment = trim((string) Yii::$app->request->post('comment'));
        if ($comment === '') {
            Yii::$app->session->setFlash('error', 'กรุณาระบุความเห็น');
            return $this->redirect(['view', 'id' => $memo->id]);
        }
        if ($decision === 'return') {
            $this->returnMemo($memo, 'staff_return', $comment);
            return $this->redirect(['view', 'id' => $memo->id]);
        }

        $nextStatus = $decision === 'submit_director' ? Memo::STATUS_SUBMITTED_TO_DIRECTOR : Memo::STATUS_SUBMITTED_TO_DEPUTY;
        $memo->current_actor_id = $this->firstUserId($decision === 'submit_director' ? User::ROLE_DIRECTOR : User::ROLE_DEPUTY_DIRECTOR);
        $this->changeStatus($memo, $nextStatus, 'staff_review', $comment);
        $this->notifyCurrentActor($memo, 'มีบันทึกเสนอรอพิจารณา');
        Yii::$app->session->setFlash('success', 'บันทึกความเห็นและเสนอขั้นต่อไปเรียบร้อยแล้ว');
        return $this->redirect(['view', 'id' => $memo->id]);
    }

    public function actionDeputyReview(int $id): Response
    {
        $this->requireRole(User::ROLE_DEPUTY_DIRECTOR, 'เฉพาะรอง ผอ. เท่านั้นที่พิจารณาขั้นนี้ได้');
        $memo = $this->findModel($id);
        if ($memo->status !== Memo::STATUS_SUBMITTED_TO_DEPUTY) {
            Yii::$app->session->setFlash('warning', 'บันทึกไม่ได้อยู่ในสถานะรอรอง ผอ.');
            return $this->redirect(['view', 'id' => $memo->id]);
        }

        $decision = (string) Yii::$app->request->post('decision', 'submit_director');
        $comment = trim((string) Yii::$app->request->post('comment'));
        if ($comment === '') {
            Yii::$app->session->setFlash('error', 'กรุณาระบุความเห็น');
            return $this->redirect(['view', 'id' => $memo->id]);
        }
        if ($decision === 'return') {
            $this->returnMemo($memo, 'deputy_return', $comment);
            return $this->redirect(['view', 'id' => $memo->id]);
        }

        $memo->current_actor_id = $this->firstUserId(User::ROLE_DIRECTOR);
        $this->changeStatus($memo, Memo::STATUS_SUBMITTED_TO_DIRECTOR, 'deputy_review', $comment);
        $this->notifyCurrentActor($memo, 'มีบันทึกเสนอรอ ผอ. พิจารณา');
        Yii::$app->session->setFlash('success', 'เสนอ ผอ. เรียบร้อยแล้ว');
        return $this->redirect(['view', 'id' => $memo->id]);
    }

    public function actionDirectorReview(int $id): Response
    {
        $this->requireRole(User::ROLE_DIRECTOR, 'เฉพาะ ผอ. เท่านั้นที่อนุมัติบันทึกได้');
        $memo = $this->findModel($id);
        if ($memo->status !== Memo::STATUS_SUBMITTED_TO_DIRECTOR) {
            Yii::$app->session->setFlash('warning', 'บันทึกไม่ได้อยู่ในสถานะรอ ผอ. พิจารณา');
            return $this->redirect(['view', 'id' => $memo->id]);
        }

        $decision = (string) Yii::$app->request->post('decision', 'approve');
        $comment = trim((string) Yii::$app->request->post('comment'));
        if ($comment === '') {
            Yii::$app->session->setFlash('error', 'กรุณาระบุคำสั่ง/ความเห็น');
            return $this->redirect(['view', 'id' => $memo->id]);
        }
        if ($decision === 'return') {
            $this->returnMemo($memo, 'director_return', $comment);
            return $this->redirect(['view', 'id' => $memo->id]);
        }

        $memo->current_actor_id = $memo->created_by;
        $this->changeStatus($memo, Memo::STATUS_APPROVED, 'director_approve', $comment);
        $this->notifyCurrentActor($memo, 'บันทึกเสนอได้รับการอนุมัติแล้ว');
        Yii::$app->session->setFlash('success', 'อนุมัติบันทึกเรียบร้อยแล้ว');
        return $this->redirect(['view', 'id' => $memo->id]);
    }

    public function actionComplete(int $id): Response
    {
        $memo = $this->findModel($id);
        $identity = $this->identity();
        if ((int) $memo->created_by !== (int) $identity->id && !$identity->hasRole(User::ROLE_ADMIN)) {
            throw new ForbiddenHttpException('เฉพาะเจ้าของเรื่องเท่านั้นที่ปิดบันทึกได้');
        }
        if ($memo->status !== Memo::STATUS_APPROVED) {
            Yii::$app->session->setFlash('warning', 'ปิดเรื่องได้หลังจากอนุมัติแล้วเท่านั้น');
            return $this->redirect(['view', 'id' => $memo->id]);
        }

        $comment = trim((string) Yii::$app->request->post('comment'));
        $memo->current_actor_id = null;
        $this->changeStatus($memo, Memo::STATUS_COMPLETED, 'complete', $comment !== '' ? $comment : 'รับทราบและปิดเรื่อง');
        Yii::$app->session->setFlash('success', 'ปิดบันทึกเรียบร้อยแล้ว');
        return $this->redirect(['view', 'id' => $memo->id]);
    }

    private function findModel(int $id): Memo
    {
        $model = Memo::find()
            ->with(['department', 'files', 'originalPdfFile', 'attachmentFiles', 'actions.actor', 'creator', 'currentActor', 'stampPositions'])
            ->where(['id' => $id])
            ->one();
        if ($model === null) {
            throw new NotFoundHttpException('ไม่พบบันทึกเสนอที่ต้องการ');
        }

        return $model;
    }

    private function identity(): User
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User) {
            throw new ForbiddenHttpException('กรุณาเข้าสู่ระบบ');
        }

        return $identity;
    }

    private function requireRole(string|array $roles, string $message): void
    {
        if (!$this->identity()->hasRole($roles)) {
            throw new ForbiddenHttpException($message);
        }
    }

    private function prepareSubmitToStaff(Memo $memo): void
    {
        $memo->status = Memo::STATUS_SUBMITTED_TO_STAFF;
        $memo->current_actor_id = $this->staffReviewerId($memo);
    }

    private function staffReviewerId(Memo $memo): ?int
    {
        $query = User::find()->where(['status' => User::STATUS_ACTIVE, 'role' => User::ROLE_STAFF]);
        if ($memo->department_id !== null) {
            $query->andWhere(['department_id' => $memo->department_id]);
        }

        $id = $query->select('id')->scalar();
        if ($id !== false && $id !== null) {
            return (int) $id;
        }

        return $this->firstUserId(User::ROLE_STAFF);
    }

    private function firstUserId(string $role): ?int
    {
        if ($role === User::ROLE_DIRECTOR && SystemSetting::isActingDirector()) {
            $actingUserId = SystemSetting::getActingDirectorUserId();
            if ($actingUserId !== null && User::find()->where(['id' => $actingUserId, 'status' => User::STATUS_ACTIVE])->exists()) {
                return $actingUserId;
            }
        }

        $id = User::find()->select('id')->where(['status' => User::STATUS_ACTIVE, 'role' => $role])->scalar();

        return $id !== false && $id !== null ? (int) $id : null;
    }

    private function changeStatus(Memo $memo, string $toStatus, string $action, string $comment): void
    {
        $fromStatus = $memo->getOldAttribute('status') ?: $memo->status;
        $memo->status = $toStatus;
        $memo->save(false, ['status', 'current_actor_id', 'updated_at', 'updated_by']);
        (new MemoAction([
            'memo_id' => $memo->id,
            'actor_id' => Yii::$app->user->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'comment' => $comment,
            'created_at' => time(),
        ]))->save(false);
    }

    private function returnMemo(Memo $memo, string $action, string $comment): void
    {
        $memo->current_actor_id = $memo->created_by;
        $this->changeStatus($memo, Memo::STATUS_RETURNED, $action, $comment);
        $this->notifyCurrentActor($memo, 'บันทึกเสนอถูกส่งกลับแก้ไข');
        Yii::$app->session->setFlash('success', 'ส่งกลับแก้ไขเรียบร้อยแล้ว');
    }

    private function saveStampPosition(Memo $memo, string $type, float $x, float $y): void
    {
        $stampPosition = MemoStampPosition::findOne(['memo_id' => $memo->id, 'stamp_type' => $type]) ?? new MemoStampPosition([
            'memo_id' => $memo->id,
            'stamp_type' => $type,
            'created_by' => Yii::$app->user->id,
            'created_at' => time(),
        ]);

        $stampPosition->position_x = $x;
        $stampPosition->position_y = $y;
        $stampPosition->updated_at = time();
        $stampPosition->save(false);
    }

    private function notifyCurrentActor(Memo $memo, string $message): void
    {
        if ($memo->current_actor_id === null) {
            return;
        }
        $recipient = User::findOne($memo->current_actor_id);
        $actor = $this->identity();
        if (!$recipient instanceof User) {
            return;
        }

        try {
            (new LineMessagingService())->notifyMemo($recipient, $memo, $actor, $message);
        } catch (\Throwable $e) {
            Yii::warning($e, __METHOD__);
        }
    }

    private function canEdit(Memo $memo, User $identity): bool
    {
        return ((int) $memo->created_by === (int) $identity->id || $identity->hasRole(User::ROLE_ADMIN))
            && in_array($memo->status, [Memo::STATUS_DRAFT, Memo::STATUS_RETURNED], true);
    }

    private function canAdjustStampPositions(Memo $memo, User $identity): bool
    {
        return (int) $memo->created_by === (int) $identity->id || $identity->hasRole(User::ROLE_ADMIN);
    }

    private function canView(Memo $memo, User $identity): bool
    {
        if ($identity->hasRole([User::ROLE_ADMIN, User::ROLE_OFFICER, User::ROLE_DEPUTY_DIRECTOR, User::ROLE_DIRECTOR])) {
            return true;
        }
        if ((int) $memo->created_by === (int) $identity->id || (int) $memo->current_actor_id === (int) $identity->id) {
            return true;
        }

        return $identity->role === User::ROLE_STAFF
            && $identity->department_id !== null
            && $memo->department_id !== null
            && (int) $identity->department_id === (int) $memo->department_id;
    }

    private function applyVisibilityScope(ActiveQuery $query, User $identity): void
    {
        if ($identity->hasRole([User::ROLE_ADMIN, User::ROLE_OFFICER, User::ROLE_DEPUTY_DIRECTOR, User::ROLE_DIRECTOR])) {
            return;
        }

        if ($identity->role === User::ROLE_STAFF && $identity->department_id !== null) {
            $query->andWhere([
                'or',
                ['created_by' => $identity->id],
                ['current_actor_id' => $identity->id],
                ['department_id' => $identity->department_id],
            ]);
            return;
        }

        $query->andWhere([
            'or',
            ['created_by' => $identity->id],
            ['current_actor_id' => $identity->id],
        ]);
    }

    private function applyMyScope(ActiveQuery $query, User $identity): void
    {
        if ($identity->hasRole(User::ROLE_ADMIN)) {
            $query->andWhere(['not in', 'status', [Memo::STATUS_COMPLETED]]);
            return;
        }

        $query->andWhere([
            'or',
            ['current_actor_id' => $identity->id],
            ['and', ['created_by' => $identity->id], ['status' => [Memo::STATUS_DRAFT, Memo::STATUS_RETURNED, Memo::STATUS_APPROVED]]],
        ]);
    }

    private function applyKeyword(ActiveQuery $query, string $keyword): void
    {
        if ($keyword === '') {
            return;
        }

        $query->andWhere([
            'or',
            ['like', 'subject', $keyword],
            ['like', 'memo_no', $keyword],
        ]);
    }
}
