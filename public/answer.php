<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/kb.php';
send_security_headers();
header('Content-Type: application/json; charset=utf-8');

if (!function_exists('vi_norm')) {
    function vi_norm(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;
        return preg_replace('/\s+/', ' ', trim($s));
    }
}

$debug = isset($_GET['debug']);

try {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') throw new Exception('Thiếu q');

    $pdo = db();
    $diag = [];
    $t0 = microtime(true);

    // 1. ưu tiên nguồn IUH Official – tin cậy cao
    $hits = kb_search_chunks_v2($pdo, $q, 10, [
        'source'    => 'IUH Official',
        'trust_min' => 0.9,
        'days'      => 730
    ]);
    $diag[] = 'v2:iuh,0.9/730=' . count($hits);

    // 2. nới lỏng
    if (!$hits) {
        $hits = kb_search_chunks_v2($pdo, $q, 10, [
            'source'    => 'IUH Official',
            'trust_min' => 0.7,
            'days'      => 1200
        ]);
        $diag[] = 'v2:iuh,0.7/1200=' . count($hits);
    }

    // 3. mọi nguồn
    if (!$hits) {
        $hits = kb_search_chunks_v2($pdo, $q, 10, [
            'source'    => '%',
            'trust_min' => 0.7,
            'days'      => 1200
        ]);
        $diag[] = 'v2:any,0.7/1200=' . count($hits);
    }

    // Gom theo post
    $byPost = [];
    foreach ($hits as $h) {
        $pid = $h['post_id'] ?? 0;
        if (!$pid) continue;
        if (!isset($byPost[$pid])) $byPost[$pid] = ['chunks' => [], 'bestScore' => -INF, 'meta' => $h];
        $byPost[$pid]['chunks'][] = $h;
        $byPost[$pid]['bestScore'] = max($byPost[$pid]['bestScore'], (float)($h['score'] ?? 0));
    }

    uasort($byPost, function ($a, $b) {
        $sa = $a['bestScore'];
        $sb = $b['bestScore'];
        $ta = strtotime($a['meta']['created_time'] ?? '1970-01-01');
        $tb = strtotime($b['meta']['created_time'] ?? '1970-01-01');
        return ($sb <=> $sa) ?: ($tb <=> $ta);
    });

    $answer = '';
    $cites  = [];

    if ($byPost) {
        $top = reset($byPost);
        $chunks = array_slice($top['chunks'], 0, 3);
        foreach ($chunks as $c) {
            $txt = $c['text_clean'] ?? $c['text'] ?? '';
            $txt = trim(preg_replace('/\s+/u', ' ', $txt));
            if ($txt !== '') $answer .= ($answer ? "\n\n" : '') . mb_substr($txt, 0, 700) . (mb_strlen($txt) > 700 ? '…' : '');
        }
        $m = $top['meta'];
        $cites[] = [
            'url'   => $m['permalink_url'] ?? '',
            'date'  => $m['created_time'] ?? '',
            'trust' => isset($m['trust']) ? (float)$m['trust'] : null,
            'title' => $m['title'] ?? ''
        ];
    }

    // Guardrail câu hỏi "tăng 2tr/tín chỉ"
    $norm = vi_norm($q);
    $askRaise2m = (preg_match('/(2\s*(tr|trieu)|2m)/u', $norm) && preg_match('/tin\s*chi/u', $norm))
        || preg_match('/tang/u', $norm);
    if ($askRaise2m) {
        $has2m = $answer && preg_match('/\b2\s*(tr|trieu)\b|\b2\.?000\.?000\b/u', vi_norm($answer));
        if (!$has2m) {
            $answer = "Chúng tôi **không tìm thấy** thông báo chính thức xác nhận *“tăng 2 triệu/tín chỉ”*. "
                . "Các bài chính thống gần đây chủ yếu nhắc **thời hạn đóng học phí** và hướng dẫn. "
                . "Vui lòng kiểm chứng tại liên kết trích dẫn bên dưới.";
        }
    }

    if ($answer === '') {
        $answer = 'Chưa thấy bài đăng chính thức khớp câu hỏi. '
            . 'Bạn thử diễn đạt khác (ví dụ: “học phí HK2 2024 2025”, “mức thu theo tín chỉ”).';
    }

    $out = ['answer' => $answer, 'citations' => $cites];
    if ($debug) $out['_debug'] = ['diag' => $diag, 't_ms' => round((microtime(true) - $t0) * 1000, 1), 'hits' => count($hits)];
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // luôn trả JSON để UI hiển thị thông báo
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
