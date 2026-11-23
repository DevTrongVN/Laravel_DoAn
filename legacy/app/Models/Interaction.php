<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/Reaction.php';

/** Quản lý tương tác: Like, Share, Bookmark bài viết. */
final class Interaction
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function toggleLike(int $userId, int $postId): bool
    {
        return (new Reaction())->set($userId, $postId, 'like') !== null;
    }

    public function share(int $userId, int $postId): bool
    {
        $chk = $this->pdo->prepare('SELECT user_id FROM posts WHERE id = ?');
        $chk->execute([$postId]);
        $authorId = $chk->fetchColumn();
        if ($authorId === false || (int)$authorId === $userId) {
            return false;
        }
        // Unique key (user_id, post_id, type) makes a share idempotent per user/post.
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO interactions (user_id, post_id, type, created_at) VALUES (?, ?, 'share', NOW())");
        $stmt->execute([$userId, $postId]);
        return $stmt->rowCount() > 0;
    }

    public function hasSharedLink(int $userId, int $postId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM interactions WHERE user_id = ? AND post_id = ? AND type = 'share' LIMIT 1");
        $stmt->execute([$userId, $postId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Thêm bookmark cho bài viết (idempotent, đồng bộ bảng interactions và bookmarks).
     */
    public function addBookmark(int $userId, int $postId): bool
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO interactions (user_id, post_id, type, created_at) VALUES (?, ?, 'bookmark', NOW())");
        $stmt->execute([$userId, $postId]);
        try {
            $this->pdo->prepare("INSERT IGNORE INTO bookmarks (user_id, post_id, created_at) VALUES (?, ?, NOW())")->execute([$userId, $postId]);
        } catch (Throwable) {}
        return true;
    }

    /**
     * Xóa bookmark cho bài viết thuộc về người dùng hiện tại (idempotent).
     */
    public function removeBookmark(int $userId, int $postId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM interactions WHERE user_id = ? AND post_id = ? AND type = 'bookmark'");
        $stmt->execute([$userId, $postId]);
        try {
            $this->pdo->prepare("DELETE FROM bookmarks WHERE user_id = ? AND post_id = ?")->execute([$userId, $postId]);
        } catch (Throwable) {}
        return true;
    }

    /**
     * Kiểm tra trạng thái bookmark của người dùng đối với một bài viết.
     */
    public function isBookmarked(int $userId, int $postId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM interactions WHERE user_id = ? AND post_id = ? AND type = 'bookmark' LIMIT 1");
        $stmt->execute([$userId, $postId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Bật/tắt trạng thái bookmark (nếu đã lưu thì xóa, chưa lưu thì thêm).
     */
    public function toggleBookmark(int $userId, int $postId): bool
    {
        if ($this->isBookmarked($userId, $postId)) {
            $this->removeBookmark($userId, $postId);
            return false;
        }

        $this->addBookmark($userId, $postId);
        return true;
    }

    public function isLiked(int $userId, int $postId): bool
    {
        return (new Reaction())->current($userId, $postId) !== null;
    }
}
