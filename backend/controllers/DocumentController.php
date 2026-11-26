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
use yii\helpers\FileHelper;
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
                    'delete' => ['post'],
                    'submit-to-deputy' => ['post'],
                    'deputy-comment' => ['post'],
                    'director-order' => ['post'],
                    'forward-to-staff' => ['post'],
                    'complete' => ['post'],
                    'generate-stamped-pdf' => ['post'],
                    'update-stamp-positions' => ['post'],
                    'update-summary' => ['post'],
                    'submit-to-approver' => ['post'],
                    'approver-approve' => ['post'],
                    'approver-return' => ['post'],
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

        if ($identity instanceof User && !$identity->hasRole([User::ROLE_ADMIN, User::ROLE_DIRECTOR])) {
            if ($identity->role === User::ROLE_DEPUTY_DIRECTOR) {
                // รอง ผอ. / ผู้ช่วย ผอ. (ที่ไม่ได้รักษาการ ผอ.): เห็นหนังสือทั้งหมดในกลุ่มงานที่ตนเองกำกับดูแล
                $deptIds = $identity->departmentIds();
                if ($deptIds !== []) {
                    $deputyScopeCond = [
                        'or',
                        ['{{%document}}.[[department_id]]' => $deptIds],
                        ['{{%document}}.[[department_id]]' => null],
                        ['{{%document}}.[[approver_id]]' => $identity->id],
                    ];
                    $query->andWhere($deputyScopeCond);
                    $countQuery->andWhere($deputyScopeCond);
                    $departments = array_values(array_filter(
                        $departments,
                        static fn (Department $dept): bool => in_array((int) $dept->id, $deptIds, true)
                    ));
                }
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
        $isTopExecutive = $identity->hasRole([User::ROLE_ADMIN, User::ROLE_DIRECTOR]);
        $showMemoWorkbench = true; // เปิดให้ทุกบทบาท (รวมคุณครู/บุคลากรทั่วไป) เข้าถึงบันทึกข้อความของตนเองได้

        // 1. ดึงข้อมูลหนังสือภายนอกที่เกี่ยวข้องกับตนเอง (งานรอดำเนินการ)
        $docQuery = Document::find()
            ->with(['department', 'originalFile'])
            ->orderBy(['{{%document}}.[[updated_at]]' => SORT_DESC, '{{%document}}.[[id]]' => SORT_DESC]);

        if ($identity->hasRole(User::ROLE_ADMIN)) {
            $docQuery->where(['{{%document}}.[[status]]' => $statuses]);
        } elseif ($identity->role === User::ROLE_DEPUTY_DIRECTOR && !$isTopExecutive) {
            // รอง ผอ. / ผู้ช่วย ผอ.: แสดงเฉพาะหนังสือรอพิจารณาในกลุ่มงานที่ตนเองกำกับดูแล (หรือที่ตนเป็นผู้ตรวจ)
            $deptIds = $identity->departmentIds();
            $deputyPendingCond = ['and', ['{{%document}}.[[status]]' => $statuses]];
            if ($deptIds !== []) {
                $deputyPendingCond[] = ['or', ['{{%document}}.[[department_id]]' => $deptIds], ['{{%document}}.[[department_id]]' => null]];
            }
            $docQuery->where([
                'or',
                $deputyPendingCond,
                [
                    'and',
                    ['{{%document}}.[[status]]' => Document::STATUS_SUBMITTED_TO_APPROVER],
                    ['{{%document}}.[[approver_id]]' => $identity->id],
                ],
            ]);
        } elseif ($identity->role === User::ROLE_STAFF || $identity->role === User::ROLE_GENERAL_USER) {
            $assignedScopeQuery = Document::find();
            $this->applyStaffAssignmentScope($assignedScopeQuery, $identity);
            $assignedDocIds = $assignedScopeQuery->select('{{%document}}.[[id]]')->column();

            $staffConds = ['or'];
            if ($assignedDocIds !== []) {
                $staffConds[] = ['and', ['{{%document}}.[[status]]' => $statuses], ['{{%document}}.[[id]]' => $assignedDocIds]];
            }
            $staffConds[] = [
                'and',
                ['{{%document}}.[[status]]' => Document::STATUS_SUBMITTED_TO_APPROVER],
                ['{{%document}}.[[approver_id]]' => $identity->id],
            ];
            $docQuery->where($staffConds);
        } else {
            $docQuery->where([
                'or',
                ['{{%document}}.[[status]]' => $statuses],
                [
                    'and',
                    ['{{%document}}.[[status]]' => Document::STATUS_SUBMITTED_TO_APPROVER],
                    ['{{%document}}.[[approver_id]]' => $identity->id],
                ],
            ]);
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
        $docPendingCount = (int) $docDataProvider->getTotalCount();
        $memoPendingCount = (int) $memoDataProvider->getTotalCount();

        $stats = [
            'doc_pending' => $docPendingCount,
            'memo_active' => $memoPendingCount,
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
            'docPendingCount' => $docPendingCount,
            'memoPendingCount' => $memoPendingCount,
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
        $form->document->approver_id = SystemSetting::getDocumentApproverId();

        if (Yii::$app->request->isPost && $form->load(Yii::$app->request->post())) {
            // บังคับใช้ approver_id จากการตั้งค่าของ Admin เสมอ
            $form->document->approver_id = SystemSetting::getDocumentApproverId();
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
        $allUsers = User::find()
            ->where(['status' => User::STATUS_ACTIVE])
            ->orderBy([new \yii\db\Expression("COALESCE(NULLIF(fullname, ''), username) ASC")])
            ->all();

        return $this->render('create', [
            'form' => $form,
            'departments' => $departments,
            'isActingDirector' => $isActingDirector,
            'effectiveDirectorName' => $effectiveDirectorName,
            'effectiveDirectorPosition' => $effectiveDirectorPosition,
            'deputyByDept' => $deputyByDept,
            'allDeputyNames' => $allDeputyNames,
            'allUsers' => $allUsers,
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
            ->with(['department'])
            ->where(['status' => User::STATUS_ACTIVE])
            ->andWhere(['role' => [User::ROLE_GENERAL_USER, User::ROLE_OFFICER, User::ROLE_STAFF]])
            ->orderBy([new \yii\db\Expression("COALESCE(NULLIF(fullname, ''), username) ASC")]);

        return $this->render('view', [
            'model' => $model,
            'departments' => Department::find()->where(['is_active' => true])->orderBy(['sort_order' => SORT_ASC])->all(),
            'staffUsers' => $staffQuery->all(),
            'allUsers' => User::find()
                ->where(['status' => User::STATUS_ACTIVE])
                ->orderBy([new \yii\db\Expression("COALESCE(NULLIF(fullname, ''), username) ASC")])
                ->all(),
            'previewImagePath' => $this->previewImagePath($model),
        ]);
    }

    /**
     * ลบหนังสือที่ลงรับแล้ว (สำหรับเจ้าหน้าที่ธุรการกรณีลงรับผิดพลาด หรือผู้ดูแลระบบ)
     */
    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        $identity = Yii::$app->user->identity;

        if (!$identity instanceof User || !$identity->canDeleteDocument($model)) {
            throw new ForbiddenHttpException('ท่านไม่มีสิทธิ์ลบหนังสือฉบับนี้');
        }

        $receiveNo = (string) $model->receive_no;
        $subject = (string) $model->subject;
        $year = date('Y', strtotime($model->receive_date));
        $uploadDir = Yii::getAlias('@backend/web/uploads/documents/' . $year . '/' . $model->id);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // บันทึก Audit Log ก่อนลบ
            (new AuditLog([
                'document_id' => $model->id,
                'user_id' => $identity->id,
                'event' => 'document.deleted',
                'detail' => sprintf('ลบหนังสือเลขรับ %s เรื่อง "%s" ที่ลงรับผิดพลาด (ผู้ลบ: %s)', $receiveNo, $subject, $identity->displayName()),
                'ip_address' => Yii::$app->request->userIP,
                'created_at' => time(),
            ]))->save(false);

            // ลบข้อมูลเอกสาร (ตารางลูกที่มี Foreign Key CASCADE จะถูกลบอัตโนมัติ)
            $model->delete();

            // ลบไฟล์เอกสารในโฟลเดอร์ uploads ของหนังสือนั้น
            if (is_dir($uploadDir)) {
                FileHelper::removeDirectory($uploadDir);
            }

            $transaction->commit();
            Yii::$app->session->setFlash('success', "ลบหนังสือเลขรับ {$receiveNo} เรียบร้อยแล้ว");
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error('Failed to delete document id=' . $id . ': ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'ไม่สามารถลบหนังสือได้: ' . $e->getMessage());
        }

        return $this->redirect(['index']);
    }

    public function actionSubmitToApprover(int $id): Response
    {
        $this->requireRole([User::ROLE_OFFICER, User::ROLE_ADMIN], 'เฉพาะเจ้าหน้าที่ธุรการหรือผู้ดูแลระบบเท่านั้นที่เสนอผู้ตรวจสอบได้');

        $model = $this->findModel($id);
        if (!in_array($model->status, [Document::STATUS_REGISTERED, Document::STATUS_RETURNED], true)) {
            Yii::$app->session->setFlash('warning', 'หนังสือไม่ได้อยู่ในสถานะที่สามารถเสนอผู้ตรวจสอบได้');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        // ผู้ตรวจสอบถูกกำหนดโดย Admin ในหน้า user/index เท่านั้น (ธุรการไม่สามารถเลือกหรือเปลี่ยนเองได้)
        $approver = SystemSetting::getDocumentApprover();
        if ($approver === null) {
            Yii::$app->session->setFlash('error', 'ยังไม่ได้กำหนดผู้ตรวจสอบ/อนุมัติในระบบ กรุณาติดต่อผู้ดูแลระบบ (Admin) เพื่อตั้งค่าในหน้าผู้ใช้และบทบาท');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $model->approver_id = $approver->id;

        $comment = trim((string) Yii::$app->request->post('comment', ''));
        $actionComment = $comment !== '' ? $comment : 'เสนอให้ ' . $approver->displayName() . ' ตรวจสอบ/อนุมัติ';

        $model->save(false, ['approver_id']);
        $this->changeStatus(
            $model,
            Document::STATUS_SUBMITTED_TO_APPROVER,
            'submit_to_approver',
            $actionComment,
        );
        $this->regenerateStampedPdf($model);

        // ส่งแจ้งเตือน LINE ให้ผู้ตรวจสอบ
        $identity = Yii::$app->user->identity;
        if ($identity instanceof User) {
            (new LineMessagingService())->notifyDocumentWorkflow(
                $approver,
                $model,
                $identity,
                'มีหนังสือรับใหม่รอท่านตรวจสอบและอนุมัติ',
                $comment
            );
        }

        Yii::$app->session->setFlash('success', 'เสนอหนังสือให้ ' . $approver->displayName() . ' ตรวจสอบ/อนุมัติเรียบร้อยแล้ว');
        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionApproverApprove(int $id): Response
    {
        $model = $this->findModel($id);
        $identity = Yii::$app->user->identity;

        if (!$identity instanceof User || !($identity->hasRole(User::ROLE_ADMIN) || (int) $identity->id === (int) $model->approver_id)) {
            throw new ForbiddenHttpException('เฉพาะผู้ตรวจสอบ/อนุมัติที่ได้รับมอบหมายหรือผู้ดูแลระบบเท่านั้นที่อนุมัติได้');
        }

        if ($model->status !== Document::STATUS_SUBMITTED_TO_APPROVER) {
            Yii::$app->session->setFlash('warning', 'หนังสือไม่ได้อยู่ในสถานะรอตรวจสอบ/อนุมัติ');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        // กรณีตรวจพบว่าติ๊กกลุ่มงานผิด ผู้ตรวจสอบสามารถแก้ไขกลุ่มงานใหม่ได้ทันที
        $newDeptId = (int) Yii::$app->request->post('department_id', 0);
        $deptChangedNote = '';
        $newDept = null;
        if ($newDeptId > 0 && $newDeptId !== (int) $model->department_id) {
            $newDept = Department::findOne(['id' => $newDeptId, 'is_active' => true]);
            if ($newDept !== null) {
                $oldDeptName = $model->department?->name ?? 'ไม่ระบุ';
                $model->department_id = $newDept->id;
                $model->save(false, ['department_id']);
                $model->refresh();
                $deptChangedNote = " (ผู้ตรวจสอบแก้ไขกลุ่มงานจาก {$oldDeptName} เป็น {$newDept->name})";
            }
        }

        $comment = trim((string) Yii::$app->request->post('comment', ''));
        if ($comment !== '') {
            $actionComment = $comment . $deptChangedNote;
        } else {
            $actionComment = 'อนุมัติความถูกต้องและเสนอต่อรอง ผอ.' . $deptChangedNote;
        }

        $this->changeStatus(
            $model,
            Document::STATUS_SUBMITTED_TO_DEPUTY,
            'approver_approved',
            $actionComment,
        );
        $this->regenerateStampedPdf($model);

        // แจ้งเตือน รอง ผอ. / ผู้ช่วย ผอ. ประจำกลุ่มงานทุกคนที่เชื่อม LINE ไว้
        $deputies = User::find()
            ->where(['{{%user}}.role' => User::ROLE_DEPUTY_DIRECTOR, '{{%user}}.status' => User::STATUS_ACTIVE])
            ->joinWith('departments')
            ->andWhere(['or',
                ['{{%user}}.department_id' => $model->department_id],
                ['user_department.department_id' => $model->department_id],
            ])
            ->all();

        $lineService = new LineMessagingService();
        $notifiedIds = [];
        foreach ($deputies as $deputy) {
            if ($deputy instanceof User && !isset($notifiedIds[(int) $deputy->id])) {
                $notifiedIds[(int) $deputy->id] = true;
                try {
                    $lineService->notifyDocumentWorkflow(
                        $deputy,
                        $model,
                        $identity,
                        'มีหนังสือรับผ่านการอนุมัติ เสนอถึงท่านเพื่อลงความเห็น',
                        $comment
                    );
                } catch (\Throwable $e) {
                    Yii::warning('LINE notify deputy failed: ' . $e->getMessage(), __METHOD__);
                }
            }
        }

        if ($newDept !== null) {
            Yii::$app->session->setFlash('success', 'แก้ไขกลุ่มงานเป็น "' . $newDept->name . '" พร้อมอนุมัติและเสนอต่อรอง ผอ. เรียบร้อยแล้ว');
        } else {
            Yii::$app->session->setFlash('success', 'อนุมัติหนังสือและเสนอต่อรอง ผอ. เรียบร้อยแล้ว');
        }
        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionApproverReturn(int $id): Response
    {
        $model = $this->findModel($id);
        $identity = Yii::$app->user->identity;

        if (!$identity instanceof User || !($identity->hasRole(User::ROLE_ADMIN) || (int) $identity->id === (int) $model->approver_id)) {
            throw new ForbiddenHttpException('เฉพาะผู้ตรวจสอบ/อนุมัติที่ได้รับมอบหมายหรือผู้ดูแลระบบเท่านั้นที่ส่งกลับได้');
        }

        if ($model->status !== Document::STATUS_SUBMITTED_TO_APPROVER) {
            Yii::$app->session->setFlash('warning', 'หนังสือไม่ได้อยู่ในสถานะรอตรวจสอบ/อนุมัติ');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $comment = trim((string) Yii::$app->request->post('comment', ''));
        $actionComment = $comment !== '' ? $comment : 'ส่งกลับให้เจ้าหน้าที่ธุรการแก้ไข';

        $this->changeStatus(
            $model,
            Document::STATUS_RETURNED,
            'approver_returned',
            $actionComment,
        );
        $this->regenerateStampedPdf($model);

        // แจ้งเตือนเจ้าหน้าที่ธุรการผู้ลงทะเบียน
        $officer = User::findOne($model->created_by);
        if ($officer instanceof User) {
            (new LineMessagingService())->notifyDocumentWorkflow(
                $officer,
                $model,
                $identity,
                'หนังสือรับถูกส่งกลับให้แก้ไข',
                $comment
            );
        }

        Yii::$app->session->setFlash('info', 'ส่งกลับหนังสือให้เจ้าหน้าที่ธุรการแก้ไขเรียบร้อยแล้ว');
        return $this->redirect(['view', 'id' => $model->id]);
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

            // แจ้งเตือนผู้ลงทะเบียนหนังสือเมื่อถูกส่งกลับแก้ไข
            if ($model->created_by !== null && $identity instanceof User) {
                $creator = User::findOne((int) $model->created_by);
                if ($creator instanceof User) {
                    try {
                        (new LineMessagingService())->notifyDocumentWorkflow(
                            $creator,
                            $model,
                            $identity,
                            'หนังสือรับถูกส่งกลับให้แก้ไข',
                            $comment
                        );
                    } catch (\Throwable $e) {
                        Yii::warning('LINE notify creator on deputy return failed: ' . $e->getMessage(), __METHOD__);
                    }
                }
            }

            Yii::$app->session->setFlash('success', 'ส่งกลับแก้ไขเรียบร้อยแล้ว');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $this->changeStatus($model, Document::STATUS_SUBMITTED_TO_DIRECTOR, 'deputy_comment', $comment);
        $this->saveStamp($model, DocumentStamp::TYPE_DEPUTY_COMMENT, $comment, 170, 128);
        $this->regenerateStampedPdf($model);

        // แจ้งเตือน ผอ. (หรือผู้รักษาการแทน ผอ. หากเปิดโหมดรักษาการ) ผ่าน LINE
        if ($identity instanceof User) {
            $directors = [];
            if (SystemSetting::isActingDirector()) {
                $actingId = SystemSetting::getActingDirectorUserId();
                if ($actingId !== null) {
                    $actingUser = User::findOne(['id' => $actingId, 'status' => User::STATUS_ACTIVE]);
                    if ($actingUser instanceof User) {
                        $directors[] = $actingUser;
                    }
                }
            }
            if ($directors === []) {
                $directors = User::find()
                    ->where(['role' => User::ROLE_DIRECTOR, 'status' => User::STATUS_ACTIVE])
                    ->andWhere(['is not', 'line_user_id', null])
                    ->andWhere(['<>', 'line_user_id', ''])
                    ->all();
            }

            $lineService = new LineMessagingService();
            foreach ($directors as $director) {
                try {
                    $lineService->notifyDocumentWorkflow(
                        $director,
                        $model,
                        $identity,
                        'มีหนังสือรับเสนอเพื่อพิจารณาสั่งการ',
                        $comment
                    );
                } catch (\Throwable $e) {
                    Yii::warning('LINE notify director failed: ' . $e->getMessage(), __METHOD__);
                }
            }
        }

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

    public function actionUpdateSummary(int $id): Response
    {
        $model = $this->findModel($id);
        if (!$this->canCurrentUserEditSummary($model)) {
            throw new ForbiddenHttpException('คุณไม่มีสิทธิ์แก้ไขสรุปเนื้อหาของหนังสือนี้');
        }

        $summary = trim((string) Yii::$app->request->post('summary', ''));
        $model->summary = $summary !== '' ? $summary : trim((string) $model->subject);

        if ($model->save(false)) {
            $this->saveAudit($model, 'document.summary_updated', 'แก้ไขสรุปเนื้อหาในหนังสือโดยย่อ', false);
            $this->regenerateStampedPdf($model);
            Yii::$app->session->setFlash('success', 'บันทึกสรุปเนื้อหาในหนังสือเรียบร้อยแล้ว');
        } else {
            Yii::$app->session->setFlash('error', 'ไม่สามารถบันทึกสรุปเนื้อหาได้');
        }

        return $this->redirect(['view', 'id' => $model->id]);
    }

    public function actionComplete(int $id): Response
    {
        $this->requireRole([User::ROLE_DIRECTOR, User::ROLE_DEPUTY_DIRECTOR, User::ROLE_STAFF, User::ROLE_GENERAL_USER], 'เฉพาะผู้บริหาร หรือผู้รับมอบหมายเท่านั้นที่ปิดเรื่องได้');

        $model = $this->findModel($id);
        if ($model->status === Document::STATUS_COMPLETED) {
            Yii::$app->session->setFlash('info', 'หนังสือนี้ปิดเรื่องเรียบร้อยแล้ว');
            return $this->redirect(['view', 'id' => $model->id]);
        }
        if (!$this->canCurrentUserCompleteAssignment($model)) {
            Yii::$app->session->setFlash('warning', 'คุณไม่มีสิทธิ์ปิดเรื่องสำหรับหนังสือในสถานะนี้');
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

        $rawAssigned = Yii::$app->request->post('assigned_to', []);
        $assignedIds = array_values(array_unique(array_filter(
            array_map('intval', is_array($rawAssigned) ? $rawAssigned : [$rawAssigned]),
            static fn (int $uid): bool => $uid > 0
        )));
        $instruction = trim((string) Yii::$app->request->post('instruction'));

        if ($assignedIds === []) {
            Yii::$app->session->setFlash('error', 'กรุณาเลือกผู้ปฏิบัติอย่างน้อย 1 คน');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $staffList = User::find()
            ->with('departments')
            ->where([
                'id' => $assignedIds,
                'status' => User::STATUS_ACTIVE,
                'role' => [User::ROLE_GENERAL_USER, User::ROLE_OFFICER, User::ROLE_STAFF],
            ])
            ->all();

        if ($staffList === []) {
            Yii::$app->session->setFlash('error', 'กรุณาเลือกผู้ปฏิบัติที่ยังใช้งานอยู่');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $identity = Yii::$app->user->identity;
        $selectedStaffById = [];
        foreach ($staffList as $staffUser) {
            $selectedStaffById[(int) $staffUser->id] = $staffUser;
        }
        $selectedIds = array_keys($selectedStaffById);
        $newlyAssignedStaff = [];

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $existingByUserId = [];
            foreach ($model->assignments as $existing) {
                if ($existing->assigned_to === null) {
                    // ลบแถว placeholder ของกลุ่มงานเมื่อมีการระบุตัวผู้ปฏิบัติแล้ว
                    $existing->delete();
                    continue;
                }
                $uid = (int) $existing->assigned_to;
                if (!in_array($uid, $selectedIds, true)) {
                    // เอาผู้ปฏิบัติที่ถูกยกเลิกการเลือกออก
                    $existing->delete();
                    continue;
                }
                $existingByUserId[$uid] = $existing;
            }

            foreach ($selectedStaffById as $uid => $staffUser) {
                if (isset($existingByUserId[$uid])) {
                    $assignment = $existingByUserId[$uid];
                    $assignment->assigned_by = Yii::$app->user->id;
                    if ($instruction !== '') {
                        $assignment->instruction = $instruction;
                    }
                    $assignment->save(false);
                } else {
                    $assignment = new DocumentAssignment([
                        'document_id' => $model->id,
                        'department_id' => $staffUser->department_id ?: $model->department_id,
                        'assigned_to' => $uid,
                        'assigned_by' => Yii::$app->user->id,
                        'instruction' => $instruction !== '' ? $instruction : null,
                        'status' => 'pending',
                        'due_date' => $model->due_date,
                    ]);
                    $assignment->save(false);
                    $newlyAssignedStaff[] = $staffUser;
                }
            }

            $staffNames = array_map(static fn (User $u): string => $u->displayName(), $staffList);
            $comment = 'ส่งต่อให้ผู้ปฏิบัติ (' . count($staffNames) . ' คน): ' . implode(', ', $staffNames)
                . ($instruction !== '' ? ' - ' . $instruction : '');
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

        // แจ้งเตือนผ่าน LINE (แจ้งคนที่เพิ่มใหม่ทุกคน หรือทุกคนที่เลือกหากเป็นการส่งใหม่)
        $notifyTargets = $newlyAssignedStaff !== [] ? $newlyAssignedStaff : $staffList;
        if ($identity instanceof User) {
            $lineService = new LineMessagingService();
            foreach ($notifyTargets as $targetUser) {
                try {
                    $lineService->notifyAssignedDocument($targetUser, $model, $identity, $instruction);
                } catch (\Throwable $e) {
                    Yii::warning('LINE notify assigned staff failed (user_id=' . $targetUser->id . '): ' . $e->getMessage(), __METHOD__);
                }
            }
        }

        Yii::$app->session->setFlash('success', 'ส่งต่อหนังสือให้ผู้ปฏิบัติ ' . count($staffList) . ' คน เรียบร้อยแล้ว');
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
                Document::STATUS_SUBMITTED_TO_APPROVER,
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

        if ($identity->department_id === null) {
            return false;
        }
        if ($model->department_id !== null && (int) $model->department_id === (int) $identity->department_id) {
            return true;
        }

        foreach ($model->assignments as $assignment) {
            if ($assignment->department_id !== null && (int) $assignment->department_id === (int) $identity->department_id) {
                return true;
            }
            if ($assignment->assigned_by !== null && (int) $assignment->assigned_by === (int) $identity->id) {
                return true;
            }
        }

        return false;
    }

    private function canCurrentUserCompleteAssignment(Document $model): bool
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User || !$identity->canCompleteDocument()) {
            return false;
        }
        if ($model->status === Document::STATUS_COMPLETED) {
            return false;
        }

        // ผู้ดูแลระบบ, ผอ., หรือผู้รักษาการแทน ผอ. สามารถปิดเรื่องได้ในขั้นรอพิจารณาและมอบหมายแล้ว
        if ($identity->role === User::ROLE_ADMIN || $identity->hasRole(User::ROLE_DIRECTOR)) {
            return in_array($model->status, [
                Document::STATUS_SUBMITTED_TO_DEPUTY,
                Document::STATUS_SUBMITTED_TO_DIRECTOR,
                Document::STATUS_ASSIGNED,
            ], true);
        }

        // รอง ผอ. / ผู้ช่วย ผอ.: ปิดเรื่องได้ในสถานะรอรอง ผอ. และสถานะมอบหมายแล้ว (ในสายงานหรือหนังสือที่ตนเองเคยลงนาม)
        if ($identity->role === User::ROLE_DEPUTY_DIRECTOR) {
            if ($model->status === Document::STATUS_SUBMITTED_TO_DEPUTY) {
                return $identity->canGiveDeputyComment($model->department_id);
            }
            if ($model->status === Document::STATUS_ASSIGNED) {
                if ($identity->canGiveDeputyComment($model->department_id)) {
                    return true;
                }
                return WorkflowAction::find()
                    ->where(['document_id' => $model->id, 'actor_id' => $identity->id])
                    ->exists();
            }
            return false;
        }

        if ($model->status !== Document::STATUS_ASSIGNED) {
            return false;
        }

        $assignments = $model->assignments;
        if ($assignments === []) {
            return false;
        }

        // ผู้ปฏิบัติที่ได้รับมอบหมายตรง (รองรับหลายคนพร้อมกัน และข้ามกลุ่มงาน)
        foreach ($assignments as $assignment) {
            if ($assignment->assigned_to !== null && (int) $assignment->assigned_to === (int) $identity->id) {
                return true;
            }
        }

        // หัวหน้ากลุ่มงาน: ปิดเรื่องได้หากยังไม่มีการระบุตัวผู้ปฏิบัติและเอกสารอยู่ในกลุ่มงานของตน
        if ($identity->role === User::ROLE_STAFF && $identity->department_id !== null) {
            foreach ($assignments as $assignment) {
                if (
                    $assignment->assigned_to === null
                    && $assignment->department_id !== null
                    && (int) $assignment->department_id === (int) $identity->department_id
                ) {
                    return true;
                }
            }
        }

        return false;
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

        $conditions = [
            'or',
            ['current_actor_id' => $identity->id],
            [
                'and',
                ['created_by' => $identity->id],
                ['status' => [Memo::STATUS_DRAFT, Memo::STATUS_RETURNED, Memo::STATUS_APPROVED]],
            ],
        ];

        // หากเป็น ผอ. หรือ รักษาราชการแทน ผอ. ให้เห็นบันทึกข้อความทั้งหมดที่อยู่ในสถานะ "รอ ผอ.พิจารณา"
        $isActingDirector = SystemSetting::isActingDirector() && (int) $identity->id === (int) SystemSetting::getActingDirectorUserId();
        if ($identity->role === User::ROLE_DIRECTOR || $isActingDirector) {
            $conditions[] = ['status' => Memo::STATUS_SUBMITTED_TO_DIRECTOR];
        }

        // หากเป็น รอง ผอ. ให้เห็นบันทึกข้อความทั้งหมดที่อยู่ในสถานะ "รอรอง ผอ.พิจารณา"
        if ($identity->role === User::ROLE_DEPUTY_DIRECTOR) {
            $conditions[] = ['status' => Memo::STATUS_SUBMITTED_TO_DEPUTY];
        }

        // หากเป็น หัวหน้ากลุ่มงาน หรือ เจ้าหน้าที่ ให้เห็นบันทึกข้อความที่อยู่ในสถานะ "รอหัวหน้ากลุ่มงาน/เจ้าหน้าที่ตรวจทาน"
        if ($identity->role === User::ROLE_STAFF || $identity->role === User::ROLE_OFFICER) {
            if ($identity->department_id !== null) {
                $conditions[] = ['and', ['status' => Memo::STATUS_SUBMITTED_TO_STAFF], ['or', ['department_id' => $identity->department_id], ['department_id' => null]]];
            } else {
                $conditions[] = ['status' => Memo::STATUS_SUBMITTED_TO_STAFF];
            }
        }

        $query->andWhere($conditions);
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

        // กรณีเป็นผู้ได้รับมอบหมายให้ตรวจสอบ/อนุมัติ (approver)
        if ($model->approver_id !== null && (int) $model->approver_id === (int) $identity->id) {
            return true;
        }

        if ($identity->role !== User::ROLE_STAFF && $identity->role !== User::ROLE_GENERAL_USER) {
            return false;
        }

        // กรณีมอบหมายตรงถึงตนเอง
        if ($this->canCurrentUserCompleteAssignment($model)) {
            return true;
        }

        // กรณีมอบหมายถึงตนเองหรือกลุ่มงานของตนเอง (รองรับหลายคนและข้ามกลุ่มงาน)
        foreach ($model->assignments as $assignment) {
            if ($assignment->assigned_to !== null && (int) $assignment->assigned_to === (int) $identity->id) {
                return true;
            }
            if (
                $identity->department_id !== null
                && $assignment->department_id !== null
                && (int) $assignment->department_id === (int) $identity->department_id
            ) {
                return true;
            }
        }

        // กรณีหนังสือภายนอกทั่วไป (ชั้นความลับปกติ) ที่อยู่ในสถานะเผยแพร่/สั่งการแล้ว อนุญาตให้บุคลากรทุกคนเปิดดูได้
        if ($model->confidentiality === 'normal' && in_array($model->status, [Document::STATUS_ASSIGNED, Document::STATUS_COMPLETED], true)) {
            return true;
        }

        return false;
    }

    public function canCurrentUserEditSummary(Document $model): bool
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User) {
            return false;
        }

        if ($identity->hasRole([User::ROLE_ADMIN, User::ROLE_OFFICER, User::ROLE_DIRECTOR])) {
            return true;
        }

        if ($model->approver_id !== null && (int) $model->approver_id === (int) $identity->id) {
            return true;
        }

        if ($identity->role === User::ROLE_DEPUTY_DIRECTOR) {
            return $identity->canGiveDeputyComment($model->department_id);
        }

        if ($identity->role === User::ROLE_STAFF) {
            return $identity->belongsToDepartment($model->department_id);
        }

        return $this->canCurrentUserCompleteAssignment($model);
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
