<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';

/**
 * Model xử lý báo cáo bình luận vi phạm.
 * Lý do: offensive | spam | inappropriate | misinformation | other
 * Trạng thái: pending | reviewed | rejected
 */
final class CommentReport
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Gửi báo cáo bình luận.
     * Ném ngoại lệ nếu tự báo cáo bình luận của mình, hoặc đã báo cáo trước đó.
     */
    public function create(int $commentId, int $userId, string $reason, ?string $details = null): int
    {
        $validReasons = ['offensive', 'spam', 'inappropriate', 'misinformation', 'other'];
        if (!in_array($reason, $validReasons, true)) {
            throw new InvalidArgumentException('Lý do báo cáo không hợp lệ.');
        }

        $details = $details !== null ? trim($details) : null;
        if ($details !== null && mb_strlen($details) > 1000) {
            throw new InvalidArgumentException('Mô tả báo cáo tối đa 1.000 ký tự.');
        }

        // Kiểm tra bình luận tồn tại & không phải của chính mình
        $check = $this->pdo->prepare("SELECT user_id FROM comments WHERE id = ? AND moderation_status <> 'blocked' LIMIT 1");
        $check->execute([$commentId]);
        $commentOwnerId = $check->fetchColumn();
        if ($commentOwnerId === false) {
            throw new RuntimeException('Bình luận không tồn tại hoặc đã bị khóa.');
        }
        if ((int)$commentOwnerId === $userId) {
            throw new \DomainException('Bạn không thể báo cáo bình luận của chính mình.');
        }

        // Chống báo cáo trùng
        $dup = $this->pdo->prepare('SELECT id FROM comment_reports WHERE user_id = ? AND comment_id = ? LIMIT 1');
        $dup->execute([$userId, $commentId]);
        if ($dup->fetchColumn()) {
            throw new \DomainException('Bạn đã gửi báo cáo cho bình luận này trước đó.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO comment_reports (comment_id, user_id, reason, details) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$commentId, $userId, $reason, $details !== '' ? $details : null]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Lấy chi tiết một báo cáo theo id.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT cr.*, c.content AS comment_content, c.post_id,
                   reporter.username AS reporter_username, reporter.full_name AS reporter_name,
                   author.username AS comment_author_username, author.full_name AS comment_author_name,
                   reviewer.full_name AS reviewer_name
            FROM comment_reports cr
            JOIN comments c ON c.id = cr.comment_id
            JOIN users reporter ON reporter.id = cr.user_id
            JOIN users author ON author.id = c.user_id
            LEFT JOIN users reviewer ON reviewer.id = cr.reviewed_by
            WHERE cr.id = ?
            LIMIT 1
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Danh sách báo cáo bình luận (admin), có lọc theo status, reason, q và phân trang.
     * Trả về: ['data' => [...], 'total' => int, 'page' => int, 'last_page' => int, 'per_page' => int]
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $where  = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]          = 'cr.status = :status';
            $params['status'] = (string)$filters['status'];
        }

        if (!empty($filters['reason'])) {
            $where[]          = 'cr.reason = :reason';
            $params['reason'] = (string)$filters['reason'];
        }

        if (!empty($filters['q'])) {
            $where[]     = '(c.content LIKE :q OR reporter.full_name LIKE :q OR reporter.username LIKE :q OR author.full_name LIKE :q OR author.username LIKE :q OR cr.details LIKE :q)';
            $params['q'] = '%' . trim((string)$filters['q']) . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "
            SELECT COUNT(*)
            FROM comment_reports cr
            JOIN comments c ON c.id = cr.comment_id
            JOIN users reporter ON reporter.id = cr.user_id
            JOIN users author ON author.id = c.user_id
            {$whereClause}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue(':' . $k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT cr.id, cr.comment_id, cr.user_id, cr.reason, cr.details, cr.status,
                   cr.reviewed_by, cr.reviewed_at, cr.admin_note, cr.created_at,
                   c.content AS comment_content, c.post_id, c.moderation_status AS comment_moderation_status,
                   p.title AS post_title,
                   reporter.username AS reporter_username, reporter.full_name AS reporter_name,
                   author.username AS comment_author_username, author.full_name AS comment_author_name,
                   reviewer.full_name AS reviewer_name
            FROM comment_reports cr
            JOIN comments c ON c.id = cr.comment_id
            JOIN posts p ON p.id = c.post_id
            JOIN users reporter ON reporter.id = cr.user_id
            JOIN users author ON author.id = c.user_id
            LEFT JOIN users reviewer ON reviewer.id = cr.reviewed_by
            {$whereClause}
            ORDER BY (cr.status = 'pending') DESC, cr.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
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
     * Cập nhật trạng thái xử lý báo cáo (Admin).
     */
    public function updateStatus(int $id, string $status, int $reviewedBy, ?string $adminNote = null): bool
    {
        if (!in_array($status, ['pending', 'reviewed', 'rejected'], true)) {
            throw new InvalidArgumentException('Trạng thái không hợp lệ. Chấp nhận: pending, reviewed, rejected.');
        }
        $adminNote = $adminNote !== null ? trim($adminNote) : null;
        if ($adminNote !== null && mb_strlen($adminNote) > 1000) {
            throw new InvalidArgumentException('Ghi chú Admin tối đa 1.000 ký tự.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE comment_reports SET status = ?, reviewed_by = ?, reviewed_at = NOW(), admin_note = ? WHERE id = ?'
        );
        $stmt->execute([$status, $reviewedBy, $adminNote, $id]);
        return $stmt->rowCount() > 0;
    }
}
