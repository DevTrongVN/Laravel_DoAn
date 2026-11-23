<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';

final class Reaction
{
    public const TYPES = ['like', 'love', 'haha', 'sad', 'angry'];
    private PDO $pdo;

    public function __construct() { $this->pdo = Database::getInstance()->getConnection(); }

    /** Selecting the current reaction a second time removes it; choosing another replaces it. */
    public function set(int $userId, int $postId, ?string $reaction): ?string
    {
        if ($reaction !== null && !in_array($reaction, self::TYPES, true)) {
            throw new InvalidArgumentException('Loại cảm xúc không hợp lệ.');
        }
        $find = $this->pdo->prepare('SELECT reaction FROM post_reactions WHERE user_id = ? AND post_id = ?');
        $find->execute([$userId, $postId]);
        $current = $find->fetchColumn();
        if ($current !== false && $current === $reaction) {
            $this->pdo->prepare('DELETE FROM post_reactions WHERE user_id = ? AND post_id = ?')->execute([$userId, $postId]);
            return null;
        }
        if ($reaction === null) {
            $this->pdo->prepare('DELETE FROM post_reactions WHERE user_id = ? AND post_id = ?')->execute([$userId, $postId]);
            return null;
        }
        $stmt = $this->pdo->prepare('INSERT INTO post_reactions (user_id, post_id, reaction) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE reaction = VALUES(reaction)');
        $stmt->execute([$userId, $postId, $reaction]);
        return $reaction;
    }

    public function counts(int $postId): array
    {
        $rows = $this->pdo->prepare('SELECT reaction, COUNT(*) AS total FROM post_reactions WHERE post_id = ? GROUP BY reaction');
        $rows->execute([$postId]);
        $counts = array_fill_keys(self::TYPES, 0);
        foreach ($rows->fetchAll() as $row) $counts[$row['reaction']] = (int)$row['total'];
        return $counts;
    }

    public function current(int $userId, int $postId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT reaction FROM post_reactions WHERE user_id = ? AND post_id = ?');
        $stmt->execute([$userId, $postId]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string)$value;
    }
}
