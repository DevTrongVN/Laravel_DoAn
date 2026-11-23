<?php
declare(strict_types=1);

/**
 * router.php — Bộ định tuyến cho PHP Built-in Web Server khi chạy trong VS Code.
 * Giúp chạy đồ án Góc Nhỏ trực tiếp trên VS Code mà không cần Apache của Laragon.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

// 1. Bảo vệ trang Quản trị: Chặn truy cập trực tiếp file admin.html, bắt buộc qua Server-side Controller
if ($uri === '/admin.html') {
    $_GET['url'] = 'admin';
    require __DIR__ . '/public/index.php';
    return;
}

// 2. Phục vụ file tĩnh hợp lệ (CSS, JS, SVG, ảnh, font...)
$filePath = __DIR__ . '/public' . $uri;
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false; // PHP Built-in server sẽ tự trả về file
}

// 3. Chuyển tất cả request khác về Front Controller index.php
if ($uri !== '/') {
    $_GET['url'] = ltrim($uri, '/');
}

require __DIR__ . '/public/index.php';
