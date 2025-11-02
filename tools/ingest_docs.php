<?php
// CLI: php tools/ingest_docs.php --dir=storage/iuh_docs --source="IUH Official" --trust=1.0 --topic=auto --doc=auto
if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/kb_ingest.php';
require_once __DIR__ . '/../lib/text_utils.php';
require_once __DIR__ . '/../vendor/autoload.php';

$opts = [
    'dir'    => null,
    'source' => 'IUH Official',
    'trust'  => 1.00,
    'topic'  => 'auto',   // auto → đoán từ nội dung
    'doc'    => 'auto',
];
foreach ($argv as $a) {
    if (preg_match('/--dir=(.+)/', $a, $m))    $opts['dir']    = $m[1];
    if (preg_match('/--source=(.+)/', $a, $m)) $opts['source'] = $m[1];
    if (preg_match('/--trust=(\d+(?:\.\d+)?)/', $a, $m)) $opts['trust'] = (float)$m[1];
    if (preg_match('/--topic=(.+)/', $a, $m)) $opts['topic']   = $m[1];
    if (preg_match('/--doc=(.+)/', $a, $m))   $opts['doc']     = $m[1];
}
if (!$opts['dir'] || !is_dir($opts['dir'])) {
    exit("Usage: php tools/ingest_docs.php --dir=/path/to/folder [--source='IUH Official'] [--trust=1.0]\n");
}

$pdo = db();
$srcId = kb_ensure_source($pdo, $opts['source'], 'web', $opts['trust']);

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($opts['dir']));
$count = 0;
$skip = 0;
foreach ($rii as $f) {
    if ($f->isDir()) continue;
    $path = $f->getPathname();
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'docx'])) continue;

    echo ">> $path\n";
    try {
        $raw = ($ext === 'pdf') ? kb_parse_pdf($path) : kb_parse_docx($path);
        $raw = trim($raw);
        if ($raw === '') {
            echo "   (empty)\n";
            continue;
        }

        $clean  = tu_clean_text($raw);
        $topic  = ($opts['topic'] === 'auto') ? (tu_guess_topic($clean) ?? null) : $opts['topic'];
        $dtype  = ($opts['doc'] === 'auto')   ? (tu_guess_doc_type($clean) ?? null) : $opts['doc'];
        $title  = basename($path);
        $md5    = md5($clean);
        $ctime  = date('Y-m-d H:i:s', filemtime($path));

        // upsert post theo md5
        $postId = kb_upsert_post($pdo, $srcId, [
            'title' => $title,
            'raw'   => $raw,
            'clean' => $clean,
            'topic' => $topic,
            'doc_type' => $dtype,
            'url'   => null,                  // có thể lưu path tải về nếu muốn
            'created_time' => $ctime,
            'updated_time' => $ctime,
            'trust' => $opts['trust'],
            'md5'   => $md5,
        ]);

        // nếu post mới được thêm, chèn chunks
        $chk = $pdo->prepare("SELECT COUNT(*) FROM kb_chunks WHERE post_id=?");
        $chk->execute([$postId]);
        if ((int)$chk->fetchColumn() === 0) {
            $chunks = tu_chunk_text($clean, 1800, 200);
            kb_insert_chunks($pdo, $postId, $chunks, $opts['trust']);
            echo "   + inserted post #$postId, chunks=" . count($chunks) . "\n";
            $count++;
        } else {
            echo "   = existed (skip chunks)\n";
            $skip++;
        }
    } catch (Throwable $e) {
        echo "   ! error: " . $e->getMessage() . "\n";
    }
}

echo "DONE. new=$count, existed=$skip\n";
