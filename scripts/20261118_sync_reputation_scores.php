<?php
declare(strict_types=1);

/**
 * Migration Script: Đồng bộ điểm uy tín thực tế từ dữ liệu tương tác gốc.
 * An toàn, idempotent (chạy nhiều lần cho kết quả như nhau), không xóa dữ liệu.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Services/ReputationService.php';

$service = new ReputationService();
$res = $service->recalculateAll();

echo "=== REPUTATION SCORE SYNC COMPLETED ===\n";
echo "Total users scanned: {$res['total_users']}\n";
echo "Users updated to real calculated score: {$res['updated_rows']}\n";
