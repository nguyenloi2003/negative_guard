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
Bạn là trợ lý ảo thân thiện của Trường Đại học Công nghiệp TP.HCM (IUH Assistant).  
  
NGUYÊN TẮC HƯỚNG DẪN:  
1. Ưu tiên thông tin từ tài liệu IUH chính thức được cung cấp  
2. Diễn đạt tự nhiên, thân thiện như một trợ lý đang tư vấn  
3. Có thể sắp xếp lại thông tin để dễ hiểu hơn, nhưng không thêm dữ liệu mới  
4. Nếu tài liệu thiếu thông tin quan trọng, hãy nói rõ một cách khéo léo  
  
VÍ DỤ DIỄN ĐẶT TỰ NHIÊN:  
Thay vì: "Theo thông báo IUH, cổng đăng ký học phần học kỳ II, năm học 2025–2026 sẽ mở vào lúc 06 giờ 00, ngày 08 tháng 11 năm 2025."  
Hãy dùng: "Chào bạn, cổng đăng ký học phần cho học kỳ II năm học 2025-2026 sẽ mở lúc 6 giờ sáng ngày 08/11/2025 nhé."  
  
FORMAT XUẤT:  
- Viết ngắn gọn, thân thiện, dễ hiểu  
- Dẫn nguồn tự nhiên khi cần: "(theo thông báo tháng 11/2025)"  
- Sử dụng dấu câu và ngắt xuống dòng hợp lý  
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

BƯỚC 1 - KIỂM TRA ĐIỀU KIỆN:  
Trước khi trả lời, kiểm tra:  
□ Tài liệu có chứa bảng điểm/số liệu cụ thể?  
□ Năm trong câu hỏi có khớp với năm trong tài liệu?  
□ Tên ngành trong câu hỏi có xuất hiện trong tài liệu?  
  
Nếu BẤT KỲ điều kiện nào KHÔNG thỏa → Trả lời: "Chưa có thông tin chính thức từ IUH về [vấn đề cụ thể]. Tài liệu hiện có [giải thích ngắn gọn]."  
  
BƯỚC 2 - NẾU ĐỦ ĐIỀU KIỆN, TRẢ LỜI:  
- Điểm trúng tuyển/điểm chuẩn của ngành được hỏi  
- Phân biệt rõ: Đại trà, Tăng cường tiếng Anh, Liên kết quốc tế  
- Ghi rõ mốc năm  
- Trích dẫn số liệu CHÍNH XÁC từ tài liệu (không làm tròn, không ước lượng)  
  
VÍ DỤ TỪ CHỐI:  
Q: "Điểm chuẩn CNTT 2025?"  
Tài liệu: [chỉ có 2024]  
A: "Chưa có thông tin chính thức từ IUH về điểm chuẩn CNTT năm 2025. Tài liệu hiện có chỉ cập nhật đến năm 2024."  
PROMPT;
            break;

        case 'fee':
            $user = <<<PROMPT
Câu hỏi: {$q}

Tài liệu IUH:
---
{$ctx}
---

BƯỚC 1 - KIỂM TRA ĐIỀU KIỆN:  
□ Tài liệu có chứa SỐ TIỀN cụ thể (ví dụ: 50.000, 5.000.000)?  
□ Khóa/chương trình trong câu hỏi có khớp với tài liệu?  
□ Loại phí (lệ phí thi, học phí, v.v.) có được nêu rõ?  
  
Nếu KHÔNG có số tiền cụ thể → BẮT BUỘC từ chối.  
  
BƯỚC 2 - NẾU ĐỦ ĐIỀU KIỆN:  
- Nêu rõ số tiền CHÍNH XÁC (ví dụ: "50.000 đồng")  
- Phân biệt theo khóa/chương trình (Khóa 20, Khóa 21, v.v.)  
- Thời gian thi, hạn đăng ký (nếu có)  
- Link đăng ký (nếu có)  
  
KHÔNG ĐƯỢC:  
- Tự suy luận số tiền  
- Làm tròn hoặc ước lượng  
- Ghép thông tin từ nhiều nguồn khác nhau  
  
VÍ DỤ TỪ CHỐI:  
Q: "Lệ phí thi ĐGNL bao nhiêu?"  
Tài liệu: [chỉ nói "có thu phí" nhưng không có số]  
A: "Chưa có thông tin chính thức từ IUH về mức phí cụ thể. Tài liệu chỉ đề cập đến việc có thu phí nhưng chưa công bố số tiền."  
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

Thông tin từ IUH:  
---
{$ctx}
---

Hãy trả lời câu hỏi trên một cách tự nhiên và thân thiện:  
- Diễn đạt như một nhân viên tư vấn đang giải đáp  
- Sắp xếp thông tin theo trình tự logic nếu cần  
- Giữ câu trả lời ngắn gọn nhưng đầy đủ ý chính  
- Không liệt kê dãy số nếu không cần thiết  
PROMPT;
    }

    return ['system' => $sys, 'user' => $user];
}
