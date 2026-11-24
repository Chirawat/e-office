<?php

declare(strict_types=1);

namespace common\components;

use setasign\Fpdi\Tcpdf\Fpdi;
use Yii;

class PdfNormalizer
{
    /**
     * Loads $sourcePath into FPDI. If FPDI's free parser rejects PDF 1.5+ object/xref streams,
     * attempts server-side conversion to PDF 1.4 via Ghostscript (gs) or qpdf when available.
     */
    public static function setSourceFileWithFallback(Fpdi $pdf, string $sourcePath): int
    {
        try {
            return (int) $pdf->setSourceFile($sourcePath);
        } catch (\Throwable $e) {
            if (!self::isCompressionError($e)) {
                throw $e;
            }

            $normalizedPath = self::normalizeToPdf14($sourcePath);
            if ($normalizedPath !== null && is_file($normalizedPath)) {
                return (int) $pdf->setSourceFile($normalizedPath);
            }

            throw new \RuntimeException(
                'ไฟล์ PDF นี้ใช้การบีบอัดเวอร์ชันใหม่ (PDF 1.5+ Object Streams) ที่ระบบประทับตราไม่รองรับ '
                . 'กรุณาลบหนังสือและอัปโหลดไฟล์ PDF ใหม่อีกครั้งผ่านหน้าลงทะเบียน (ระบบจะปรับรูปแบบไฟล์ให้อัตโนมัติ)',
                0,
                $e
            );
        }
    }

    private static function isCompressionError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'compression technique')
            || str_contains($msg, 'cross-reference')
            || str_contains($msg, 'fpdi-pdf-parser');
    }

    private static function normalizeToPdf14(string $sourcePath): ?string
    {
        $targetPath = dirname($sourcePath) . '/' . pathinfo($sourcePath, PATHINFO_FILENAME) . '-pdf14.pdf';
        if (is_file($targetPath) && filesize($targetPath) > 0 && filemtime($targetPath) >= filemtime($sourcePath)) {
            return $targetPath;
        }

        if (!function_exists('exec')) {
            return null;
        }

        // 1. Try Ghostscript (gs)
        $gs = self::findExecutable(['/usr/bin/gs', '/usr/local/bin/gs', '/opt/homebrew/bin/gs', 'gs']);
        if ($gs !== null) {
            $cmd = implode(' ', [
                escapeshellarg($gs),
                '-sDEVICE=pdfwrite',
                '-dCompatibilityLevel=1.4',
                '-dNOPAUSE',
                '-dQUIET',
                '-dBATCH',
                '-sOutputFile=' . escapeshellarg($targetPath),
                escapeshellarg($sourcePath),
            ]);
            @exec($cmd . ' 2>&1', $out, $code);
            if (($code ?? 1) === 0 && is_file($targetPath) && filesize($targetPath) > 0) {
                return $targetPath;
            }
            @unlink($targetPath);
        }

        // 2. Try qpdf
        $qpdf = self::findExecutable(['/usr/bin/qpdf', '/usr/local/bin/qpdf', '/opt/homebrew/bin/qpdf', 'qpdf']);
        if ($qpdf !== null) {
            $cmd = implode(' ', [
                escapeshellarg($qpdf),
                '--object-streams=disable',
                escapeshellarg($sourcePath),
                escapeshellarg($targetPath),
            ]);
            @exec($cmd . ' 2>&1', $out, $code);
            if (($code ?? 1) === 0 && is_file($targetPath) && filesize($targetPath) > 0) {
                return $targetPath;
            }
            @unlink($targetPath);
        }

        Yii::warning('Unable to normalize compressed PDF on server (neither gs nor qpdf available): ' . $sourcePath, __METHOD__);
        return null;
    }

    /**
     * @param string[] $candidates
     */
    private static function findExecutable(array $candidates): ?string
    {
        $openBasedir = (string) ini_get('open_basedir');
        foreach ($candidates as $bin) {
            if (!str_contains($bin, DIRECTORY_SEPARATOR)) {
                $out = [];
                @exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null', $out, $code);
                if (($code ?? 1) === 0 && !empty($out[0])) {
                    return trim($out[0]);
                }
                continue;
            }

            if ($openBasedir !== '' && !self::isAllowedByOpenBasedir($bin, $openBasedir)) {
                continue;
            }
            if (@is_executable($bin)) {
                return $bin;
            }
        }
        return null;
    }

    private static function isAllowedByOpenBasedir(string $path, string $openBasedir): bool
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        foreach (explode(PATH_SEPARATOR, $openBasedir) as $allowed) {
            $allowed = rtrim($allowed, DIRECTORY_SEPARATOR);
            if ($allowed !== '' && ($path === $allowed || str_starts_with($path, $allowed . DIRECTORY_SEPARATOR))) {
                return true;
            }
        }
        return false;
    }
}
