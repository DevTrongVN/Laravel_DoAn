<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';

/** Quản lý thẻ Hashtag & Thống kê Top Hashtags HOT. */
final class Tag
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function top(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare("
            SELECT h.id, h.name, COUNT(DISTINCT ph.post_id) AS usage_count
            FROM hashtags h
            JOIN post_hashtags ph ON ph.hashtag_id = h.id
            JOIN posts p ON p.id = ph.post_id
            WHERE p.moderation_status = 'safe'
            GROUP BY h.id, h.name
            ORDER BY usage_count DESC, h.name ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncPostTags(int $postId, array $tags): void
    {
        $this->pdo->prepare('DELETE FROM post_hashtags WHERE post_id = ?')->execute([$postId]);
        foreach ($tags as $tag) {
            $name = strtolower(trim(ltrim((string)$tag, '#')));
            if ($name === '') continue;
            
            $stmt = $this->pdo->prepare('INSERT IGNORE INTO hashtags (name) VALUES (?)');
            $stmt->execute([$name]);

            $find = $this->pdo->prepare('SELECT id FROM hashtags WHERE name = ? LIMIT 1');
            $find->execute([$name]);
            $tagId = $find->fetchColumn();

            if ($tagId) {
                $this->pdo->prepare('INSERT IGNORE INTO post_hashtags (post_id, hashtag_id) VALUES (?, ?)')
                    ->execute([$postId, (int)$tagId]);
            }
        }
    }

    public function getTagsByPost(int $postId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT h.name FROM hashtags h
            JOIN post_hashtags ph ON ph.hashtag_id = h.id
            WHERE ph.post_id = ?
        ');
        $stmt->execute([$postId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}
