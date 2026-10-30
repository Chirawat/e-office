<?php

declare(strict_types=1);

namespace common\components;

use common\models\Document;
use common\models\Memo;
use common\models\User;
use Yii;
use yii\helpers\Url;

class LineMessagingService
{
    private string $apiBase = 'https://api.line.me/v2/bot';

    public function notifyAssignedDocument(User $recipient, Document $document, User $actor, string $instruction = ''): bool
    {
        if ($recipient->line_user_id === null || $recipient->line_user_id === '') {
            Yii::warning('LINE notification skipped: recipient has no line_user_id. user_id=' . $recipient->id, __METHOD__);
            return false;
        }

        $lines = [
            'คุณได้รับมอบหมายงานใหม่',
            '',
            'เลขรับ: ' . $document->receive_no,
            'เรื่อง: ' . $document->subject,
            'จาก: ' . $document->sender,
            'ผู้มอบหมาย: ' . $actor->username,
        ];

        if ($instruction !== '') {
            $lines[] = 'หมายเหตุ: ' . $instruction;
        }

        $lines[] = '';
        $lines[] = 'เปิดงาน: ' . Url::to(['/document/view', 'id' => $document->id], true);

        return $this->pushText($recipient->line_user_id, implode("\n", $lines));
    }

    public function notifyMemo(User $recipient, Memo $memo, User $actor, string $message): bool
    {
        if ($recipient->line_user_id === null || $recipient->line_user_id === '') {
            Yii::warning('LINE notification skipped: recipient has no line_user_id. user_id=' . $recipient->id, __METHOD__);
            return false;
        }

        $lines = [
            $message,
            '',
            'บันทึกเสนอ: ' . ($memo->memo_no ?: 'ยังไม่ระบุเลขที่'),
            'เรื่อง: ' . $memo->subject,
            'ผู้ดำเนินการ: ' . $actor->username,
            '',
            'เปิดบันทึก: ' . Url::to(['/memo/view', 'id' => $memo->id], true),
        ];

        return $this->pushText($recipient->line_user_id, implode("\n", $lines));
    }

    public function notifyAdminNewTeacherSignup(User $newTeacher): void
    {
        // ค้นหาผู้ดูแลระบบที่มี line_user_id
        $admins = User::find()
            ->where(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE])
            ->andWhere(['is not', 'line_user_id', null])
            ->all();

        $deptName = $newTeacher->department?->name ?? 'ไม่ระบุ';
        $lines = [
            '🔔 มีคุณครูสมัครใช้งาน e-Office ใหม่',
            '',
            'ชื่อ-นามสกุล: ' . $newTeacher->displayName(),
            'ชื่อผู้ใช้: ' . $newTeacher->username,
            'เบอร์โทร: ' . ($newTeacher->phone ?: '-'),
            'กลุ่มงาน: ' . $deptName,
            'สถานะ: รอการอนุมัติ',
            '',
            'ตรวจสอบและอนุมัติได้ที่: ' . Url::to(['/user/index', 'status' => User::STATUS_INACTIVE], true),
        ];

        $message = implode("\n", $lines);
        foreach ($admins as $admin) {
            $this->pushText($admin->line_user_id, $message);
        }
    }

    public function notifyTeacherApproved(User $teacher): bool
    {
        if ($teacher->line_user_id === null || $teacher->line_user_id === '') {
            return false;
        }

        $lines = [
            '🎉 บัญชี e-Office ของคุณได้รับการอนุมัติแล้ว',
            '',
            'ยินดีต้อนรับคุณครู ' . $teacher->displayName(),
            'กลุ่มงาน: ' . ($teacher->department?->name ?? 'ทั่วไป'),
            'บทบาท: ' . $teacher->roleLabel(),
            '',
            'ท่านสามารถเข้าสู่ระบบ e-Office ได้ทันทีที่: ' . Url::to(['/site/login'], true),
        ];

        return $this->pushText($teacher->line_user_id, implode("\n", $lines));
    }

    public function pushText(string $lineUserId, string $text): bool
    {
        return $this->send('/message/push', [
            'to' => $lineUserId,
            'messages' => [
                [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
        ]);
    }

    public function replyText(string $replyToken, string $text): bool
    {
        return $this->send('/message/reply', [
            'replyToken' => $replyToken,
            'messages' => [
                [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
        ]);
    }

    public function validateSignature(string $body, ?string $signature): bool
    {
        $secret = (string) Yii::$app->params['line.channelSecret'];
        if ($secret === '') {
            return true;
        }
        if ($signature === null || $signature === '') {
            return false;
        }

        return hash_equals(base64_encode(hash_hmac('sha256', $body, $secret, true)), $signature);
    }

    private function send(string $path, array $payload): bool
    {
        $token = (string) Yii::$app->params['line.channelAccessToken'];
        if ($token === '') {
            Yii::warning('LINE notification skipped: line.channelAccessToken is empty.', __METHOD__);
            return false;
        }

        $ch = curl_init($this->apiBase . $path);
        if ($ch === false) {
            Yii::warning('Unable to initialize cURL for LINE Messaging API.', __METHOD__);
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $statusCode < 200 || $statusCode >= 300) {
            Yii::warning('LINE API request failed. status=' . $statusCode . ' error=' . $error . ' response=' . (string) $response, __METHOD__);
            return false;
        }

        return true;
    }
}
