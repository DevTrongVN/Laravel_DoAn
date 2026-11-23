<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Services/AIService.php';

$sampleAI = "Trí tuệ nhân tạo (AI) đang phát triển rất nhanh và thay đổi cách con người làm việc cũng như học tập. Nhiều người lo lắng AI sẽ thay thế lập trình viên, nhưng thực tế cho thấy nó chỉ là công cụ hỗ trợ giúp chúng ta làm việc hiệu quả hơn. Để không bị tụt lại phía sau, chúng ta cần chủ động học hỏi và làm chủ công nghệ mới mỗi ngày.";

echo "=== ĐOẠN VĂN GỐC (Độ dài: " . mb_strlen($sampleAI) . " ký tự, " . str_word_count($sampleAI) . " từ) ===\n";
echo $sampleAI . "\n\n";

$aiService = new AIService();

// Test improveStyle hiện tại
echo "--- HIỆN TẠI (trước khi nâng cấp) ---\n";
echo "[PROFESSIONAL]:\n" . $aiService->improveStyle($sampleAI, 'professional') . "\n\n";
echo "[LITERARY]:\n" . $aiService->improveStyle($sampleAI, 'literary') . "\n\n";
echo "[CONCISE]:\n" . $aiService->improveStyle($sampleAI, 'concise') . "\n\n";
