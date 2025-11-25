<?php
// lib/kb_ingest.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/text_utils.php';

use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordIO;

function _env_unquote(?string $s): string
{
    $s = $s ?? '';
    return trim($s, " \t\n\r\0\x0B\"'");
}
function _cmd_quote(string $s): string
{
    // Windows: dùng "…", *nix: dùng escapeshellarg
    if (DIRECTORY_SEPARATOR === '\\') {
        return '"' . str_replace('"', '\"', $s) . '"';
    }
    return escapeshellarg($s);
}

function _winarg(string $s): string
{
    // Quote kiểu Windows cho CreateProcess
    return '"' . str_replace('"', '\"', $s) . '"';
}

/** Lấy/đảm bảo source id */
function kb_ensure_source(PDO $pdo, string $name, string $platform = 'web', float $trust = 1.0, ?string $url = null): int
{
    $sel = $pdo->prepare("SELECT id FROM kb_sources WHERE source_name=? LIMIT 1");
    $sel->execute([$name]);
    if ($id = $sel->fetchColumn()) return (int)$id;

    $ins = $pdo->prepare("INSERT INTO kb_sources(platform,source_name,trust_level,url) VALUES (?,?,?,?)");
    $ins->execute([$platform, $name, $trust, $url]);
    return (int)$pdo->lastInsertId();
}

/** Upsert post theo md5 để tránh trùng */
function kb_upsert_post(PDO $pdo, int $sourceId, array $meta): int
{
    $sel = $pdo->prepare("SELECT id FROM kb_posts WHERE md5=? LIMIT 1");
    $sel->execute([$meta['md5']]);
    if ($id = $sel->fetchColumn()) return (int)$id;

    $sql = "INSERT INTO kb_posts(source_id, fb_post_id, title, message_raw, message_clean,
                                 topic, doc_type, permalink_url, created_time, updated_time,
                                 trust_level, md5)
            VALUES (NULLIF(?,0), NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $sourceId,
        $meta['title'] ?? null,
        $meta['raw'] ?? null,
        $meta['clean'] ?? null,
        $meta['topic'] ?? null,
        $meta['doc_type'] ?? null,
        $meta['url'] ?? null,
        $meta['created_time'] ?? null,
        $meta['updated_time'] ?? null,
        $meta['trust'] ?? 1.0,
        $meta['md5'],
    ]);
    return (int)$pdo->lastInsertId();
}

function kb_insert_chunks(PDO $pdo, int $postId, array $chunks, float $trust = 1.0)
{
    $ins = $pdo->prepare("INSERT INTO kb_chunks(post_id,chunk_idx,text,text_clean,tokens,trust_level)
                          VALUES (?,?,?,?,?,?)");
    foreach ($chunks as $i => $c) {
        $clean = tu_clean_text($c);
        $ins->execute([$postId, $i, $c, $clean, mb_strlen($c, 'UTF-8'), $trust]);
    }
}

function kb_parse_pdf(string $path): string
{
    $pagesDir = 'C:/temp_ocr/pdfpages_' . uniqid();
    if (!is_dir($pagesDir)) {
        mkdir($pagesDir, 0777, true);
    }
    if (!is_dir($pagesDir)) {
        throw new RuntimeException("Không thể tạo thư mục tạm: $pagesDir");
    } else {
        // echo "[DEBUG] Đã tạo thư mục: $pagesDir\n";
    }

    $pdftoppm = _env_unquote(envv('PDFTOPPM_BIN', 'pdftoppm'));
    $cmd = _cmd_quote($pdftoppm) . ' -png ' . _cmd_quote(str_replace('\\', '/', $path)) . ' ' . _cmd_quote(str_replace('\\', '/', $pagesDir . '/page'));

    // echo "[DEBUG CMD] $cmd\n";
    @exec($cmd, $outLines, $code);

    // echo "[DEBUG] Return code = $code\n";
    // echo "[DEBUG] Files sinh ra:\n";
    print_r(glob($pagesDir . '/*.png'));

    $text = '';
    foreach (glob($pagesDir . '/page*.png') as $img) {
        // echo "[DEBUG OCR] Đang đọc $img\n";
        try {
            $text .= kb_parse_image($img) . "\n";
        } catch (Throwable $e) {
            // echo "[ERROR OCR] " . $e->getMessage() . "\n";
        }
        @unlink($img);
    }

    if (trim($text) !== '') return $text;
    throw new RuntimeException('Unable to extract text from PDF (Smalot + OCR fallback failed).');
}



function kb_parse_docx(string $path): string
{
    // 1) Dùng PhpWord trước
    try {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $elem) {
                if (method_exists($elem, 'getText')) {
                    $text .= $elem->getText() . "\n";
                } elseif (method_exists($elem, 'getElements')) {
                    foreach ($elem->getElements() as $e2) {
                        if (method_exists($e2, 'getText')) $text .= $e2->getText() . "\n";
                    }
                }
            }
        }
        if (trim($text) !== '') return $text;
    } catch (Throwable $e) {
        // tiếp fallback
    }

    // 2) Fallback: đọc trực tiếp word/document.xml
    $zip = new ZipArchive();
    if ($zip->open($path) === true) {
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml) {
            // bỏ tag XML -> giữ text
            $txt = preg_replace('/<w:p[^>]*>/i', "\n", $xml);
            $txt = preg_replace('/<[^>]+>/', ' ', $txt);
            $txt = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $txt = preg_replace('/\s+/u', ' ', $txt);
            return trim($txt);
        }
    }

    throw new RuntimeException('Unable to extract text from DOCX (PhpWord & XML fallback failed).');
}

function kb_parse_image(string $path): string
{
    $binRaw = envv('TESSERACT_BIN', 'tesseract');
    $bin    = _env_unquote($binRaw);
    $tmpBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('ocr_');
    $tmpTxt  = $tmpBase . '.txt';

    // thêm -l vie nếu cần OCR tiếng Việt
    $cmd = _cmd_quote($bin) . ' ' . _cmd_quote($path) . ' ' . _cmd_quote($tmpBase) . ' -l vie';

    @exec($cmd, $outLines, $code);

    if ($code === 0 && is_file($tmpTxt)) {
        $txt = @file_get_contents($tmpTxt) ?: '';
        @unlink($tmpTxt);

        if ($txt !== '' && !mb_check_encoding($txt, 'UTF-8')) {
            $txt = mb_convert_encoding($txt, 'UTF-8', 'auto');
        }
        if (trim($txt) !== '') return $txt;
    }

    throw new RuntimeException("Unable to extract text from image via OCR (cmd=$cmd, code=$code)");
}
