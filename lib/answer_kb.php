<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/kb.php';

if (!function_exists('vi_norm')) {
    function vi_norm(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $x = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($x !== false) $s = $x;
        $s = preg_replace('/[^a-z0-9\s]/', ' ', $s);
        return preg_replace('/\s+/', ' ', trim($s));
    }
}

/**
 * Trả lời ngắn gọn dựa trên KB + link kiểm chứng.
 * Return: ['ok'=>bool, 'text'=>string, 'cite'=>['url'=>..,'date'=>..]]
 */
function kb_best_answer_string(PDO $pdo, string $q): array
{
    $trustMin = (float) envv('AUTO_KB_REPLY_MIN_TRUST', 0.85);
    $days     = (int)   envv('AUTO_KB_REPLY_TIMEBOX_DAYS', 730);

    $hits = kb_search_chunks_v2($pdo, $q, 6, [
        'source'    => 'IUH Official',
        'trust_min' => $trustMin,
        'days'      => $days
    ]);

    // fallback nhẹ nếu chưa thấy
    if (!$hits) {
        $hits = kb_search_chunks_v2($pdo, $q, 6, [
            'source' => 'IUH Official',
            'trust_min' => max(0.7, $trustMin - 0.1),
            'days' => $days + 365
        ]);
    }
    if (!$hits) return ['ok' => false];

    // gom theo post, lấy post điểm cao nhất
    $byPost = [];
    foreach ($hits as $h) {
        $pid = $h['post_id'] ?? 0;
        if (!$pid) continue;
        if (!isset($byPost[$pid])) $byPost[$pid] = ['score' => -INF, 'm' => $h, 'chunks' => []];
        $byPost[$pid]['score'] = max($byPost[$pid]['score'], (float)($h['score'] ?? 0));
        $byPost[$pid]['chunks'][] = $h;
    }
    uasort($byPost, fn($a, $b) => ($b['score'] <=> $a['score']));
    $top = reset($byPost);
    if (!$top) return ['ok' => false];

    // ghép 1–2 đoạn cho súc tích
    $ans = '';
    foreach (array_slice($top['chunks'], 0, 2) as $c) {
        $t = trim(preg_replace('/\s+/u', ' ', $c['text_clean'] ?? $c['text'] ?? ''));
        if ($t !== '') $ans .= ($ans ? ' ' : '') . $t;
    }
    $ans = mb_substr($ans, 0, 500);

    // guardrail ví dụ tin “tăng 2 triệu / tín chỉ”
    $normQ = vi_norm($q);
    if (preg_match('/(2\s*(tr|trieu)|2m)/', $normQ) && preg_match('/tin chi/', $normQ)) {
        // nếu nội dung không hề nhắc tăng 2tr → phủ định mềm
        if (!preg_match('/(2[\s\.]*000[\s\.]*000|2\s*(tr|trieu))/i', vi_norm($ans))) {
            $ans = "Hiện **không thấy** thông báo chính thức về *“tăng 2 triệu/tín chỉ”*. "
                . "Thông tin gần đây chủ yếu là **thời hạn đóng học phí** và hướng dẫn thanh toán. ";
        }
    }

    $m = $top['m'];
    $cite = [
        'url'  => $m['permalink_url'] ?? '',
        'date' => $m['created_time'] ?? '',
    ];

    // gói câu trả lời dạng thân thiện bình luận
    $out = $ans;
    if ($cite['url']) $out .= " (Nguồn: " . $cite['url'] . ")";
    return ['ok' => true, 'text' => $out, 'cite' => $cite];
}

/** Phân loại nhanh comment có phải câu hỏi/định hướng cần KB không */
function looks_like_question(string $msg): bool
{
    $n = vi_norm($msg);
    if (str_contains($n, '?')) return true;
    $kw = ['bao nhieu', 'gia', 'hoc phi', 'tang', 'lich', 'thoi han', 'sao', 'co phai', 'dung khong', 'thong bao', 'thi thu', 'thoi gian', 'tu van'];
    foreach ($kw as $k) if (str_contains($n, $k)) return true;
    return false;
}
