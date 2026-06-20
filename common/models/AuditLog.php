<?php

declare(strict_types=1);

namespace common\models;

use yii\db\ActiveRecord;

class AuditLog extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%audit_log}}';
    }

    public function rules(): array
    {
        return [
            [['event'], 'required'],
            [['document_id', 'user_id', 'created_at'], 'integer'],
            [['detail'], 'string'],
            [['event'], 'string', 'max' => 120],
            [['ip_address'], 'string', 'max' => 64],
        ];
    }
}
