<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Models/Post.php';

$pdo = Database::getInstance()->getConnection();
$postModel = new Post();

$targetPostIds = [
    4580 => 'Hoàng Nam (@hoang_nam)',
    4655 => 'Huỳnh Duy Mai (@duy-mai_hub)',
    1    => 'RobloxAdmin (@admin)'
];

echo "========================================================================================\n";
echo "BÁO CÁO KIỂM THỬ ĐỒNG BỘ SỐ LIỆU TƯƠNG TÁC (SINGLE SOURCE OF TRUTH)\n";
echo "========================================================================================\n\n";

$allPassed = true;

foreach ($targetPostIds as $postId => $authorInfo) {
    // 1. Lấy thông tin bài viết
    $stmtP = $pdo->prepare("SELECT p.id, p.user_id, p.title, p.views_count, u.full_name, u.username FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id = ?");
    $stmtP->execute([$postId]);
    $p = $stmtP->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        echo "LỖI: Không tìm thấy bài viết ID {$postId}\n";
        $allPassed = false;
        continue;
    }

    $userId = (int)$p['user_id'];

    // 2. Truy vấn thực tế trực tiếp từ bảng quan hệ trong MySQL
    // Cảm xúc: đếm từ post_reactions
    $stmtReactions = $pdo->prepare("SELECT COUNT(*) FROM post_reactions WHERE post_id = ?");
    $stmtReactions->execute([$postId]);
    $sqlLikes = (int)$stmtReactions->fetchColumn();

    // Bình luận: đếm từ comments (moderation_status = 'safe')
    $stmtComments = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ? AND moderation_status = 'safe'");
    $stmtComments->execute([$postId]);
    $sqlComments = (int)$stmtComments->fetchColumn();

    // Chia sẻ: đếm distinct user từ interactions (share) + posts (repost an toàn)
    $stmtShares = $pdo->prepare("SELECT COUNT(DISTINCT s.user_id) FROM (SELECT user_id, post_id FROM interactions WHERE type = 'share' UNION ALL SELECT user_id, shared_post_id AS post_id FROM posts WHERE shared_post_id IS NOT NULL AND moderation_status = 'safe') s WHERE s.post_id = ? AND s.user_id <> ?");
    $stmtShares->execute([$postId, $userId]);
    $sqlShares = (int)$stmtShares->fetchColumn();

    // Lượt xem: đọc từ cột views_count trong bảng posts
    $sqlViews = (int)$p['views_count'];

    // 3. Gọi qua Post::find (Detail API /show)
    $detailData = $postModel->find($postId, null);

    // 4. Gọi qua Post::list (Feed API / Danh sách bài viết)
    $listDataRes = $postModel->list(['user_id' => $userId], null, 1, 50);
    $listData = null;
    foreach ($listDataRes['data'] as $item) {
        if ((int)$item['id'] === $postId) {
            $listData = $item;
            break;
        }
    }

    echo "----------------------------------------------------------------------------------------\n";
    echo "BÀI VIẾT ID {$postId}: \"{$p['title']}\"\n";
    echo "Tác giả: {$p['full_name']} (@{$p['username']})\n";
    echo "----------------------------------------------------------------------------------------\n";

    // Kiểm tra Thích / Cảm xúc
    $detailLikes = $detailData['likes_count'] ?? null;
    $listLikes = $listData['likes_count'] ?? null;
    $likesMatch = ($detailLikes === $sqlLikes && $listLikes === $sqlLikes);
    echo "1. THÍCH / CẢM XÚC (Like / Reaction):\n";
    echo "   - MySQL DB (post_reactions) : {$sqlLikes}\n";
    echo "   - API Danh sách (Post::list): {$listLikes}\n";
    echo "   - API Chi tiết (Post::find) : {$detailLikes}\n";
    echo "   - JSON Aliases kiểm tra    : likes_count={$detailData['likes_count']}, like_count={$detailData['like_count']}, total_reactions={$detailData['total_reactions']}, reaction_count={$detailData['reaction_count']}\n";
    echo "   => Kết quả: " . ($likesMatch ? "[PASSED] Khớp 100%" : "[FAILED] Lệch số liệu") . "\n\n";

    // Kiểm tra Bình luận
    $detailComments = $detailData['comments_count'] ?? null;
    $listComments = $listData['comments_count'] ?? null;
    $commentsMatch = ($detailComments === $sqlComments && $listComments === $sqlComments);
    echo "2. BÌNH LUẬN (Comments):\n";
    echo "   - MySQL DB (comments safe)  : {$sqlComments}\n";
    echo "   - API Danh sách (Post::list): {$listComments}\n";
    echo "   - API Chi tiết (Post::find) : {$detailComments}\n";
    echo "   - JSON Aliases kiểm tra    : comments_count={$detailData['comments_count']}, comment_count={$detailData['comment_count']}, total_comments={$detailData['total_comments']}\n";
    echo "   => Kết quả: " . ($commentsMatch ? "[PASSED] Khớp 100%" : "[FAILED] Lệch số liệu") . "\n\n";

    // Kiểm tra Chia sẻ
    $detailShares = $detailData['shares_count'] ?? null;
    $listShares = $listData['shares_count'] ?? null;
    $sharesMatch = ($detailShares === $sqlShares && $listShares === $sqlShares);
    echo "3. CHIA SẺ (Shares):\n";
    echo "   - MySQL DB (shares distinct): {$sqlShares}\n";
    echo "   - API Danh sách (Post::list): {$listShares}\n";
    echo "   - API Chi tiết (Post::find) : {$detailShares}\n";
    echo "   - JSON Aliases kiểm tra    : shares_count={$detailData['shares_count']}, share_count={$detailData['share_count']}, total_shares={$detailData['total_shares']}\n";
    echo "   => Kết quả: " . ($sharesMatch ? "[PASSED] Khớp 100%" : "[FAILED] Lệch số liệu") . "\n\n";

    // Kiểm tra Lượt xem
    $detailViews = $detailData['views_count'] ?? null;
    $listViews = $listData['views_count'] ?? null;
    $viewsMatch = ($detailViews === $sqlViews && $listViews === $sqlViews);
    echo "4. LƯỢT XEM (Views):\n";
    echo "   - MySQL DB (posts.views)    : {$sqlViews}\n";
    echo "   - API Danh sách (Post::list): {$listViews}\n";
    echo "   - API Chi tiết (Post::find) : {$detailViews}\n";
    echo "   - JSON Aliases kiểm tra    : views_count={$detailData['views_count']}, views={$detailData['views']}, view_count={$detailData['view_count']}\n";
    echo "   => Kết quả: " . ($viewsMatch ? "[PASSED] Khớp 100%" : "[FAILED] Lệch số liệu") . "\n\n";

    if (!$likesMatch || !$commentsMatch || !$sharesMatch || !$viewsMatch) {
        $allPassed = false;
    }
}

echo "========================================================================================\n";
echo "KẾT LUẬN CHUNG: " . ($allPassed ? "TẤT CẢ 3 BÀI VIẾT KHỚP CHÍNH XÁC 100% VỚI NGUỒN SỰ THẬT MYSQL!" : "CÒN LỖI LỆCH SỐ LIỆU!") . "\n";
echo "========================================================================================\n";
