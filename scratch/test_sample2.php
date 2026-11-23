<?php
declare(strict_types=1);

require_once __DIR__ . '/test_new_rewrite.php';

$sample2 = "Hôm nay mình rất mệt mỏi vì công việc quá nhiều. Mình nghĩ là ai cũng cần nghỉ ngơi sau thời gian làm việc căng thẳng để lấy lại năng lượng.";

$pro2 = AdvancedRewriteEngine::rewriteProfessional($sample2);
$lit2 = AdvancedRewriteEngine::rewriteLiterary($sample2);
$con2 = AdvancedRewriteEngine::rewriteConcise($sample2);

echo "=== ĐOẠN VĂN GỐC 2 ===\n" . $sample2 . "\n\n";
echo "=== 1. NGHIÊM TÚC 2 ===\n" . $pro2 . "\n\n";
echo "=== 2. VĂN CHƯƠNG 2 ===\n" . $lit2 . "\n\n";
echo "=== 3. SÚC TÍCH 2 ===\n" . $con2 . "\n\n";
