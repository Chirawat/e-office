<?php

declare(strict_types=1);

namespace common\components;

use common\models\Document;
use common\models\DocumentFile;
use common\models\DocumentStamp;
use common\models\SystemSetting;
use common\models\User;
use setasign\Fpdi\Tcpdf\Fpdi;
use Yii;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\helpers\FileHelper;

class PdfStampService extends Component
{
    private const SHOW_NOTE_DEBUG_FRAMES = false;
    private const NOTE_SIGNATURE_WIDTH = 42.0;
    private const NOTE_SIGNATURE_HEIGHT = 14.0;

    private const DEPUTY_NOTE_LAYOUT = [
        'signature_width' => self::NOTE_SIGNATURE_WIDTH,
        'signature_height' => self::NOTE_SIGNATURE_HEIGHT,
        'signature_x_offset' => 13.0,
        'signature_y_offset' => 1.0,
        'date_x_offset' => 0.0,
        'date_y_offset' => 15.0,
        'date_width' => 30.0,
        'date_align_to_signature' => true,
    ];

    private const DIRECTOR_NOTE_LAYOUT = [
        'signature_width' => self::NOTE_SIGNATURE_WIDTH,
        'signature_height' => self::NOTE_SIGNATURE_HEIGHT,
        'signature_x_offset' => 11.0,
        'signature_y_offset' => 5.0,
        'date_x_offset' => 0.0,
        'date_y_offset' => 15.0,
        'date_width' => 30.0,
        'date_align_to_signature' => true,
    ];

    public function generate(Document $document): DocumentFile
    {
        $originalFile = $document->originalFile;
        if ($originalFile === null) {
            throw new InvalidArgumentException('ไม่พบไฟล์ PDF ต้นฉบับ');
        }

        $sourcePath = Yii::getAlias('@backend/web/' . ltrim($originalFile->storage_path, '/'));
        if (!is_file($sourcePath)) {
            throw new InvalidArgumentException('ไม่พบไฟล์ PDF ต้นฉบับในระบบไฟล์');
        }

        $year = date('Y', strtotime($document->receive_date));
        $outputDir = Yii::getAlias('@backend/web/uploads/documents/' . $year . '/' . $document->id);
        FileHelper::createDirectory($outputDir);

        $outputPath = $outputDir . '/stamped.pdf';
        $relativePath = 'uploads/documents/' . $year . '/' . $document->id . '/stamped.pdf';

        $pdf = new Fpdi('P', 'mm');
        $pdf->SetCreator('eoffice');
        $pdf->SetAuthor('eoffice');
        $pdf->SetTitle($document->receive_no . ' ' . $document->subject);
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

            // ตราประทับลงรับหนังสือ ให้ประทับไว้ที่หน้าแรกเสมอ
            if ($pageNo === 1) {
                $this->drawReceiveStampPage($pdf, $document, (float) $size['width'], (float) $size['height']);
            }

            // ตราประทับแยกกลุ่มงาน, ความเห็นรอง ผอ. และ คำสั่ง ผอ. ให้อยู่หน้าสุดท้ายเสมอ
            if ($pageNo === $pageCount) {
                $this->drawLastPageStamps($pdf, $document, (float) $size['width'], (float) $size['height']);
            }
        }

        $pdf->Output($outputPath, 'F');

        $stampedFile = $document->stampedFile ?? new DocumentFile([
            'document_id' => $document->id,
            'file_type' => DocumentFile::TYPE_STAMPED,
            'created_by' => $this->currentUserId(),
            'created_at' => time(),
        ]);

        $stampedFile->original_name = 'stamped.pdf';
        $stampedFile->storage_path = $relativePath;
        $stampedFile->mime_type = 'application/pdf';
        $stampedFile->file_size = filesize($outputPath) ?: null;
        $stampedFile->save(false);

        try {
            $this->generatePreviewImage($stampedFile);
        } catch (\Throwable $e) {
            Yii::warning('Preview image generation skipped: ' . $e->getMessage(), __METHOD__);
        }

        return $stampedFile;
    }

    public function generatePreviewImage(DocumentFile $file): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }

        $sourcePath = Yii::getAlias('@backend/web/' . ltrim($file->storage_path, '/'));
        if (!is_file($sourcePath)) {
            return null;
        }

        $outputPath = dirname($sourcePath) . '/preview-page-1.png';
        if (is_file($outputPath) && filemtime($outputPath) >= filemtime($sourcePath)) {
            return $this->webRelativePath($outputPath);
        }

        $binary = $this->pdftoppmBinary();
        if ($binary === null) {
            return null;
        }

        $prefix = dirname($sourcePath) . '/preview-page-1';
        $libraryPath = $this->popplerLibraryPath($binary);
        $envPrefix = $libraryPath !== null
            ? 'DYLD_FALLBACK_LIBRARY_PATH=' . escapeshellarg($libraryPath) . ' '
            : '';

        $command = $envPrefix . implode(' ', [
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

        try {
            @exec($command, $output, $exitCode);
            if (($exitCode ?? 1) !== 0 || !is_file($outputPath)) {
                Yii::warning('Unable to generate PDF preview image: ' . implode("\n", (array) ($output ?? [])), __METHOD__);
                return null;
            }
        } catch (\Throwable $e) {
            Yii::warning('exec pdftoppm failed: ' . $e->getMessage(), __METHOD__);
            return null;
        }

        return $this->webRelativePath($outputPath);
    }

    private function drawReceiveStampPage(Fpdi $pdf, Document $document, float $pageWidth, float $pageHeight): void
    {
        $receivePosition = $this->stampPosition($document, DocumentStamp::TYPE_RECEIVE, max(118, $pageWidth - 72), 14, $pageWidth, $pageHeight);
        $this->drawReceiveStamp($pdf, $document, $receivePosition['x'], $receivePosition['y']);
    }

    private function drawLastPageStamps(Fpdi $pdf, Document $document, float $pageWidth, float $pageHeight): void
    {
        $departmentPosition = $this->stampPosition($document, DocumentStamp::TYPE_DEPARTMENT, 18, max(190, $pageHeight - 78), $pageWidth, $pageHeight);
        $this->drawDepartmentStamp($pdf, $document, $departmentPosition['x'], $departmentPosition['y']);

        $deputyStamp = $this->latestStamp($document, DocumentStamp::TYPE_DEPUTY_COMMENT);
        $deputyComment = $this->stampContent($deputyStamp);
        if ($deputyComment !== null) {
            $position = $this->stampPosition($document, DocumentStamp::TYPE_DEPUTY_COMMENT, $pageWidth - 82, min(170, $pageHeight - 120), $pageWidth, $pageHeight);
            $this->drawBlueNote($pdf, 'เรียน ผู้อำนวยการ', $deputyComment, $position['x'], $position['y'], 68, $deputyStamp, self::DEPUTY_NOTE_LAYOUT, 'deputy');
        }

        $directorStamp = $this->latestStamp($document, DocumentStamp::TYPE_DIRECTOR_ORDER);
        $directorOrder = $this->stampContent($directorStamp);
        if ($directorOrder !== null) {
            $position = $this->stampPosition($document, DocumentStamp::TYPE_DIRECTOR_ORDER, $pageWidth - 78, max(230, $pageHeight - 82), $pageWidth, $pageHeight);
            $this->drawBlueNote($pdf, 'ทราบ', $directorOrder, $position['x'], $position['y'], 64, $directorStamp, self::DIRECTOR_NOTE_LAYOUT, 'director');
        }
    }

    private function drawReceiveStamp(Fpdi $pdf, Document $document, float $x, float $y): void
    {
        $w = 58;
        $h = 23;

        $pdf->SetDrawColor(179, 65, 75);
        $pdf->SetTextColor(179, 65, 75);
        $pdf->SetLineWidth(0.35);
        $pdf->Rect($x, $y, $w, $h);
        $pdf->SetFont('freeserif', 'B', 11);
        $pdf->SetXY($x + 2, $y + 1.5);
        $pdf->Cell($w - 4, 5, 'โรงเรียนกุดชุมวิทยาคม', 0, 2, 'C');
        $pdf->SetFont('freeserif', '', 10);
        $pdf->Cell($w - 4, 5, 'รับที่ ' . $document->receive_no, 0, 2, 'L');
        $pdf->Cell($w - 4, 5, 'วันที่ ' . $this->buddhistDate($document->receive_date), 0, 2, 'L');
        $pdf->Cell($w - 4, 5, 'เวลา ' . substr((string) $document->receive_time, 0, 5) . ' น.', 0, 2, 'L');
    }

    private function drawDepartmentStamp(Fpdi $pdf, Document $document, float $x, float $y): void
    {
        $w = 60;
        $padding = 2.0;
        $innerW = $w - ($padding * 2);
        $currX = $x + $padding;
        $startY = $y;
        $currY = $y + 2.0;

        $pdf->SetDrawColor(37, 60, 155);
        $pdf->SetTextColor(37, 60, 155);
        $pdf->SetLineWidth(0.25);

        // 1. หัวข้อ: เรียน ผู้อำนวยการโรงเรียน
        $pdf->SetFont('freeserif', 'B', 10);
        $pdf->SetXY($currX, $currY);
        $pdf->Cell($innerW, 4.5, 'เรียน ผู้อำนวยการโรงเรียน', 0, 2, 'L');
        $currY = $pdf->GetY() + 0.5;

        // 2. สรุปหนังสือ
        $summary = trim((string) ($document->summary ?: $document->subject));
        if ($summary !== '') {
            $pdf->SetFont('freeserif', '', 8.5);
            $pdf->SetXY($currX, $currY);
            $pdf->MultiCell($innerW, 3.8, $summary, 0, 'L', false, 1, $currX, $currY);
            $currY = $pdf->GetY() + 1.0;
        }

        // 3. เห็นสมควรมอบ
        $pdf->SetFont('freeserif', 'B', 9);
        $pdf->SetXY($currX, $currY);
        $pdf->Cell($innerW, 4.2, 'เห็นสมควรมอบ', 0, 2, 'L');
        $currY = $pdf->GetY() + 0.5;

        // 4. รายการกลุ่มงาน
        $checkedCode = $document->department?->code;
        $departments = [
            'academic' => 'กลุ่มบริหารวิชาการ',
            'personnel' => 'กลุ่มบริหารบุคคล',
            'budget' => 'กลุ่มบริหารงบประมาณ',
            'general' => 'กลุ่มบริหารทั่วไป',
            'student_affairs' => 'กลุ่มบริหารกิจการนักเรียน',
        ];

        $pdf->SetFont('freeserif', '', 8.5);
        foreach ($departments as $code => $name) {
            $prefix = ($checkedCode === $code) ? '[ / ] ' : '[   ] ';
            $pdf->SetXY($currX, $currY);
            $pdf->Cell($innerW, 3.8, $prefix . $name, 0, 2, 'L');
            $currY = $pdf->GetY();
        }

        $currY += 1.0;

        // 5. ผู้ลงนาม (เจ้าหน้าที่ธุรการ)
        $deptStamp = $this->latestStamp($document, DocumentStamp::TYPE_DEPARTMENT);
        $officerId = $deptStamp?->created_by ?: $document->created_by;
        $officer = $officerId ? User::findOne((int) $officerId) : null;
        $signaturePath = $this->signaturePathForUser($officer);

        if ($signaturePath !== null) {
            $sigW = 28;
            $sigH = 11;
            $sigX = $x + (($w - $sigW) / 2);
            $sigY = $currY + 0.5;
            [$imageX, $imageY, $imageWidth, $imageHeight] = $this->containedImageBox($signaturePath, $sigX, $sigY, $sigW, $sigH);
            $pdf->Image($signaturePath, $imageX, $imageY, $imageWidth, $imageHeight, '', '', '', false, 300);
            $currY = $sigY + $sigH + 0.5;
        } else {
            $currY += 1.5;
            $pdf->SetFont('freeserif', '', 8.5);
            $pdf->SetXY($currX, $currY);
            $pdf->Cell($innerW, 3.8, '....................................................', 0, 2, 'C');
            $currY = $pdf->GetY() + 0.5;
        }

        // ชื่อ-สกุล เจ้าหน้าที่ธุรการ
        $rawName = $officer ? $officer->displayName() : '';
        $nameText = $rawName !== '' ? '(' . trim($rawName, "() \t\n\r\0\x0B") . ')' : '( .................................................... )';
        $pdf->SetFont('freeserif', 'B', 8.5);
        $pdf->SetXY($currX, $currY);
        $pdf->Cell($innerW, 3.8, $nameText, 0, 2, 'C');
        $currY = $pdf->GetY();

        // ตำแหน่ง
        $pdf->SetFont('freeserif', '', 8);
        $pdf->SetXY($currX, $currY);
        $pdf->Cell($innerW, 3.6, 'เจ้าหน้าที่ธุรการ', 0, 2, 'C');
        $currY = $pdf->GetY() + 1.5;

        // วาดกรอบสี่เหลี่ยมรอบตราประทับทั้งหมด
        $totalH = $currY - $startY;
        $pdf->Rect($x, $startY, $w, $totalH);
    }

    private function drawBlueNote(Fpdi $pdf, string $title, string $content, float $x, float $y, float $w, ?DocumentStamp $stamp = null, array $layout = [], string $context = 'deputy'): void
    {
        $pdf->SetTextColor(37, 60, 155);
        $pdf->SetDrawColor(37, 60, 155);
        $pdf->SetFont('freeserif', 'B', 11);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, 6, $title, 0, 2, 'L');
        $pdf->SetFont('freeserif', '', 10.5);
        $pdf->MultiCell($w, 5.2, $content, 0, 'L', false, 1, $x, $pdf->GetY());
        $contentBottomY = $pdf->GetY();

        $signaturePath = $this->signaturePathForStamp($stamp);
        $signatureWidth = (float) ($layout['signature_width'] ?? self::NOTE_SIGNATURE_WIDTH);
        $signatureHeight = (float) ($layout['signature_height'] ?? self::NOTE_SIGNATURE_HEIGHT);
        $signatureX = $x + (float) ($layout['signature_x_offset'] ?? (($w - $signatureWidth) / 2));
        $signatureY = $contentBottomY + (float) ($layout['signature_y_offset'] ?? 1);

        if ($signaturePath !== null) {
            [$imageX, $imageY, $imageWidth, $imageHeight] = $this->containedImageBox($signaturePath, $signatureX, $signatureY, $signatureWidth, $signatureHeight);
            $pdf->Image($signaturePath, $imageX, $imageY, $imageWidth, $imageHeight, '', '', '', false, 300);
            $currentY = $signatureY + $signatureHeight + 0.5;
        } else {
            $currentY = $contentBottomY + 2.0;
        }

        // หาผู้ลงนาม
        $user = ($stamp && $stamp->created_by) ? User::findOne((int) $stamp->created_by) : null;
        $schoolName = (string) SystemSetting::get(SystemSetting::KEY_SCHOOL_NAME, 'โรงเรียนกุดชุมวิทยาคม');

        // 1. ชื่อ
        $rawName = '';
        if ($user) {
            $rawName = $user->displayName();
        } elseif ($context === 'director') {
            $rawName = SystemSetting::isActingDirector()
                ? (string) SystemSetting::get(SystemSetting::KEY_ACTING_DIRECTOR_NAME)
                : (string) SystemSetting::get(SystemSetting::KEY_DIRECTOR_NAME);
        }
        $nameText = $rawName !== '' ? '(' . trim($rawName, "() \t\n\r\0\x0B") . ')' : '';

        // 2. ตำแหน่ง
        $posText = '';
        if ($user) {
            $posText = $user->officialPosition($context);
        } elseif ($context === 'director') {
            $posText = SystemSetting::isActingDirector()
                ? (string) SystemSetting::get(SystemSetting::KEY_ACTING_DIRECTOR_POSITION, 'รองผู้อำนวยการ รักษาการในตำแหน่งผู้อำนวยการ' . $schoolName)
                : (string) SystemSetting::get(SystemSetting::KEY_DIRECTOR_POSITION, 'ผู้อำนวยการ' . $schoolName);
        } elseif ($context === 'deputy') {
            $posText = 'รองผู้อำนวยการ' . $schoolName;
        }

        // 3. วันที่
        $dateText = $this->buddhistDate($stamp?->created_at ?? 'now');

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

    private function drawNoteDebugFrame(Fpdi $pdf, float $x, float $y, float $w, float $contentBottomY, array $layout): void
    {
        $frameHeight = max((float) ($layout['debug_height'] ?? 46), ($contentBottomY - $y) + 22);
        $signatureWidth = (float) ($layout['signature_width'] ?? ($w * 0.5));
        $signatureHeight = (float) ($layout['signature_height'] ?? 14.0);
        $signatureX = $x + (float) ($layout['signature_x_offset'] ?? ($w * 0.38));
        $signatureY = $contentBottomY + (float) ($layout['signature_y_offset'] ?? 1);
        [$dateX, $dateWidth] = $this->dateLayout($x, $w, $layout);
        $dateY = $signatureY + (float) ($layout['date_y_offset'] ?? 13);

        $pdf->SetLineStyle(['width' => 0.2, 'dash' => '1.5,1.5', 'color' => [220, 38, 38]]);
        $pdf->Rect($x, $y, $w, $frameHeight);

        $pdf->SetLineStyle(['width' => 0.15, 'dash' => '1,1', 'color' => [37, 99, 235]]);
        $pdf->Rect($signatureX, $signatureY, $signatureWidth, $signatureHeight);
        $pdf->Rect($dateX, $dateY, $dateWidth, 5.2);

        $pdf->SetTextColor(220, 38, 38);
        $pdf->SetFont('freeserif', '', 7);
        $pdf->SetXY($x + 1, $y + 1);
        $pdf->Cell($w - 2, 3.5, 'note frame', 0, 2, 'L');
        $pdf->SetTextColor(37, 99, 235);
        $pdf->SetXY($signatureX, max(0, $signatureY - 3.5));
        $pdf->Cell($signatureWidth, 3.5, 'signature', 0, 2, 'C');
        $pdf->SetXY($dateX, max(0, $dateY - 3.5));
        $pdf->Cell($dateWidth, 3.5, 'date', 0, 2, 'R');
        $pdf->SetLineStyle(['width' => 0.2, 'dash' => 0, 'color' => [37, 60, 155]]);
    }

    private function dateLayout(float $x, float $w, array $layout): array
    {
        $dateWidth = (float) ($layout['date_width'] ?? $w);
        $dateAlign = (bool) ($layout['date_align_to_signature'] ?? false) ? 'C' : 'R';
        $dateX = $x + (float) ($layout['date_x_offset'] ?? 0);

        if ((bool) ($layout['date_align_to_signature'] ?? false)) {
            $signatureWidth = (float) ($layout['signature_width'] ?? ($w * 0.5));
            $signatureX = $x + (float) ($layout['signature_x_offset'] ?? ($w * 0.38));
            $dateX = $signatureX + (($signatureWidth - $dateWidth) / 2) + (float) ($layout['date_x_offset'] ?? 0);
        }

        return [$dateX, $dateWidth, $dateAlign];
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

    private function buddhistDate(int|string|null $date): string
    {
        if ($date === null || $date === '' || $date === 'now') {
            $timestamp = time();
        } else {
            $timestamp = is_numeric($date) ? (int) $date : strtotime((string) $date);
        }
        if ($timestamp === false) {
            return (string) $date;
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

        $day = (int) date('j', $timestamp);
        $month = $thaiShortMonths[(int) date('n', $timestamp)];
        $year = (int) date('Y', $timestamp) + 543;

        return "{$day} {$month} {$year}";
    }

    private function stampContent(?DocumentStamp $stamp): ?string
    {
        if ($stamp === null || trim((string) $stamp->content) === '') {
            return null;
        }

        return (string) $stamp->content;
    }

    private function signaturePathForStamp(?DocumentStamp $stamp): ?string
    {
        if ($stamp === null || $stamp->created_by === null) {
            return null;
        }

        $user = User::findOne((int) $stamp->created_by);
        return $this->signaturePathForUser($user);
    }

    private function signaturePathForUser(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($user->signature_path) {
            $path = Yii::getAlias('@backend/web/' . ltrim($user->signature_path, '/'));
            if (is_file($path)) {
                return $this->pdfSafeSignaturePath($path);
            }
        }

        // ค้นหาลายเซ็นจากประวัติบัญชีเดิมของผู้ใช้คนเดียวกัน (กรณีเปลี่ยนบัญชี/ผูกไลน์ใหม่)
        $cleanUsername = preg_replace('/__deleted_\d+$/', '', (string) $user->username);
        $previousUser = User::find()
            ->where(['not', ['signature_path' => null]])
            ->andWhere(['or',
                ['like', 'username', $cleanUsername . '%', false],
                ['phone' => $user->phone],
                ['fullname' => $user->fullname],
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($previousUser && $previousUser->signature_path) {
            $prevPath = Yii::getAlias('@backend/web/' . ltrim($previousUser->signature_path, '/'));
            if (is_file($prevPath)) {
                return $this->pdfSafeSignaturePath($prevPath);
            }
        }

        return null;
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

    private function stampPosition(Document $document, string $type, float $defaultX, float $defaultY, float $pageWidth, float $pageHeight): array
    {
        $stamp = $this->latestStamp($document, $type);
        $x = $stamp?->position_x !== null ? (float) $stamp->position_x : $defaultX;
        $y = $stamp?->position_y !== null ? (float) $stamp->position_y : $defaultY;

        if ($x < 0 || $x > $pageWidth - 10) {
            $x = $defaultX;
        }
        if ($y < 0 || $y > $pageHeight - 10) {
            $y = $defaultY;
        }

        return ['x' => $x, 'y' => $y];
    }

    private function latestStamp(Document $document, string $type): ?DocumentStamp
    {
        $latest = null;
        foreach ($document->stamps as $stamp) {
            if ($stamp->stamp_type === $type) {
                $latest = $stamp;
            }
        }

        return $latest;
    }

    private function currentUserId(): ?int
    {
        return Yii::$app->has('user', true) && !Yii::$app->user->isGuest ? (int) Yii::$app->user->id : null;
    }

    private function pdftoppmBinary(): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }

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

    private function popplerLibraryPath(string $binary): ?string
    {
        $runtimeLib = Yii::getAlias('@app/../runtime/poppler/lib');
        if (str_starts_with($binary, Yii::getAlias('@app/../runtime/poppler')) && is_dir($runtimeLib)) {
            return $runtimeLib;
        }

        return null;
    }

    private function webRelativePath(string $absolutePath): string
    {
        $webRoot = Yii::getAlias('@backend/web');
        return ltrim(str_replace($webRoot, '', $absolutePath), '/');
    }
}
