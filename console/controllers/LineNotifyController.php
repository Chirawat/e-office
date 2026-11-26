<?php

declare(strict_types=1);

namespace console\controllers;

use common\components\LineMessagingService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * ส่งข้อความสรุปรวมแจ้งเตือน LINE (Digest) จากคิวตามรอบเวลา (11:00 น. และ 15:00 น.)
 * ตัวอย่าง Cron Job: 0 11,15 * * 1-5 /usr/local/php83/bin/php /path/to/yii line-notify/flush
 */
class LineNotifyController extends Controller
{
    public function actionFlush(int $force = 0): int
    {
        $service = new LineMessagingService();
        $result = $service->flushDigestQueue($force === 1);

        $this->stdout(sprintf(
            "[%s] LINE Digest Flush completed: sent_users=%d, sent_items=%d, skipped_items=%d, deferred_users=%d\n",
            date('Y-m-d H:i:s'),
            $result['sent_users'],
            $result['sent_items'],
            $result['skipped_items'],
            $result['deferred_users']
        ));

        return ExitCode::OK;
    }
}
