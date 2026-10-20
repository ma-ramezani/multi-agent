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
    public const MAX_CHARS = 2000000; // 2MB — enough for whole books; bigger files get truncated

    public const SUPPORTED_EXTENSIONS = [
        'txt', 'md', 'markdown', 'csv', 'json', 'log', 'xml',
        'html', 'htm', 'pdf', 'docx', 'xlsx', 'xls', 'pptx',
        'png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp',
        'mp3', 'wav', 'ogg', 'm4a', 'webm', 'flac',
    ];

    /**
     * Normalize extracted text: strip invisible bidi marks, fix reversed RTL
     * lines left by some PDF generators, unify glyphs, collapse whitespace.
     */
    public static function normalize(string $text): string
    {
        // Fix reversed Persian lines (common in PDFs generated with visual-order BIDI)
        $text = self::fixReversedPersian($text);
        // invisible directional / zero-width marks
        $text = str_replace(["\u{200E}", "\u{200F}", "\u{200B}", "\u{FEFF}"], '', $text);
        // Tesseract inserts ZWNJ between words as bidi glue; a plain space is
        // safer for search and LLM reading (می‌شود and می شود stay readable).
        $text = str_replace("\u{200C}", ' ', $text);
        // Arabic glyphs → Persian
        $text = str_replace(['ي', 'ك', 'ة'], ['ی', 'ک', 'ه'], $text);
        // Arabic-Indic digits → Persian digits (keep Latin digits as-is)
        $text = str_replace(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], $text);
        // collapse whitespace
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    /**
     * Detect and fix lines where Persian text was extracted in reverse
     * visual order (e.g. "دنک ظفح" instead of "حفظ کردن"). Heuristic:
     * if the reversed line contains more common Persian words than the
     * original, we keep the reversed version.
     */
    protected static function fixReversedPersian(string $text): string
    {
        $common = [' است ', ' در ', ' به ', ' را ', ' از ', ' که ', ' این ', ' برای ', ' با ', ' بر ', ' هر ', ' یک ', ' شد ', ' شود ', ' کند ', ' کنید ', ' باشد '];
        $lines = explode("\n", $text);
        foreach ($lines as &$line) {
            $trim = trim($line);
            if ($trim === '' || !preg_match('/[\x{0600}-\x{06FF}]/u', $trim)) {
                continue;
            }
            // Skip lines that are clearly code/URLs
            if (preg_match('/https?:\/\//', $trim) || preg_match('/^\s*[A-Za-z0-9_.-]+@[A-Za-z]/', $trim)) {
                continue;
            }
            // Count common words in original vs reversed
            $rev = self::mbStrRev($trim);
            $origScore = 0;
            $revScore = 0;
            $lowOrig = ' ' . mb_strtolower($trim) . ' ';
            $lowRev = ' ' . mb_strtolower($rev) . ' ';
            foreach ($common as $w) {
                if (str_contains($lowOrig, $w)) {
                    $origScore++;
                }
                if (str_contains($lowRev, $w)) {
                    $revScore++;
                }
            }
            // Also check if original starts with Latin but reversed starts with Persian (common in reversed)
            $startsPersianOrig = (bool) preg_match('/^[\x{0600}-\x{06FF}]/u', ltrim($trim));
            $startsPersianRev = (bool) preg_match('/^[\x{0600}-\x{06FF}]/u', ltrim($rev));
            // Heuristic: if reversed scores higher, or original doesn't start with Persian but reversed does
            if ($revScore > $origScore + 1 || (!$startsPersianOrig && $startsPersianRev && $revScore >= $origScore)) {
                $line = $rev;
            }
        }
        unset($line);
        return implode("\n", $lines);
    }

    protected static function mbStrRev(string $s): string
    {
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        return $chars ? implode('', array_reverse($chars)) : $s;
    }

    public static function isGarbage(string $text): bool
    {
        $len = mb_strlen($text);
        if ($len < 20) {
            return false;
        }
        // Count control characters (except \n, \r, \t)
        $ctrl = preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text);
        if ($ctrl / max(1, $len) > 0.02) {
            return true;
        }
        // Count characters that are not Persian/latin/digit/punctuation/space
        // If too many weird symbols, likely binary garbage
        $allowed = preg_match_all('/[\p{L}\p{N}\s\.\,\;\:\!\?\-\(\)\[\]\{\}\/\+\=\*\%\&\@\#\|\_\"\'\`]/u', $text);
        if ($allowed / max(1, $len) < 0.6) {
            return true;
        }
        // Check for excessive backticks, @, and control-like sequences typical of binary PDF garbage
        if (substr_count($text, '`') > $len * 0.02 && substr_count($text, '@') > $len * 0.01) {
            return true;
        }
        return false;
    }

    /**
     * @return array{text: string, kind: string, detail: string}
     */
    public static function read(string $absPath, ?string $mime = null, ?string $filename = null, bool $forceOcr = false): array
    {
        $ext = strtolower(pathinfo($filename ?? $absPath, PATHINFO_EXTENSION));
        // Fallback: if filename has no extension, try actual file path and mime, then real file type
        if ($ext === '') {
            $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        }
        if ($ext === '' && $mime) {
            $map = ['application/pdf' => 'pdf', 'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx', 'application/vnd.ms-powerpoint' => 'ppt', 'application/vnd.ms-excel' => 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', 'text/plain' => 'txt'];
            $ext = $map[strtolower(trim(explode(';', $mime)[0]))] ?? '';
        }
        // Last resort: detect via file header (handles mislabeled PDFs like .pdf that are actually docx)
        if ($ext === '' || !in_array($ext, self::SUPPORTED_EXTENSIONS, true)) {
            $realMime = @mime_content_type($absPath);
            if ($realMime) {
                $map2 = ['application/pdf' => 'pdf', 'application/zip' => 'docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx'];
                $detected = $map2[strtolower(trim(explode(';', $realMime)[0]))] ?? '';
                if ($detected && in_array($detected, self::SUPPORTED_EXTENSIONS, true)) {
                    $ext = $detected;
                }
            }
            // Fallback via `file` command for Office docs (zip-based)
            if ($ext === '' || $ext === 'txt') {
                $fileOut = trim((string) @shell_exec('file -b ' . escapeshellarg($absPath) . ' 2>/dev/null'));
                if (stripos($fileOut, 'Word 2007') !== false || stripos($fileOut, 'Microsoft Word') !== false) {
                    $ext = 'docx';
                } elseif (stripos($fileOut, 'PowerPoint 2007') !== false || stripos($fileOut, 'Microsoft PowerPoint') !== false) {
                    $ext = 'pptx';
                } elseif (stripos($fileOut, 'Excel 2007') !== false) {
                    $ext = 'xlsx';
                } elseif (stripos($fileOut, 'PDF document') !== false) {
                    $ext = 'pdf';
                }
            }
        }

        if (!is_file($absPath)) {
            return ['text' => '', 'kind' => 'missing', 'detail' => 'فایل یافت نشد'];
        }

        // Force OCR for PDFs whose embedded text is garbled (e.g. reversed RTL).
        if ($forceOcr && $ext === 'pdf') {
            $out = self::pdfOcrText($absPath, null);
            $out['text'] = mb_substr(self::normalize((string) $out['text']), 0, self::MAX_CHARS);
            return $out;
        }

        try {
            $out = match ($ext) {
                'txt', 'md', 'markdown', 'csv', 'json', 'log', 'xml' => self::plainText($absPath),
                'html', 'htm' => self::htmlText($absPath),
                'pdf' => self::pdfText($absPath),
                'docx' => self::docxText($absPath),
                'doc' => self::docText($absPath),
                'xlsx', 'xls' => self::spreadsheetText($absPath, $ext),
                'pptx' => self::pptxText($absPath),
                'ppt' => self::pptText($absPath),
                'png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp' => self::ocrText($absPath),
                'mp3', 'wav', 'ogg', 'm4a', 'webm', 'flac' => self::audioText($absPath),
                default => self::sniffText($absPath),
            };
        } catch (\Throwable $e) {
            return ['text' => '', 'kind' => 'error', 'detail' => 'خطا در خواندن فایل: ' . $e->getMessage()];
        }

        $out['text'] = mb_substr(self::normalize((string) $out['text']), 0, self::MAX_CHARS);
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
        try {
            $pdf = $parser->parseFile($path);
        } catch (\Throwable $e) {
            return self::pdfOcrText($path, null);
        }
        $pages = [];
        foreach ($pdf->getPages() as $i => $page) {
            try {
                $t = trim($page->getText());
            } catch (\Throwable) {
                $t = '';
            }
            if ($t !== '') {
                $pages[] = "— صفحه " . ($i + 1) . " —\n" . $t;
            }
        }
        $text = implode("\n\n", $pages);
        if ($text !== '' && !self::isGarbage($text)) {
            return ['text' => $text, 'kind' => 'text', 'detail' => count($pages) . ' صفحه'];
        }
        // Empty or garbage (e.g. binary garbage from encrypted/scanned PDF) → OCR fallback
        if ($text !== '' && self::isGarbage($text)) {
            // Try OCR as fallback for garbled PDFs
            try {
                $count = count($pdf->getPages());
            } catch (\Throwable) {
                $count = null;
            }
            $ocr = self::pdfOcrText($path, $count);
            if ($ocr['text'] !== '') {
                return $ocr;
            }
            // If OCR also fails, return garbage as last resort with warning
            return ['text' => $text, 'kind' => 'text', 'detail' => count($pages) . ' صفحه (متن ناخوانا — پیشنهاد OCR)'];
        }
        // No embedded text → scanned/image PDF → OCR fallback
        try {
            $count = count($pdf->getPages());
        } catch (\Throwable) {
            $count = null;
        }
        return self::pdfOcrText($path, $count);
    }

    /**
     * OCR a scanned PDF page-by-page: pdftoppm renders, tesseract reads.
     * Slow (seconds per page) — callers should run this in a queue job.
     */
    protected static function pdfOcrText(string $path, ?int $pageCount): array
    {
        $pdftoppm = trim((string) @shell_exec('which pdftoppm 2>/dev/null'));
        $tess = trim((string) @shell_exec('which tesseract 2>/dev/null'));
        if ($pdftoppm === '' || $tess === '') {
            return ['text' => '', 'kind' => 'empty', 'detail' => 'PDF اسکن‌شده است و ابزار OCR (poppler) روی سرور نیست'];
        }
        if ($pageCount === null) {
            $info = @shell_exec(escapeshellarg(trim((string) @shell_exec('which pdfinfo 2>/dev/null'))) . ' ' . escapeshellarg($path) . ' 2>/dev/null');
            $pageCount = $info && preg_match('/Pages:\s+(\d+)/', $info, $m) ? (int) $m[1] : 200;
        }
        $pageCount = max(1, min($pageCount, 500));

        $dir = sys_get_temp_dir() . '/pdfocr_' . bin2hex(random_bytes(6));
        @mkdir($dir, 0700, true);
        $out = [];
        try {
            for ($p = 1; $p <= $pageCount; $p++) {
                set_time_limit(60);
                @exec(
                    escapeshellarg($pdftoppm) . ' -png -r 300 -f ' . $p . ' -l ' . $p . ' -singlefile '
                    . escapeshellarg($path) . ' ' . escapeshellarg($dir . '/pg') . ' 2>/dev/null',
                    $o, $code
                );
                $img = $dir . '/pg.png';
                if (!is_file($img)) {
                    continue;
                }
                // `timeout` guards against tesseract hanging on a pathological page;
                // exit 124 = skipped page, we simply continue with the next one.
                // Persian-first keeps RTL logical order; mixing eng in the same
                // run can flip paragraph direction and mirror the lines.
                $hasTimeout = trim((string) @shell_exec('which timeout 2>/dev/null')) !== '';
                $tessCmd = escapeshellarg($tess) . ' ' . escapeshellarg($img) . ' ' . escapeshellarg($dir . '/t')
                    . ' -l fas --psm 6 2>/dev/null';
                if ($hasTimeout) {
                    $tessCmd = 'timeout 180 ' . $tessCmd;
                }
                @exec($tessCmd, $o2, $code2);
                $txt = is_file($dir . '/t.txt') ? trim((string) @file_get_contents($dir . '/t.txt')) : '';
                @unlink($dir . '/t.txt');
                // No Persian at all → probably an English-only page → retry in English.
                if (($txt === '' || !preg_match('/[\x{0600}-\x{06FF}]/u', $txt)) && is_file($img)) {
                    $engCmd = escapeshellarg($tess) . ' ' . escapeshellarg($img) . ' ' . escapeshellarg($dir . '/t')
                        . ' -l eng --psm 6 2>/dev/null';
                    if ($hasTimeout) {
                        $engCmd = 'timeout 180 ' . $engCmd;
                    }
                    @exec($engCmd, $o3, $code3);
                    $txtEn = is_file($dir . '/t.txt') ? trim((string) @file_get_contents($dir . '/t.txt')) : '';
                    @unlink($dir . '/t.txt');
                    if ($txtEn !== '') {
                        $txt = $txtEn;
                    }
                }
                @unlink($img);
                if ($txt !== '') {
                    $out[] = "— صفحه {$p} (OCR) —\n" . $txt;
                }
            }
        } finally {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }

        $text = implode("\n\n", $out);
        if ($text === '') {
            return ['text' => '', 'kind' => 'empty', 'detail' => 'OCR هم متنی از PDF پیدا نکرد'];
        }
        return ['text' => $text, 'kind' => 'ocr', 'detail' => count($out) . ' صفحه OCR شد'];
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

    protected static function docText(string $path): array
    {
        // Try catdoc, antiword, libreoffice, then strings fallback
        $bin = trim((string) @shell_exec('which catdoc 2>/dev/null'));
        if ($bin !== '') {
            $out = @shell_exec(escapeshellarg($bin) . ' ' . escapeshellarg($path) . ' 2>/dev/null');
            if (is_string($out) && trim($out) !== '' && !self::isGarbage($out)) {
                return ['text' => trim($out), 'kind' => 'text', 'detail' => 'catdoc'];
            }
        }
        $bin = trim((string) @shell_exec('which antiword 2>/dev/null'));
        if ($bin !== '') {
            $out = @shell_exec(escapeshellarg($bin) . ' ' . escapeshellarg($path) . ' 2>/dev/null');
            if (is_string($out) && trim($out) !== '' && !self::isGarbage($out)) {
                return ['text' => trim($out), 'kind' => 'text', 'detail' => 'antiword'];
            }
        }
        $bin = trim((string) @shell_exec('which libreoffice 2>/dev/null'));
        if ($bin !== '') {
            $tmpdir = sys_get_temp_dir() . '/doc2txt_' . bin2hex(random_bytes(4));
            @mkdir($tmpdir, 0700, true);
            @exec(escapeshellarg($bin) . ' --headless --convert-to txt:Text --outdir ' . escapeshellarg($tmpdir) . ' ' . escapeshellarg($path) . ' 2>/dev/null', $o, $code);
            $txtFile = $tmpdir . '/' . pathinfo($path, PATHINFO_FILENAME) . '.txt';
            if (is_file($txtFile)) {
                $txt = trim((string) @file_get_contents($txtFile));
                @unlink($txtFile);
                @rmdir($tmpdir);
                if ($txt !== '' && !self::isGarbage($txt)) {
                    return ['text' => $txt, 'kind' => 'text', 'detail' => 'libreoffice'];
                }
            }
            @rmdir($tmpdir);
        }
        // Fallback: strings extraction (works for many .doc with embedded text)
        $out = @shell_exec('strings ' . escapeshellarg($path) . ' 2>/dev/null | head -n 2000');
        if (is_string($out) && trim($out) !== '') {
            // Filter to keep only Persian/latin lines
            $lines = array_filter(array_map('trim', explode("\n", $out)), fn($l) => mb_strlen($l) > 3 && preg_match('/[\x{0600}-\x{06FF}A-Za-z]/u', $l));
            $txt = implode("\n", array_slice($lines, 0, 500));
            if ($txt !== '' && !self::isGarbage($txt)) {
                return ['text' => $txt, 'kind' => 'text', 'detail' => 'strings'];
            }
        }
        return self::unsupported('فرمت قدیمی .doc با این ابزار قابل خواندن نیست؛ لطفاً به .docx تبدیل کنید یا نسخه‌ی PDF آن را بارگذاری کنید');
    }

    protected static function pptText(string $path): array
    {
        $bin = trim((string) @shell_exec('which catppt 2>/dev/null'));
        if ($bin !== '') {
            $out = @shell_exec(escapeshellarg($bin) . ' ' . escapeshellarg($path) . ' 2>/dev/null');
            if (is_string($out) && trim($out) !== '' && !self::isGarbage($out)) {
                return ['text' => trim($out), 'kind' => 'text', 'detail' => 'catppt'];
            }
        }
        $out = @shell_exec('strings ' . escapeshellarg($path) . ' 2>/dev/null | head -n 2000');
        if (is_string($out) && trim($out) !== '') {
            $lines = array_filter(array_map('trim', explode("\n", $out)), fn($l) => mb_strlen($l) > 3 && preg_match('/[\x{0600}-\x{06FF}A-Za-z]/u', $l));
            $txt = implode("\n", array_slice($lines, 0, 500));
            if ($txt !== '' && !self::isGarbage($txt)) {
                return ['text' => $txt, 'kind' => 'text', 'detail' => 'strings'];
            }
        }
        return self::unsupported('فرمت قدیمی .ppt با این ابزار قابل خواندن نیست؛ لطفاً به .pptx تبدیل کنید');
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

    protected static function audioText(string $path): array
    {
        $r = SpeechToText::transcribe($path);
        if ($r === null) {
            return ['text' => '', 'kind' => 'empty', 'detail' => 'فایل صوتی است؛ رونویسی خودکار روی سرور فعال نیست'];
        }
        return ['text' => $r['text'], 'kind' => 'audio', 'detail' => $r['detail']];
    }

    protected static function ocrText(string $path): array
    {
        $bin = trim((string) @shell_exec('which tesseract 2>/dev/null'));
        if ($bin === '') {
            return self::unsupported('OCR نصب نیست؛ برای عکس فعلاً فقط نام فایل ثبت می‌شود');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ocr') . '.txt';
        $base = substr($tmp, 0, -4);
        // Primary pass with Persian: keeps RTL logical order. (fas+eng together
        // can flip paragraph direction and mirror the lines.)
        $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($path) . ' ' . escapeshellarg($base) . ' -l fas --psm 6 2>/dev/null';
        if (trim((string) @shell_exec('which timeout 2>/dev/null')) !== '') {
            $cmd = 'timeout 120 ' . $cmd;
        }
        @exec($cmd, $o, $code);
        $text = is_file($base . '.txt') ? trim((string) @file_get_contents($base . '.txt')) : '';
        @unlink($base . '.txt');
        // Fallback: page has no Persian at all → probably English-only → re-run with eng.
        if ($text === '' || !preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            $cmd2 = escapeshellarg($bin) . ' ' . escapeshellarg($path) . ' ' . escapeshellarg($base) . ' -l eng --psm 6 2>/dev/null';
            if (trim((string) @shell_exec('which timeout 2>/dev/null')) !== '') {
                $cmd2 = 'timeout 120 ' . $cmd2;
            }
            @exec($cmd2, $o2, $code2);
            $text2 = is_file($base . '.txt') ? trim((string) @file_get_contents($base . '.txt')) : '';
            @unlink($base . '.txt');
            if ($text2 !== '') {
                $text = $text2;
            }
        }
        @unlink($tmp);
        $text = trim($text);
        if ($text === '') {
            return ['text' => '', 'kind' => 'empty', 'detail' => 'از عکس متنی تشخیص داده نشد'];
        }
        return ['text' => $text, 'kind' => 'ocr', 'detail' => 'OCR فارسی'];
    }
}
