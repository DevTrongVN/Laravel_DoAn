<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Models/Post.php';

$pdo = Database::getInstance()->getConnection();
$postModel = new Post();

// Tìm người dùng có tên Mai hoặc Duy
$stmtU = $pdo->query("SELECT id, username, full_name FROM users WHERE full_name LIKE '%Mai%' OR username LIKE '%mai%' OR full_name LIKE '%Duy%'");
$users = $stmtU->fetchAll(PDO::FETCH_ASSOC);
echo "--- USERS MATCHING 'Mai' or 'Duy' ---\n";
foreach ($users as $u) {
    echo "ID: " . $u['id'] . " | @" . $u['username'] . " | " . $u['full_name'] . "\n";
}

// Tìm bài viết của Hoàng Nam (user_id = 3 thường là hoang_nam)
$stmtHN = $pdo->query("SELECT p.id, p.title, p.user_id, u.full_name, u.username FROM posts p JOIN users u ON u.id = p.user_id WHERE u.full_name LIKE '%Hoàng Nam%' OR u.username LIKE '%hoang_nam%' ORDER BY p.id DESC LIMIT 1");
$postHN = $stmtHN->fetch(PDO::FETCH_ASSOC);

// Tìm bài viết của người dùng có họ Huỳnh hoặc tên Mai/Duy
$stmtHDM = $pdo->query("SELECT p.id, p.title, p.user_id, u.full_name, u.username FROM posts p JOIN users u ON u.id = p.user_id WHERE u.full_name LIKE '%Huỳnh%' OR u.full_name LIKE '%Mai%' OR u.full_name LIKE '%Duy%' ORDER BY p.id DESC LIMIT 1");
$postHDM = $stmtHDM->fetch(PDO::FETCH_ASSOC);

// Tìm 1 bài viết khác (ví dụ bài mới nhất trên feed)
$stmtOther = $pdo->query("SELECT p.id, p.title, p.user_id, u.full_name, u.username FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id NOT IN (" . (int)($postHN['id'] ?? 0) . ", " . (int)($postHDM['id'] ?? 0) . ") ORDER BY p.id DESC LIMIT 1");
$postOther = $stmtOther->fetch(PDO::FETCH_ASSOC);

$targets = [$postHN, $postHDM, $postOther];

echo "\n--- 3 BÀI VIẾT ĐƯỢC CHỌN ĐỂ KIỂM THỬ SỐ LIỆU ĐỒNG BỘ ---\n";
foreach ($targets as $tp) {
    if (!$tp) continue;
    echo "ID: " . $tp['id'] . " | Tác giả: " . $tp['full_name'] . " (@" . $tp['username'] . ") | Tiêu đề: " . $tp['title'] . "\n";
}

echo "\n--- SO SÁNH SỐ LIỆU: DB vs Post::find vs Post::list ---\n";

foreach ($targets as $tp) {
    if (!$tp) continue;
    $postId = (int)$tp['id'];
    $userId = (int)$tp['user_id'];

    // 1. Direct DB queries
    $stmtLike = $pdo->prepare("SELECT COUNT(*) FROM post_reactions WHERE post_id = ?");
    $stmtLike->execute([$postId]);
    $dbLikes = (int)$stmtLike->fetchColumn();

    $stmtCmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ? AND moderation_status = 'safe'");
    $stmtCmt->execute([$postId]);
    $dbComments = (int)$stmtCmt->fetchColumn();

    $stmtShare = $pdo->prepare("SELECT COUNT(DISTINCT s.user_id) FROM (SELECT user_id, post_id FROM interactions WHERE type = 'share' UNION ALL SELECT user_id, shared_post_id AS post_id FROM posts WHERE shared_post_id IS NOT NULL AND moderation_status = 'safe') s WHERE s.post_id = ? AND s.user_id <> ?");
    $stmtShare->execute([$postId, $userId]);
    $dbShares = (int)$stmtShare->fetchColumn();

    $stmtView = $pdo->prepare("SELECT views_count FROM posts WHERE id = ?");
    $stmtView->execute([$postId]);
    $dbViews = (int)$stmtView->fetchColumn();

    // 2. Post::find (Detail API / Show)
    $detail = $postModel->find($postId, null);

    // 3. Post::list (Feed / Profile / Search API) lọc chính xác theo post_id hoặc user_id
    // Thử lấy qua user_id filter
    $userList = $postModel->list(['user_id' => $userId], null, 1, 50);
    $inUserList = null;
    foreach ($userList['data'] as $p) {
        if ((int)$p['id'] === $postId) {
            $inUserList = $p;
            break;
        }
    }

    // Thử lấy qua latest list (Feed API)
    $feedList = $postModel->list(['sort' => 'latest'], null, 1, 50);
    $inFeedList = null;
    foreach ($feedList['data'] as $p) {
        if ((int)$p['id'] === $postId) {
            $inFeedList = $p;
            break;
        }
    }

    $listPost = $inUserList ?? $inFeedList;

    echo "\n=======================================================\n";
    echo "BÀI VIẾT ID {$postId} | Tác giả: {$tp['full_name']} (@{$tp['username']})\n";
    echo "Tiêu đề: {$tp['title']}\n";
    echo "-------------------------------------------------------\n";

    echo "1. THÍCH / CẢM XÚC:\n";
    echo "   - DB Thực tế: " . $dbLikes . "\n";
    echo "   - Detail API (Post::find): " . ($detail['likes_count'] ?? 'null') . "\n";
    echo "   - Detail Aliases: likes_count=" . $detail['likes_count'] . ", like_count=" . $detail['like_count'] . ", total_reactions=" . $detail['total_reactions'] . ", reaction_count=" . $detail['reaction_count'] . "\n";
    if ($listPost) {
        echo "   - List API (Post::list): " . ($listPost['likes_count'] ?? 'null') . "\n";
        echo "   - List Aliases: likes_count=" . $listPost['likes_count'] . ", like_count=" . $listPost['like_count'] . ", total_reactions=" . $listPost['total_reactions'] . "\n";
    }
    $likesMatch = ($detail['likes_count'] === $dbLikes) && (!$listPost || $listPost['likes_count'] === $dbLikes);
    echo "   => KHỚP 100%: " . ($likesMatch ? "CHÍNH XÁC (MATCH)" : "LỆCH (MISMATCH)") . "\n";

    echo "\n2. BÌNH LUẬN:\n";
    echo "   - DB Thực tế: " . $dbComments . "\n";
    echo "   - Detail API (Post::find): " . ($detail['comments_count'] ?? 'null') . "\n";
    echo "   - Detail Aliases: comments_count=" . $detail['comments_count'] . ", comment_count=" . $detail['comment_count'] . ", total_comments=" . $detail['total_comments'] . "\n";
    if ($listPost) {
        echo "   - List API (Post::list): " . ($listPost['comments_count'] ?? 'null') . "\n";
        echo "   - List Aliases: comments_count=" . $listPost['comments_count'] . ", comment_count=" . $listPost['comment_count'] . ", total_comments=" . $listPost['total_comments'] . "\n";
    }
    $cmtsMatch = ($detail['comments_count'] === $dbComments) && (!$listPost || $listPost['comments_count'] === $dbComments);
    echo "   => KHỚP 100%: " . ($cmtsMatch ? "CHÍNH XÁC (MATCH)" : "LỆCH (MISMATCH)") . "\n";

    echo "\n3. CHIA SẺ:\n";
    echo "   - DB Thực tế: " . $dbShares . "\n";
    echo "   - Detail API (Post::find): " . ($detail['shares_count'] ?? 'null') . "\n";
    echo "   - Detail Aliases: shares_count=" . $detail['shares_count'] . ", share_count=" . $detail['share_count'] . ", total_shares=" . $detail['total_shares'] . "\n";
    if ($listPost) {
        echo "   - List API (Post::list): " . ($listPost['shares_count'] ?? 'null') . "\n";
        echo "   - List Aliases: shares_count=" . $listPost['shares_count'] . ", share_count=" . $listPost['share_count'] . ", total_shares=" . $listPost['total_shares'] . "\n";
    }
    $sharesMatch = ($detail['shares_count'] === $dbShares) && (!$listPost || $listPost['shares_count'] === $dbShares);
    echo "   => KHỚP 100%: " . ($sharesMatch ? "CHÍNH XÁC (MATCH)" : "LỆCH (MISMATCH)") . "\n";

    echo "\n4. LƯỢT XEM:\n";
    echo "   - DB Thực tế (posts.views_count): " . $dbViews . "\n";
    echo "   - Detail API (Post::find): " . ($detail['views_count'] ?? 'null') . "\n";
    echo "   - Detail Aliases: views_count=" . $detail['views_count'] . ", views=" . $detail['views'] . ", view_count=" . $detail['view_count'] . "\n";
    if ($listPost) {
        echo "   - List API (Post::list): " . ($listPost['views_count'] ?? 'null') . "\n";
        echo "   - List Aliases: views_count=" . $listPost['views_count'] . ", views=" . $listPost['views'] . ", view_count=" . $listPost['view_count'] . "\n";
    }
    $viewsMatch = ($detail['views_count'] === $dbViews) && (!$listPost || $listPost['views_count'] === $dbViews);
    echo "   => KHỚP 100%: " . ($viewsMatch ? "CHÍNH XÁC (MATCH)" : "LỆCH (MISMATCH)") . "\n";
}
