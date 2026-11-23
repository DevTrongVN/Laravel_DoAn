<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../app/Controllers/Api/ApiAIController.php';

$controller = new ApiAIController();

echo "=== KIỂM TRA PHƯƠNG THỨC improveStyle / rewrite TRONG ApiAIController ===\n";

// Kiểm tra xem phương thức rewrite() và improveStyle() có tồn tại không
$ref = new ReflectionClass(ApiAIController::class);
echo "Có phương thức improveStyle: " . ($ref->hasMethod('improveStyle') ? 'CÓ' : 'KHÔNG') . "\n";
echo "Có phương thức rewrite: " . ($ref->hasMethod('rewrite') ? 'CÓ' : 'KHÔNG') . "\n";
