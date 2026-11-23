<?php
declare(strict_types=1);

// Thử nghiệm thuật toán viết lại văn phong nâng cao cho 3 phong cách
class RewriteEngineTest
{
    /**
     * Tách văn bản thành danh sách câu hoàn chỉnh
     */
    public static function splitSentences(string $text): array
    {
        $text = trim($text);
        if ($text === '') return [];
        
        // Chuẩn hóa dấu ngắt dòng và khoảng trắng
        $text = preg_replace("/[\r\n]+/u", "\n", $text);
        $paragraphs = explode("\n", $text);
        
        $allSentences = [];
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') continue;
            // Tách theo dấu . ! ? kèm khoảng trắng hoặc cuối dòng
            $parts = preg_split('/(?<=[.!?])\s+/u', $para, -1, PREG_SPLIT_NO_EMPTY) ?: [$para];
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $allSentences[] = $p;
                }
            }
        }
        return $allSentences;
    }

    /**
     * Dọn dẹp khoảng trắng, dấu câu và viết hoa đầu câu
     */
    public static function finalizeSentence(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = preg_replace('/\s+([,.?!;:])/u', '$1', $s);
        $s = trim($s);
        if ($s === '') return '';
        
        // Viết hoa chữ cái đầu
        $first = mb_substr($s, 0, 1, 'UTF-8');
        $rest = mb_substr($s, 1, null, 'UTF-8');
        $s = mb_strtoupper($first, 'UTF-8') . $rest;
        
        if (!preg_match('/[.!?]$/u', $s)) {
            $s .= '.';
        }
        return $s;
    }
}

$sampleAI = "Trí tuệ nhân tạo (AI) đang phát triển rất nhanh và thay đổi cách con người làm việc cũng như học tập. Nhiều người lo lắng AI sẽ thay thế lập trình viên, nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn. Để không bị tụt lại phía sau, chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày.";

$sentences = RewriteEngineTest::splitSentences($sampleAI);
echo "Số câu tách được: " . count($sentences) . "\n";
foreach ($sentences as $i => $s) {
    echo "Câu " . ($i + 1) . ": " . $s . "\n";
}
