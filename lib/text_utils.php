<?php
// lib/text_utils.php

function tu_clean_text(string $s): string
{
    // chuẩn hoá Unicode, xoá ký tự vô hình, gộp khoảng trắng
    if (class_exists('Normalizer')) {
        $s = Normalizer::normalize($s, Normalizer::FORM_C);
    }
    $s = preg_replace('/\x{200B}|\x{200C}|\x{200D}|\x{FEFF}/u', '', $s);  // zero-width
    $s = preg_replace('/[ \t]+/u', ' ', $s);
    $s = preg_replace('/\R{2,}/u', "\n\n", $s);
    return trim($s);
}

function tu_guess_topic(string $text): ?string
{
    $t = mb_strtolower($text, 'UTF-8');
    if (preg_match('/học\s*phí|họcphi|học phí/u', $t)) return 'hocphi';
    if (preg_match('/khai giảng|tuyển sinh|lịch học/u', $t)) return 'daotao';
    if (preg_match('/tađv|anh văn|ngoại ngữ/u', $t)) return 'ngoaingu';
    return null;
}

function tu_guess_doc_type(string $text): ?string
{
    $t = mb_strtolower($text, 'UTF-8');
    if (preg_match('/thông báo|quy định|hướng dẫn/u', $t)) return 'announcement';
    if (preg_match('/lịch|schedule/u', $t)) return 'schedule';
    if (preg_match('/câu hỏi thường gặp|faq/u', $t)) return 'faq';
    return null;
}

/**
 * Cắt đoạn theo ký tự (đủ tốt cho tiếng Việt).
 * $target ~ 1600–2200 ký tự; $overlap 150–250.
 */
function tu_chunk_text(string $text, int $target = 1800, int $overlap = 200): array
{
    $text = trim($text);
    $L = mb_strlen($text, 'UTF-8');
    if ($L <= $target) return [$text];
    $chunks = [];
    $i = 0;
    while ($i < $L) {
        $end = min($L, $i + $target);
        // ưu tiên cắt tại dấu chấm/hết dòng
        $slice = mb_substr($text, $i, $end - $i, 'UTF-8');
        if ($end < $L) {
            if (preg_match('/^(.{100,}?[.!?](?:\s|$))/u', $slice, $m)) {
                $slice = $m[1];
            }
        }
        $chunks[] = trim($slice);
        if ($end >= $L) break;
        $i += max(1, mb_strlen($slice, 'UTF-8') - $overlap);
    }
    return $chunks;
}
