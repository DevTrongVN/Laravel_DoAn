<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/NotificationHelper.php';

/**
 * Model quản lý Khiếu nại mở khóa bài viết (Post Appeals).
 */
final class PostAppeal
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Tác giả gửi khiếu nại yêu cầu Admin xem xét lại bài viết bị khóa/gắn cờ.
     */
    public function create(int $postId, int $userId, string $reason): int
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Vui lòng nhập lý do / lời giải trình khiếu nại.');
        }
        if (mb_strlen($reason) > 3000) {
            throw new InvalidArgumentException('Nội dung khiếu nại tối đa 3.000 ký tự.');
        }

        // Kiểm tra bài viết
        $checkStmt = $this->pdo->prepare('SELECT id, user_id, title, moderation_status FROM posts WHERE id = ?');
        $checkStmt->execute([$postId]);
        $post = $checkStmt->fetch();

        if (!$post) {
            throw new RuntimeException('Không tìm thấy bài viết.');
        }

        if ((int)$post['user_id'] !== $userId) {
            throw new DomainException('Bạn chỉ có thể gửi khiếu nại cho bài viết do chính mình sở hữu.');
        }

        if (!in_array($post['moderation_status'], ['blocked', 'flagged'], true)) {
            throw new DomainException('Bài viết này đang ở trạng thái an toàn, không cần khiếu nại.');
        }

        // Chặn spam: Nếu bài viết đang có khiếu nại pending
        $pendingStmt = $this->pdo->prepare("SELECT id FROM post_appeals WHERE post_id = ? AND status = 'pending' LIMIT 1");
        $pendingStmt->execute([$postId]);
        if ($pendingStmt->fetchColumn()) {
            throw new DomainException('Bài viết này đang có một khiếu nại chờ Ban Quản Trị xem xét. Vui lòng không gửi trùng lặp.');
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO post_appeals (post_id, user_id, reason, status, created_at)
            VALUES (?, ?, ?, \'pending\', NOW())
        ');
        $stmt->execute([$postId, $userId, $reason]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Lấy khiếu nại mới nhất của một bài viết.
     */
    public function findLatestByPost(int $postId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT pa.*, u.full_name AS reviewer_name
            FROM post_appeals pa
            LEFT JOIN users u ON u.id = pa.reviewed_by
            WHERE pa.post_id = ?
            ORDER BY pa.id DESC
            LIMIT 1
        ');
        $stmt->execute([$postId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Danh sách khiếu nại cho Quản trị viên (có tìm kiếm, lọc status và phân trang).
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $where  = [];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'approved', 'rejected'], true)) {
            $where[]  = 'pa.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['q'])) {
            $where[]  = '(p.title LIKE ? OR u.full_name LIKE ? OR u.username LIKE ? OR pa.reason LIKE ?)';
            $keyword  = '%' . trim((string)$filters['q']) . '%';
            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Đếm tổng
        $countSql = "
            SELECT COUNT(*)
            FROM post_appeals pa
            JOIN posts p ON p.id = pa.post_id
            JOIN users u ON u.id = pa.user_id
            {$whereClause}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Lấy dữ liệu
        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT pa.id, pa.post_id, pa.user_id, pa.reason, pa.status,
                   pa.admin_response, pa.reviewed_by, pa.reviewed_at, pa.created_at,
                   p.title AS post_title, p.moderation_status, p.ai_reason,
                   u.full_name AS author_name, u.username AS author_username, u.avatar AS author_avatar,
                   rev.full_name AS reviewer_name, rev.username AS reviewer_username
            FROM post_appeals pa
            JOIN posts p ON p.id = pa.post_id
            JOIN users u ON u.id = pa.user_id
            LEFT JOIN users rev ON rev.id = pa.reviewed_by
            {$whereClause}
            ORDER BY (pa.status = 'pending') DESC, pa.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $this->pdo->prepare($sql);
        $paramIdx = 1;
        foreach ($params as $val) {
            $stmt->bindValue($paramIdx++, $val, PDO::PARAM_STR);
        }
        $stmt->bindValue($paramIdx++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($paramIdx++, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data'      => $stmt->fetchAll(),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    /**
     * Admin xử lý khiếu nại (Chấp nhận hoặc Từ chối).
     */
    public function resolve(int $appealId, string $action, int $adminId, ?string $adminResponse = null): bool
    {
        if (!in_array($action, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Hành động không hợp lệ. Chọn "approved" hoặc "rejected".');
        }

        $stmt = $this->pdo->prepare('
            SELECT pa.*, p.title AS post_title
            FROM post_appeals pa
            JOIN posts p ON p.id = pa.post_id
            WHERE pa.id = ?
        ');
        $stmt->execute([$appealId]);
        $appeal = $stmt->fetch();

        if (!$appeal) {
            throw new RuntimeException('Không tìm thấy khiếu nại.');
        }

        $postId       = (int)$appeal['post_id'];
        $authorId     = (int)$appeal['user_id'];
        $postTitle    = (string)$appeal['post_title'];
        $responseNote = $adminResponse !== null ? trim($adminResponse) : null;

        $this->pdo->beginTransaction();
        try {
            // Cập nhật trạng thái khiếu nại
            $updateAppeal = $this->pdo->prepare('
                UPDATE post_appeals
                SET status = ?, admin_response = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ?
            ');
            $updateAppeal->execute([$action, $responseNote, $adminId, $appealId]);

            if ($action === 'approved') {
                // Khôi phục bài viết về an toàn
                $updatePost = $this->pdo->prepare("
                    UPDATE posts
                    SET moderation_status = 'safe', ai_status = 'approved', moderated_by = ?, moderated_at = NOW()
                    WHERE id = ?
                ");
                $updatePost->execute([$adminId, $postId]);

                // Gửi thông báo chúc mừng tác giả
                $notifText = "Khiếu nại của bạn cho bài viết \"{$postTitle}\" đã được Quản trị viên CHẤP NHẬN. Bài viết đã được khôi phục hiển thị bình thường.";
                if ($responseNote) {
                    $notifText .= " Lời nhắn từ Admin: {$responseNote}";
                }
                NotificationHelper::send($authorId, $adminId, 'system', $notifText, "/post/{$postId}");
            } else {
                // Từ chối khiếu nại
                $notifText = "Khiếu nại mở khóa cho bài viết \"{$postTitle}\" đã bị Ban Quản Trị TỪ CHỐI.";
                if ($responseNote) {
                    $notifText .= " Lý do: {$responseNote}";
                }
                NotificationHelper::send($authorId, $adminId, 'system', $notifText, "/post/{$postId}");
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
