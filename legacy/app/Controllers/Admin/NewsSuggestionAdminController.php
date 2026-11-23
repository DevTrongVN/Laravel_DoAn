<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/NotificationHelper.php';
require_once __DIR__ . '/../../Models/Ticket.php';

/**
 * Quản trị viên: Quản lý Đề xuất Tin tức từ 2 nguồn (Ticket do người dùng gửi & AI gợi ý tiềm năng tin tức).
 */
class NewsSuggestionAdminController extends Controller
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    private function checkAdmin(): void
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập để tiếp tục.'], 401);
        }
        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Chỉ quản trị viên mới có quyền thực hiện.'], 403);
        }
    }

    /**
     * GET /api/admin/news-suggestions
     * Hỗ trợ tìm kiếm, lọc theo nguồn (all, ticket, ai), trạng thái (all, pending, approved, rejected) và phân trang.
     */
    public function index(): never
    {
        $this->checkAdmin();

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 15)));
        $source  = trim((string)($_GET['source'] ?? 'all'));
        $status  = trim((string)($_GET['status'] ?? ''));
        $q       = trim((string)($_GET['q'] ?? ''));

        // Xây dựng unified query từ 2 nguồn: tickets (upgrade) và posts (ai_news_suggested = 1)
        $queries = [];

        if ($source === 'all' || $source === 'ticket') {
            $queries[] = "
                SELECT 
                    CONVERT(CONCAT('ticket_', t.id) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS unified_id,
                    CONVERT('ticket' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source,
                    CONVERT('Ticket từ tác giả' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_label,
                    t.id AS raw_id,
                    t.post_id,
                    CONVERT(p.title USING utf8mb4) COLLATE utf8mb4_unicode_ci AS post_title,
                    CONVERT(p.post_type USING utf8mb4) COLLATE utf8mb4_unicode_ci AS post_type,
                    CONVERT(p.moderation_status USING utf8mb4) COLLATE utf8mb4_unicode_ci AS moderation_status,
                    u.id AS user_id,
                    CONVERT(u.full_name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS author_name,
                    CONVERT(u.username USING utf8mb4) COLLATE utf8mb4_unicode_ci AS author_username,
                    t.author_reputation_snapshot AS author_reputation,
                    CONVERT('Tác giả gửi yêu cầu nâng bài viết lên Tin tức' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS suggestion_reason,
                    CONVERT(t.status USING utf8mb4) COLLATE utf8mb4_unicode_ci AS status,
                    CONVERT(COALESCE(t.admin_note, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS admin_note,
                    t.created_at
                FROM tickets t
                JOIN posts p ON p.id = t.post_id
                JOIN users u ON u.id = t.user_id
                WHERE t.ticket_type = 'upgrade'
            ";
        }

        if ($source === 'all' || $source === 'ai') {
            $queries[] = "
                SELECT 
                    CONVERT(CONCAT('ai_', p.id) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS unified_id,
                    CONVERT('ai' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source,
                    CONVERT('AI gợi ý tiềm năng' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_label,
                    p.id AS raw_id,
                    p.id AS post_id,
                    CONVERT(p.title USING utf8mb4) COLLATE utf8mb4_unicode_ci AS post_title,
                    CONVERT(p.post_type USING utf8mb4) COLLATE utf8mb4_unicode_ci AS post_type,
                    CONVERT(p.moderation_status USING utf8mb4) COLLATE utf8mb4_unicode_ci AS moderation_status,
                    u.id AS user_id,
                    CONVERT(u.full_name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS author_name,
                    CONVERT(u.username USING utf8mb4) COLLATE utf8mb4_unicode_ci AS author_username,
                    u.reputation_score AS author_reputation,
                    CONVERT(COALESCE(p.ai_news_reason, 'Hệ thống AI đánh giá bài viết có giá trị thông tin cao') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS suggestion_reason,
                    CONVERT(COALESCE(p.ai_status, 'pending') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS status,
                    CONVERT(COALESCE(p.ai_reason, '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS admin_note,
                    p.created_at
                FROM posts p
                JOIN users u ON u.id = p.user_id
                WHERE p.ai_news_suggested = 1
            ";
        }

        if (empty($queries)) {
            $this->json(['data' => [], 'pagination' => ['page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1]]);
        }

        $unionSql = implode(' UNION ALL ', $queries);

        $where  = [];
        $params = [];

        if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $where[]          = 'sub.status = :status';
            $params['status'] = $status;
        }

        if ($q !== '') {
            $where[]      = '(sub.post_title LIKE :q1 OR sub.author_name LIKE :q2 OR sub.author_username LIKE :q3 OR sub.suggestion_reason LIKE :q4)';
            $searchTerm   = '%' . $q . '%';
            $params['q1'] = $searchTerm;
            $params['q2'] = $searchTerm;
            $params['q3'] = $searchTerm;
            $params['q4'] = $searchTerm;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Đếm tổng số
        $countSql = "SELECT COUNT(*) FROM ({$unionSql}) sub {$whereSql}";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue(':' . $k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        // Lấy dữ liệu phân trang
        $sql = "
            SELECT sub.*
            FROM ({$unionSql}) sub
            {$whereSql}
            ORDER BY (sub.status = 'pending') DESC, sub.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        foreach ($items as &$item) {
            $item['raw_id']            = (int)$item['raw_id'];
            $item['post_id']           = (int)$item['post_id'];
            $item['user_id']           = (int)$item['user_id'];
            $item['author_reputation'] = (int)$item['author_reputation'];
        }
        unset($item);

        $this->json([
            'data'       => $items,
            'pagination' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => max(1, (int)ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * POST /api/admin/news-suggestions/:id/review
     * Body JSON: { "source": "ticket"|"ai", "action": "approved"|"rejected", "admin_note"?: string }
     */
    public function review(string $id): never
    {
        $this->checkAdmin();

        $raw       = file_get_contents('php://input');
        $data      = json_decode($raw ?: '', true) ?: $_POST;
        $source    = (string)($data['source'] ?? '');
        $action    = (string)($data['action'] ?? $data['status'] ?? '');
        $adminNote = trim((string)($data['admin_note'] ?? ''));
        $adminId   = (int)Auth::id();

        if (!in_array($action, ['approved', 'rejected'], true)) {
            $this->json(['error' => 'Hành động duyệt không hợp lệ. Chọn "approved" hoặc "rejected".'], 422);
        }

        // Nếu id có prefix như 'ticket_12' hoặc 'ai_34', tự tách
        if (str_starts_with($id, 'ticket_')) {
            $source = 'ticket';
            $rawId  = (int)substr($id, 7);
        } elseif (str_starts_with($id, 'ai_')) {
            $source = 'ai';
            $rawId  = (int)substr($id, 3);
        } else {
            $rawId = (int)$id;
        }

        if ($source === 'ticket') {
            try {
                (new Ticket())->resolve($rawId, $action, $adminNote !== '' ? $adminNote : null);
                $this->json([
                    'message' => $action === 'approved' ? 'Đã duyệt ticket thành Tin tức thành công.' : 'Đã từ chối ticket.',
                    'action'  => $action,
                ]);
            } catch (Throwable $e) {
                $this->json(['error' => $e->getMessage()], 422);
            }
        } elseif ($source === 'ai') {
            // Nguồn AI: rawId là post_id
            $postId = $rawId;
            $find   = $this->pdo->prepare('SELECT id, user_id, title FROM posts WHERE id = ?');
            $find->execute([$postId]);
            $post = $find->fetch();

            if (!$post) {
                $this->json(['error' => 'Không tìm thấy bài viết.'], 404);
            }

            if ($action === 'approved') {
                $stmt = $this->pdo->prepare("
                    UPDATE posts 
                    SET post_type = 'news', ai_status = 'approved', moderated_by = ?, moderated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$adminId, $postId]);

                // Thông báo chúc mừng tác giả
                $notifContent = 'Chúc mừng! Bài viết của bạn đã được quản trị viên duyệt nâng cấp thành Tin tức nổi bật.';
                $this->pdo->prepare("
                    INSERT INTO notifications (user_id, type, content, target_url, created_at)
                    VALUES (?, 'ticket_approved', ?, ?, NOW())
                ")->execute([(int)$post['user_id'], $notifContent, "/post/{$postId}"]);

                $this->json([
                    'message' => 'Đã duyệt bài viết do AI gợi ý thành Tin tức thành công.',
                    'action'  => 'approved',
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE posts 
                    SET ai_status = 'rejected', moderated_by = ?, moderated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$adminId, $postId]);

                $this->json([
                    'message' => 'Đã từ chối gợi ý tin tức từ AI.',
                    'action'  => 'rejected',
                ]);
            }
        } else {
            $this->json(['error' => 'Nguồn đề xuất không xác định.'], 422);
        }
    }
}
