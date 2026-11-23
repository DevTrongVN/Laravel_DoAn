<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';

/** Quản lý quan hệ Theo dõi & Thuật toán sắp xếp kênh theo tần suất truy cập. */
final class Follow
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function toggle(int $followerId, int $followingId): bool
    {
        if ($followerId === $followingId) return false;

        $check = $this->pdo->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?');
        $check->execute([$followerId, $followingId]);
        $exists = (bool)$check->fetchColumn();

        if ($exists) {
            $del = $this->pdo->prepare('DELETE FROM follows WHERE follower_id = ? AND following_id = ?');
            $del->execute([$followerId, $followingId]);
            return false;
        }

        $ins = $this->pdo->prepare('INSERT INTO follows (follower_id, following_id, created_at) VALUES (?, ?, NOW())');
        $ins->execute([$followerId, $followingId]);
        return true;
    }

    public function isFollowing(int $followerId, int $followingId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?');
        $stmt->execute([$followerId, $followingId]);
        return (bool)$stmt->fetchColumn();
    }

    public function trackVisit(int $followerId, int $followingId): void
    {
        // Schema hiện tại của bảng follows không có cột visit_count; bỏ qua an toàn để tránh lỗi SQL 1054
    }

    /** Lấy danh sách kênh đang theo dõi, sắp xếp theo thứ tự mới nhất */
    public function getFollowingWithVisitRank(int $followerId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id, u.username, u.full_name, u.avatar, u.is_verified, COALESCE(u.reputation_score, 0) AS reputation_score, f.visit_count, f.created_at
            FROM follows f
            JOIN users u ON u.id = f.following_id
            WHERE f.follower_id = ?
            ORDER BY f.created_at DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, $followerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countFollowers(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM follows WHERE following_id = ?');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function countFollowing(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM follows WHERE follower_id = ?');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
}
