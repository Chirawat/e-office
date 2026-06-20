<?php

declare(strict_types=1);

namespace common\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class UserDepartment extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%user_department}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'department_id', 'created_at'], 'required'],
            [['user_id', 'department_id', 'created_at'], 'integer'],
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getDepartment(): ActiveQuery
    {
        return $this->hasOne(Department::class, ['id' => 'department_id']);
    }
}
