<?php
// CLI: php tools/ingest_docs.php --dir=storage/iuh_docs --ext=pdf,docx,png,jpg,jpeg,tiff
if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../vendor/autoload.php';         // autoload TRƯỚC để tải lib PDF/Word
require_once __DIR__ . '/../lib/kb_ingest.php';
require_once __DIR__ . '/../lib/text_utils.php';

$opts = [
    'dir'     => null,
    'source'  => 'IUHDemo',
    'trust'   => 1.00,
    'topic'   => 'auto',  // auto → đoán từ nội dung
    'doc'     => 'auto',
    'verbose' => false,
    'force'   => false,   // true → xóa chunk cũ, nạp lại
    'ext'     => 'pdf,docx', // phần mở rộng cho phép
];

foreach ($argv as $a) {
    if (preg_match('/--dir=(.+)/',   $a, $m)) $opts['dir']     = $m[1];
    if (preg_match('/--source=(.+)/', $a, $m)) $opts['source']  = $m[1];
    if (preg_match('/--trust=(\d+(?:\.\d+)?)/', $a, $m)) $opts['trust'] = (float)$m[1];
    if (preg_match('/--topic=(.+)/', $a, $m)) $opts['topic']   = $m[1];
    if (preg_match('/--doc=(.+)/',   $a, $m)) $opts['doc']     = $m[1];
    if ($a === '--verbose') $opts['verbose'] = true;
    if ($a === '--force')   $opts['force']   = true;
    if (preg_match('/--ext=([a-z0-9,]+)/i', $a, $m)) $opts['ext'] = strtolower($m[1]);
}

function vprintln($s)
{
    global $opts;
    if ($opts['verbose']) echo $s . PHP_EOL;
}

if (!$opts['dir'] || !is_dir($opts['dir'])) {
    exit("Usage: php tools/ingest_docs.php --dir=/path/to/folder [--source='IUHDemo'] [--trust=1.0] [--topic=auto] [--doc=auto] [--verbose] [--force] [--ext=pdf,docx]\n");
}
$allowExt = array_filter(array_map('trim', explode(',', $opts['ext'])));
$allowExt = $allowExt ?: ['pdf', 'docx'];

// Chuẩn bị PDO & source
$pdo   = db();
$srcId = kb_ensure_source($pdo, $opts['source'], 'web', $opts['trust']);

// Log pdftotext (nếu có)
$pdftotext = envv('PDFTOTEXT_BIN', 'pdftotext');
if ($opts['verbose']) {
    @exec($pdftotext . ' -v', $out, $code);
    if ($code === 0 && !empty($out)) {
        echo "[pdftotext] " . $out[0] . PHP_EOL;
    } else {
        echo "[pdftotext] not found or not in PATH. Set PDFTOTEXT_BIN in .env if needed." . PHP_EOL;
    }
}

// Duyệt thư mục
$rii   = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($opts['dir']));
$new   = 0;
$exist = 0;
$upd   = 0;

foreach ($rii as $f) {
    if ($f->isDir()) continue;

    $path = $f->getPathname();
    // Normalize path cho Windows (\ → / chỉ để log; exec vẫn dùng escapeshellarg trong kb_parse_pdf)
    $logPath = str_replace('\\', DIRECTORY_SEPARATOR, $path);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        $raw = kb_parse_pdf($path);
    } elseif ($ext === 'docx') {
        $raw = kb_parse_docx($path);
    } elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'tiff'])) {
        $raw = kb_parse_image($path);
    }

    if (!in_array($ext, $allowExt, true)) continue;

    echo ">> {$logPath}\n";

    try {
        $raw = ($ext === 'pdf') ? kb_parse_pdf($path) : kb_parse_docx($path);
        $raw = trim($raw);
        if ($raw === '') {
            echo "   (empty)\n";
            continue;
        }

        $clean = tu_clean_text($raw);
        $topic = ($opts['topic'] === 'auto') ? (tu_guess_topic($clean) ?? null)    : $opts['topic'];
        $dtype = ($opts['doc']   === 'auto') ? (tu_guess_doc_type($clean) ?? null) : $opts['doc'];
        $title = basename($path);
        $md5   = md5($clean);
        $ctime = date('Y-m-d H:i:s', @filemtime($path) ?: time());

        // Kiểm tra tồn tại theo md5
        $sel = $pdo->prepare("SELECT id FROM kb_posts WHERE md5=? LIMIT 1");
        $sel->execute([$md5]);
        $existId = (int)$sel->fetchColumn();

        if ($existId && !$opts['force']) {
            echo "   = existed (skip chunks)\n";
            $exist++;
            continue;
        }

        // Nếu --force và đã tồn tại: xoá chunk để nạp lại
        if ($existId && $opts['force']) {
            $pdo->prepare("DELETE FROM kb_chunks WHERE post_id=?")->execute([$existId]);
            $upd++;
            $postId = $existId;
            echo "   ~ force re-chunk post #{$postId}\n";
        } else {
            // upsert post theo md5
            $postId = kb_upsert_post($pdo, $srcId, [
                'title'        => $title,
                'raw'          => $raw,
                'clean'        => $clean,
                'topic'        => $topic,
                'doc_type'     => $dtype,
                'url'          => null,
                'created_time' => $ctime,
                'updated_time' => $ctime,
                'trust'        => $opts['trust'],
                'md5'          => $md5,
            ]);
        }

        // Kiểm tra số chunk hiện có
        $chk = $pdo->prepare("SELECT COUNT(*) FROM kb_chunks WHERE post_id=?");
        $chk->execute([$postId]);
        $has = (int)$chk->fetchColumn();

        if ($has > 0 && !$opts['force']) {
            echo "   = existed (keep existing chunks)\n";
            $exist++;
            continue;
        }

        // Tạo & chèn chunks
        $chunks = tu_chunk_text($clean, 1800, 200);
        kb_insert_chunks($pdo, $postId, $chunks, $opts['trust']);
        echo "   + inserted post #{$postId}, chunks=" . count($chunks) . "\n";

        if ($existId && $opts['force']) {
            // tính là cập nhật
        } else {
            $new++;
        }
    } catch (Throwable $e) {
        echo "   ! error: " . $e->getMessage() . "\n";
        if ($opts['verbose']) {
            echo "     trace: " . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
        }
    }
}

echo "DONE. new={$new}, updated={$upd}, existed={$exist}\n";
