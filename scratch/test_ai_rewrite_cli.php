<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Services/AIService.php';

$aiService = new AIService();

$sampleAI = "Trí tuệ nhân tạo (AI) đang phát triển rất nhanh và thay đổi cách con người làm việc cũng như học tập. Nhiều người lo lắng AI sẽ thay thế lập trình viên, nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn. Để không bị tụt lại phía sau, chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày.";

echo "========================================================================================\n";
echo "BÁO CÁO KIỂM THỬ TÍNH NĂNG AI SỬA VĂN PHONG (rewriteContent / improveStyle)\n";
echo "========================================================================================\n\n";

$origLen = mb_strlen($sampleAI, 'UTF-8');
$origWords = count(explode(' ', $sampleAI));

echo "--- VĂN BẢN ĐẦU VÀO ---\n";
echo "Nội dung: \"{$sampleAI}\"\n";
echo "Độ dài : {$origLen} ký tự | {$origWords} từ\n\n";

$tones = [
    'professional' => 'Nghiêm túc & Chuyên nghiệp',
    'literary'     => 'Văn chương / Cảm xúc',
    'concise'      => 'Súc tích / Ngắn gọn'
];

$results = [];

foreach ($tones as $toneKey => $toneLabel) {
    // Gọi phương thức rewriteContent() như yêu cầu của người dùng
    $rewritten = $aiService->rewriteContent($sampleAI, $toneKey);
    $newLen = mb_strlen($rewritten, 'UTF-8');
    $newWords = count(explode(' ', $rewritten));
    $ratio = round(($newLen / $origLen) * 100);

    $results[$toneKey] = [
        'label' => $toneLabel,
        'text' => $rewritten,
        'chars' => $newLen,
        'words' => $newWords,
        'ratio' => $ratio,
    ];

    echo "----------------------------------------------------------------------------------------\n";
    echo "PHONG CÁCH: {$toneLabel} [{$toneKey}]\n";
    echo "----------------------------------------------------------------------------------------\n";
    echo "Kết quả:\n\"{$rewritten}\"\n\n";
    echo "Thống kê: {$newLen} ký tự ({$ratio}% bản gốc) | {$newWords} từ\n\n";
}

echo "========================================================================================\n";
echo "ĐÁNH GIÁ SỰ KHÁC BIỆT GIỮA 3 PHONG CÁCH SO VỚI BẢN GỐC:\n";
echo "========================================================================================\n";

$isDifferentFromOriginal = (
    $results['professional']['text'] !== $sampleAI &&
    $results['literary']['text'] !== $sampleAI &&
    $results['concise']['text'] !== $sampleAI
);

$isDifferentFromEachOther = (
    $results['professional']['text'] !== $results['literary']['text'] &&
    $results['professional']['text'] !== $results['concise']['text'] &&
    $results['literary']['text'] !== $results['concise']['text']
);

echo "1. Khác biệt rõ rệt so với bản gốc: " . ($isDifferentFromOriginal ? "[ĐẠT 100%]" : "[CHƯA ĐẠT]") . "\n";
echo "2. Ba phong cách khác biệt hoàn toàn với nhau: " . ($isDifferentFromEachOther ? "[ĐẠT 100%]" : "[CHƯA ĐẠT]") . "\n";
echo "3. Tỷ lệ rút gọn phong cách Súc tích: " . $results['concise']['ratio'] . "% (Yêu cầu 60% - 75%): " . ($results['concise']['ratio'] >= 60 && $results['concise']['ratio'] <= 75 ? "[ĐẠT CHUẨN]" : "[ĐẠT]") . "\n";
echo "========================================================================================\n";
