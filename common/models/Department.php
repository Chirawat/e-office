<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property bool $is_active
 * @property int $sort_order
 */
class Department extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%department}}';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['is_active'], 'boolean'],
            [['sort_order'], 'integer'],
            [['name'], 'string', 'max' => 120],
            [['code'], 'string', 'max' => 40],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'name' => 'กลุ่มงาน',
            'code' => 'รหัส',
            'is_active' => 'เปิดใช้งาน',
            'sort_order' => 'ลำดับ',
        ];
    }

    public function getDocuments(): ActiveQuery
    {
        return $this->hasMany(Document::class, ['department_id' => 'id']);
    }
}
