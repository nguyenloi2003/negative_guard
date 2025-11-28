<?php
// php  (bản robust + debug)
// Chấm điểm comment gần đây, tự lọc theo created_time (không dùng 'since' của Graph)

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/fb_graph.php';
require_once __DIR__ . '/../lib/openai_client.php';

function aa_upsert(string $objectId, string $objectType, string $action, int $risk, string $reason = '', ?string $responseText = null): void
{
    $pdo = db();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $sql = 'INSERT INTO auto_actions(object_id,object_type,action,risk,reason,response_text)
                VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  risk = GREATEST(risk, VALUES(risk)),
                  reason = VALUES(reason),
                  response_text = COALESCE(VALUES(response_text), response_text),
                  created_at = CURRENT_TIMESTAMP';
    } else {
        $sql = 'INSERT INTO auto_actions(object_id,object_type,action,risk,reason,response_text)
                VALUES (?,?,?,?,?,?)
                ON CONFLICT(object_id, action) DO UPDATE SET
                  risk = MAX(auto_actions.risk, excluded.risk),
                  reason = excluded.reason,
                  response_text = COALESCE(excluded.response_text, auto_actions.response_text),
                  created_at = CURRENT_TIMESTAMP';
    }
    $pdo->prepare($sql)->execute([$objectId, $objectType, $action, $risk, $reason, $responseText]);
}

$window    = (int)($argv[1] ?? 30);                       // phút
$DEBUG     = in_array('--debug', $argv, true) || in_array('-d', $argv, true);
$threshold = (int)envv('AUTO_RISK_THRESHOLD', 60);
$doHide    = filter_var(envv('AUTO_ACTION_HIDE', 'false'), FILTER_VALIDATE_BOOLEAN);
$doReply   = filter_var(envv('AUTO_REPLY_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN);
$prefix    = envv('AUTO_REPLY_PREFIX', '⚠️[BQT]');
$pageId    = envv('FB_PAGE_ID');

$sinceUnix = time() - max(0, $window) * 60;
$noTimeFilter = ($window === 0);
$out = ['scanned' => 0, 'high_risk' => 0, 'replied' => 0, 'hidden' => 0, 'skipped' => 0, 'posts' => 0];


if ($DEBUG) {
    $sinceStr = $noTimeFilter ? 'NO_TIME_FILTER' : gmdate('c', $sinceUnix);
    fwrite(STDERR, "PAGE_ID={$pageId}, window={$window}m, since={$sinceStr}\n");
    fwrite(STDERR, "CFG: doReply=" . ($doReply ? 'true' : 'false') . ", doHide=" . ($doHide ? 'true' : 'false') . ", threshold={$threshold}\n");
}

try {
    // 1) Lấy 25 post mới nhất (không lọc)
    $postsRes = fb_api("/$pageId/posts", [
        'limit'  => 25,
        'fields' => 'id,created_time,permalink_url'
    ]);
    $posts = $postsRes['data'] ?? [];
    $out['posts'] = count($posts);
    if ($DEBUG) fwrite(STDERR, "Posts fetched: " . count($posts) . PHP_EOL);
} catch (Exception $e) {
    echo json_encode(['error' => 'Graph posts: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}

// 2) Với mỗi post: lấy comment (phân trang), rồi tự lọc theo created_time
foreach ($posts as $p) {
    $pid = $p['id'];
    $postTime = strtotime($p['created_time'] ?? '1970-01-01');
    if ($DEBUG) fwrite(STDERR, "Post $pid @ " . ($p['created_time'] ?? '?') . PHP_EOL);

    $after = null;
    do {
        $params = [
            'filter' => 'stream',
            'limit'  => 100,
            'fields' => 'id,from{id,name},message,created_time'
        ];
        if ($after) $params['after'] = $after;

        try {
            $commentsRes = fb_api("/$pid/comments", $params);
        } catch (Exception $e) {
            if ($DEBUG) fwrite(STDERR, "  comments error: " . $e->getMessage() . PHP_EOL);
            break;
        }

        $chunk = $commentsRes['data'] ?? [];
        if ($DEBUG) fwrite(STDERR, "  chunk comments: " . count($chunk) . PHP_EOL);

        foreach ($chunk as $c) {
            $cid  = $c['id'] ?? '';
            $msg  = trim($c['message'] ?? '');
            $fromId   = $c['from']['id']   ?? '';
            $fromName = $c['from']['name'] ?? '(unknown)';
            $ct       = strtotime($c['created_time'] ?? '1970-01-01');

            if (!$cid || $msg === '') continue;
            if (!$noTimeFilter && $ct < $sinceUnix) continue;

            $isFromPage = ($pageId && $fromId === $pageId);

            // nếu đã hành động thì thôi
            $chk = db()->prepare('SELECT 1 FROM auto_actions WHERE object_id=? AND action IN ("replied","hidden") LIMIT 1');
            $chk->execute([$cid]);
            if ($chk->fetchColumn()) {
                $out['skipped']++;
                if ($DEBUG) fwrite(STDERR, "      skip: already replied/hidden before\n");
                continue;
            }

            $out['scanned']++;
            if ($DEBUG) {
                $preview = mb_substr($msg, 0, 80);
                fwrite(STDERR, "    + scan $cid by {$fromName} @ {$c['created_time']} :: {$preview}\n");
            }

            // analyze (try/catch)
            try {
                $ar   = analyze_text_with_schema($msg);
            } catch (Throwable $e) {
                aa_upsert($cid, 'comment', 'skipped', 0, 'analyze_error:' . substr($e->getMessage(), 0, 120));
                $out['skipped']++;
                if ($DEBUG) fwrite(STDERR, "      analyze_error: " . $e->getMessage() . "\n");
                continue;
            }

            $risk   = (int)($ar['overall_risk'] ?? 0);
            $labels = $ar['labels'] ?? [];
            if ($DEBUG) fwrite(STDERR, "      risk={$risk} labels=" . json_encode($labels, JSON_UNESCAPED_UNICODE) . "\n");

            // log score
            aa_upsert($cid, 'comment', 'score', $risk, 'cli_scan');

            if ($risk < $threshold) {
                aa_upsert($cid, 'comment', 'skipped', $risk, 'under_threshold');
                if ($DEBUG) fwrite(STDERR, "      skip: under_threshold {$risk}<{$threshold}\n");
                continue;
            }
            $out['high_risk']++;

            // chọn template
            if (!empty($labels['scam_phishing'])) {
                $tpl = "Cảnh báo: Có dấu hiệu mời chào/lừa đảo. Vui lòng cảnh giác, không cung cấp thông tin cá nhân hay chuyển tiền.";
            } elseif (!empty($labels['hate_speech'])) {
                $tpl = "Nhắc nhở: Xin giữ trao đổi văn minh, tránh lời lẽ xúc phạm/công kích.";
            } elseif (!empty($labels['misinformation'])) {
                $tpl = "Lưu ý: Nội dung có thể chưa đủ nguồn xác thực. Vui lòng bổ sung đường dẫn đến nguồn tin cậy.";
            } else {
                $tpl = "Lưu ý: Nội dung có rủi ro gây hiểu nhầm. Vui lòng kiểm chứng và sử dụng ngôn từ phù hợp.";
            }
            $reply = trim($prefix . ' ' . $tpl);

            // === TỰ TRẢ LỜI ===
            if ($isFromPage) {
                if ($DEBUG) fwrite(STDERR, "      skip reply: fromPage=true (comment của chính Page)\n");
            } elseif (!$doReply) {
                if ($DEBUG) fwrite(STDERR, "      skip reply: doReply=false (config)\n");
            } else {
                try {
                    fb_comment($cid, $reply);
                    aa_upsert($cid, 'comment', 'replied', 0, 'cli_scan', $reply);
                    $out['replied']++;
                    if ($DEBUG) fwrite(STDERR, "      replied OK\n");
                    usleep(600000);
                } catch (Throwable $eAct) {
                    $reason = (strpos($eAct->getMessage(), '1446036') !== false) ? 'spam_blocked' : 'reply_error:' . substr($eAct->getMessage(), 0, 120);
                    aa_upsert($cid, 'comment', 'skipped', $risk, $reason, $reply);
                    if ($DEBUG) fwrite(STDERR, "      reply ERROR: " . $eAct->getMessage() . "\n");
                }
            }
            // === ẨN ===
            if ($isFromPage) {
                if ($DEBUG && $doHide) fwrite(STDERR, "      skip hide: fromPage=true\n");
            } elseif ($doHide) {
                try {
                    fb_hide_comment($cid, true);
                    aa_upsert($cid, 'comment', 'hidden', 0, 'cli_scan');
                    $out['hidden']++;
                    if ($DEBUG) fwrite(STDERR, "      hidden OK\n");
                    usleep(600000);
                } catch (Throwable $eAct) {
                    aa_upsert($cid, 'comment', 'skipped', $risk, 'hide_error:' . substr($eAct->getMessage(), 0, 120));
                    if ($DEBUG) fwrite(STDERR, "      hide ERROR: " . $eAct->getMessage() . "\n");
                }
            }
        }

        $after = $commentsRes['paging']['cursors']['after'] ?? null;
    } while ($after);
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

if ($out['scanned'] > 0) {
    echo "Triggering KB sync...\n";
    $syncCmd = 'php ' . __DIR__ . '/sync_fb_to_kb.php --since=1d --limit=50';
    passthru($syncCmd);
}
