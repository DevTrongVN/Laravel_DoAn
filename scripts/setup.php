<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Chỉ chạy script này trong Terminal Laragon.\n");
}

$config = require __DIR__ . '/../config/database.php';
if (!preg_match('/^[A-Za-z0-9_]+$/', $config['dbname'])) {
    fwrite(STDERR, "DB_NAME chỉ được gồm chữ, số và dấu gạch dưới.\n");
    exit(1);
}

try {
    $server = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=%s', $config['host'], $config['port'], $config['charset']),
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $server->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $config['dbname']));
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['dbname'], $config['charset']),
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );

    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    if ($schema === false) throw new RuntimeException('Không đọc được database/schema.sql.');
    foreach (preg_split('/;\s*(?:\R|$)/', trim($schema)) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') $pdo->exec($statement);
    }

    // Tự động đồng bộ các cột mới và bảng phụ trợ cho các DB đã tồn tại từ trước
    require_once __DIR__ . '/20261116_safe_additive_sync.php';

    $userInsert = $pdo->prepare('INSERT IGNORE INTO users (username,email,password,full_name,avatar,bio,`role`,is_verified,reputation_score) VALUES (?,?,?,?,?,?,?,?,?)');
    $userInsert->execute(['admin','admin@example.com',password_hash('admin12345', PASSWORD_DEFAULT),'Quản trị viên','/assets/images/avatars/user-admin.svg','Quản trị hệ thống','admin',1,0]);
    $userInsert->execute(['demo','demo@example.com',password_hash('demo12345', PASSWORD_DEFAULT),'Người viết thử','/assets/images/avatars/user-demo.svg','Tài khoản dùng để thử đăng bài và tương tác.','user',0,0]);

    $count = (int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
    if ($count === 0) {
        $demoId = (int)$pdo->query("SELECT id FROM users WHERE username='demo'")->fetchColumn();
        $adminId = (int)$pdo->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();
        $post = $pdo->prepare("INSERT INTO posts (user_id,title,slug,summary,content,post_type,moderation_status) VALUES (?,?,?,?,?,'blog','safe')");
        $post->execute([$demoId,'Chào mừng đến với Góc Nhỏ','chao-mung-goc-nho','Bài viết đầu tiên trên blog.','Đây là bài viết mẫu trong cơ sở dữ liệu. Hãy đăng nhập bằng tài khoản demo để tạo bài viết, bình luận và tương tác.']);
        $firstPost = (int)$pdo->lastInsertId();
        $post->execute([$demoId,'Viết và chia sẻ điều bạn học được','viet-va-chia-se','Một nơi để lưu lại những ý tưởng hữu ích.','Bạn có thể đăng bài blog, gắn hashtag và xây dựng uy tín thông qua tương tác của cộng đồng.']);
        $secondPost = (int)$pdo->lastInsertId();
        $news = $pdo->prepare("INSERT INTO posts (user_id,title,slug,summary,content,post_type,moderation_status) VALUES (?,?,?,?,?,'news','safe')");
        $news->execute([$adminId,'Góc nhìn công nghệ tuần này','goc-nhin-cong-nghe','Tin mẫu để thử khu vực Tin tức.','Đây là bài Tin tức mẫu do tài khoản quản trị tạo. Quản trị viên và tài khoản tích xanh có thể đăng trực tiếp nội dung Tin tức.']);
        $newsPost = (int)$pdo->lastInsertId();
        $tag = $pdo->prepare('INSERT INTO hashtags (name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
        $link = $pdo->prepare('INSERT IGNORE INTO post_hashtags (post_id,hashtag_id) VALUES (?,?)');
        foreach ([$firstPost => ['chao_mung','blog'], $secondPost => ['ghichep','congdong'], $newsPost => ['congnghe','tintuc']] as $postId => $tags) {
            foreach ($tags as $name) {
                $tag->execute([$name]); $tagId = (int)$pdo->lastInsertId(); $link->execute([(int)$postId,$tagId]);
            }
        }
    }

    echo "Đã tạo/cập nhật schema và dữ liệu mẫu cho CSDL {$config['dbname']}.\n";
    echo "Admin: admin@example.com / admin12345\n";
    echo "Demo:  demo@example.com / demo12345\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Cài đặt thất bại: {$exception->getMessage()}\n");
    exit(1);
}
