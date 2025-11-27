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
    // 1) Nhận diện năm + tên ngành  
    $norm = vi_norm($q);
    preg_match('/\b(20\d{2}|19\d{2})\b/u', $norm, $m);
    $year = $m[1] ?? date('Y');

    // Ý định câu hỏi  
    $intent = preg_match('/(điểm\s*(trúng|chuẩn|xét)\s*tuyển|điểm\s*sàn)/u', $norm);
    if (!$intent) return null;

    // Lấy tên ngành sau khi trừ các từ khóa  
    $normMajor = trim(preg_replace('/\b(điểm|trúng|chuẩn|xét|tuyển|sàn|năm|' . preg_quote($year, '/') . ')\b/u', ' ', $norm));
    $normMajor = preg_replace('/\s+/u', ' ', $normMajor);
    if ($normMajor === '') $normMajor = $norm;

    // 2) Tìm kiếm chunks  
    $hits = kb_search_chunks_v2($pdo, 'điểm trúng tuyển ' . $normMajor . ' ' . $year, 12, [
        'source'    => 'IUHDemo',
        'trust_min' => (float)($opts['trust_min'] ?? 0.85),
        'days'      => (int)($opts['days'] ?? 900),
    ]);
    if (!$hits) return null;

    // 3) Gom theo post, sắp xếp theo thời gian mới nhất  
    $byPost = [];
    foreach ($hits as $h) {
        $pid = (int)$h['post_id'];
        if (!isset($byPost[$pid])) $byPost[$pid] = ['meta' => $h, 'text' => ''];
        $byPost[$pid]['text'] .= "\n" . ($h['text_clean'] ?? $h['text'] ?? '');
    }
    usort(
        $byPost,
        fn($a, $b) =>
        strtotime($b['meta']['created_time'] ?? '1970-01-01') <=>
            strtotime($a['meta']['created_time'] ?? '1970-01-01')
    );

    // 4) Regex pattern cho tên ngành và 4 cột điểm  
    $rxMajor = '(nh[óo]m\s*ng[àa]nh\s*)?'
        . '(qu[aă]n\s*(?:l[ýy]|tr[ịi])\s*x[âa]y\s*d[ựu]ng|'
        . preg_quote($normMajor, '/') . ')';

    $rx = '/(?P<name>' . $rxMajor . ').{0,300}?'
        . '(?P<TN>\d{1,2}(?:[.,]\d{1,2})?)\s+'
        . '(?P<DGNL1200>\d{3,4})\s+'
        . '(?P<DGNL30>\d{1,2}(?:[.,]\d{1,2})?)\s+'
        . '(?P<KH>\d{1,2}(?:[.,]\d{1,2})?)/isu';

    // Regex phát hiện tiêu đề chương trình  
    $rxProgram = '/(chương\s*trình\s*(đại\s*trà|tăng\s*cường\s*tiếng\s*anh|liên\s*kết\s*quốc\s*tế))/iu';

    $allResults = [];

    // 5) Duyệt qua từng post để tìm tất cả các match  
    foreach ($byPost as $item) {
        $txt = $item['text'];

        // Tìm TẤT CẢ các match trong text  
        preg_match_all($rx, $txt, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if (empty($matches)) continue;

        foreach ($matches as $m) {
            // Lấy vị trí của match để tìm tiêu đề chương trình phía trước  
            $offset = $m[0][1];
            $textBefore = mb_substr($txt, max(0, $offset - 800), 800);

            // Phát hiện chương trình dựa trên tiêu đề  
            $program = 'Chương trình Đại trà'; // Mặc định  
            if (preg_match($rxProgram, $textBefore, $pMatch)) {
                $programRaw = mb_strtolower($pMatch[1]);
                if (preg_match('/tăng\s*cường/u', $programRaw)) {
                    $program = 'Chương trình Tăng cường tiếng Anh';
                } elseif (preg_match('/liên\s*kết/u', $programRaw)) {
                    $program = 'Chương trình Liên kết quốc tế';
                }
            }

            // Chuẩn hóa dấu phẩy thành dấu chấm  
            $allResults[] = [
                'program'   => $program,
                'name'      => trim($m['name'][0]),
                'TN'        => str_replace(',', '.', $m['TN'][0]),
                'DGNL1200'  => $m['DGNL1200'][0],
                'DGNL30'    => str_replace(',', '.', $m['DGNL30'][0]),
                'KH'        => str_replace(',', '.', $m['KH'][0]),
                'meta'      => $item['meta'],
            ];
        }

        // Nếu đã tìm được kết quả, dừng lại (chỉ lấy từ post mới nhất)  
        if (!empty($allResults)) break;
    }

    if (empty($allResults)) return null;

    // 6) Format câu trả lời với TẤT CẢ các chương trình  
    $majorName = $allResults[0]['name'];
    $answer = "Điểm trúng tuyển **{$majorName}** năm **{$year}**:\n\n";

    foreach ($allResults as $result) {
        $answer .= "**{$result['program']}:**\n";
        $answer .= "- TN: **{$result['TN']}**\n";
        $answer .= "- ĐGNL (thang 1200): **{$result['DGNL1200']}**\n";
        $answer .= "- ĐGNL (thang 30): **{$result['DGNL30']}**\n";
        $answer .= "- Xét kết hợp: **{$result['KH']}**\n\n";
    }

    // 7) Trả về kết quả với citation từ post đầu tiên  
    $firstMeta = $allResults[0]['meta'];
    return [
        'answer'   => trim($answer),
        'citation' => [
            'url'   => $firstMeta['permalink_url'] ?? '',
            'date'  => $firstMeta['created_time'] ?? '',
            'title' => $firstMeta['title'] ?? '',
        ],
    ];
}

function kb_answer_fee(PDO $pdo, string $q, array $opts = []): ?array
{
    $norm = vi_norm($q);

    // 1. Nhận diện ý định
    $intent = preg_match('/(l[ệe]\s*ph[íi]|ph[íi]\s*(thi|xét|đăng\s*ký|học|sát\s*hạch|xét\s*tuyển)|học\s*ph[íi])/iu', $norm);
    if (!$intent) return null;

    // 2. Truy xuất dữ liệu IUH
    $hits = kb_search_chunks_v2($pdo, $q, 15, [
        'source'    => 'IUHDemo',
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
