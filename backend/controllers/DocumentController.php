<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\DocumentForm;
use common\components\LineMessagingService;
use common\components\PdfStampService;
use common\models\AuditLog;
use common\models\Department;
use common\models\Document;
use common\models\DocumentAssignment;
use common\models\DocumentStamp;
use common\models\User;
use common\models\WorkflowAction;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class DocumentController extends Controller
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
                    'submit-to-deputy' => ['post'],
                    'deputy-comment' => ['post'],
                    'director-order' => ['post'],
                    'forward-to-staff' => ['post'],
                    'complete' => ['post'],
                    'generate-stamped-pdf' => ['post'],
                    'update-stamp-positions' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $query = Document::find()->with(['department', 'originalFile'])->orderBy(['id' => SORT_DESC]);
        $keyword = trim((string) Yii::$app->request->get('q', ''));
        $identity = Yii::$app->user->identity;
        if ($identity instanceof User && !$identity->hasRole(User::ROLE_ADMIN)) {
            if ($identity->hasRole(User::ROLE_DEPUTY_DIRECTOR)) {
                $query->andWhere(['status' => [Document::STATUS_SUBMITTED_TO_DEPUTY, Document::STATUS_SUBMITTED_TO_DIRECTOR, Document::STATUS_RETURNED]]);
            } elseif ($identity->hasRole(User::ROLE_DIRECTOR)) {
                $query->andWhere(['status' => [Document::STATUS_SUBMITTED_TO_DIRECTOR, Document::STATUS_ASSIGNED, Document::STATUS_COMPLETED]]);
            } elseif ($identity->role === User::ROLE_STAFF || $identity->role === User::ROLE_GENERAL_USER) {
                $query->andWhere(['status' => [Document::STATUS_ASSIGNED, Document::STATUS_COMPLETED]]);
                $this->applyStaffAssignmentScope($query, $identity);
            }
        }
        if ($keyword !== '') {
            $query->andWhere([
                'or',
                ['like', 'subject', $keyword],
                ['like', 'external_no', $keyword],
            ]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'keyword' => $keyword,
        ]);
    }

    public function actionMy(): string
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User) {
            throw new ForbiddenHttpException('กรุณาเข้าสู่ระบบก่อนดูงานของฉัน');
        }

        $statuses = $this->myWorkStatuses($identity);
        $keyword = trim((string) Yii::$app->request->get('q', ''));
        $query = Document::find()
            ->with(['department', 'originalFile'])
            ->where(['{{%document}}.[[status]]' => $statuses])
            ->orderBy(['{{%document}}.[[updated_at]]' => SORT_DESC, '{{%document}}.[[id]]' => SORT_DESC]);
        if ($identity->role === User::ROLE_STAFF || $identity->role === User::ROLE_GENERAL_USER) {
            $this->applyStaffAssignmentScope($query, $identity);
        }
        if ($keyword !== '') {
            $query->andWhere([
                'or',
                ['like', '{{%document}}.[[subject]]', $keyword],
                ['like', '{{%document}}.[[external_no]]', $keyword],
            ]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('my', [
            'dataProvider' => $dataProvider,
            'statuses' => $statuses,
            'roleLabel' => $identity->roleLabel(),
            'summary' => $this->myWorkSummary($statuses, $identity),
            'keyword' => $keyword,
        ]);
    }

    public function actionCreate(): Response|string
    {
        $this->requireRole([User::ROLE_OFFICER], 'เฉพาะเจ้าหน้าที่ธุรการเท่านั้นที่ลงทะเบียนหนังสือรับได้');

        $form = new DocumentForm();
        $form->document->receive_no = $this->suggestNextReceiveNo();

        if (Yii::$app->request->isPost && $form->load(Yii::$app->request->post())) {
            $document = $form->save();
            if ($document !== null) {
                $this->regenerateStampedPdf($document, false);
                Yii::$app->session->setFlash('success', 'ลงทะเบียนหนังสือรับเรียบร้อยแล้ว');
                return $this->redirect(['view', 'id' => $document->id]);
            }
        }

        return $this->render('create', [
            'form' => $form,
            'departments' => Department::find()->where(['is_active' => true])->orderBy(['sort_order' => SORT_ASC])->all(),
        ]);
    }

    public function actionView(int $id): string
    {
        $model = $this->findModel($id);
        if (!$this->canCurrentUserViewDocument($model)) {
            throw new ForbiddenHttpException('คุณไม่มีสิทธิ์ดูหนังสือของกลุ่มงานอื่น');
        }

        $identity = Yii::$app->user->identity;
        $staffQuery = User::find()
            ->where(['status' => User::STATUS_ACTIVE, 'role' => User::ROLE_GENERAL_USER])
            ->orderBy(['username' => SORT_ASC]);
        if ($identity instanceof User && $identity->role === User::ROLE_STAFF && $identity->department_id !== null) {
            $staffQuery->joinWith('userDepartments')
                ->andWhere(['user_department.department_id' => $identity->department_id])
                ->distinct();
        }

        return $this->render('view', [
            'model' => $model,
            'departments' => Department::find()->where(['is_active' => true])->orderBy(['sort_order' => SORT_ASC])->all(),
            'staffUsers' => $staffQuery->all(),
            'previewImagePath' => $this->previewImagePath($model),
        ]);
    }

    public function actionSubmitToDeputy(int $id): Response
    {
        $this->requireRole([User::ROLE_OFFICER], 'เฉพาะเจ้าหน้าที่ธุรการเท่านั้นที่เสนอหนังสือให้รอง ผอ. ได้');

        $model = $this->findModel($id);
        if (!in_array($model->status, [Document::STATUS_REGISTERED, Document::STATUS_RETURNED], true)) {
            Yii::$app->session->setFlash('warning', 'หนังสือไม่ได้อยู่ในสถานะที่สามารถเสนอรอง ผอ. ได้');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $comment = $model->status === Document::STATUS_RETURNED
            ? 'แก้ไขแล้วและเสนอรอง ผอ. อีกครั้ง'
            : 'เสนอรอง ผอ. เพื่อตรวจทานและลงความเห็น';

        $this->changeStatus(
            $model,
            Document::STATUS_SUBMITTED_TO_DEPUTY,
            'submit_to_deputy',
            $comment,
        );
        $this->regenerateStampedPdf($model);

        Yii::$app->session->setFlash('success', 'เสนอรอง ผอ. เรียบร้อยแล้ว');
        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionDeputyComment(int $id): Response
    {
        $this->requireRole([User::ROLE_DEPUTY_DIRECTOR], 'เฉพาะรอง ผอ. เท่านั้นที่ลงความเห็นได้');

        $model = $this->findModel($id);
        if ($model->status !== Document::STATUS_SUBMITTED_TO_DEPUTY) {
            Yii::$app->session->setFlash('warning', 'หนังสือไม่ได้อยู่ในสถานะรอรอง ผอ.');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $comment = trim((string) Yii::$app->request->post('comment'));
        $decision = (string) Yii::$app->request->post('decision', 'submit');

        if ($comment === '') {
            Yii::$app->session->setFlash('error', 'กรุณาระบุความเห็นรอง ผอ.');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        if ($decision === 'return') {
            $this->changeStatus($model, Document::STATUS_RETURNED, 'deputy_return', $comment);
            $this->regenerateStampedPdf($model);
            Yii::$app->session->setFlash('success', 'ส่งกลับแก้ไขเรียบร้อยแล้ว');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $this->changeStatus($model, Document::STATUS_SUBMITTED_TO_DIRECTOR, 'deputy_comment', $comment);
        $this->saveStamp($model, DocumentStamp::TYPE_DEPUTY_COMMENT, $comment, 170, 128);
        $this->regenerateStampedPdf($model);

        Yii::$app->session->setFlash('success', 'บันทึกความเห็นและเสนอ ผอ. เรียบร้อยแล้ว');
        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionDirectorOrder(int $id): Response
    {
        $this->requireRole([User::ROLE_DIRECTOR], 'เฉพาะ ผอ. เท่านั้นที่สั่งการและมอบหมายงานได้');

        $model = $this->findModel($id);
        if ($model->status !== Document::STATUS_SUBMITTED_TO_DIRECTOR) {
            Yii::$app->session->setFlash('warning', 'หนังสือไม่ได้อยู่ในสถานะรอ ผอ. สั่งการ');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $comment = trim((string) Yii::$app->request->post('comment'));
        $departmentId = Yii::$app->request->post('department_id');
        $dueDate = Yii::$app->request->post('due_date') ?: $model->due_date;

        if ($comment === '') {
            Yii::$app->session->setFlash('error', 'กรุณาระบุคำสั่ง ผอ.');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $this->changeStatus($model, Document::STATUS_ASSIGNED, 'director_order', $comment, false);

            $assignment = new DocumentAssignment([
                'document_id' => $model->id,
                'department_id' => $departmentId ?: $model->department_id,
                'assigned_by' => Yii::$app->user->id,
                'instruction' => $comment,
                'status' => 'pending',
                'due_date' => $dueDate,
            ]);
            $assignment->save(false);

            $this->saveStamp($model, DocumentStamp::TYPE_DIRECTOR_ORDER, $comment, 230, 132, false);
            $this->saveAudit($model, 'document.assigned', 'ผอ. สั่งการและมอบหมายงาน', false);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $this->regenerateStampedPdf($model);
        Yii::$app->session->setFlash('success', 'บันทึกคำสั่งและมอบหมายงานเรียบร้อยแล้ว');
        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionUpdateStampPositions(int $id): Response
    {
        $this->requireRole([User::ROLE_OFFICER], 'เฉพาะเจ้าหน้าที่ธุรการเท่านั้นที่ปรับตำแหน่งตราประทับได้');

        $model = $this->findModel($id);
        $positions = (array) Yii::$app->request->post('stamps', []);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($this->stampPositionDefaults() as $type => $default) {
                $input = (array) ($positions[$type] ?? []);
                $x = isset($input['x']) && $input['x'] !== '' ? (float) $input['x'] : (float) $default['x'];
                $y = isset($input['y']) && $input['y'] !== '' ? (float) $input['y'] : (float) $default['y'];
                $this->saveStampPosition($model, $type, $x, $y);
            }

            $this->saveAudit($model, 'document.stamp_positions_updated', 'ปรับตำแหน่งตราประทับ', false);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $this->regenerateStampedPdf($model);
        Yii::$app->session->setFlash('success', 'บันทึกตำแหน่งตราประทับและสร้าง PDF ใหม่แล้ว');

        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionComplete(int $id): Response
    {
        $this->requireRole([User::ROLE_DEPUTY_DIRECTOR, User::ROLE_STAFF, User::ROLE_GENERAL_USER], 'เฉพาะรอง ผอ. หรือผู้รับมอบหมายเท่านั้นที่ปิดเรื่องได้');

        $model = $this->findModel($id);
        $identity = Yii::$app->user->identity;
        if (
            !(
                $model->status === Document::STATUS_ASSIGNED
                || ($identity instanceof User && $identity->role === User::ROLE_DEPUTY_DIRECTOR && $model->status === Document::STATUS_SUBMITTED_TO_DEPUTY)
            )
        ) {
            Yii::$app->session->setFlash('warning', 'หนังสือไม่ได้อยู่ในสถานะที่สามารถปิดเรื่องได้');
            return $this->redirect(['view', 'id' => $model->id]);
        }
        if (!$this->canCurrentUserCompleteAssignment($model)) {
            Yii::$app->session->setFlash('warning', 'หนังสือนี้ถูกส่งต่อให้ผู้ปฏิบัติรายอื่นแล้ว');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $comment = trim((string) Yii::$app->request->post('comment'));
        $this->changeStatus(
            $model,
            Document::STATUS_COMPLETED,
            'complete',
            $comment !== '' ? $comment : 'ปิดเรื่อง',
        );
        $this->regenerateStampedPdf($model);

        Yii::$app->session->setFlash('success', 'ปิดเรื่องเรียบร้อยแล้ว');
        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionForwardToStaff(int $id): Response
    {
        $this->requireRole([User::ROLE_STAFF], 'เฉพาะเจ้าหน้าที่/กลุ่มงานเท่านั้นที่ส่งต่อผู้ปฏิบัติได้');

        $model = $this->findModel($id);
        if ($model->status !== Document::STATUS_ASSIGNED) {
            Yii::$app->session->setFlash('warning', 'หนังสือไม่ได้อยู่ในสถานะที่สามารถส่งต่อผู้ปฏิบัติได้');
            return $this->redirect(['view', 'id' => $model->id]);
        }
        if (!$this->canCurrentUserForwardAssignment($model)) {
            Yii::$app->session->setFlash('warning', 'หนังสือนี้ถูกส่งต่อให้ผู้ปฏิบัติรายอื่นแล้ว');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $assignedTo = (int) Yii::$app->request->post('assigned_to');
        $instruction = trim((string) Yii::$app->request->post('instruction'));
        $staff = User::find()
            ->with('departments')
            ->where(['id' => $assignedTo, 'status' => User::STATUS_ACTIVE, 'role' => User::ROLE_GENERAL_USER])
            ->one();
        if ($staff === null) {
            Yii::$app->session->setFlash('error', 'กรุณาเลือกผู้ปฏิบัติที่ยังใช้งานอยู่');
            return $this->redirect(['view', 'id' => $model->id]);
        }
        $identity = Yii::$app->user->identity;
        if (
            $identity instanceof User
            && $identity->department_id !== null
            && !$staff->belongsToDepartment($identity->department_id)
        ) {
            Yii::$app->session->setFlash('error', 'เลือกผู้ปฏิบัติได้เฉพาะกลุ่มงานเดียวกัน');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $assignment = $this->latestAssignment($model) ?? new DocumentAssignment([
                'document_id' => $model->id,
                'department_id' => $model->department_id,
                'status' => 'pending',
            ]);
            $assignment->assigned_to = $staff->id;
            $assignment->assigned_by = Yii::$app->user->id;
            if ($instruction !== '') {
                $assignment->instruction = $instruction;
            }
            $assignment->save(false);

            $comment = 'ส่งต่อให้ผู้ปฏิบัติ: ' . $staff->username . ($instruction !== '' ? ' - ' . $instruction : '');
            (new WorkflowAction([
                'document_id' => $model->id,
                'actor_id' => Yii::$app->user->id,
                'action' => 'forward_to_staff',
                'from_status' => $model->status,
                'to_status' => $model->status,
                'comment' => $comment,
                'created_at' => time(),
            ]))->save(false);
            $this->saveAudit($model, 'document.forwarded_to_staff', $comment, false);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        try {
            if ($identity instanceof User) {
                (new LineMessagingService())->notifyAssignedDocument($staff, $model, $identity, $instruction);
            }
        } catch (\Throwable $e) {
            Yii::warning($e, __METHOD__);
        }

        Yii::$app->session->setFlash('success', 'ส่งต่อหนังสือให้ผู้ปฏิบัติเรียบร้อยแล้ว');
        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionGenerateStampedPdf(int $id): Response
    {
        $this->requireRole([User::ROLE_OFFICER], 'เฉพาะเจ้าหน้าที่ธุรการเท่านั้นที่สร้าง PDF ประทับตราใหม่ได้');

        $model = $this->findModel($id);

        try {
            $service = new PdfStampService();
            $service->generate($model);
            $this->saveAudit($model, 'document.stamped_pdf_generated', 'สร้าง PDF ฉบับประทับตรา');
            Yii::$app->session->setFlash('success', 'สร้าง PDF ฉบับประทับตราเรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            Yii::$app->session->setFlash('error', 'ไม่สามารถสร้าง PDF ฉบับประทับตราได้: ' . $e->getMessage());
        }

        return $this->redirect(['view', 'id' => $model->id]);
    }

    private function findModel(int $id): Document
    {
        $model = Document::find()
            ->with(['department', 'originalFile', 'stampedFile', 'stamps', 'workflowActions.actor', 'assignments.assignedTo', 'assignments.assignedBy'])
            ->where(['id' => $id])
            ->one();

        if ($model === null) {
            throw new NotFoundHttpException('ไม่พบหนังสือที่ต้องการ');
        }

        return $model;
    }

    private function requireRole(string|array $roles, string $message): void
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User || !$identity->hasRole($roles)) {
            throw new ForbiddenHttpException($message);
        }
    }

    private function myWorkStatuses(User $identity): array
    {
        if ($identity->hasRole(User::ROLE_ADMIN)) {
            return [
                Document::STATUS_REGISTERED,
                Document::STATUS_SUBMITTED_TO_DEPUTY,
                Document::STATUS_SUBMITTED_TO_DIRECTOR,
                Document::STATUS_ASSIGNED,
                Document::STATUS_RETURNED,
            ];
        }

        if ($identity->hasRole(User::ROLE_OFFICER)) {
            return [Document::STATUS_REGISTERED, Document::STATUS_RETURNED];
        }

        if ($identity->hasRole(User::ROLE_DEPUTY_DIRECTOR)) {
            return [Document::STATUS_SUBMITTED_TO_DEPUTY];
        }

        if ($identity->hasRole(User::ROLE_DIRECTOR)) {
            return [Document::STATUS_SUBMITTED_TO_DIRECTOR];
        }

        if ($identity->role === User::ROLE_STAFF || $identity->role === User::ROLE_GENERAL_USER) {
            return [Document::STATUS_ASSIGNED];
        }

        return [];
    }

    private function canCurrentUserForwardAssignment(Document $model): bool
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User || !$identity->canForwardDocumentToUser()) {
            return false;
        }
        if ($identity->role === User::ROLE_ADMIN) {
            return true;
        }
        if ($identity->role === User::ROLE_DEPUTY_DIRECTOR) {
            return $model->status === Document::STATUS_SUBMITTED_TO_DEPUTY;
        }

        $assignment = $this->latestAssignment($model);
        if ($assignment === null) {
            return false;
        }

        return $identity->department_id !== null
            && $assignment->department_id !== null
            && (int) $assignment->department_id === (int) $identity->department_id;
    }

    private function canCurrentUserCompleteAssignment(Document $model): bool
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User || !$identity->canCompleteDocument()) {
            return false;
        }
        if ($identity->role === User::ROLE_ADMIN) {
            return true;
        }

        $assignment = $this->latestAssignment($model);
        if ($assignment === null) {
            return false;
        }

        if ($identity->role === User::ROLE_GENERAL_USER) {
            return $assignment->assigned_to !== null && (int) $assignment->assigned_to === (int) $identity->id;
        }

        return $identity->role === User::ROLE_STAFF
            && $assignment->assigned_to === null
            && $identity->department_id !== null
            && $assignment->department_id !== null
            && (int) $assignment->department_id === (int) $identity->department_id;
    }

    private function latestAssignment(Document $model): ?DocumentAssignment
    {
        $latest = null;
        foreach ($model->assignments as $assignment) {
            if ($latest === null || (int) $assignment->id > (int) $latest->id) {
                $latest = $assignment;
            }
        }

        return $latest;
    }

    private function myWorkSummary(array $statuses, User $identity): array
    {
        if ($statuses === []) {
            return [];
        }

        $query = Document::find()
            ->select(['status' => '{{%document}}.[[status]]', 'total' => new Expression('COUNT(DISTINCT {{%document}}.[[id]])')])
            ->where(['{{%document}}.[[status]]' => $statuses])
            ->groupBy('{{%document}}.[[status]]');
        if ($identity->role === User::ROLE_STAFF || $identity->role === User::ROLE_GENERAL_USER) {
            $this->applyStaffAssignmentScope($query, $identity);
        }

        $rows = $query
            ->asArray()
            ->all();

        $summary = array_fill_keys($statuses, 0);
        foreach ($rows as $row) {
            $summary[(string) $row['status']] = (int) $row['total'];
        }

        return $summary;
    }

    private function applyStaffAssignmentScope($query, User $identity): void
    {
        $conditions = [['document_assignment.assigned_to' => $identity->id]];

        if ($identity->role === User::ROLE_STAFF && $identity->department_id !== null) {
            $conditions[] = [
                'and',
                ['document_assignment.assigned_to' => null],
                ['document_assignment.department_id' => $identity->department_id],
            ];
        }

        array_unshift($conditions, 'or');

        $query->joinWith('assignments')
            ->andWhere($conditions)
            ->distinct();
    }

    private function canCurrentUserViewDocument(Document $model): bool
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User) {
            return false;
        }
        if ($identity->hasRole([User::ROLE_ADMIN, User::ROLE_OFFICER, User::ROLE_DEPUTY_DIRECTOR, User::ROLE_DIRECTOR])) {
            return true;
        }
        if ($identity->role !== User::ROLE_STAFF && $identity->role !== User::ROLE_GENERAL_USER) {
            return false;
        }

        if ($identity->role === User::ROLE_GENERAL_USER) {
            return $this->canCurrentUserCompleteAssignment($model);
        }

        $assignment = $this->latestAssignment($model);
        return $assignment !== null
            && $identity->department_id !== null
            && $assignment->department_id !== null
            && (int) $assignment->department_id === (int) $identity->department_id;
    }

    private function regenerateStampedPdf(Document $model, bool $flashWarning = true): void
    {
        try {
            $model->refresh();
            $model->populateRelation('department', null);
            $model->populateRelation('originalFile', null);
            $model->populateRelation('stampedFile', null);
            $model->populateRelation('stamps', null);
            $model = Document::find()
                ->with(['department', 'originalFile', 'stampedFile', 'stamps'])
                ->where(['id' => $model->id])
                ->one() ?? $model;

            (new PdfStampService())->generate($model);
            $this->saveAudit($model, 'document.stamped_pdf_auto_generated', 'ระบบสร้าง PDF ฉบับประทับตราอัตโนมัติ');
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            if ($flashWarning) {
                Yii::$app->session->addFlash('warning', 'บันทึก workflow แล้ว แต่สร้าง PDF ประทับตราอัตโนมัติไม่สำเร็จ: ' . $e->getMessage());
            }
        }
    }

    private function stampPositionDefaults(): array
    {
        return [
            DocumentStamp::TYPE_RECEIVE => ['x' => 144, 'y' => 14, 'label' => 'ตรารับหนังสือ'],
            DocumentStamp::TYPE_DEPARTMENT => ['x' => 18, 'y' => 219, 'label' => 'ตรากลุ่มงาน'],
            DocumentStamp::TYPE_DEPUTY_COMMENT => ['x' => 128, 'y' => 170, 'label' => 'ความเห็นรอง ผอ.'],
            DocumentStamp::TYPE_DIRECTOR_ORDER => ['x' => 132, 'y' => 230, 'label' => 'คำสั่ง ผอ.'],
        ];
    }

    private function saveStampPosition(Document $model, string $type, float $x, float $y): void
    {
        $stamp = $this->latestStamp($model, $type);
        if ($stamp === null) {
            $stamp = new DocumentStamp([
                'document_id' => $model->id,
                'stamp_type' => $type,
                'page_no' => 1,
                'content' => $this->defaultStampContent($model, $type),
                'created_by' => Yii::$app->user->id,
                'created_at' => time(),
            ]);
        }

        $stamp->position_x = $x;
        $stamp->position_y = $y;
        $stamp->save(false);
    }

    private function defaultStampContent(Document $model, string $type): string
    {
        return match ($type) {
            DocumentStamp::TYPE_RECEIVE => "รับที่ {$model->receive_no}\nวันที่ {$model->receive_date}\nเวลา {$model->receive_time}",
            DocumentStamp::TYPE_DEPARTMENT => 'ตรากลุ่มงานที่เกี่ยวข้อง',
            default => '',
        };
    }

    private function latestStamp(Document $model, string $type): ?DocumentStamp
    {
        $latest = null;
        foreach ($model->stamps as $stamp) {
            if ($stamp->stamp_type === $type) {
                $latest = $stamp;
            }
        }

        return $latest;
    }

    private function previewImagePath(Document $model): ?string
    {
        $file = $model->stampedFile ?: $model->originalFile;
        if ($file === null) {
            return null;
        }

        try {
            return (new PdfStampService())->generatePreviewImage($file);
        } catch (\Throwable $e) {
            Yii::warning($e, __METHOD__);
            return null;
        }
    }

    private function suggestNextReceiveNo(): string
    {
        $buddhistYear = (int) date('Y') + 543;
        $suffix = '/' . $buddhistYear;

        $receiveNumbers = Document::find()
            ->select('receive_no')
            ->where(['like', 'receive_no', '%' . $suffix, false])
            ->column();

        $max = 0;
        foreach ($receiveNumbers as $receiveNo) {
            if (preg_match('/^(\d+)\/' . preg_quote((string) $buddhistYear, '/') . '$/', (string) $receiveNo, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return ($max + 1) . $suffix;
    }

    private function changeStatus(Document $model, string $toStatus, string $action, string $comment, bool $useTransaction = true): void
    {
        $runner = function () use ($model, $toStatus, $action, $comment): void {
            $fromStatus = $model->status;
            $model->status = $toStatus;
            $model->save(false, ['status', 'updated_at', 'updated_by']);

            (new WorkflowAction([
                'document_id' => $model->id,
                'actor_id' => Yii::$app->user->id,
                'action' => $action,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'comment' => $comment,
                'created_at' => time(),
            ]))->save(false);

            $this->saveAudit($model, 'workflow.' . $action, $comment, false);
        };

        if (!$useTransaction) {
            $runner();
            return;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $runner();
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    private function saveStamp(Document $model, string $type, string $content, int $positionY, int $positionX, bool $useTransaction = true): void
    {
        $runner = static function () use ($model, $type, $content, $positionY, $positionX): void {
            $latest = null;
            foreach ($model->stamps as $stamp) {
                if ($stamp->stamp_type === $type) {
                    $latest = $stamp;
                }
            }

            (new DocumentStamp([
                'document_id' => $model->id,
                'stamp_type' => $type,
                'page_no' => 1,
                'position_x' => $latest?->position_x ?? $positionX,
                'position_y' => $latest?->position_y ?? $positionY,
                'content' => $content,
                'created_by' => Yii::$app->user->id,
                'created_at' => time(),
            ]))->save(false);
        };

        if (!$useTransaction) {
            $runner();
            return;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $runner();
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    private function saveAudit(Document $model, string $event, string $detail, bool $useTransaction = true): void
    {
        $runner = static function () use ($model, $event, $detail): void {
            (new AuditLog([
                'document_id' => $model->id,
                'user_id' => Yii::$app->user->id,
                'event' => $event,
                'detail' => $detail,
                'ip_address' => Yii::$app->request->userIP,
                'created_at' => time(),
            ]))->save(false);
        };

        if (!$useTransaction) {
            $runner();
            return;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $runner();
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}
