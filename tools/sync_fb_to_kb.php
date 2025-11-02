<?php
// CLI: php tools/sync_fb_to_kb.php --since=30d --limit=200 --debug
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/fb_graph.php';
require_once __DIR__ . '/../lib/kb.php';

$sinceArg = '30d';
$limit    = 200;
$debug    = false;
foreach ($argv as $a) {
    if (preg_match('/--since=(\d+)([dhm])/', $a, $m)) $sinceArg = $m[1] . $m[2];
    if (preg_match('/--limit=(\d+)/', $a, $m)) $limit = (int)$m[1];
    if ($a === '--debug') $debug = true;
}

$mult = ['m' => 60, 'h' => 3600, 'd' => 86400];
$unit = substr($sinceArg, -1);
$num  = (int)substr($sinceArg, 0, -1);
$since = time() - $num * ($mult[$unit] ?? 86400);

echo "Sync FB → KB since=" . date(DATE_ATOM, $since) . " limit={$limit}\n";

$pdo = db();
$pageId = envv('FB_PAGE_ID');
if (!$pageId) {
    fwrite(STDERR, "Missing FB_PAGE_ID in .env\n");
    exit(2);
}

$fields = 'id,message,created_time,updated_time,permalink_url';
$collected = [];
$after = null;

try {
    while (count($collected) < $limit) {
        $batch = min(100, $limit - count($collected)); // Graph ≤100
        $params = ['since' => $since, 'limit' => $batch, 'fields' => $fields];
        if ($after) $params['after'] = $after;

        if ($debug) echo "[DEBUG] GET /{$pageId}/posts?limit={$batch}" . ($after ? "&after=$after" : '') . "\n";
        $resp = fb_api("/{$pageId}/posts", $params);

        // Lỗi token: code 190
        if (isset($resp['error'])) {
            $msg = json_encode($resp['error'], JSON_UNESCAPED_UNICODE);
            if (strpos($msg, '"code":190') !== false) {
                fwrite(STDERR, "Token hết hạn/không hợp lệ. Cập nhật FB_PAGE_ACCESS_TOKEN rồi chạy lại.\n");
                exit(2);
            }
            throw new Exception($msg);
        }

        $data = $resp['data'] ?? [];
        if ($debug) echo "[DEBUG] received " . count($data) . " posts\n";
        if (!$data) break;

        foreach ($data as $row) {
            $collected[] = $row;
            if (count($collected) >= $limit) break;
        }

        $after = $resp['paging']['cursors']['after'] ?? null;
        if (!$after) break;
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Graph error: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Fetched posts: " . count($collected) . "\n";

$ok = 0;
$skip = 0;
foreach ($collected as $p) {
    try {
        // lấy bản đầy đủ (vì /posts trả thiếu field đôi khi)
        $full = fb_api('/' . $p['id'], ['fields' => $fields]);
        $id = kb_upsert_post_from_fb($pdo, $full);
        if ($id) {
            $ok++;
        } else {
            $skip++;
        }
        if ($debug) echo "UPSERT OK: {$p['id']} → kb_post_id=$id\n";
    } catch (Throwable $e) {
        $skip++;
        if ($debug) echo "UPSERT ERR {$p['id']}: " . $e->getMessage() . "\n";
    }
}
echo "Done. inserted/updated=$ok skipped=$skip\n";
