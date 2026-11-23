<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';

final class PostReport
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function submit(int $userId, int $postId, string $reason, string $details = ''): int
    {
        $validReasons = ['offensive', 'spam', 'inappropriate', 'misinformation', 'other', 'impersonation', 'harassment'];
        if (!in_array($reason, $validReasons, true)) {
            throw new InvalidArgumentException('Vui lòng chọn một lý do báo cáo hợp lệ.');
        }
        $details = trim($details);
        if (mb_strlen($details) > 1000) {
            throw new InvalidArgumentException('Mô tả báo cáo tối đa 1.000 ký tự.');
        }

        $post = $this->pdo->prepare("SELECT user_id FROM posts WHERE id=? AND moderation_status <> 'blocked'");
        $post->execute([$postId]);
        $ownerId = $post->fetchColumn();
        if ($ownerId === false) {
            throw new RuntimeException('Bài viết không tồn tại hoặc đã bị khóa.');
        }
        if ((int)$ownerId === $userId) {
            throw new DomainException('Bạn không thể báo cáo bài viết của chính mình.');
        }

        $duplicate = $this->pdo->prepare('SELECT id FROM post_reports WHERE user_id=? AND post_id=? LIMIT 1');
        $duplicate->execute([$userId, $postId]);
        if ($duplicate->fetchColumn()) {
            throw new DomainException('Bạn đã gửi báo cáo cho bài viết này trước đó.');
        }

        $stmt = $this->pdo->prepare('INSERT INTO post_reports (user_id, post_id, reason, details) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $postId, $reason, $details !== '' ? $details : null]);
        return (int)$this->pdo->lastInsertId();
    }

    public function listAll(): array
    {
        return $this->pdo->query("
            SELECT r.id, r.post_id, r.user_id, r.reason, r.details, r.status, r.reviewed_by, r.reviewed_at, r.admin_note, r.created_at,
                   p.title AS post_title, p.moderation_status,
                   u.username, u.full_name AS reporter_name,
                   author.full_name AS author_name, author.username AS author_username,
                   reviewer.full_name AS reviewer_name
            FROM post_reports r
            JOIN posts p ON p.id = r.post_id
            JOIN users u ON u.id = r.user_id
            JOIN users author ON author.id = p.user_id
            LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
            ORDER BY (r.status = 'pending') DESC, r.created_at DESC
        ")->fetchAll();
    }

    /**
     * Danh sách báo cáo bài viết có phân trang, tìm kiếm và lọc trạng thái/lý do.
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $where  = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]          = 'r.status = :status';
            $params['status'] = (string)$filters['status'];
        }

        if (!empty($filters['reason'])) {
            $where[]          = 'r.reason = :reason';
            $params['reason'] = (string)$filters['reason'];
        }

        if (!empty($filters['q'])) {
            $where[]     = '(p.title LIKE :q OR u.full_name LIKE :q OR u.username LIKE :q OR author.full_name LIKE :q OR author.username LIKE :q OR r.details LIKE :q)';
            $params['q'] = '%' . trim((string)$filters['q']) . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "
            SELECT COUNT(*)
            FROM post_reports r
            JOIN posts p ON p.id = r.post_id
            JOIN users u ON u.id = r.user_id
            JOIN users author ON author.id = p.user_id
            {$whereClause}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue(':' . $k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT r.id, r.post_id, r.user_id, r.reason, r.details, r.status,
                   r.reviewed_by, r.reviewed_at, r.admin_note, r.created_at,
                   p.title AS post_title, p.moderation_status,
                   u.username, u.full_name AS reporter_name,
                   author.full_name AS author_name, author.username AS author_username,
                   reviewer.full_name AS reviewer_name
            FROM post_reports r
            JOIN posts p ON p.id = r.post_id
            JOIN users u ON u.id = r.user_id
            JOIN users author ON author.id = p.user_id
            LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
            {$whereClause}
            ORDER BY (r.status = 'pending') DESC, r.created_at DESC
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

    public function updateStatus(int $reportId, string $status): bool
    {
        if (!in_array($status, ['pending', 'reviewed', 'dismissed'], true)) {
            throw new InvalidArgumentException('Trạng thái báo cáo không hợp lệ.');
        }
        $stmt = $this->pdo->prepare('UPDATE post_reports SET status=? WHERE id=?');
        $stmt->execute([$status, $reportId]);
        return $stmt->rowCount() > 0;
    }
}
