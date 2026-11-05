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

    /**
     * แจ้งเตือนเมื่อครูหรือบุคลากรได้รับมอบหมายหนังสือเข้าใหม่ (Flex Message พร้อมปุ่มเปิดอ่าน)
     */
    public function notifyAssignedDocument(User $recipient, Document $document, User $actor, string $instruction = ''): bool
    {
        if ($recipient->line_user_id === null || $recipient->line_user_id === '') {
            Yii::warning('LINE notification skipped: recipient has no line_user_id. user_id=' . $recipient->id, __METHOD__);
            return false;
        }

        $altText = 'คุณได้รับมอบหมายงานใหม่: ' . $document->subject;
        $flex = $this->buildAssignedDocumentFlex($document, $actor, $instruction);

        $sent = $this->pushFlex($recipient->line_user_id, $altText, $flex);
        if (!$sent) {
            Yii::warning('pushFlex failed for assigned document, falling back to text. user_id=' . $recipient->id, __METHOD__);
            $lines = [
                'คุณได้รับมอบหมายงานใหม่',
                '',
                'เลขรับ: ' . $document->receive_no,
                'เรื่อง: ' . $document->subject,
                'จาก: ' . $document->sender,
                'ผู้มอบหมาย: ' . $actor->displayName(),
            ];
            if ($instruction !== '') {
                $lines[] = 'หมายเหตุ: ' . $instruction;
            }
            $lines[] = '';
            $lines[] = 'เปิดงาน: ' . Url::to(['/document/view', 'id' => $document->id], true);
            return $this->pushText($recipient->line_user_id, implode("\n", $lines));
        }

        return true;
    }

    /**
     * แจ้งเตือนกระบวนการหนังสือรับ (เสนอผู้ตรวจสอบ/อนุมัติ, เสนอรอง ผอ., ส่งกลับแก้ไข)
     */
    public function notifyDocumentWorkflow(User $recipient, Document $document, User $actor, string $title, string $message = ''): bool
    {
        if ($recipient->line_user_id === null || $recipient->line_user_id === '') {
            return false;
        }

        $lines = [
            $title,
            '',
            'เลขรับ: ' . $document->receive_no,
            'เรื่อง: ' . $document->subject,
            'จาก: ' . $document->sender,
            'ดำเนินการโดย: ' . $actor->displayName(),
        ];
        if ($message !== '') {
            $lines[] = 'ข้อความ: ' . $message;
        }
        $lines[] = '';
        $lines[] = 'เปิดดูเอกสาร: ' . Url::to(['/document/view', 'id' => $document->id], true);

        return $this->pushText($recipient->line_user_id, implode("\n", $lines));
    }

    /**
     * แจ้งเตือนเกี่ยวกับบันทึกข้อความ e-Memo (Flex Message พร้อมปุ่มเปิดดู)
     */
    public function notifyMemo(User $recipient, Memo $memo, User $actor, string $message): bool
    {
        if ($recipient->line_user_id === null || $recipient->line_user_id === '') {
            Yii::warning('LINE notification skipped: recipient has no line_user_id. user_id=' . $recipient->id, __METHOD__);
            return false;
        }

        $altText = $message . ': ' . $memo->subject;
        $flex = $this->buildMemoFlex($memo, $actor, $message);

        $sent = $this->pushFlex($recipient->line_user_id, $altText, $flex);
        if (!$sent) {
            Yii::warning('pushFlex failed for memo, falling back to text. user_id=' . $recipient->id, __METHOD__);
            $lines = [
                $message,
                '',
                'บันทึกเสนอ: ' . ($memo->memo_no ?: 'ยังไม่ระบุเลขที่'),
                'เรื่อง: ' . $memo->subject,
                'ผู้ดำเนินการ: ' . $actor->displayName(),
                '',
                'เปิดบันทึก: ' . Url::to(['/memo/view', 'id' => $memo->id], true),
            ];
            return $this->pushText($recipient->line_user_id, implode("\n", $lines));
        }

        return true;
    }

    /**
     * แจ้งเตือนผู้ดูแลระบบเมื่อมีคุณครูสมัครใช้งานใหม่
     */
    public function notifyAdminNewTeacherSignup(User $newTeacher, bool $isAutoApproved = false): void
    {
        $admins = User::find()
            ->where(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE])
            ->andWhere(['is not', 'line_user_id', null])
            ->all();

        $altText = $isAutoApproved
            ? '✨ มีสมาชิกใหม่ (อนุมัติอัตโนมัติแล้ว): ' . $newTeacher->displayName()
            : '🔔 มีคุณครูสมัครใช้งาน e-Office ใหม่: ' . $newTeacher->displayName();
        $flex = $this->buildAdminNewTeacherSignupFlex($newTeacher, $isAutoApproved);

        $deptName = $newTeacher->department?->name ?? 'ไม่ระบุ';
        $statusLabel = $isAutoApproved ? 'อนุมัติอัตโนมัติ (เข้าใช้งานได้ทันที)' : 'รอการอนุมัติ';
        $targetUrl = $isAutoApproved
            ? Url::to(['/user/index'], true)
            : Url::to(['/user/index', 'status' => User::STATUS_INACTIVE], true);

        $fallbackText = implode("\n", [
            $isAutoApproved ? '✨ มีสมาชิกใหม่ (อนุมัติอัตโนมัติ)' : '🔔 มีคุณครูสมัครใช้งาน e-Office ใหม่',
            '',
            'ชื่อ-นามสกุล: ' . $newTeacher->displayName(),
            'ชื่อผู้ใช้: ' . $newTeacher->username,
            'เบอร์โทร: ' . ($newTeacher->phone ?: '-'),
            'กลุ่มงาน: ' . $deptName,
            'สถานะ: ' . $statusLabel,
            '',
            'ดูรายละเอียดได้ที่: ' . $targetUrl,
        ]);

        foreach ($admins as $admin) {
            $sent = $this->pushFlex($admin->line_user_id, $altText, $flex);
            if (!$sent) {
                $this->pushText($admin->line_user_id, $fallbackText);
            }
        }
    }

    /**
     * แจ้งเตือนคุณครูเมื่อบัญชีผู้ใช้ได้รับการอนุมัติ
     */
    public function notifyTeacherApproved(User $teacher): bool
    {
        if ($teacher->line_user_id === null || $teacher->line_user_id === '') {
            return false;
        }

        $altText = '🎉 บัญชี e-Office ของคุณได้รับการอนุมัติแล้ว';
        $flex = $this->buildTeacherApprovedFlex($teacher);

        $sent = $this->pushFlex($teacher->line_user_id, $altText, $flex);
        if (!$sent) {
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

        return true;
    }

    /**
     * ส่ง Flex Message แบบ Push ไปยังผู้รับ
     */
    public function pushFlex(string $lineUserId, string $altText, array $flexContents): bool
    {
        return $this->send('/message/push', [
            'to' => $lineUserId,
            'messages' => [
                [
                    'type' => 'flex',
                    'altText' => mb_substr($altText, 0, 400, 'UTF-8'),
                    'contents' => $flexContents,
                ],
            ],
        ]);
    }

    /**
     * ตอบกลับ Flex Message แบบ Reply
     */
    public function replyFlex(string $replyToken, string $altText, array $flexContents): bool
    {
        return $this->send('/message/reply', [
            'replyToken' => $replyToken,
            'messages' => [
                [
                    'type' => 'flex',
                    'altText' => mb_substr($altText, 0, 400, 'UTF-8'),
                    'contents' => $flexContents,
                ],
            ],
        ]);
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

    /**
     * สร้าง Flex Bubble สำหรับงานหนังสือเข้าที่ได้รับมอบหมาย
     */
    public function buildAssignedDocumentFlex(Document $document, User $actor, string $instruction = ''): array
    {
        $urgency = $document->urgency;
        $urgencyTheme = match ($urgency) {
            Document::URGENCY_MOST_URGENT => [
                'headerBg' => '#991b1b', // dark red
                'badgeBg' => '#fee2e2',
                'badgeColor' => '#991b1b',
                'label' => 'ด่วนที่สุด',
            ],
            Document::URGENCY_VERY_URGENT => [
                'headerBg' => '#dc2626', // red
                'badgeBg' => '#fee2e2',
                'badgeColor' => '#dc2626',
                'label' => 'ด่วนมาก',
            ],
            Document::URGENCY_URGENT => [
                'headerBg' => '#d97706', // amber
                'badgeBg' => '#fef3c7',
                'badgeColor' => '#b45309',
                'label' => 'ด่วน',
            ],
            default => [
                'headerBg' => '#0d6efd', // e-office primary blue
                'badgeBg' => '#dbeafe',
                'badgeColor' => '#1d4ed8',
                'label' => 'ปกติ',
            ],
        };

        $viewUrl = $this->normalizeHttpsUrl(Url::to(['/document/view', 'id' => $document->id], true));
        $senderName = $actor->displayName() . ($actor->roleLabel() ? ' (' . $actor->roleLabel() . ')' : '');

        $bodyContents = [
            [
                'type' => 'text',
                'text' => 'คุณได้รับมอบหมายงานใหม่',
                'weight' => 'bold',
                'size' => 'md',
                'color' => '#1e293b',
            ],
            [
                'type' => 'text',
                'text' => $document->subject,
                'weight' => 'bold',
                'size' => 'sm',
                'wrap' => true,
                'color' => '#0f172a',
                'margin' => 'sm',
                'maxLines' => 4,
            ],
            [
                'type' => 'separator',
                'margin' => 'md',
                'color' => '#e2e8f0',
            ],
            [
                'type' => 'box',
                'layout' => 'vertical',
                'margin' => 'md',
                'spacing' => 'sm',
                'contents' => [
                    $this->createKeyValueRow('เลขรับ', $document->receive_no ?: '-', true),
                    $this->createKeyValueRow('จาก', $document->sender ?: '-'),
                    $this->createKeyValueRow('ผู้มอบหมาย', $senderName),
                ],
            ],
        ];

        if (!empty($document->due_date)) {
            $bodyContents[3]['contents'][] = $this->createKeyValueRow('ครบกำหนด', $this->formatThaiDate($document->due_date));
        }

        if (trim($instruction) !== '') {
            $bodyContents[] = [
                'type' => 'box',
                'layout' => 'vertical',
                'margin' => 'md',
                'paddingAll' => '10px',
                'backgroundColor' => '#f0fdf4',
                'cornerRadius' => 'md',
                'borderColor' => '#bbf7d0',
                'borderWidth' => '1px',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '📌 คำสั่งการ / มอบหมาย:',
                        'size' => 'xs',
                        'color' => '#166534',
                        'weight' => 'bold',
                    ],
                    [
                        'type' => 'text',
                        'text' => $instruction,
                        'size' => 'xs',
                        'color' => '#14532d',
                        'wrap' => true,
                        'margin' => 'xs',
                    ],
                ],
            ];
        }

        return [
            'type' => 'bubble',
            'size' => 'mega',
            'header' => [
                'type' => 'box',
                'layout' => 'horizontal',
                'alignItems' => 'center',
                'paddingAll' => '12px',
                'backgroundColor' => $urgencyTheme['headerBg'],
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => 'e-Office • หนังสือเข้า',
                        'weight' => 'bold',
                        'color' => '#ffffff',
                        'size' => 'sm',
                        'flex' => 3,
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'backgroundColor' => $urgencyTheme['badgeBg'],
                        'cornerRadius' => 'md',
                        'paddingStart' => '8px',
                        'paddingEnd' => '8px',
                        'paddingTop' => '2px',
                        'paddingBottom' => '2px',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => $urgencyTheme['label'],
                                'color' => $urgencyTheme['badgeColor'],
                                'size' => 'xxs',
                                'weight' => 'bold',
                                'align' => 'center',
                            ],
                        ],
                    ],
                ],
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'paddingAll' => '16px',
                'contents' => $bodyContents,
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'paddingAll' => '12px',
                'paddingTop' => '0px',
                'contents' => [
                    [
                        'type' => 'button',
                        'style' => 'primary',
                        'color' => '#0d6efd',
                        'height' => 'sm',
                        'action' => [
                            'type' => 'uri',
                            'label' => '📄 เปิดอ่านหนังสือ',
                            'uri' => $viewUrl,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * สร้าง Flex Bubble สำหรับบันทึกข้อความ e-Memo
     */
    public function buildMemoFlex(Memo $memo, User $actor, string $message): array
    {
        $viewUrl = $this->normalizeHttpsUrl(Url::to(['/memo/view', 'id' => $memo->id], true));
        $statusLabel = Memo::statusOptions()[$memo->status] ?? $memo->status;
        $actorName = $actor->displayName() . ($actor->roleLabel() ? ' (' . $actor->roleLabel() . ')' : '');

        $bodyContents = [
            [
                'type' => 'text',
                'text' => $message,
                'weight' => 'bold',
                'size' => 'md',
                'color' => '#1e293b',
                'wrap' => true,
            ],
            [
                'type' => 'text',
                'text' => $memo->subject,
                'weight' => 'bold',
                'size' => 'sm',
                'wrap' => true,
                'color' => '#0f172a',
                'margin' => 'sm',
                'maxLines' => 4,
            ],
            [
                'type' => 'separator',
                'margin' => 'md',
                'color' => '#e2e8f0',
            ],
            [
                'type' => 'box',
                'layout' => 'vertical',
                'margin' => 'md',
                'spacing' => 'sm',
                'contents' => [
                    $this->createKeyValueRow('เลขที่บันทึก', $memo->memo_no ?: 'ฉบับร่าง', true),
                    $this->createKeyValueRow('ผู้ดำเนินการ', $actorName),
                    $this->createKeyValueRow('กลุ่มงาน', $memo->department?->name ?? 'ทั่วไป'),
                ],
            ],
        ];

        return [
            'type' => 'bubble',
            'size' => 'mega',
            'header' => [
                'type' => 'box',
                'layout' => 'horizontal',
                'alignItems' => 'center',
                'paddingAll' => '12px',
                'backgroundColor' => '#0f766e', // Teal
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => 'e-Office • บันทึกข้อความ',
                        'weight' => 'bold',
                        'color' => '#ffffff',
                        'size' => 'sm',
                        'flex' => 3,
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'backgroundColor' => '#ccfbf1',
                        'cornerRadius' => 'md',
                        'paddingStart' => '8px',
                        'paddingEnd' => '8px',
                        'paddingTop' => '2px',
                        'paddingBottom' => '2px',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => $statusLabel,
                                'color' => '#0f766e',
                                'size' => 'xxs',
                                'weight' => 'bold',
                                'align' => 'center',
                            ],
                        ],
                    ],
                ],
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'paddingAll' => '16px',
                'contents' => $bodyContents,
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'paddingAll' => '12px',
                'paddingTop' => '0px',
                'contents' => [
                    [
                        'type' => 'button',
                        'style' => 'primary',
                        'color' => '#0f766e',
                        'height' => 'sm',
                        'action' => [
                            'type' => 'uri',
                            'label' => '📝 เปิดดูบันทึกข้อความ',
                            'uri' => $viewUrl,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * สร้าง Flex Bubble สำหรับแจ้งเตือนการอนุมัติบัญชีผู้ใช้ครู
     */
    public function buildTeacherApprovedFlex(User $teacher): array
    {
        $loginUrl = $this->normalizeHttpsUrl(Url::to(['/site/login'], true));
        $deptName = $teacher->department?->name ?? 'ทั่วไป';

        return [
            'type' => 'bubble',
            'size' => 'mega',
            'header' => [
                'type' => 'box',
                'layout' => 'horizontal',
                'alignItems' => 'center',
                'paddingAll' => '12px',
                'backgroundColor' => '#16a34a', // Green
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '🎉 e-Office • อนุมัติบัญชีแล้ว',
                        'weight' => 'bold',
                        'color' => '#ffffff',
                        'size' => 'sm',
                    ],
                ],
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'paddingAll' => '16px',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => 'ยินดีต้อนรับคุณครู ' . $teacher->displayName(),
                        'weight' => 'bold',
                        'size' => 'md',
                        'color' => '#1e293b',
                        'wrap' => true,
                    ],
                    [
                        'type' => 'text',
                        'text' => 'บัญชี e-Office ของท่านได้รับการอนุมัติเรียบร้อยแล้ว สามารถเข้าสู่ระบบเพื่อรับงานและสร้างบันทึกข้อความได้ทันที',
                        'size' => 'xs',
                        'color' => '#64748b',
                        'wrap' => true,
                        'margin' => 'sm',
                    ],
                    [
                        'type' => 'separator',
                        'margin' => 'md',
                        'color' => '#e2e8f0',
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'margin' => 'md',
                        'spacing' => 'sm',
                        'contents' => [
                            $this->createKeyValueRow('ชื่อผู้ใช้', $teacher->username, true),
                            $this->createKeyValueRow('กลุ่มงาน', $deptName),
                            $this->createKeyValueRow('บทบาท', $teacher->roleLabel()),
                        ],
                    ],
                ],
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'paddingAll' => '12px',
                'paddingTop' => '0px',
                'contents' => [
                    [
                        'type' => 'button',
                        'style' => 'primary',
                        'color' => '#16a34a',
                        'height' => 'sm',
                        'action' => [
                            'type' => 'uri',
                            'label' => '🔑 เข้าสู่ระบบ e-Office',
                            'uri' => $loginUrl,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * สร้าง Flex Bubble สำหรับแจ้งเตือนแอดมินเมื่อมีครูสมัครใช้งานใหม่
     */
    public function buildAdminNewTeacherSignupFlex(User $newTeacher, bool $isAutoApproved = false): array
    {
        $reviewUrl = $isAutoApproved
            ? $this->normalizeHttpsUrl(Url::to(['/user/index'], true))
            : $this->normalizeHttpsUrl(Url::to(['/user/index', 'status' => User::STATUS_INACTIVE], true));
        $deptName = $newTeacher->department?->name ?? 'ไม่ระบุ';

        $headerTitle = $isAutoApproved ? '✨ สมาชิกใหม่ (อนุมัติอัตโนมัติ)' : '🔔 สมาชิกใหม่รอการอนุมัติ';
        $badgeText = $isAutoApproved ? 'อนุมัติแล้ว' : 'รออนุมัติ';
        $badgeColor = $isAutoApproved ? '#15803d' : '#b45309';
        $badgeBg = $isAutoApproved ? '#dcfce7' : '#fef3c7';
        $buttonLabel = $isAutoApproved ? '👥 ดูรายชื่อผู้ใช้งาน' : '🔍 ตรวจสอบและอนุมัติ';

        return [
            'type' => 'bubble',
            'size' => 'mega',
            'header' => [
                'type' => 'box',
                'layout' => 'horizontal',
                'alignItems' => 'center',
                'paddingAll' => '12px',
                'backgroundColor' => '#2563eb', // Blue
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => $headerTitle,
                        'weight' => 'bold',
                        'color' => '#ffffff',
                        'size' => 'sm',
                        'flex' => 3,
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'backgroundColor' => $badgeBg,
                        'cornerRadius' => 'md',
                        'paddingStart' => '8px',
                        'paddingEnd' => '8px',
                        'paddingTop' => '2px',
                        'paddingBottom' => '2px',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => $badgeText,
                                'color' => $badgeColor,
                                'size' => 'xxs',
                                'weight' => 'bold',
                                'align' => 'center',
                            ],
                        ],
                    ],
                ],
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'paddingAll' => '16px',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => $isAutoApproved ? 'มีคุณครูลงทะเบียนใหม่และได้รับอนุมัติทันที' : 'มีคุณครูสมัครใช้งาน e-Office ใหม่',
                        'weight' => 'bold',
                        'size' => 'md',
                        'color' => '#1e293b',
                    ],
                    [
                        'type' => 'separator',
                        'margin' => 'md',
                        'color' => '#e2e8f0',
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'margin' => 'md',
                        'spacing' => 'sm',
                        'contents' => [
                            $this->createKeyValueRow('ชื่อ-นามสกุล', $newTeacher->displayName(), true),
                            $this->createKeyValueRow('ชื่อผู้ใช้', $newTeacher->username),
                            $this->createKeyValueRow('เบอร์โทร', $newTeacher->phone ?: '-'),
                            $this->createKeyValueRow('กลุ่มงาน', $deptName),
                        ],
                    ],
                ],
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'paddingAll' => '12px',
                'paddingTop' => '0px',
                'contents' => [
                    [
                        'type' => 'button',
                        'style' => 'primary',
                        'color' => '#2563eb',
                        'height' => 'sm',
                        'action' => [
                            'type' => 'uri',
                            'label' => $buttonLabel,
                            'uri' => $reviewUrl,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function createKeyValueRow(string $label, string $value, bool $bold = false): array
    {
        return [
            'type' => 'box',
            'layout' => 'baseline',
            'spacing' => 'sm',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => $label,
                    'color' => '#64748b',
                    'size' => 'xs',
                    'flex' => 3,
                ],
                [
                    'type' => 'text',
                    'text' => $value,
                    'wrap' => true,
                    'color' => '#1e293b',
                    'size' => 'xs',
                    'flex' => 6,
                    'weight' => $bold ? 'bold' : 'regular',
                ],
            ],
        ];
    }

    private function formatThaiDate(?string $date): string
    {
        if (!$date) {
            return '-';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }
        $months = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
            5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
            9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.',
        ];
        return (int) date('j', $ts) . ' ' . ($months[(int) date('n', $ts)] ?? '') . ' ' . ((int) date('Y', $ts) + 543);
    }

    private function normalizeHttpsUrl(string $url): string
    {
        if (str_starts_with($url, 'http://')) {
            return 'https://' . substr($url, 7);
        }
        return $url;
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
