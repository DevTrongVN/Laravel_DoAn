<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['dbname'], $config['charset']),
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = file_get_contents(__DIR__ . '/../database/migrations/20261116_create_post_views.sql');
    $pdo->exec($sql);
    echo "Thành công: Đã tạo hoặc kiểm tra bảng post_views thành công!\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Lỗi khi tạo bảng post_views: " . $e->getMessage() . "\n");
    exit(1);
}
