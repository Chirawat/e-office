<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$items = [
    'PHP Version' => PHP_VERSION,
    'Server API (SAPI)' => PHP_SAPI,
    'Loaded php.ini' => php_ini_loaded_file() ?: '(none)',
    'Additional .ini files' => php_ini_scanned_files() ?: '(none)',
    'user_ini.filename' => (string) ini_get('user_ini.filename'),
    'user_ini.cache_ttl (sec)' => (string) ini_get('user_ini.cache_ttl'),
    'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
    'post_max_size' => (string) ini_get('post_max_size'),
    'memory_limit' => (string) ini_get('memory_limit'),
    'max_execution_time' => (string) ini_get('max_execution_time'),
    'max_file_uploads' => (string) ini_get('max_file_uploads'),
    '.user.ini exists here' => is_file(__DIR__ . '/.user.ini') ? 'YES (' . filesize(__DIR__ . '/.user.ini') . ' bytes)' : 'NO',
    '.htaccess exists here' => is_file(__DIR__ . '/.htaccess') ? 'YES (' . filesize(__DIR__ . '/.htaccess') . ' bytes)' : 'NO',
];

$uploadVal = (string) ini_get('upload_max_filesize');
$isOk = (int) $uploadVal >= 10 || str_contains(strtoupper($uploadVal), 'G');
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>ตรวจสอบค่า PHP Upload Limit</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; max-width: 720px; margin: 32px auto; padding: 0 16px; color: #1e293b; }
        h1 { font-size: 1.25rem; margin-bottom: 12px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; margin-bottom: 16px; }
        .ok { background: #dcfce7; color: #166534; }
        .warn { background: #fef2f2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; width: 38%; color: #475569; }
        td { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; word-break: break-all; }
        .note { margin-top: 16px; font-size: 0.82rem; color: #64748b; }
    </style>
</head>
<body>
    <h1>ตรวจสอบค่า PHP Upload Limit</h1>
    <div class="badge <?= $isOk ? 'ok' : 'warn' ?>">
        <?= $isOk ? 'OK: upload_max_filesize = ' . htmlspecialchars($uploadVal) : 'ยังไม่เปลี่ยน: upload_max_filesize = ' . htmlspecialchars($uploadVal) ?>
    </div>
    <table>
        <?php foreach ($items as $label => $value): ?>
            <tr>
                <th><?= htmlspecialchars($label) ?></th>
                <td><?= htmlspecialchars($value) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <p class="note">หมายเหตุ: เมื่อตรวจสอบและแก้ไขเสร็จแล้ว แนะนำให้ลบไฟล์ <code>check-upload.php</code> ออกจากเซิร์ฟเวอร์ครับ</p>
</body>
</html>
