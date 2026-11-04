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
use common\models\DocumentRead;
use common\models\DocumentStamp;
use common\models\Memo;
use common\models\MemoRead;
use common\models\SystemSetting;
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
        $query = Document::find()
            ->with(['department', 'originalFile'])
            ->orderBy(['{{%document}}.[[id]]' => SORT_DESC]);
        $keyword = trim((string) Yii::$app->request->get('q', ''));
        $departmentId = Yii::$app->request->get('department_id');
        $departmentId = ($departmentId !== null && $departmentId !== '') ? (int) $departmentId : null;
        $identity = Yii::$app->user->identity;

        $myAssignedDocIds = [];

        if ($identity instanceof User) {
            // ดึง ID หนังสือที่มอบหมายถึงตนเองหรือกลุ่มงานของตนเอง
            $myIdsQuery = Document::find()->select('{{%document}}.[[id]]');
            $this->applyStaffAssignmentScope($myIdsQuery, $identity);
            $myAssignedDocIds = array_map('intval', $myIdsQuery->column());
        }

        // รายการกลุ่มงานทั้งหมดที่เปิดใช้งาน
        $departments = Department::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        // ฐานการนับจำนวนหนังสือสำหรับแต่ละแท็บ
        $countQuery = Document::find();

        if ($identity instanceof User && !$identity->hasRole(User::ROLE_ADMIN)) {
            if ($identity->hasRole(User::ROLE_DEPUTY_DIRECTOR)) {
                $query->andWhere(['{{%document}}.[[status]]' => [Document::STATUS_SUBMITTED_TO_DEPUTY, Document::STATUS_SUBMITTED_TO_DIRECTOR, Document::STATUS_RETURNED]]);
                $countQuery->andWhere(['{{%document}}.[[status]]' => [Document::STATUS_SUBMITTED_TO_DEPUTY, Document::STATUS_SUBMITTED_TO_DIRECTOR, Document::STATUS_RETURNED]]);
                $isActing = SystemSetting::isActingDirector() && (int) $identity->id === SystemSetting::getActingDirectorUserId();
                if (!$isActing && $identity->role === User::ROLE_DEPUTY_DIRECTOR) {
                    $myDepts = $identity->departmentIds();
                    if ($myDepts !== []) {
                        $query->andWhere(['{{%document}}.[[department_id]]' => $myDepts]);
                        $countQuery->andWhere(['{{%document}}.[[department_id]]' => $myDepts]);
                    }
                }
            } elseif ($identity->hasRole(User::ROLE_DIRECTOR)) {
                $query->andWhere(['{{%document}}.[[status]]' => [Document::STATUS_SUBMITTED_TO_DIRECTOR, Document::STATUS_ASSIGNED, Document::STATUS_COMPLETED]]);
                $countQuery->andWhere(['{{%document}}.[[status]]' => [Document::STATUS_SUBMITTED_TO_DIRECTOR, Document::STATUS_ASSIGNED, Document::STATUS_COMPLETED]]);
            } elseif ($identity->role === User::ROLE_STAFF || $identity->role === User::ROLE_GENERAL_USER) {
                $query->andWhere(['{{%document}}.[[status]]' => [Document::STATUS_ASSIGNED, Document::STATUS_COMPLETED]]);
                $query->andWhere(['{{%document}}.[[confidentiality]]' => 'normal']);
                $countQuery->andWhere(['{{%document}}.[[status]]' => [Document::STATUS_ASSIGNED, Document::STATUS_COMPLETED]]);
                $countQuery->andWhere(['{{%document}}.[[confidentiality]]' => 'normal']);
            }
        }

        // คัดกรองตามกลุ่มงานหากมีการเลือกแท็บ
        if ($departmentId !== null) {
            $query->andWhere(['{{%document}}.[[department_id]]' => $departmentId]);
        }

        if ($keyword !== '') {
            $searchCond = [
                'or',
                ['like', '{{%document}}.[[subject]]', $keyword],
                ['like', '{{%document}}.[[external_no]]', $keyword],
                ['like', '{{%document}}.[[receive_no]]', $keyword],
            ];
            $query->andWhere($searchCond);
            $countQuery->andWhere($searchCond);
        }

        // คำนวณจำนวนหนังสือรวมทั้งหมด
        $countAll = (int) (clone $countQuery)->count();

        // คำนวณจำนวนหนังสือของแต่ละกลุ่มงาน
        $deptCounts = [];
        $deptCountRows = (clone $countQuery)
            ->select(['department_id', 'COUNT(*) AS cnt'])
            ->groupBy('department_id')
            ->asArray()
            ->all();
        foreach ($deptCountRows as $row) {
            if ($row['department_id'] !== null) {
                $deptCounts[(int) $row['department_id']] = (int) $row['cnt'];
            }
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        $unreadCountAll = $countAll;
        $unreadDeptCounts = $deptCounts;
        $readDocMap = [];
        if ($identity instanceof User) {
            $readDocIds = DocumentRead::find()
                ->select('document_id')
                ->where(['user_id' => $identity->id])
                ->column();
            $readDocMap = array_fill_keys(array_map('intval', $readDocIds), true);

            $unreadBaseQuery = (clone $countQuery)
                ->leftJoin('{{%document_read}}', '{{%document_read}}.[[document_id]] = {{%document}}.[[id]] AND {{%document_read}}.[[user_id]] = :uid', [':uid' => $identity->id])
                ->andWhere(['{{%document_read}}.[[id]]' => null]);

            $unreadCountAll = (int) (clone $unreadBaseQuery)->count();

            $unreadDeptCounts = [];
            $unreadDeptRows = (clone $unreadBaseQuery)
                ->select(['{{%document}}.[[department_id]]', 'COUNT(*) AS cnt'])
                ->groupBy('{{%document}}.[[department_id]]')
                ->asArray()
                ->all();
            foreach ($unreadDeptRows as $row) {
                if ($row['department_id'] !== null) {
                    $unreadDeptCounts[(int) $row['department_id']] = (int) $row['cnt'];
                }
            }
        }

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'keyword' => $keyword,
            'departmentId' => $departmentId,
            'departments' => $departments,
            'countAll' => $countAll,
            'deptCounts' => $deptCounts,
            'unreadCountAll' => $unreadCountAll,
            'unreadDeptCounts' => $unreadDeptCounts,
            'myAssignedDocIds' => $myAssignedDocIds,
            'readDocMap' => $readDocMap,
        ]);
    }

    public function actionMy(): string
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User) {
            throw new ForbiddenHttpException('กรุณาเข้าสู่ระบบก่อนดูงานของฉัน');
        }

        $statuses = $this->myWorkStatuses($identity);
        $keyword = trim((string) Yii::$app->request->get('q_doc', Yii::$app->request->get('q', '')));
        $memoKeyword = trim((string) Yii::$app->request->get('q_memo', ''));
        $showMemoWorkbench = true; // เปิดให้ทุกบทบาท (รวมคุณครู/บุคลากรทั่วไป) เข้าถึงบันทึกข้อความของตนเองได้

        // 1. ดึงข้อมูลหนังสือภายนอกที่เกี่ยวข้องกับตนเอง
        $docQuery = Document::find()
            ->with(['department', 'originalFile'])
            ->where(['{{%document}}.[[status]]' => $statuses])
            ->orderBy(['{{%document}}.[[updated_at]]' => SORT_DESC, '{{%document}}.[[id]]' => SORT_DESC]);

        if ($identity->role === User::ROLE_STAFF || $identity->role === User::ROLE_GENERAL_USER) {
            $this->applyStaffAssignmentScope($docQuery, $identity);
        } elseif ($identity->role === User::ROLE_DEPUTY_DIRECTOR && !(SystemSetting::isActingDirector() && (int)$identity->id === (int)SystemSetting::getActingDirectorUserId())) {
            $deptIds = $identity->departmentIds();
            if (!empty($deptIds)) {
                $docQuery->andWhere(['or', ['{{%document}}.[[department_id]]' => $deptIds], ['{{%document}}.[[department_id]]' => null]]);
            }
        }

        if ($keyword !== '') {
            $docQuery->andWhere([
                'or',
                ['like', '{{%document}}.[[subject]]', $keyword],
                ['like', '{{%document}}.[[external_no]]', $keyword],
                ['like', '{{%document}}.[[receive_no]]', $keyword],
            ]);
        }

        $docDataProvider = new ActiveDataProvider([
            'query' => $docQuery,
            'pagination' => [
                'pageSize' => 15,
                'pageParam' => 'doc-page',
                'pageSizeParam' => 'doc-per-page',
            ],
        ]);

        // 2. ดึงข้อมูลบันทึกข้อความ (e-Memo) ที่เกี่ยวข้องกับตนเอง
        $memoQuery = Memo::find()
            ->with(['department', 'creator', 'currentActor'])
            ->orderBy(['updated_at' => SORT_DESC, 'id' => SORT_DESC]);
        $this->applyMemoWorkbenchScope($memoQuery, $identity);

        if ($memoKeyword !== '') {
            $memoQuery->andWhere([
                'or',
                ['like', 'subject', $memoKeyword],
                ['like', 'memo_no', $memoKeyword],
            ]);
        }

        $memoDataProvider = new ActiveDataProvider([
            'query' => $memoQuery,
            'pagination' => [
                'pageSize' => 15,
                'pageParam' => 'memo-page',
                'pageSizeParam' => 'memo-per-page',
            ],
        ]);

        // 3. สรุปสถิติงานที่ต้องดำเนินการ/ติดตามสำหรับช่วงทดลองใช้ (เฉพาะงานค้างที่ต้อง Take Action)
        $myAssignedDocCount = $docDataProvider->getTotalCount();
        
        // บันทึกข้อความที่กำลังอยู่ระหว่างดำเนินการ (เสนอตามสายงาน หรือรอตรวจ/ลงนาม)
        $activeMemoQuery = Memo::find()
            ->where(['not in', 'status', [Memo::STATUS_COMPLETED, Memo::STATUS_APPROVED]])
            ->andWhere([
                'or',
                ['current_actor_id' => $identity->id],
                ['created_by' => $identity->id],
            ]);
        $myActiveMemosCount = (int) $activeMemoQuery->count();

        $stats = [
            'doc_pending' => $myAssignedDocCount,
            'memo_active' => $myActiveMemosCount,
        ];

        $unreadDocQuery = (clone $docQuery)
            ->leftJoin('{{%document_read}}', '{{%document_read}}.[[document_id]] = {{%document}}.[[id]] AND {{%document_read}}.[[user_id]] = :uid', [':uid' => $identity->id])
            ->andWhere(['{{%document_read}}.[[id]]' => null]);
        $unreadDocCount = (int) $unreadDocQuery->count();

        $unreadMemoQuery = (clone $memoQuery)
            ->leftJoin('{{%memo_read}}', '{{%memo_read}}.[[memo_id]] = {{%memo}}.[[id]] AND {{%memo_read}}.[[user_id]] = :uid', [':uid' => $identity->id])
            ->andWhere(['{{%memo_read}}.[[id]]' => null]);
        $unreadMemoCount = (int) $unreadMemoQuery->count();

        $readDocIds = DocumentRead::find()
            ->select('document_id')
            ->where(['user_id' => $identity->id])
            ->column();
        $readDocMap = array_fill_keys(array_map('intval', $readDocIds), true);

        $readMemoIds = MemoRead::find()
            ->select('memo_id')
            ->where(['user_id' => $identity->id])
            ->column();
        $readMemoMap = array_fill_keys(array_map('intval', $readMemoIds), true);

        return $this->render('my', [
            'identity' => $identity,
            'docDataProvider' => $docDataProvider,
            'memoDataProvider' => $memoDataProvider,
            'stats' => $stats,
            'keyword' => $keyword,
            'memoKeyword' => $memoKeyword,
            'roleLabel' => $identity->roleLabel(),
            'unreadDocCount' => $unreadDocCount,
            'unreadMemoCount' => $unreadMemoCount,
            'readDocMap' => $readDocMap,
            'readMemoMap' => $readMemoMap,
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
            Yii::$app->session->setFlash('error', 'ไม่สามารถลงทะเบียนเอกสารได้ กรุณาตรวจสอบข้อมูลที่กรอกให้ถูกต้อง');
        }

        $departments = Department::find()->where(['is_active' => true])->orderBy(['sort_order' => SORT_ASC])->all();

        // ข้อมูลผู้ลงนาม: ผู้อำนวยการ / ผู้รักษาการแทน
        $isActingDirector = SystemSetting::isActingDirector();
        $effectiveDirectorName = SystemSetting::getCurrentDirectorName();
        $effectiveDirectorPosition = SystemSetting::getCurrentDirectorPosition();

        // ข้อมูลผู้ลงนาม: รองผู้อำนวยการประจำกลุ่มงาน
        $deputyUsers = User::find()
            ->where(['role' => User::ROLE_DEPUTY_DIRECTOR, 'status' => User::STATUS_ACTIVE])
            ->with(['department', 'departments'])
            ->all();

        $deputyByDept = [];
        foreach ($departments as $dept) {
            $assignedDeputies = [];
            foreach ($deputyUsers as $deputy) {
                if ($deputy->belongsToDepartment($dept->id)) {
                    $assignedDeputies[] = $deputy->displayName();
                }
            }
            $deputyByDept[$dept->id] = $assignedDeputies !== [] ? implode(', ', $assignedDeputies) : null;
        }

        $allDeputyNames = array_map(static fn (User $u): string => $u->displayName(), $deputyUsers);

        return $this->render('create', [
            'form' => $form,
            'departments' => $departments,
            'isActingDirector' => $isActingDirector,
            'effectiveDirectorName' => $effectiveDirectorName,
            'effectiveDirectorPosition' => $effectiveDirectorPosition,
            'deputyByDept' => $deputyByDept,
            'allDeputyNames' => $allDeputyNames,
        ]);
    }

    public function actionView(int $id): string
    {
        $model = $this->findModel($id);
        if (!$this->canCurrentUserViewDocument($model)) {
            throw new ForbiddenHttpException('คุณไม่มีสิทธิ์ดูหนังสือของกลุ่มงานอื่น');
        }

        $this->regenerateStampedPdfForPreview($model);
        $model = $this->findModel($id);

        $identity = Yii::$app->user->identity;
        if ($identity instanceof User) {
            DocumentRead::markAsRead($model->id, (int) $identity->id);
        }

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

        $identity = Yii::$app->user->identity;
        if ($identity instanceof User && !$identity->canGiveDeputyComment($model->department_id)) {
            throw new ForbiddenHttpException('ท่านไม่มีสิทธิ์ลงความเห็นในเอกสารนี้ เนื่องจากอยู่นอกสายบังคับบัญชากลุ่มงานที่รับผิดชอบ');
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

        // กรณีเป็น รอง ผอ. ที่รักษาการแทน ผอ. ด้วย
        if ($identity->role === User::ROLE_DEPUTY_DIRECTOR && SystemSetting::isActingDirector() && (int)$identity->id === (int)SystemSetting::getActingDirectorUserId()) {
            return [Document::STATUS_SUBMITTED_TO_DEPUTY, Document::STATUS_SUBMITTED_TO_DIRECTOR];
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

    private function applyMemoWorkbenchScope($query, User $identity): void
    {
        if ($identity->hasRole(User::ROLE_ADMIN)) {
            $query->andWhere(['not in', 'status', [Memo::STATUS_COMPLETED]]);
            return;
        }

        $query->andWhere([
            'or',
            ['current_actor_id' => $identity->id],
            [
                'and',
                ['created_by' => $identity->id],
                ['status' => [Memo::STATUS_DRAFT, Memo::STATUS_RETURNED, Memo::STATUS_APPROVED]],
            ],
        ]);
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

        // กรณีมอบหมายตรงถึงตนเอง
        if ($this->canCurrentUserCompleteAssignment($model)) {
            return true;
        }

        // กรณีมอบหมายถึงกลุ่มงานของตนเอง
        $assignment = $this->latestAssignment($model);
        if ($assignment !== null
            && $identity->department_id !== null
            && $assignment->department_id !== null
            && (int) $assignment->department_id === (int) $identity->department_id) {
            return true;
        }

        // กรณีหนังสือภายนอกทั่วไป (ชั้นความลับปกติ) ที่อยู่ในสถานะเผยแพร่/สั่งการแล้ว อนุญาตให้บุคลากรทุกคนเปิดดูได้
        if ($model->confidentiality === 'normal' && in_array($model->status, [Document::STATUS_ASSIGNED, Document::STATUS_COMPLETED], true)) {
            return true;
        }

        return false;
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

    private function regenerateStampedPdfForPreview(Document $model): void
    {
        $stampedFile = $model->stampedFile;
        if ($stampedFile === null || !$stampedFile->storage_path) {
            return;
        }

        $stampedPath = Yii::getAlias('@backend/web/' . ltrim($stampedFile->storage_path, '/'));
        if (!is_file($stampedPath)) {
            return;
        }

        $this->regenerateStampedPdf($model, false);
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

    private function getDocumentPageCount(Document $model): int
    {
        $file = $model->originalFile ?: $model->stampedFile;
        if ($file === null) {
            return 1;
        }

        $sourcePath = Yii::getAlias('@backend/web/' . ltrim($file->storage_path, '/'));
        if (!is_file($sourcePath)) {
            return 1;
        }

        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
            return (int) $pdf->setSourceFile($sourcePath);
        } catch (\Throwable $e) {
            $content = @file_get_contents($sourcePath);
            if ($content !== false) {
                if (preg_match('/\/Type\s*\/Pages[^>]*\/Count\s+(\d+)/', $content, $matches)) {
                    return max(1, (int) $matches[1]);
                }
                $pageMatches = preg_match_all('/\/Type\s*\/Page\b/', $content);
                if ($pageMatches > 0) {
                    return max(1, (int) $pageMatches);
                }
            }
            return 1;
        }
    }

    private function saveStampPosition(Document $model, string $type, float $x, float $y): void
    {
        $pageNo = ($type === DocumentStamp::TYPE_RECEIVE) ? 1 : $this->getDocumentPageCount($model);
        $stamp = $this->latestStamp($model, $type);
        if ($stamp === null) {
            $stamp = new DocumentStamp([
                'document_id' => $model->id,
                'stamp_type' => $type,
                'page_no' => $pageNo,
                'content' => $this->defaultStampContent($model, $type),
                'created_by' => Yii::$app->user->id,
                'created_at' => time(),
            ]);
        } else {
            $stamp->page_no = $pageNo;
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
        return SystemSetting::suggestNextReceiveNo();
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
        $runner = function () use ($model, $type, $content, $positionY, $positionX): void {
            $latest = null;
            foreach ($model->stamps as $stamp) {
                if ($stamp->stamp_type === $type) {
                    $latest = $stamp;
                }
            }

            $pageNo = ($type === DocumentStamp::TYPE_RECEIVE) ? 1 : $this->getDocumentPageCount($model);

            (new DocumentStamp([
                'document_id' => $model->id,
                'stamp_type' => $type,
                'page_no' => $pageNo,
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
