<?php
// lib/kb_qa.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/kb.php';   // đã có vi_norm(), kb_search_chunks_v2()

/**
 * Trả lời nhanh “điểm trúng tuyển … năm …” nếu tìm được trong KB (bảng PDF/ảnh đã OCR).
 * Trả về: ['answer' => string, 'citation' => ['url'=>..., 'date'=>..., 'title'=>...]] hoặc null nếu không match.
 */
function kb_answer_cutoff(PDO $pdo, string $q, array $opts = []): ?array
{
    // 1) nhận diện năm + tên ngành (cho phép sai “quản trị/quản lý”)
    $norm = vi_norm($q); // dùng hàm sẵn có trong kb.php
    preg_match('/\b(20\d{2}|19\d{2})\b/u', $norm, $m);
    $year = $m[1] ?? date('Y');

    // ý định câu hỏi
    $intent = preg_match('/(điểm\s*(trúng|chuẩn|xét)\s*tuyển|điểm\s*sàn)/u', $norm);
    if (!$intent) return null;

    // lấy “tên ngành” còn lại sau khi trừ các từ khóa
    $normMajor = trim(preg_replace('/\b(điểm|trúng|chuẩn|xét|tuyển|sàn|năm|' . preg_quote($year, '/') . ')\b/u', ' ', $norm));
    $normMajor = preg_replace('/\s+/u', ' ', $normMajor); // gộp khoảng trắng  
    if ($normMajor === '') $normMajor = $norm;

    // 2) ưu tiên tài liệu IUH Official
    $hits = kb_search_chunks_v2($pdo, 'điểm trúng tuyển ' . $normMajor . ' ' . $year, 12, [
        'source'    => 'IUH Official',
        'trust_min' => (float)($opts['trust_min'] ?? 0.85),
        'days'      => (int)($opts['days'] ?? 900),
    ]);
    if (!$hits) return null;

    // gom theo post → lấy bài tốt nhất (mới nhất)
    $byPost = [];
    foreach ($hits as $h) {
        $pid = (int)$h['post_id'];
        if (!isset($byPost[$pid])) $byPost[$pid] = ['meta' => $h, 'text' => ''];
        $byPost[$pid]['text'] .= "\n" . ($h['text_clean'] ?? $h['text'] ?? '');
    }
    usort(
        $byPost,
        fn($a, $b) =>
        strtotime($b['meta']['created_time'] ?? '1970-01-01') <=> strtotime($a['meta']['created_time'] ?? '1970-01-01')
    );

    // 3) regex linh hoạt bắt 4 cột điểm (TN, ĐGNL 1200, ĐGNL 30, Kết hợp)
    $rxMajor = '(nh[óo]m\s*ng[àa]nh\s*)?'  // optional "Nhóm ngành"  
        . '(qu[aă]n\s*(?:l[ýy]|tr[ịi])\s*x[âa]y\s*d[ựu]ng|'
        . preg_quote($normMajor, '/') . ')';

    $rx = '/(?P<name>' . $rxMajor . ').{0,300}?'  // tăng từ 120 lên 300  
        . '(?P<TN>\d{1,2}(?:[.,]\d{1,2})?)\s+'
        . '(?P<DGNL1200>\d{3,4})\s+'
        . '(?P<DGNL30>\d{1,2}(?:[.,]\d{1,2})?)\s+'
        . '(?P<KH>\d{1,2}(?:[.,]\d{1,2})?)/isu';

    // Regex phát hiện tiêu đề chương trình  
    $rxProgram = '/(chương\s*trình\s*(đại\s*trà|tăng\s*cường\s*tiếng\s*anh|liên\s*kết\s*quốc\s*tế))/iu';

    $allResults = [];

    foreach ($byPost as $item) {
        $txt = $item['text'];
        error_log("[DEBUG] Checking text: " . mb_substr($txt, 0, 200));
        error_log("[DEBUG] Regex pattern: " . $rx);

        // Tìm tất cả các match trong text  
        preg_match_all($rx, $txt, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if (preg_match($rx, $txt, $m)) {
            error_log("[DEBUG] MATCHED! Name: " . $m['name']);
            $title = $item['meta']['title'] ?? '';
            $date  = $item['meta']['created_time'] ?? '';
            $url   = $item['meta']['permalink_url'] ?? '';

            // chuẩn hoá dấu phẩy → chấm
            $fmt = fn($v) => str_replace(',', '.', $v);

            $answer = sprintf(
                "Điểm trúng tuyển **%s** năm **%s**:\n- TN: **%s**\n- ĐGNL (thang 1200): **%s**\n- ĐGNL (thang 30): **%s**\n- Xét kết hợp: **%s**",
                trim($m['name']),
                $year,
                $fmt($m['TN']),
                $m['DGNL1200'],
                $fmt($m['DGNL30']),
                $fmt($m['KH'])
            );
            return [
                'answer'   => $answer,
                'citation' => ['url' => $url, 'date' => $date, 'title' => $title],
            ];
        } else {
            error_log("[DEBUG] No match for this post");
        }
    }
    return null;
}

function kb_answer_fee(PDO $pdo, string $q, array $opts = []): ?array
{
    $norm = vi_norm($q);

    // 1. Nhận diện ý định
    $intent = preg_match('/(l[ệe]\s*ph[íi]|ph[íi]\s*(thi|xét|đăng\s*ký|học|sát\s*hạch|xét\s*tuyển)|học\s*ph[íi])/iu', $norm);
    if (!$intent) return null;

    // 2. Truy xuất dữ liệu IUH
    $hits = kb_search_chunks_v2($pdo, $q, 15, [
        'source'    => 'IUH Official',
        'trust_min' => (float)($opts['trust_min'] ?? 0.75),
        'days'      => (int)($opts['days'] ?? 900),
    ]);
    if (!$hits) return null;

    // 3. Gom nội dung theo post
    $byPost = [];
    foreach ($hits as $h) {
        $pid = (int)$h['post_id'];
        if (!isset($byPost[$pid])) $byPost[$pid] = ['meta' => $h, 'text' => ''];
        $byPost[$pid]['text'] .= "\n" . ($h['text_clean'] ?? $h['text'] ?? '');
    }

    // 4. Biểu thức bắt các mẫu “phí / miễn phí / đồng”
    $rx = '/(?:(l[ệe]\s*ph[íi]|ph[íi]|học\s*ph[íi]).{0,100}?)?'
        . '(?P<amount>\d{1,3}(?:[.,]?\d{3})*(?:\s*(?:đ|đồng)))'
        . '|(?P<free>miễn\s*ph[íi])/isu';

    foreach ($byPost as $item) {
        $txt = $item['text'];
        $matches = [];
        if (preg_match_all($rx, $txt, $matches, PREG_SET_ORDER)) {
            // Ưu tiên “miễn phí”, sau đó “số tiền nhỏ nhất”
            $amounts = [];
            $isFree = false;
            foreach ($matches as $m) {
                if (!empty($m['free'])) {
                    $isFree = true;
                    break;
                }
                if (!empty($m['amount'])) {
                    $amounts[] = trim($m['amount']);
                }
            }

            $title = $item['meta']['title'] ?? '';
            $date  = $item['meta']['created_time'] ?? '';
            $url   = $item['meta']['permalink_url'] ?? '';

            if ($isFree) {
                $answer = "Theo thông báo của IUH, **kỳ thi hoặc hoạt động này được miễn phí** (sinh viên không phải đóng lệ phí).";
            } elseif ($amounts) {
                // Lấy giá trị nhỏ nhất trong các số tiền
                $min = min(array_map(fn($v) => (float)preg_replace('/[^\d.]/', '', str_replace(',', '.', $v)), $amounts));
                $display = number_format($min, 0, ',', '.') . ' đồng';
                $answer = "Theo thông báo của IUH, **lệ phí / học phí liên quan là khoảng {$display}**.";
            } else {
                continue;
            }

            return [
                'answer'   => $answer,
                'citation' => ['url' => $url, 'date' => $date, 'title' => $title],
            ];
        }
    }

    return null;
}
