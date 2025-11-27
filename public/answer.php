<?php
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/kb.php';            // kb_search_chunks_v2
require_once __DIR__ . '/../lib/openai_client.php'; // openai_post + _extract_text_from_response
require_once __DIR__ . '/../lib/text_utils.php';
require_once __DIR__ . '/../lib/kb_qa.php';         // kb_answer_cutoff
require_once __DIR__ . '/../lib/prompt_template.php';

send_security_headers();
header('Content-Type: application/json; charset=utf-8');

try {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') throw new Exception('Thiếu q');

    $pdo = db();

    /* =========================
     * 0) LUẬT ĐẶC THÙ: “điểm trúng/điểm chuẩn”
     * ========================= */
    $isCutoffIntent = preg_match('/(điểm\s*(trúng|chuẩn|xét)\s*tuyển|điểm\s*sàn)/iu', $q);
    if ($isCutoffIntent) {
        $cut = kb_answer_cutoff($pdo, $q, [
            'trust_min' => (float)envv('AUTO_KB_REPLY_MIN_TRUST', 0.85),
            'days'      => (int)envv('AUTO_KB_REPLY_TIMEBOX_DAYS', 730),
        ]);
        if ($cut) {
            echo json_encode([
                'answer'    => $cut['answer'],
                'citations' => [$cut['citation']],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        // nếu không tìm được bằng extractor → tiếp tục RAG chung bên dưới
    }
    $isFeeIntent = preg_match('/(l[ệe]\s*ph[íi]|học\s*ph[íi]|ph[íi]\s*(thi|đăng\s*ký|xét|sát\s*hạch))/iu', $q);
    if ($isFeeIntent) {
        $fee = kb_answer_fee($pdo, $q, [
            'trust_min' => 0.75,
            'days'      => 900,
        ]);
        if ($fee) {
            echo json_encode([
                'answer'    => $fee['answer'],
                'citations' => [$fee['citation']],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
    }

    /* =========================
     * 1) TRUY HỒI (ưu tiên nguồn IUH)
     * ========================= */
    $hits = kb_search_chunks_v2($pdo, $q, 12, [
        'source'    => 'IUHDemo',
        'trust_min' => 0.7,
        'days'      => 1200,
    ]);
    if (!$hits) {
        $hits = kb_search_chunks_v2($pdo, $q, 12, [
            'source'    => '%',
            'trust_min' => 0.6,
            'days'      => 1600,
        ]);
    }

    // Gom theo post + thu thập postId
    $byPost  = [];
    $postIds = [];
    foreach ($hits as $h) {
        $pid = (int)($h['post_id'] ?? 0);
        if (!$pid) continue;
        $postIds[$pid] = 1;
        if (!isset($byPost[$pid])) $byPost[$pid] = ['chunks' => [], 'bestScore' => -INF];
        $byPost[$pid]['chunks'][]   = $h;
        $byPost[$pid]['bestScore']  = max($byPost[$pid]['bestScore'], (float)($h['score'] ?? 0));
    }

    // Không có gì để tóm tắt
    if (!$byPost) {
        echo json_encode([
            'answer'    => 'Chưa tìm thấy tài liệu phù hợp trong kho IUH. Bạn thử mô tả rõ hơn (vd: “học phí HK2 2024-2025”, “đăng ký học phần”, “khai giảng TT NNTH”).',
            'citations' => [],
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    // Lấy metadata post 1 lần
    $postMeta = [];
    $ids = implode(',', array_map('intval', array_keys($postIds)));
    $stmt = $pdo->query("SELECT id, title, permalink_url, created_time, trust_level FROM kb_posts WHERE id IN ($ids)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $postMeta[(int)$row['id']] = $row;
    }

    // Sắp xếp: score ↓ rồi created_time ↓
    uasort($byPost, function ($a, $b) use ($postMeta) {
        $sa = $a['bestScore'];
        $sb = $b['bestScore'];
        $ida = (int)($a['chunks'][0]['post_id'] ?? 0);
        $idb = (int)($b['chunks'][0]['post_id'] ?? 0);
        $ta = strtotime($postMeta[$ida]['created_time'] ?? '1970-01-01');
        $tb = strtotime($postMeta[$idb]['created_time'] ?? '1970-01-01');
        return ($sb <=> $sa) ?: ($tb <=> $ta);
    });

    /* =========================
     * 2) GHÉP CONTEXT + CITES
     * ========================= */
    $contexts  = [];
    $citations = [];
    $takePosts = array_slice(array_keys($byPost), 0, 3);
    foreach ($takePosts as $pid) {
        // lấy max 2 chunks mỗi post
        $chunks = array_slice($byPost[$pid]['chunks'], 0, 2);
        foreach ($chunks as $c) {
            $txt = $c['text_clean'] ?? $c['text'] ?? '';
            $txt = trim(preg_replace('/\s+/u', ' ', (string)$txt));
            if ($txt !== '') $contexts[] = mb_substr($txt, 0, 1800);
        }
        $m = $postMeta[$pid] ?? [];
        $cit = [
            'url'   => $m['permalink_url'] ?? '',
            'date'  => $m['created_time']  ?? '',
            'trust' => isset($m['trust_level']) ? (float)$m['trust_level'] : null,
            'title' => $m['title'] ?? ''
        ];
        // chỉ thêm cite khi có URL hoặc title
        if (($cit['url'] ?? '') !== '' || ($cit['title'] ?? '') !== '') {
            $citations[] = $cit;
        }
    }

    if (!$contexts) {
        echo json_encode([
            'answer'    => 'Chưa đủ ngữ liệu để trả lời. Bạn thử lại với từ khóa khác gần nội dung thông báo chính thức.',
            'citations' => $citations,
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    /* =========================
 * 3) GỌI GROQ (RAG TÓM TẮT)
 * ========================= */

    require_once __DIR__ . '/../lib/prompt_template.php';

    $model = envv('OPENAI_MODEL', 'llama-3.1-8b-instant'); // Groq model
    $ctx   = implode("\n---\n", array_slice($contexts, 0, 6));

    /* Xác định intent chính */
    $intent = match (true) {
        $isCutoffIntent => 'cutoff',
        $isFeeIntent    => 'fee',
        preg_match('/(lịch|thời gian|thi|khai giảng|đăng ký học phần)/iu', $q) => 'schedule',
        preg_match('/(liên hệ|phòng|khoa|số điện thoại|email)/iu', $q)         => 'contact',
        preg_match('/(tuyển sinh|xét tuyển|hồ sơ)/iu', $q)                    => 'admission',
        preg_match('/(đăng ký|rút học phần|bảo lưu|điểm rèn luyện)/iu', $q)  => 'academic',
        default => 'general',
    };

    if ($intent === 'fee') {
        // Chuẩn hóa đơn vị tiền
        $answer = preg_replace('/\b(\d{1,3}(?:[.,]\d{3})+)\s?(?:đ|vnđ|đồng)?\b/iu', '$1 đồng', $answer);

        // Ưu tiên dữ liệu khóa mới nhất nếu có trong context
        if (preg_match('/khóa\s?21/iu', $ctx) && !preg_match('/khóa\s?21/iu', $answer)) {
            $answer .= "\n\n*Cập nhật: Theo thông báo gần nhất, sinh viên khóa 21 được miễn lệ phí.*";
        }

        // Thêm nguồn nếu có URL
        if (preg_match('/(https?:\/\/[^\s]+)/i', $ctx, $m)) {
            $answer .= "\n\nNguồn: {$m[1]}";
        }
    }

    /* Sinh prompt từ template */
    $tpl = iuh_prompt_template($intent, $q, $ctx);

    $answer = '';
    try {
        $resp = openai_post('/responses', [
            'model' => $model,
            'input' => [
                ['role' => 'system', 'content' => $tpl['system']],
                ['role' => 'user',   'content' => $tpl['user']],
            ],
            'text' => ['format' => ['type' => 'text']],
            'temperature' => (float)envv('LLM_TEMPERATURE', 0.3),
            'top_p'       => (float)envv('LLM_TOP_P', 0.4),
        ]);

        $answer = _extract_text_from_response($resp) ?? '';
        $answer = trim($answer);

        // Nếu câu hỏi về CNTT + điểm chuẩn mà chưa nêu chương trình → thêm nhắc nhở
        if (
            preg_match('/công nghệ thông tin/iu', $q) &&
            preg_match('/điểm.*tuyển/iu', $q) &&
            !preg_match('/chương trình/iu', $answer)
        ) {
            $answer .= "\n\n*Lưu ý: Có thể có nhiều chương trình đào tạo với điểm chuẩn khác nhau. Vui lòng kiểm tra nguồn để biết chi tiết.*";
        }
    } catch (Throwable $e) {
        error_log("[LLM ERROR] " . $e->getMessage());
    }

    /* Nếu không có kết quả từ Groq, fallback bằng context gốc */
    if ($answer === '') {
        $answer = implode("\n\n", array_slice($contexts, 0, 2));
    }

    /* Trả về JSON */
    echo json_encode([
        'answer'    => $answer,
        'citations' => $citations,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
