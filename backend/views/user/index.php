<?php

declare(strict_types=1);

use common\models\User;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'ผู้ใช้และบทบาท';
$this->params['breadcrumbs'][] = $this->title;
$this->registerCssFile('@web/css/eoffice.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$roleOptions = User::roleOptions();
$csrf = Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken);
$currentUserId = (int) Yii::$app->user->id;
?>

<div class="eoffice-page">
    <div class="eoffice-titlebar">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <p>กำหนดสิทธิ์ผู้ใช้ตามขั้นตอนงานสารบรรณ</p>
        </div>
        <?= Html::a('<i class="bi bi-person-plus" aria-hidden="true"></i><span>สร้างผู้ใช้</span>', ['create'], ['class' => 'btn btn-primary']) ?>
    </div>

    <div class="content-card">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'tableOptions' => ['class' => 'table table-hover align-middle'],
            'columns' => [
                'id',
                'username',
                'email',
                [
                    'attribute' => 'department_id',
                    'label' => 'กลุ่มงาน',
                    'value' => static function (User $model): string {
                        if ($model->role === User::ROLE_GENERAL_USER) {
                            $names = array_map(static fn ($department): string => $department->name, $model->departments);
                            return $names !== [] ? implode(', ', $names) : '-';
                        }

                        return $model->department->name ?? '-';
                    },
                ],
                [
                    'attribute' => 'role',
                    'format' => 'raw',
                    'value' => static function (User $model) use ($roleOptions, $csrf): string {
                        $select = Html::dropDownList('role', $model->role, $roleOptions, [
                            'class' => 'form-select form-select-sm',
                        ]);
                        $button = Html::submitButton('<i class="bi bi-save" aria-hidden="true"></i><span>บันทึก</span>', ['class' => 'btn btn-sm btn-primary']);

                        return Html::beginForm(['update-role', 'id' => $model->id], 'post', ['class' => 'role-form'])
                            . $csrf
                            . $select
                            . $button
                            . Html::endForm();
                    },
                ],
                [
                    'attribute' => 'status',
                    'value' => static fn (User $model): string => match ($model->status) {
                        User::STATUS_ACTIVE => 'ใช้งาน',
                        User::STATUS_INACTIVE => 'รอยืนยัน',
                        User::STATUS_DELETED => 'ปิดใช้งาน',
                        default => (string) $model->status,
                    },
                ],
                [
                    'label' => 'LINE',
                    'format' => 'raw',
                    'value' => static function (User $model): string {
                        if ($model->line_user_id !== null && $model->line_user_id !== '') {
                            $title = $model->line_display_name !== null && $model->line_display_name !== ''
                                ? 'เชื่อมต่อกับ ' . $model->line_display_name
                                : 'บัญชีนี้เชื่อมต่อ LINE แล้ว';

                            return Html::tag('span', '<i class="bi bi-chat-dots" aria-hidden="true"></i><span>เชื่อมต่อ LINE แล้ว</span>', [
                                'class' => 'btn btn-sm btn-success line-connected-button',
                                'role' => 'button',
                                'aria-disabled' => 'true',
                                'title' => $title,
                            ]);
                        }

                        return Html::tag('span', '<i class="bi bi-chat-slash" aria-hidden="true"></i><span>ยังไม่เชื่อมต่อ</span>', [
                            'class' => 'line-unconnected-badge',
                        ]);
                    },
                ],
                [
                    'label' => 'จัดการ',
                    'format' => 'raw',
                    'value' => static function (User $model) use ($currentUserId): string {
                        $buttons = Html::a('<i class="bi bi-pencil-square" aria-hidden="true"></i><span>แก้ไข</span>', ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-outline-primary']);
                        if ((int) $model->id !== $currentUserId && (int) $model->status !== User::STATUS_DELETED) {
                            $buttons .= ' ' . Html::a('<i class="bi bi-trash" aria-hidden="true"></i><span>ลบ</span>', ['delete', 'id' => $model->id], [
                                'class' => 'btn btn-sm btn-outline-danger',
                                'data-method' => 'post',
                                'data-confirm' => 'ยืนยันลบผู้ใช้นี้หรือไม่? ระบบจะปิดใช้งานบัญชีแทนการลบข้อมูลถาวร',
                            ]);
                        }

                        return '<div class="table-actions">' . $buttons . '</div>';
                    },
                ],
            ],
        ]) ?>
    </div>
</div>
