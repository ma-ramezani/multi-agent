<?php

namespace App\Neuron;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Unified text extraction for RAG ingestion and chat attachments.
 *
 * Supported: txt/md/csv/json/log/html/xml, pdf, docx, xlsx/xls,
 * pptx, images (png/jpg/webp/gif/bmp via Tesseract OCR fa+en).
 */
class DocumentReader
{
    public const MAX_CHARS = 200000;

    public const SUPPORTED_EXTENSIONS = [
        'txt', 'md', 'markdown', 'csv', 'json', 'log', 'xml',
        'html', 'htm', 'pdf', 'docx', 'xlsx', 'xls', 'pptx',
        'png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp',
    ];

    /**
     * @return array{text: string, kind: string, detail: string}
     */
    public static function read(string $absPath, ?string $mime = null, ?string $filename = null): array
    {
        $ext = strtolower(pathinfo($filename ?? $absPath, PATHINFO_EXTENSION));

        if (!is_file($absPath)) {
            return ['text' => '', 'kind' => 'missing', 'detail' => 'فایل یافت نشد'];
        }

        try {
            $out = match ($ext) {
                'txt', 'md', 'markdown', 'csv', 'json', 'log', 'xml' => self::plainText($absPath),
                'html', 'htm' => self::htmlText($absPath),
                'pdf' => self::pdfText($absPath),
                'docx' => self::docxText($absPath),
                'xlsx', 'xls' => self::spreadsheetText($absPath, $ext),
                'pptx' => self::pptxText($absPath),
                'png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp' => self::ocrText($absPath),
                'doc' => self::unsupported('فرمت قدیمی .doc پشتیبانی نمی‌شود؛ لطفاً به .docx تبدیل کنید'),
                'ppt' => self::unsupported('فرمت قدیمی .ppt پشتیبانی نمی‌شود؛ لطفاً به .pptx تبدیل کنید'),
                default => self::sniffText($absPath),
            };
        } catch (\Throwable $e) {
            return ['text' => '', 'kind' => 'error', 'detail' => 'خطا در خواندن فایل: ' . $e->getMessage()];
        }

        $out['text'] = mb_substr(trim((string) $out['text']), 0, self::MAX_CHARS);
        return $out;
    }

    public static function isSupported(?string $filename): bool
    {
        $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        return in_array($ext, self::SUPPORTED_EXTENSIONS, true);
    }

    // ---------- readers ----------

    protected static function plainText(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false || ($raw !== '' && !mb_check_encoding($raw, 'UTF-8') && !mb_check_encoding($raw = mb_convert_encoding($raw, 'UTF-8', 'auto'), 'UTF-8'))) {
            return ['text' => '', 'kind' => 'error', 'detail' => 'متن فایل قابل خواندن نیست'];
        }
        return ['text' => $raw, 'kind' => 'text', 'detail' => ''];
    }

    protected static function sniffText(string $path): array
    {
        $raw = @file_get_contents($path, false, null, 0, 200000);
        if ($raw !== false && !str_contains($raw, "\0") && mb_check_encoding($raw, 'UTF-8')) {
            return ['text' => $raw, 'kind' => 'text', 'detail' => ''];
        }
        return self::unsupported('این فرمت پشتیبانی نمی‌شود');
    }

    protected static function unsupported(string $msg): array
    {
        return ['text' => '', 'kind' => 'unsupported', 'detail' => $msg];
    }

    protected static function htmlText(string $path): array
    {
        $raw = (string) @file_get_contents($path);
        $raw = strip_tags($raw);
        return ['text' => html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'kind' => 'text', 'detail' => ''];
    }

    protected static function pdfText(string $path): array
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            return self::unsupported('کتابخانه PDF نصب نیست');
        }
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($path);
        $pages = [];
        foreach ($pdf->getPages() as $i => $page) {
            $t = trim($page->getText());
            if ($t !== '') {
                $pages[] = "— صفحه " . ($i + 1) . " —\n" . $t;
            }
        }
        $text = implode("\n\n", $pages);
        if ($text === '') {
            return ['text' => '', 'kind' => 'empty', 'detail' => 'از PDF متنی استخراج نشد (ممکن است اسکن‌شده باشد؛ نسخه OCR آن را بارگذاری کنید)'];
        }
        return ['text' => $text, 'kind' => 'text', 'detail' => count($pages) . ' صفحه'];
    }

    protected static function docxText(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return self::unsupported('فایل docx خراب است');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!$xml) {
            return self::unsupported('ساختار docx نامعتبر است');
        }
        $dom = new \DOMDocument();
        @$dom->loadXML($xml);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $parts = [];
        foreach ($xp->query('//w:p') as $p) {
            $t = '';
            foreach ($xp->query('.//w:t', $p) as $tn) {
                $t .= $tn->textContent;
            }
            // keep table-cell separation readable
            $t = trim($t);
            if ($t !== '') {
                $parts[] = $t;
            }
        }
        $text = implode("\n", $parts);
        if ($text === '') {
            return ['text' => '', 'kind' => 'empty', 'detail' => 'از Word متنی استخراج نشد'];
        }
        return ['text' => $text, 'kind' => 'text', 'detail' => count($parts) . ' پاراگراف'];
    }

    protected static function spreadsheetText(string $path, string $ext): array
    {
        try {
            $reader = IOFactory::createReader($ext === 'xls' ? 'Xls' : 'Xlsx');
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
        } catch (\Throwable $e) {
            return self::unsupported('فایل اکسل خوانده نشد: ' . $e->getMessage());
        }
        $out = [];
        foreach ($book->getSheetNames() as $si => $name) {
            $sheet = $book->getSheet($si);
            $maxRow = min($sheet->getHighestDataRow(), 5000);
            $maxCol = min(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), 50);
            $rows = [];
            for ($r = 1; $r <= $maxRow; $r++) {
                $cells = [];
                for ($c = 1; $c <= $maxCol; $c++) {
                    $v = $sheet->getCell([$c, $r])->getFormattedValue();
                    $cells[] = is_scalar($v) ? trim((string) $v) : '';
                }
                if (implode('', $cells) !== '') {
                    $rows[] = implode(' | ', $cells);
                }
            }
            if ($rows) {
                $out[] = "— شیت: {$name} (" . count($rows) . " ردیف) —\n" . implode("\n", $rows);
            }
        }
        $book->disconnectWorksheets();
        $text = implode("\n\n", $out);
        if ($text === '') {
            return ['text' => '', 'kind' => 'empty', 'detail' => 'اکسل خالی است'];
        }
        return ['text' => $text, 'kind' => 'table', 'detail' => count($out) . ' شیت'];
    }

    protected static function pptxText(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return self::unsupported('فایل pptx خراب است');
        }
        $slides = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $m)) {
                $slides[(int) $m[1]] = $zip->getFromIndex($i);
            }
        }
        $zip->close();
        ksort($slides);
        $out = [];
        foreach ($slides as $n => $xml) {
            $dom = new \DOMDocument();
            @$dom->loadXML($xml);
            $xp = new \DOMXPath($dom);
            $xp->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            $texts = [];
            foreach ($xp->query('//a:t') as $tn) {
                $t = trim($tn->textContent);
                if ($t !== '') {
                    $texts[] = $t;
                }
            }
            if ($texts) {
                $out[] = "— اسلاید {$n} —\n" . implode("\n", $texts);
            }
        }
        $text = implode("\n\n", $out);
        if ($text === '') {
            return ['text' => '', 'kind' => 'empty', 'detail' => 'از پاورپوینت متنی استخراج نشد'];
        }
        return ['text' => $text, 'kind' => 'slides', 'detail' => count($out) . ' اسلاید'];
    }

    protected static function ocrText(string $path): array
    {
        $bin = trim((string) @shell_exec('which tesseract 2>/dev/null'));
        if ($bin === '') {
            return self::unsupported('OCR نصب نیست؛ برای عکس فعلاً فقط نام فایل ثبت می‌شود');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ocr') . '.txt';
        $base = substr($tmp, 0, -4);
        $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($path) . ' ' . escapeshellarg($base) . ' -l fas+eng --psm 6 2>/dev/null';
        @exec($cmd, $o, $code);
        $text = is_file($base . '.txt') ? (string) @file_get_contents($base . '.txt') : '';
        @unlink($base . '.txt');
        @unlink($tmp);
        $text = trim($text);
        if ($text === '') {
            return ['text' => '', 'kind' => 'empty', 'detail' => 'از عکس متنی تشخیص داده نشد'];
        }
        return ['text' => $text, 'kind' => 'ocr', 'detail' => 'OCR فارسی+انگلیسی'];
    }
}
