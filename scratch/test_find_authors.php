<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Models/Post.php';

$pdo = Database::getInstance()->getConnection();
$postModel = new Post();

// Tìm bài viết của Hoàng Nam (user_id = 3 hoặc username = 'hoang_nam')
$stmtHN = $pdo->prepare("SELECT p.id, p.title, p.user_id, u.full_name, u.username FROM posts p JOIN users u ON u.id = p.user_id WHERE u.username = 'hoang_nam' ORDER BY p.id DESC LIMIT 1");
$stmtHN->execute();
$postHN = $stmtHN->fetch(PDO::FETCH_ASSOC);

// Tìm bài viết của Huỳnh Duy Mai (username = 'duy-mai_hub' hoặc full_name = 'Huỳnh Duy Mai')
$stmtHDM = $pdo->prepare("SELECT p.id, p.title, p.user_id, u.full_name, u.username FROM posts p JOIN users u ON u.id = p.user_id WHERE u.username = 'duy-mai_hub' OR u.full_name LIKE '%Huỳnh Duy Mai%' ORDER BY p.id DESC LIMIT 1");
$stmtHDM->execute();
$postHDM = $stmtHDM->fetch(PDO::FETCH_ASSOC);

if (!$postHDM) {
    echo "Huỳnh Duy Mai (user_id 27) chưa có bài viết. Kiểm tra xem user 27 có bài viết nào không...\n";
    $stmtCheck = $pdo->query("SELECT COUNT(*) FROM posts WHERE user_id = 27");
    echo "User 27 post count: " . $stmtCheck->fetchColumn() . "\n";
    // Nếu user 27 chưa có bài viết, ta tạo 1 bài viết mẫu hoặc chọn bài mà Huỳnh Duy Mai tương tác
}

// 1 bài viết thứ 3: Post ID 1 hoặc bài viết có nhiều tương tác (ví dụ ID 3 hoặc ID 2 hoặc ID 1)
$stmtOther = $pdo->query("SELECT p.id, p.title, p.user_id, u.full_name, u.username FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id = 1");
$postOther = $stmtOther->fetch(PDO::FETCH_ASSOC);

echo "Post Hoàng Nam: " . json_encode($postHN, JSON_UNESCAPED_UNICODE) . "\n";
echo "Post Huỳnh Duy Mai: " . json_encode($postHDM, JSON_UNESCAPED_UNICODE) . "\n";
echo "Post thứ 3: " . json_encode($postOther, JSON_UNESCAPED_UNICODE) . "\n";
