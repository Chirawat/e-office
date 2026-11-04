<?php

declare(strict_types=1);

namespace common\components;

use common\models\Memo;
use common\models\MemoAction;
use common\models\MemoFile;
use common\models\MemoStampPosition;
use common\models\User;
use setasign\Fpdi\Tcpdf\Fpdi;
use Yii;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\helpers\FileHelper;

class MemoPdfService extends Component
{
    private const SHOW_SIGNATURE_DEBUG_FRAMES = false;

    public const SIGNATURE_WIDTH = 50.0;
    public const SIGNATURE_HEIGHT = 22.7;
    private const SIGNATURE_IMAGE_WIDTH = 36.0;
    private const SIGNATURE_IMAGE_HEIGHT = 14.5;

    public const SIGNATURE_POSITIONS = [
        MemoStampPosition::TYPE_CREATOR => ['x' => 128.0, 'y' => 184.0, 'w' => 68.0, 'h' => 33.0, 'label' => 'เจ้าของเรื่อง'],
        MemoStampPosition::TYPE_STAFF_REVIEW => ['x' => 128.0, 'y' => 207.0, 'w' => 68.0, 'h' => 33.0, 'label' => 'หัวหน้ากลุ่มงาน'],
        MemoStampPosition::TYPE_DEPUTY_REVIEW => ['x' => 128.0, 'y' => 230.0, 'w' => 68.0, 'h' => 33.0, 'label' => 'รองผู้อำนวยการ'],
        MemoStampPosition::TYPE_DIRECTOR_APPROVE => ['x' => 128.0, 'y' => 253.0, 'w' => 68.0, 'h' => 33.0, 'label' => 'ผู้อำนวยการ'],
    ];

    public function generate(Memo $memo): string
    {
        $memo = Memo::find()
            ->with(['department', 'creator', 'originalPdfFile', 'files', 'actions.actor', 'stampPositions'])
            ->where(['id' => $memo->id])
            ->one() ?? $memo;

        $originalFile = $this->originalPdfFile($memo);
        if ($originalFile === null) {
            throw new InvalidArgumentException('ไม่พบ PDF บันทึกข้อความต้นฉบับ');
        }

        $sourcePath = Yii::getAlias('@backend/web/' . ltrim($originalFile->storage_path, '/'));
        if (!is_file($sourcePath)) {
            throw new InvalidArgumentException('ไม่พบ PDF บันทึกข้อความต้นฉบับในระบบไฟล์');
        }

        $year = date('Y', strtotime($memo->memo_date));
        $outputDir = Yii::getAlias('@backend/web/uploads/memos/' . $year . '/' . $memo->id);
        FileHelper::createDirectory($outputDir);

        $outputPath = $outputDir . '/memo.pdf';
        $relativePath = 'uploads/memos/' . $year . '/' . $memo->id . '/memo.pdf';

        $pdf = new Fpdi('P', 'mm');
        $pdf->SetCreator('eoffice');
        $pdf->SetAuthor('eoffice');
        $pdf->SetTitle('บันทึกข้อความ - ' . $memo->subject);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetFont('freeserif', '', 12);

        $pageCount = $pdf->setSourceFile($sourcePath);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height'], true);

            if ($pageNo === 1) {
                $this->drawFirstPageSignatures($pdf, $memo, (float) $size['width'], (float) $size['height']);
            }
        }

        $pdf->Output($outputPath, 'F');

        return $relativePath;
    }

    public function generatePreviewImage(Memo $memo, bool $preferStamped = true): ?string
    {
        $memo = Memo::find()
            ->with(['originalPdfFile', 'files'])
            ->where(['id' => $memo->id])
            ->one() ?? $memo;

        $year = date('Y', strtotime($memo->memo_date));
        $stampedPath = Yii::getAlias('@backend/web/uploads/memos/' . $year . '/' . $memo->id . '/memo.pdf');
        $sourcePath = null;
        if ($preferStamped && is_file($stampedPath)) {
            $sourcePath = $stampedPath;
        } else {
            $originalFile = $this->originalPdfFile($memo);
            if ($originalFile !== null) {
                $candidate = Yii::getAlias('@backend/web/' . ltrim($originalFile->storage_path, '/'));
                $sourcePath = is_file($candidate) ? $candidate : null;
            }
        }

        if ($sourcePath === null) {
            return null;
        }

        $outputPath = dirname($sourcePath) . '/memo-preview-page-1.png';
        if (is_file($outputPath) && filemtime($outputPath) >= filemtime($sourcePath)) {
            return $this->webRelativePath($outputPath);
        }

        $binary = $this->pdftoppmBinary();
        if ($binary === null) {
            return null;
        }

        $prefix = dirname($sourcePath) . '/memo-preview-page-1';
        $command = implode(' ', [
            escapeshellarg($binary),
            '-png',
            '-f',
            '1',
            '-singlefile',
            '-r',
            '120',
            escapeshellarg($sourcePath),
            escapeshellarg($prefix),
        ]);

        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($outputPath)) {
            Yii::warning('Unable to generate memo PDF preview image: ' . implode("\n", $output), __METHOD__);
            return null;
        }

        return $this->webRelativePath($outputPath);
    }

    private function drawFirstPageSignatures(Fpdi $pdf, Memo $memo, float $pageWidth, float $pageHeight): void
    {
        // วาดลายเซ็นเจ้าของเรื่องเมื่อเอกสารถูกกดส่งแล้ว (ไม่ใช่ฉบับร่าง)
        if ($memo->status !== Memo::STATUS_DRAFT) {
            $this->drawSignature($pdf, $memo->creator, $memo->created_at, $this->signaturePosition($memo, MemoStampPosition::TYPE_CREATOR), $pageWidth, $pageHeight, true);
        }

        foreach ([MemoStampPosition::TYPE_STAFF_REVIEW, MemoStampPosition::TYPE_DEPUTY_REVIEW, MemoStampPosition::TYPE_DIRECTOR_APPROVE] as $actionName) {
            $action = $this->latestAction($memo, [$actionName]);
            if ($action === null) {
                continue;
            }

            $this->drawApprovalNote($pdf, $action, $this->signaturePosition($memo, $actionName), $pageWidth, $pageHeight);
        }
    }

    /**
     * @param array{x: float, y: float, w: float, h: float, signature_w?: float, label: string} $position
     */
    private function drawSignature(Fpdi $pdf, ?User $user, int|string|null $createdAt, array $position, float $pageWidth, float $pageHeight, bool $compact = false): void
    {
        $frameWidth = (float) ($position['w'] ?? self::SIGNATURE_WIDTH);
        $frameHeight = (float) ($position['h'] ?? self::SIGNATURE_HEIGHT);
        $x = min(max((float) $position['x'], 0), max(0, $pageWidth - $frameWidth));
        $y = min(max((float) $position['y'], 0), max(0, $pageHeight - 32));
        $signatureWidth = self::SIGNATURE_IMAGE_WIDTH;
        $signatureHeight = self::SIGNATURE_IMAGE_HEIGHT;
        $signatureX = $x + max(0, ($frameWidth - $signatureWidth) / 2);
        $signaturePath = $this->signaturePath($user);

        if (self::SHOW_SIGNATURE_DEBUG_FRAMES) {
            $this->drawDebugFrame($pdf, $x, $y, $frameWidth, $frameHeight, $x, $y + $signatureHeight, $frameWidth, $position['label']);
        }

        if ($signaturePath !== null) {
            [$imageX, $imageY, $imageWidth, $imageHeight] = $this->containedImageBox($signaturePath, $signatureX, $y, $signatureWidth, $signatureHeight);
            $pdf->Image($signaturePath, $imageX, $imageY, $imageWidth, $imageHeight, '', '', '', false, 300);
            $currentY = $y + $signatureHeight + 0.5;
        } else {
            $currentY = $y + 4.0;
        }

        // วาด ชื่อ \n ตำแหน่ง \n วันที่
        $pdf->SetTextColor(37, 60, 155);

        // 1. ชื่อ
        $rawName = $user ? $user->displayName() : '';
        $nameText = $rawName !== '' ? '(' . trim($rawName, "() \t\n\r\0\x0B") . ')' : '';
        if ($nameText !== '') {
            $pdf->SetFont('freeserif', 'B', 9.5);
            $pdf->SetXY($x, $currentY);
            $pdf->Cell($frameWidth, 4.3, $nameText, 0, 2, 'C');
            $currentY += 4.3;
        }

        // 2. ตำแหน่ง
        $posText = $user ? $user->officialPosition('creator') : '';
        if ($posText !== '') {
            $pdf->SetFont('freeserif', '', 8.5);
            $pdf->SetXY($x, $currentY);
            $pdf->Cell($frameWidth, 4.0, $posText, 0, 2, 'C');
            $currentY += 4.0;
        }

        // 3. วันที่
        $dateText = $this->buddhistDate($createdAt);
        $pdf->SetFont('freeserif', '', 8.5);
        $pdf->SetXY($x, $currentY);
        $pdf->Cell($frameWidth, 4.0, $dateText, 0, 2, 'C');
    }

    /**
     * @param array{x: float, y: float, w: float, h: float, label: string} $position
     */
    private function drawApprovalNote(Fpdi $pdf, MemoAction $action, array $position, float $pageWidth, float $pageHeight): void
    {
        $x = min(max((float) $position['x'], 0), max(0, $pageWidth - 72));
        $y = min(max((float) $position['y'], 0), max(0, $pageHeight - 48));
        $w = (float) ($position['w'] ?? 68.0);
        $h = (float) ($position['h'] ?? 38.0);
        $signatureWidth = self::SIGNATURE_IMAGE_WIDTH;
        $signatureHeight = self::SIGNATURE_IMAGE_HEIGHT;
        $signatureX = $x + max(0, ($w - $signatureWidth) / 2);
        $signatureY = $y + 7.5;
        $signaturePath = $this->signaturePath($action->actor);

        if (self::SHOW_SIGNATURE_DEBUG_FRAMES) {
            $this->drawDebugFrame($pdf, $x, $y, $w, $h, $x, $signatureY + $signatureHeight, $w, $position['label']);
        }

        $pdf->SetTextColor(37, 60, 155);
        $pdf->SetFont('freeserif', '', 10.0);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($w, 4.8, $action->comment ?: '-', 0, 'L', false, 1, $x, $y);

        $signatureY = max($signatureY, $pdf->GetY() + 0.5);

        if ($signaturePath !== null) {
            [$imageX, $imageY, $imageWidth, $imageHeight] = $this->containedImageBox($signaturePath, $signatureX, $signatureY, $signatureWidth, $signatureHeight);
            $pdf->Image($signaturePath, $imageX, $imageY, $imageWidth, $imageHeight, '', '', '', false, 300);
            $currentY = $signatureY + $signatureHeight + 0.5;
        } else {
            $currentY = $pdf->GetY() + 2.0;
        }

        // กำหนด context ตำแหน่งตามประเภท action
        $context = 'staff';
        if ($action->action === MemoStampPosition::TYPE_DEPUTY_REVIEW || str_contains($action->action, 'deputy')) {
            $context = 'deputy';
        } elseif ($action->action === MemoStampPosition::TYPE_DIRECTOR_APPROVE || str_contains($action->action, 'director')) {
            $context = 'director';
        }

        // วาด ชื่อ \n ตำแหน่ง \n วันที่
        $rawName = $action->actor ? $action->actor->displayName() : '';
        $nameText = $rawName !== '' ? '(' . trim($rawName, "() \t\n\r\0\x0B") . ')' : '';

        $posText = $action->actor ? $action->actor->officialPosition($context) : '';
        $dateText = $this->buddhistDate($action->created_at);

        $pdf->SetTextColor(37, 60, 155);

        // 1. ชื่อ
        if ($nameText !== '') {
            $pdf->SetFont('freeserif', 'B', 9.5);
            $pdf->SetXY($x, $currentY);
            $pdf->Cell($w, 4.3, $nameText, 0, 2, 'C');
            $currentY += 4.3;
        }

        // 2. ตำแหน่ง
        if ($posText !== '') {
            $pdf->SetFont('freeserif', '', 8.5);
            $pdf->SetXY($x, $currentY);
            $pdf->Cell($w, 4.0, $posText, 0, 2, 'C');
            $currentY += 4.0;
        }

        // 3. วันที่
        $pdf->SetFont('freeserif', '', 8.5);
        $pdf->SetXY($x, $currentY);
        $pdf->Cell($w, 4.0, $dateText, 0, 2, 'C');
    }

    /**
     * @return array{x: float, y: float, w: float, h: float, label: string}
     */
    private function signaturePosition(Memo $memo, string $type): array
    {
        $position = self::SIGNATURE_POSITIONS[$type];
        foreach ($memo->stampPositions as $stampPosition) {
            if ($stampPosition->stamp_type !== $type) {
                continue;
            }

            $position['x'] = $stampPosition->position_x !== null ? (float) $stampPosition->position_x : (float) $position['x'];
            $position['y'] = $stampPosition->position_y !== null ? (float) $stampPosition->position_y : (float) $position['y'];
        }

        return $position;
    }

    private function drawDebugFrame(Fpdi $pdf, float $x, float $y, float $width, float $height, float $dateX, float $dateY, float $dateWidth, string $label): void
    {
        $pdf->SetLineStyle(['width' => 0.15, 'dash' => '1,1', 'color' => [220, 38, 38]]);
        $pdf->Rect($x, $y, $width, $height);
        $pdf->Rect($dateX, $dateY, $dateWidth, 5.2);
        $pdf->SetTextColor(220, 38, 38);
        $pdf->SetFont('freeserif', '', 6.5);
        $pdf->SetXY($x, max(0, $y - 3.5));
        $pdf->Cell($width, 3.5, $label, 0, 2, 'C');
        $pdf->SetLineStyle(['width' => 0.2, 'dash' => 0, 'color' => [37, 60, 155]]);
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function containedImageBox(string $path, float $x, float $y, float $boxWidth, float $boxHeight): array
    {
        $size = @getimagesize($path);
        if ($size === false || (int) $size[0] <= 0 || (int) $size[1] <= 0) {
            return [$x, $y, $boxWidth, $boxHeight];
        }

        $sourceWidth = (float) $size[0];
        $sourceHeight = (float) $size[1];
        $scale = min($boxWidth / $sourceWidth, $boxHeight / $sourceHeight);
        $imageWidth = $sourceWidth * $scale;
        $imageHeight = $sourceHeight * $scale;
        $imageX = $x + (($boxWidth - $imageWidth) / 2);
        $imageY = $y + (($boxHeight - $imageHeight) / 2);

        return [$imageX, $imageY, $imageWidth, $imageHeight];
    }

    /**
     * @param string[] $actions
     */
    private function latestAction(Memo $memo, array $actions): ?MemoAction
    {
        $latest = null;
        foreach ($memo->actions as $action) {
            if (!in_array($action->action, $actions, true)) {
                continue;
            }
            if ($latest === null || (int) $action->created_at >= (int) $latest->created_at) {
                $latest = $action;
            }
        }

        return $latest;
    }

    private function originalPdfFile(Memo $memo): ?MemoFile
    {
        if ($memo->originalPdfFile instanceof MemoFile) {
            return $memo->originalPdfFile;
        }

        foreach ($memo->files as $file) {
            if ($file->description === MemoFile::TYPE_ORIGINAL) {
                return $file;
            }
        }

        foreach ($memo->files as $file) {
            if ($file->description === null || $file->description === '') {
                return $file;
            }
        }

        return null;
    }

    private function signaturePath(?User $user): ?string
    {
        if (!$user instanceof User || !$user->signature_path) {
            return null;
        }

        $path = Yii::getAlias('@backend/web/' . ltrim($user->signature_path, '/'));

        if (!is_file($path)) {
            return null;
        }

        return $this->pdfSafeSignaturePath($path);
    }

    private function pdfSafeSignaturePath(string $sourcePath): string
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            return $sourcePath;
        }

        $targetPath = dirname($sourcePath) . '/signature-pdf-safe.jpg';
        if (is_file($targetPath) && filemtime($targetPath) >= filemtime($sourcePath)) {
            return $targetPath;
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return $sourcePath;
        }

        $contents = @file_get_contents($sourcePath);
        if ($contents === false) {
            return $sourcePath;
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            return $sourcePath;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        $saved = imagejpeg($canvas, $targetPath, 95);
        imagedestroy($source);
        imagedestroy($canvas);

        return $saved ? $targetPath : $sourcePath;
    }

    private function imageHeightForWidth(?string $path, float $width): ?float
    {
        if ($path === null || !is_file($path)) {
            return null;
        }

        $size = @getimagesize($path);
        if ($size === false || (int) $size[0] <= 0) {
            return null;
        }

        return $width * ((float) $size[1] / (float) $size[0]);
    }

    private function buddhistDate(int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if ($timestamp === false) {
            return (string) $value;
        }

        $thaiShortMonths = [
            1 => 'ม.ค.',
            2 => 'ก.พ.',
            3 => 'มี.ค.',
            4 => 'เม.ย.',
            5 => 'พ.ค.',
            6 => 'มิ.ย.',
            7 => 'ก.ค.',
            8 => 'ส.ค.',
            9 => 'ก.ย.',
            10 => 'ต.ค.',
            11 => 'พ.ย.',
            12 => 'ธ.ค.',
        ];

        return (int) date('j', $timestamp)
            . ' ' . $thaiShortMonths[(int) date('n', $timestamp)]
            . ' ' . ((int) date('Y', $timestamp) + 543);
    }

    private function pdftoppmBinary(): ?string
    {
        $candidates = [
            getenv('EOFFICE_PDFTOPPM') ?: null,
            Yii::getAlias('@app/../runtime/poppler/bin/pdftoppm'),
            '/usr/bin/pdftoppm',
            '/usr/local/bin/pdftoppm',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $this->isPathAllowedByOpenBasedir($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isPathAllowedByOpenBasedir(string $path): bool
    {
        $openBasedir = (string) ini_get('open_basedir');
        if ($openBasedir === '') {
            return true;
        }

        $path = rtrim($path, DIRECTORY_SEPARATOR);
        foreach (explode(PATH_SEPARATOR, $openBasedir) as $allowedPath) {
            $allowedPath = rtrim($allowedPath, DIRECTORY_SEPARATOR);
            if ($allowedPath === '') {
                continue;
            }

            if ($path === $allowedPath || str_starts_with($path, $allowedPath . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    private function webRelativePath(string $absolutePath): string
    {
        $webRoot = Yii::getAlias('@backend/web');
        return ltrim(str_replace($webRoot, '', $absolutePath), '/');
    }
}
