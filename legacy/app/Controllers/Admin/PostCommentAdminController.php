<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/NotificationHelper.php';
require_once __DIR__ . '/../../Models/Post.php';

/**
 * Quản trị viên: Quản lý toàn diện Bài viết & Bình luận (Tìm kiếm, Lọc, Khóa, Chuyển thể loại, Xóa).
 */
class PostCommentAdminController extends Controller
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

    // ================================================================
    // 1. Quản lý Bài viết (Posts)
    // ================================================================

    /**
     * GET /api/admin/posts
     */
    public function posts(): never
    {
        $this->checkAdmin();

        $page     = max(1, (int)($_GET['page'] ?? 1));
        $perPage  = max(1, min(100, (int)($_GET['per_page'] ?? 15)));
        $q        = trim((string)($_GET['q'] ?? ''));
        $category = trim((string)($_GET['category'] ?? ''));
        $status   = trim((string)($_GET['moderation_status'] ?? ''));
        $sort     = (string)($_GET['sort'] ?? 'newest');

        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[]     = '(p.title LIKE :q OR u.full_name LIKE :q OR u.username LIKE :q OR p.content LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        if (in_array($category, ['blog', 'news'], true)) {
            $where[]            = 'p.post_type = :category';
            $params['category'] = $category;
        }

        if (in_array($status, ['safe', 'flagged', 'blocked', 'pending'], true)) {
            $where[]          = 'p.moderation_status = :status';
            $params['status'] = $status;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $orderSql = match ($sort) {
            'oldest'         => 'p.created_at ASC, p.id ASC',
            'views_desc'     => 'p.views_count DESC, p.id DESC',
            'comments_desc'  => 'comments_count DESC, p.id DESC',
            default          => 'p.created_at DESC, p.id DESC',
        };

        // Đếm tổng số bài
        $countSql = "
            SELECT COUNT(*)
            FROM posts p
            JOIN users u ON u.id = p.user_id
            {$whereSql}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue(':' . $k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        // Lấy danh sách bài viết
        $sql = "
            SELECT p.id, p.title, p.slug, p.summary, p.post_type, p.moderation_status,
                   p.ai_severity, p.ai_violation_type, p.views_count, p.created_at,
                   u.id AS user_id, u.full_name AS author_name, u.username AS author_username,
                   (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comments_count,
                   (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id = p.id) AS likes_count
            FROM posts p
            JOIN users u ON u.id = p.user_id
            {$whereSql}
            ORDER BY {$orderSql}
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll();

        foreach ($posts as &$post) {
            $post['id']             = (int)$post['id'];
            $post['views_count']    = (int)$post['views_count'];
            $post['comments_count'] = (int)$post['comments_count'];
            $post['likes_count']    = (int)$post['likes_count'];
        }
        unset($post);

        $this->json([
            'data'       => $posts,
            'pagination' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => max(1, (int)ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * POST /api/admin/posts/:id/moderation
     * Cập nhật trạng thái an toàn / khóa bài / gắn cờ.
     */
    public function updatePostModeration(string $id): never
    {
        $this->checkAdmin();

        $raw    = file_get_contents('php://input');
        $data   = json_decode($raw ?: '', true) ?: $_POST;
        $status = (string)($data['status'] ?? 'safe');
        $reason = trim((string)($data['reason'] ?? ''));

        if (!in_array($status, ['safe', 'flagged', 'blocked'], true)) {
            $this->json(['error' => 'Trạng thái kiểm duyệt không hợp lệ.'], 422);
        }

        $postId  = (int)$id;
        $adminId = (int)Auth::id();

        $find = $this->pdo->prepare('SELECT user_id, title FROM posts WHERE id = ?');
        $find->execute([$postId]);
        $post = $find->fetch();
        if (!$post) {
            $this->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }

        $stmt = $this->pdo->prepare('UPDATE posts SET moderation_status = ?, moderated_by = ?, moderated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $adminId, $postId]);

        if (in_array($status, ['blocked', 'flagged'], true)) {
            $actionText = $status === 'blocked' ? 'bị khóa do vi phạm chính sách cộng đồng' : 'bị gắn cờ cảnh báo nội dung nhạy cảm';
            NotificationHelper::sendSystemModerationNotice(
                (int)$post['user_id'],
                'post',
                (string)$post['title'],
                $actionText,
                $reason ?: 'Quyết định kiểm duyệt từ Quản trị viên.',
                "/post/{$postId}"
            );
        }

        $this->json([
            'message'           => "Đã cập nhật trạng thái bài viết thành: {$status}",
            'moderation_status' => $status,
        ]);
    }

    /**
     * POST /api/admin/posts/:id/type
     * Chuyển đổi thể loại bài viết: blog <=> news
     */
    public function updatePostType(string $id): never
    {
        $this->checkAdmin();

        $raw      = file_get_contents('php://input');
        $data     = json_decode($raw ?: '', true) ?: $_POST;
        $postType = (string)($data['post_type'] ?? 'blog');

        if (!in_array($postType, ['blog', 'news'], true)) {
            $this->json(['error' => 'Thể loại bài viết không hợp lệ.'], 422);
        }

        $postId = (int)$id;
        $stmt   = $this->pdo->prepare('UPDATE posts SET post_type = ? WHERE id = ?');
        $stmt->execute([$postType, $postId]);

        $this->json([
            'message'   => "Đã chuyển thể loại bài viết thành: " . ($postType === 'news' ? 'Tin tức' : 'Blog cá nhân'),
            'post_type' => $postType,
        ]);
    }

    /**
     * POST /api/admin/posts/:id/delete
     */
    public function deletePost(string $id): never
    {
        $this->checkAdmin();
        $postId = (int)$id;

        $find = $this->pdo->prepare('SELECT user_id, title FROM posts WHERE id = ?');
        $find->execute([$postId]);
        $post = $find->fetch();
        if (!$post) {
            $this->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }

        if (!(new Post())->deleteAny($postId)) {
            $this->json(['error' => 'Không thể xóa bài viết.'], 500);
        }

        NotificationHelper::sendSystemModerationNotice(
            (int)$post['user_id'],
            'post',
            (string)$post['title'],
            'đã bị quản trị viên xóa vĩnh viễn khỏi hệ thống',
            'Bài viết vi phạm nghiêm trọng chính sách hoặc có yêu cầu gỡ bỏ.',
            '/'
        );

        $this->json(['message' => 'Đã xóa bài viết thành công.']);
    }

    // ================================================================
    // 2. Quản lý Bình luận (Comments)
    // ================================================================

    /**
     * GET /api/admin/comments
     */
    public function comments(): never
    {
        $this->checkAdmin();

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 15)));
        $q       = trim((string)($_GET['q'] ?? ''));
        $status  = trim((string)($_GET['moderation_status'] ?? ''));
        $sort    = (string)($_GET['sort'] ?? 'newest');

        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[]     = '(c.content LIKE :q OR u.full_name LIKE :q OR u.username LIKE :q OR p.title LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        if (in_array($status, ['safe', 'flagged', 'blocked', 'pending'], true)) {
            $where[]          = 'c.moderation_status = :status';
            $params['status'] = $status;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $orderSql = $sort === 'oldest' ? 'c.created_at ASC, c.id ASC' : 'c.created_at DESC, c.id DESC';

        $countSql = "
            SELECT COUNT(*)
            FROM comments c
            JOIN users u ON u.id = c.user_id
            JOIN posts p ON p.id = c.post_id
            {$whereSql}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue(':' . $k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT c.id, c.post_id, c.user_id, c.content, c.moderation_status, c.created_at,
                   u.full_name AS author_name, u.username AS author_username,
                   p.title AS post_title
            FROM comments c
            JOIN users u ON u.id = c.user_id
            JOIN posts p ON p.id = c.post_id
            {$whereSql}
            ORDER BY {$orderSql}
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $comments = $stmt->fetchAll();

        foreach ($comments as &$c) {
            $c['id']      = (int)$c['id'];
            $c['post_id'] = (int)$c['post_id'];
            $c['user_id'] = (int)$c['user_id'];
        }
        unset($c);

        $this->json([
            'data'       => $comments,
            'pagination' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => max(1, (int)ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * POST /api/admin/comments/:id/moderation
     * Cập nhật trạng thái ẩn (blocked) / hiển thị (safe).
     */
    public function updateCommentModeration(string $id): never
    {
        $this->checkAdmin();

        $raw    = file_get_contents('php://input');
        $data   = json_decode($raw ?: '', true) ?: $_POST;
        $status = (string)($data['status'] ?? 'safe');
        $reason = trim((string)($data['reason'] ?? ''));

        if (!in_array($status, ['safe', 'blocked'], true)) {
            $this->json(['error' => 'Trạng thái bình luận không hợp lệ.'], 422);
        }

        $commentId = (int)$id;
        $find      = $this->pdo->prepare('SELECT user_id, post_id, content FROM comments WHERE id = ?');
        $find->execute([$commentId]);
        $comment = $find->fetch();
        if (!$comment) {
            $this->json(['error' => 'Không tìm thấy bình luận.'], 404);
        }

        $stmt = $this->pdo->prepare('UPDATE comments SET moderation_status = ? WHERE id = ?');
        $stmt->execute([$status, $commentId]);

        if ($status === 'blocked') {
            NotificationHelper::sendSystemModerationNotice(
                (int)$comment['user_id'],
                'comment',
                (string)$comment['content'],
                'đã bị quản trị viên ẩn do vi phạm tiêu chuẩn cộng đồng',
                $reason ?: 'Bình luận không phù hợp với chuẩn mực thảo luận.',
                "/post/{$comment['post_id']}"
            );
        }

        $this->json([
            'message'           => $status === 'blocked' ? 'Đã ẩn bình luận.' : 'Đã mở lại hiển thị bình luận.',
            'moderation_status' => $status,
        ]);
    }

    /**
     * POST /api/admin/comments/:id/delete
     */
    public function deleteComment(string $id): never
    {
        $this->checkAdmin();
        $commentId = (int)$id;

        $find = $this->pdo->prepare('SELECT user_id, post_id, content FROM comments WHERE id = ?');
        $find->execute([$commentId]);
        $comment = $find->fetch();
        if (!$comment) {
            $this->json(['error' => 'Không tìm thấy bình luận.'], 404);
        }

        require_once __DIR__ . '/../../Models/Comment.php';
        (new Comment())->deleteWithHierarchy($commentId, (int)(Auth::id() ?? 0), true);

        NotificationHelper::sendSystemModerationNotice(
            (int)$comment['user_id'],
            'comment',
            (string)$comment['content'],
            'đã bị quản trị viên xóa vĩnh viễn',
            'Bình luận vi phạm tiêu chuẩn cộng đồng.',
            "/post/{$comment['post_id']}"
        );

        $this->json(['message' => 'Đã xóa bình luận thành công.']);
    }
}
