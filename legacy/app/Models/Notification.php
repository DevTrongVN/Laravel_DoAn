<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';

/** Quản lý thông báo người dùng (có phân trang). */
final class Notification
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Lấy danh sách thông báo của người dùng, có phân trang.
     * Trả về: ['data' => [...], 'total' => int, 'page' => int, 'last_page' => int, 'unread_count' => int]
     */
    public function getByUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
        $countStmt->execute([$userId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare('
            SELECT n.id, n.type, n.content, n.target_url, n.is_read, n.created_at,
                   u.username AS actor_username, u.full_name AS actor_name, u.avatar AS actor_avatar
            FROM notifications n
            LEFT JOIN users u ON u.id = n.actor_id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(3, ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data'         => $stmt->fetchAll(),
            'total'        => $total,
            'page'         => $page,
            'last_page'    => max(1, (int)ceil($total / $perPage)),
            'unread_count' => $this->countUnread($userId),
        ];
    }

    public function countUnread(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function markAsRead(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        return $stmt->execute([$id, $userId]);
    }

    public function markAllAsRead(int $userId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        return $stmt->execute([$userId]);
    }
}
