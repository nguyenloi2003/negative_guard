<?php

/**
 * CLI: php tools/auto_moderate.php --window=60 --limit=50 --reply --hide --debug
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/fb_graph.php';
require_once __DIR__ . '/../lib/openai_client.php';
require_once __DIR__ . '/../lib/answer_kb.php'; // <- dùng KB để định hướng

$pdo = db();

/* ---------------------- ARGUMENTS ---------------------- */
$ARG = [
    'window'  => (int) envv('AUTO_SCAN_WINDOW_MINUTES', 60),
    'limit'   => (int) envv('AUTO_MAX_COMMENTS_PER_RUN', 50),
    'reply'   => filter_var(envv('AUTO_REPLY_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
    'hide'    => filter_var(envv('AUTO_ACTION_HIDE', 'false'),   FILTER_VALIDATE_BOOLEAN),
    'debug'   => false,
    'dry-run' => false,
];
foreach ($argv as $a) {
    if (preg_match('/--window=(\d+)/', $a, $m)) $ARG['window']  = (int)$m[1];
    if (preg_match('/--limit=(\d+)/', $a, $m))  $ARG['limit']   = (int)$m[1];
    if ($a === '--reply')   $ARG['reply']   = true;
    if ($a === '--hide')    $ARG['hide']    = true;
    if ($a === '--debug')   $ARG['debug']   = true;
    if ($a === '--dry-run') $ARG['dry-run'] = true;
}

/* ---------------------- ENV FLAGS ---------------------- */
$threshold     = (int) envv('AUTO_RISK_THRESHOLD', 60);
$prefix        = trim(envv('AUTO_REPLY_PREFIX', '[BQT]'));
$pageId        = envv('FB_PAGE_ID');
$pageTokenTail = substr((string) envv('FB_PAGE_ACCESS_TOKEN'), -6) ?: 'no-token';

$kbEnabled     = filter_var(envv('AUTO_KB_REPLY_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN);
$kbCooldown    = (int) envv('AUTO_KB_REPLY_COOLDOWN_SEC', 12);
$ZWJ           = "\u{200B}"; // zero-width joiner

echo "AutoModerate window={$ARG['window']}m limit={$ARG['limit']} thr={$threshold} hide="
    . ($ARG['hide'] ? 'Y' : 'N') . " reply=" . ($ARG['reply'] ? 'Y' : 'N')
    . " dry=" . ($ARG['dry-run'] ? 'Y' : 'N') . PHP_EOL;

/* WHOAMI (chẩn đoán nhanh token) */
try {
    $me = fb_api('/me', ['fields' => 'id,name']);
    echo "WHOAMI: {$me['id']} {$me['name']} | PAGE_ID={$pageId} TOKEN=***{$pageTokenTail}\n";
} catch (Throwable $e) {
    echo "Graph WHOAMI failed: {$e->getMessage()}\n";
}

/* ---------------------- HELPERS ---------------------- */
function already_done(string $commentId, string $action): bool
{
    $st = db()->prepare('SELECT 1 FROM auto_actions WHERE object_id=? AND action=? LIMIT 1');
    $st->execute([$commentId, $action]);
    return (bool)$st->fetchColumn();
}
function mark_done(string $commentId, string $action, int $risk, string $reason, ?string $payload = null): void
{
    $st = db()->prepare('INSERT IGNORE INTO auto_actions(object_id,object_type,action,risk,reason,response_text) VALUES (?,?,?,?,?,?)');
    $st->execute([$commentId, 'comment', $action, $risk, $reason, $payload]);
}
function cooldown(float $sec): void
{
    if ($sec <= 0) return;
    usleep((int)round($sec * 1e6));
}

/* ---------------------- MAIN ---------------------- */
$sinceUnix  = time() - $ARG['window'] * 60;
$processed  = 0;
$scanned    = 0;
$high_risk  = 0;

try {
    // lấy các bài viết mới
    $posts = fb_get_page_posts_since($sinceUnix, 25);
    if ($ARG['debug']) {
        $countPosts = count($posts['data'] ?? []);
        echo "[DEBUG] posts batch={$countPosts}\n";
    }

    foreach (($posts['data'] ?? []) as $p) {
        $postId   = $p['id'];
        $comments = fb_get_post_comments_since($postId, $sinceUnix, 100);

        foreach (($comments['data'] ?? []) as $c) {
            if ($processed >= $ARG['limit']) {
                echo "Reached batch limit\n";
                break 2;
            }

            $cid  = $c['id'];
            $from = $c['from']['id'] ?? '';
            $msg  = trim($c['message'] ?? '');
            if ($msg === '') continue;
            $scanned++;

            // bỏ qua comment của chính page
            if ($pageId && $from === $pageId) continue;

            // bỏ qua nếu đã xử lý
            if (already_done($cid, 'replied') || already_done($cid, 'hidden')) continue;

            /* ---- 1) THỬ TRẢ LỜI ĐỊNH HƯỚNG TỪ KB (nếu là câu hỏi) ---- */
            if ($kbEnabled && looks_like_question($msg)) {
                try {
                    $ans = kb_best_answer_string($pdo, $msg);
                    if ($ans['ok']) {
                        $reply = $prefix . ' ' . $ans['text'] . ' ' . $ZWJ . mt_rand(0, 9);

                        if ($ARG['reply'] && !$ARG['dry-run']) {
                            fb_comment($cid, $reply);
                            mark_done($cid, 'replied', 0, 'kb_answer', $reply);
                            if ($ARG['debug']) echo "KB replied {$cid}\n";
                            $processed++;
                            cooldown($kbCooldown);
                        } else {
                            echo "[DRY] KB reply {$cid}\n";
                        }
                        // KB đã trả lời thì không cần rơi vào nhánh risk nữa
                        continue;
                    }
                } catch (Throwable $e) {
                    if ($ARG['debug']) echo "KB answer error: {$e->getMessage()}\n";
                }
            }

            /* ---- 2) CHẤM ĐIỂM RỦI RO & HÀNH ĐỘNG ---- */
            $res  = analyze_text_with_schema($msg);
            $risk = (int)($res['overall_risk'] ?? 0);

            if ($risk < $threshold) {
                mark_done($cid, 'skipped', $risk, 'under_threshold');
                continue;
            }
            $high_risk++;

            // quyết định nội dung trả lời (tuỳ nhãn bật)
            $labels   = $res['labels'] ?? [];
            $template = '';
            if (!empty($labels['scam_phishing'])) {
                $template = "Cảnh báo: Có dấu hiệu mời chào/lừa đảo hoặc liên hệ ngoài nền tảng. "
                    . "Vui lòng cảnh giác, không cung cấp thông tin cá nhân hay chuyển tiền. "
                    . "Nếu có nguồn chính thống, vui lòng đính kèm để mọi người kiểm chứng.";
            } elseif (!empty($labels['hate_speech'])) {
                $template = "Nhắc nhở: Vui lòng giữ bình luận văn minh, tránh lời lẽ xúc phạm/công kích. "
                    . "Hãy tập trung vào thông tin và dẫn nguồn xác thực.";
            } elseif (!empty($labels['misinformation'])) {
                $template = "Lưu ý: Nội dung có thể thiếu nguồn xác thực. "
                    . "Vui lòng bổ sung đường dẫn đến nguồn tin cậy (công bố chính thức/bài viết của IUH).";
            } else {
                $template = "Lưu ý: Bình luận có rủi ro gây hiểu nhầm. "
                    . "Bạn vui lòng kiểm chứng và sử dụng ngôn từ phù hợp.";
            }
            $reply = $prefix . ' ' . $template . ' ' . $ZWJ . mt_rand(0, 9);

            try {
                // Reply nhắc nhở
                if ($ARG['reply'] && !already_done($cid, 'replied')) {
                    if ($ARG['dry-run']) {
                        echo "[DRY] reply {$cid}\n";
                    } else {
                        fb_comment($cid, $reply);
                        mark_done($cid, 'replied', $risk, 'auto_reply', $reply);
                        echo "Replied  {$cid} (risk={$risk})\n";
                        $processed++;
                        cooldown(0.6);
                    }
                }

                // Hide nếu bật
                if ($ARG['hide'] && !already_done($cid, 'hidden')) {
                    if ($ARG['dry-run']) {
                        echo "[DRY] hide {$cid}\n";
                    } else {
                        fb_hide_comment($cid, true);
                        mark_done($cid, 'hidden', $risk, 'auto_hide');
                        echo "Hidden   {$cid} (risk={$risk})\n";
                        $processed++;
                        cooldown(0.6);
                    }
                }
            } catch (Throwable $act) {
                $msgErr = substr($act->getMessage(), 0, 800);
                mark_done($cid, 'error', $risk, 'fb_error', $msgErr);
                echo "Action error {$cid}: {$msgErr}\n";
                // Nếu FB throttle spam (subcode 1446036) → sleep lâu
                if (strpos($msgErr, '1446036') !== false) {
                    echo "Detected spam throttle, sleeping 60s…\n";
                    sleep(60);
                }
            }
        }
    }
} catch (Throwable $e) {
    echo "Fatal: " . $e->getMessage() . PHP_EOL;
}

echo "Done. scanned={$scanned} high_risk={$high_risk} processed={$processed}\n";
