<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/UnicodeHelper.php';
require_once __DIR__ . '/../Services/MentionService.php';

/** Quản lý bình luận bài viết, phân cấp cha-con (soft-delete giữ cấu trúc), @Mention & kiểm duyệt AI. */
final class Comment
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getByPost(int $postId, string $status = 'safe', ?int $viewerId = null): array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.id, c.post_id, c.user_id, c.parent_id, c.root_id,
                   IF(c.is_deleted = 1, "", c.content) AS content,
                   c.moderation_status, c.is_deleted, c.deleted_at, c.edited_at, c.created_at,
                   u.username, u.full_name, u.avatar, u.is_verified, u.role,
                   (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS likes_count,
                   (SELECT cl.reaction FROM comment_likes cl WHERE cl.comment_id = c.id AND cl.user_id = ? LIMIT 1) AS my_reaction,
                   (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id AND cl.user_id = ?) AS liked_by_me
            FROM comments c
            JOIN users u ON u.id = c.user_id
            WHERE c.post_id = ? AND (c.moderation_status = ? OR c.is_deleted = 1)
            ORDER BY c.created_at ASC, c.id ASC
        ');
        $stmt->execute([$viewerId ?? 0, $viewerId ?? 0, $postId, $status]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['id']          = (int)$r['id'];
            $r['post_id']     = (int)$r['post_id'];
            $r['user_id']     = (int)$r['user_id'];
            $r['parent_id']   = $r['parent_id'] === null ? null : (int)$r['parent_id'];
            $r['root_id']     = $r['root_id'] === null ? null : (int)$r['root_id'];
            $r['is_deleted']  = (bool)$r['is_deleted'];
            $r['likes_count'] = (int)$r['likes_count'];
            $r['liked_by_me'] = (bool)$r['liked_by_me'];
            $r['is_verified'] = (bool)$r['is_verified'];
            $r['user'] = [
                'id'          => $r['user_id'],
                'username'    => $r['username'],
                'full_name'   => $r['full_name'],
                'avatar'      => $r['avatar'],
                'is_verified' => $r['is_verified'],
            ];
        }
        unset($r);

        return (new MentionService())->hydrateComments($rows);
    }

    public function create(int $postId, int $userId, string $content, string $status = 'safe', ?int $parentId = null): int
    {
        $content = UnicodeHelper::toNfc($content);
        $rootId = null;

        if ($parentId !== null) {
            $pStmt = $this->pdo->prepare('SELECT id, post_id, parent_id, root_id, is_deleted FROM comments WHERE id = ? LIMIT 1');
            $pStmt->execute([$parentId]);
            $parent = $pStmt->fetch();
            if (!$parent || (int)$parent['post_id'] !== $postId || !empty($parent['is_deleted'])) {
                throw new InvalidArgumentException('Bình luận được trả lời không hợp lệ hoặc đã bị xóa.');
            }
            $rootId = $parent['root_id'] !== null ? (int)$parent['root_id'] : (int)$parent['id'];
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO comments (post_id, user_id, parent_id, root_id, content, moderation_status, is_deleted, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ');
        $stmt->execute([$postId, $userId, $parentId, $rootId, $content, $status]);
        return (int)$this->pdo->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.*, u.username, u.full_name, u.avatar, u.is_verified
            FROM comments c
            JOIN users u ON u.id = c.user_id
            WHERE c.id = ? LIMIT 1
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['id']         = (int)$row['id'];
        $row['post_id']    = (int)$row['post_id'];
        $row['user_id']    = (int)$row['user_id'];
        $row['parent_id']  = $row['parent_id'] === null ? null : (int)$row['parent_id'];
        $row['root_id']    = isset($row['root_id']) && $row['root_id'] !== null ? (int)$row['root_id'] : null;
        $row['is_deleted'] = !empty($row['is_deleted']);
        if ($row['is_deleted']) {
            $row['content'] = '';
        }
        $row['user'] = [
            'id'          => $row['user_id'],
            'username'    => $row['username'],
            'full_name'   => $row['full_name'],
            'avatar'      => $row['avatar'],
            'is_verified' => (bool)$row['is_verified'],
        ];

        $hydrated = (new MentionService())->hydrateComments([$row]);
        return $hydrated[0];
    }

    public function updateModerationStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE comments SET moderation_status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    public function updateContent(int $commentId, int $userId, string $content): bool
    {
        $content = UnicodeHelper::toNfc($content);
        $stmt = $this->pdo->prepare("UPDATE comments SET content = ?, edited_at = NOW() WHERE id = ? AND user_id = ? AND moderation_status = 'safe' AND is_deleted = 0");
        $stmt->execute([$content, $commentId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /** Kiểm tra bình luận có bình luận con (bất kể trạng thái) hay không */
    public function hasChildren(int $commentId): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM comments WHERE parent_id = ? LIMIT 1');
        $st->execute([$commentId]);
        return (bool)$st->fetchColumn();
    }

    /**
     * Xóa bình luận bảo toàn phân cấp giống Facebook:
     * - Nếu có bình luận con -> Xóa mềm (is_deleted = 1, giữ parent_id & root_id, hiển thị placeholder).
     * - Nếu là nút lá (không có con) -> Xóa vật lý, sau đó dọn các tổ tiên đã xóa mềm nếu nhánh đó không còn con sống nào.
     *
     * @return array{ok: bool, soft_deleted: bool}
     */
    public function deleteWithHierarchy(int $commentId, int $actorId, bool $isAdmin = false): array
    {
        $check = $this->pdo->prepare('SELECT c.id, c.user_id, c.parent_id, c.is_deleted, p.user_id AS post_owner_id FROM comments c JOIN posts p ON p.id = c.post_id WHERE c.id = ? LIMIT 1');
        $check->execute([$commentId]);
        $row = $check->fetch();
        if (!$row) {
            return ['ok' => false, 'soft_deleted' => false];
        }
        if (!$isAdmin && (int)$row['user_id'] !== $actorId && (int)$row['post_owner_id'] !== $actorId) {
            return ['ok' => false, 'soft_deleted' => false];
        }
        if (!empty($row['is_deleted'])) {
            return ['ok' => true, 'soft_deleted' => true];
        }

        $this->pdo->beginTransaction();
        try {
            if ($this->hasChildren($commentId)) {
                // Soft delete để giữ nguyên cây cha-con nhiều cấp
                $this->pdo->prepare("UPDATE comments SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, content = '' WHERE id = ?")
                    ->execute([$actorId, $commentId]);
                $this->pdo->prepare('DELETE FROM comment_likes WHERE comment_id = ?')->execute([$commentId]);
                $this->pdo->prepare('DELETE FROM comment_mentions WHERE comment_id = ?')->execute([$commentId]);
                $this->pdo->commit();
                return ['ok' => true, 'soft_deleted' => true];
            }

            // Nút lá không có con -> xóa vật lý an toàn
            $parentId = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
            $this->pdo->prepare('DELETE FROM comments WHERE id = ?')->execute([$commentId]);

            // Dọn ngược lên tổ tiên nếu tổ tiên đã xóa mềm và nay không còn bất kỳ con nào
            $guard = 0;
            while ($parentId !== null && $guard < 50) {
                $guard++;
                $pStmt = $this->pdo->prepare('SELECT id, parent_id, is_deleted FROM comments WHERE id = ? LIMIT 1');
                $pStmt->execute([$parentId]);
                $pRow = $pStmt->fetch();
                if (!$pRow || empty($pRow['is_deleted'])) {
                    break;
                }
                if ($this->hasChildren($parentId)) {
                    break;
                }
                $nextParent = $pRow['parent_id'] !== null ? (int)$pRow['parent_id'] : null;
                $this->pdo->prepare('DELETE FROM comments WHERE id = ?')->execute([$parentId]);
                $parentId = $nextParent;
            }

            $this->pdo->commit();
            return ['ok' => true, 'soft_deleted' => false];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Comment author or author of the parent post may delete (tương thích ngược). */
    public function deleteAuthorized(int $commentId, int $actorId): bool
    {
        return $this->deleteWithHierarchy($commentId, $actorId, false)['ok'];
    }

    public function setReaction(int $commentId, int $userId, ?string $reaction): ?string
    {
        $valid = ['like', 'love', 'haha', 'sad', 'angry'];
        if ($reaction !== null && !in_array($reaction, $valid, true)) throw new InvalidArgumentException('Loại cảm xúc không hợp lệ.');
        $check = $this->pdo->prepare("SELECT 1 FROM comments WHERE id = ? AND moderation_status = 'safe' AND is_deleted = 0");
        $check->execute([$commentId]);
        if (!$check->fetchColumn()) throw new RuntimeException('Không tìm thấy bình luận.');
        $find = $this->pdo->prepare('SELECT reaction FROM comment_likes WHERE comment_id = ? AND user_id = ?');
        $find->execute([$commentId, $userId]);
        $current = $find->fetchColumn();
        if ($current !== false && $current === $reaction) {
            $this->pdo->prepare('DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?')->execute([$commentId, $userId]);
            return null;
        }
        if ($reaction === null) {
            $this->pdo->prepare('DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?')->execute([$commentId, $userId]);
            return null;
        }
        $this->pdo->prepare('INSERT INTO comment_likes (comment_id, user_id, reaction) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE reaction = VALUES(reaction)')->execute([$commentId, $userId, $reaction]);
        return $reaction;
    }
}
