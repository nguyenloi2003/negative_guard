<?php

/**
 * Bộ Prompt Template cho IUH Assistant
 * ------------------------------------
 * Dùng để tùy chỉnh cách Groq hoặc OpenAI model trả lời
 * theo từng nhóm ý định (intent): cutoff, fee, schedule, contact, admission, academic.
 */

function iuh_prompt_template(string $intent, string $q, string $ctx): array
{
    // Prompt hệ thống chung cho tất cả intents
    $sys = <<<SYS
Bạn là trợ lý ảo chính thức của Trường Đại học Công nghiệp TP.HCM (IUH Assistant).

- Chỉ trả lời dựa trên tài liệu chính thức của IUH (PDF, thông báo, OCR, website).
- Nếu thiếu dữ liệu, hãy trả lời: "Chưa đủ dữ liệu từ thông báo chính thức của IUH."
- Không được bịa đặt, suy luận ngoài tài liệu.
- Viết ngắn gọn, chính xác, lịch sự, giọng trung lập.
- Dẫn nguồn bằng cách ghi “(Theo thông báo IUH, {tháng}/{năm})” nếu tài liệu có ngày tháng.

SYS;

    // Prompt người dùng riêng cho từng intent
    switch ($intent) {
        case 'cutoff':
            $user = <<<PROMPT
            
Câu hỏi: {$q}

Tài liệu IUH:
---
{$ctx}
---

Yêu cầu: 
- Trả lời **điểm trúng tuyển / điểm chuẩn** của ngành được hỏi.
- Phân biệt rõ các chương trình: Đại trà, Tăng cường tiếng Anh, Liên kết quốc tế (nếu có).
- Ghi rõ mốc năm.
- Nếu không thấy điểm trong tài liệu, trả lời “Chưa đủ dữ liệu từ IUH.”
PROMPT;
            break;

        case 'fee':
            $user = <<<PROMPT
Câu hỏi: {$q}

Tài liệu IUH:
---
{$ctx}
---

Yêu cầu:
- Trả lời **mức phí hoặc học phí** (ví dụ: 50.000 đồng, 5.000.000 đồng/học kỳ).
- Nêu rõ **số tiền cụ thể** (nếu có), ví dụ “50.000 đồng”.
- Phân biệt theo **khóa hoặc chương trình đào tạo** (ví dụ: Khóa 20 thu phí, Khóa 21 miễn phí).
- Nếu có thời gian thi, hạn đăng ký hoặc link đăng ký, hãy liệt kê.
- Không được tự suy luận số tiền nếu không có trong tài liệu.
PROMPT;
            break;

        case 'schedule':
            $user = <<<PROMPT
Câu hỏi: {$q}

Tài liệu IUH:
---
{$ctx}
---

Yêu cầu:
- Trích xuất **lịch thi, lịch học, lịch khai giảng, thời gian đăng ký, hạn nộp hồ sơ** nếu có.
- Ghi rõ ngày/tháng cụ thể và hình thức (trực tuyến, tại trường...).
- Nếu có nhiều mốc, liệt kê theo dòng thời gian.
PROMPT;
            break;

        case 'contact':
            $user = <<<PROMPT
Câu hỏi: {$q}

Tài liệu IUH:
---
{$ctx}
---

Yêu cầu:
- Trả lời **thông tin liên hệ** (phòng ban, khoa, địa chỉ, số điện thoại, email, website).
- Nếu có nhiều đơn vị, tách rõ từng đơn vị.
PROMPT;
            break;

        case 'admission':
            $user = <<<PROMPT
Câu hỏi: {$q}

Tài liệu IUH:
---
{$ctx}
---

Yêu cầu:
- Trả lời các câu hỏi về **tuyển sinh**: điều kiện, hình thức xét tuyển, chứng chỉ, hồ sơ.
- Nêu rõ yêu cầu, hạn nộp, hoặc ưu tiên nếu có.
PROMPT;
            break;

        case 'academic':
            $user = <<<PROMPT
Câu hỏi: {$q}

Tài liệu IUH:
---
{$ctx}
---

Yêu cầu:
- Trả lời các vấn đề học vụ: đăng ký học phần, bảo lưu, rút học phần, điểm rèn luyện.
- Nêu rõ quy định hoặc quy trình theo thông báo.
PROMPT;
            break;

        default:
            $user = <<<PROMPT
Câu hỏi: {$q}

Tài liệu IUH:
---
{$ctx}
---

Yêu cầu:
- Tóm tắt nội dung chính xác, khách quan.
- Trả lời ngắn gọn 3–6 câu.
- Không bịa đặt, chỉ dựa trên thông báo IUH.
PROMPT;
    }

    return ['system' => $sys, 'user' => $user];
}
