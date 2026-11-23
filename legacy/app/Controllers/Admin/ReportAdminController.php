<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/NotificationHelper.php';
require_once __DIR__ . '/../../Models/PostReport.php';
require_once __DIR__ . '/../../Models/CommentReport.php';
require_once __DIR__ . '/../../Models/Comment.php';
require_once __DIR__ . '/../../Models/Post.php';
require_once __DIR__ . '/../../Models/PostAppeal.php';

/**
 * Quản trị viên: Quản lý báo cáo bài viết & bình luận vi phạm với phân trang, tìm kiếm và gửi thông báo kiểm duyệt.
 */
class ReportAdminController extends Controller
{
    /**
     * Tương thích ngược cho route GET /admin/reports và GET /api/admin/reports
     */
    public function index(): void
    {
        $requestedRoute = trim((string)($_GET['url'] ?? ''), '/');
        $isApi = str_starts_with($requestedRoute, 'api/')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || isset($_GET['api']);

        if ($isApi) {
            $this->userReports();
        }

        if (!Auth::check() || !Auth::isAdmin()) {
            $redirectUrl = defined('APP_URL') ? APP_URL . '/account.html' : 'account.html';
            header('Location: ' . $redirectUrl);
            exit;
        }

        $adminUrl = defined('APP_URL') ? APP_URL . '/admin#post-reports' : 'admin#post-reports';
        header('Location: ' . $adminUrl);
        exit;
    }

    /**
     * Tương thích ngược cho route POST /api/admin/reports/:id/status
     */
    public function updateStatus(string $id): never
    {
        $this->updateUserReportStatus($id);
    }

    // ----------------------------------------------------------------
    // 1. Báo cáo bài viết do người dùng gửi (post_reports)
    // ----------------------------------------------------------------

    /**
     * GET /api/admin/user-reports
     * Hỗ trợ phân trang: page, per_page, status, reason, q.
     */
    public function userReports(): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }
        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Chỉ quản trị viên mới có quyền xem báo cáo.'], 403);
        }

        $filters = [];
        if (!empty($_GET['status'])) {
            $filters['status'] = (string)$_GET['status'];
        }
        if (!empty($_GET['reason'])) {
            $filters['reason'] = (string)$_GET['reason'];
        }
        if (!empty($_GET['q'])) {
            $filters['q'] = trim((string)$_GET['q']);
        }

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 15)));

        $result = (new PostReport())->list($filters, $page, $perPage);
        $this->json($result);
    }

    /**
     * POST /api/admin/user-reports/:id/status
     * Body JSON: { "status": "reviewed|dismissed", "admin_note": "...", "post_action": "none|hide|delete" }
     */
    public function updateUserReportStatus(string $id): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }
        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Chỉ quản trị viên.'], 403);
        }

        $raw        = file_get_contents('php://input');
        $data       = json_decode($raw ?: '', true) ?: $_POST;
        $status     = (string)($data['status'] ?? '');
        $adminNote  = trim((string)($data['admin_note'] ?? '')) ?: null;
        $postAction = (string)($data['post_action'] ?? 'none');
        $adminId    = (int)Auth::id();

        if (!in_array($status, ['pending', 'reviewed', 'dismissed'], true)) {
            $this->json(['error' => 'Trạng thái báo cáo không hợp lệ.'], 422);
        }

        $pdo = Database::getInstance()->getConnection();

        // Tìm thông tin báo cáo
        $repStmt = $pdo->prepare('
            SELECT pr.*, p.id AS post_id, p.user_id AS post_author_id, p.title AS post_title
            FROM post_reports pr
            JOIN posts p ON p.id = pr.post_id
            WHERE pr.id = ?
        ');
        $repStmt->execute([(int)$id]);
        $report = $repStmt->fetch();

        if (!$report) {
            $this->json(['error' => 'Không tìm thấy báo cáo bài viết.'], 404);
        }

        // Cập nhật trạng thái báo cáo
        $pdo->prepare('
            UPDATE post_reports
            SET status = ?, reviewed_by = ?, reviewed_at = NOW(), admin_note = ?
            WHERE id = ?
        ')->execute([$status, $adminId, $adminNote, (int)$id]);

        $postId       = (int)$report['post_id'];
        $postAuthorId = (int)$report['post_author_id'];
        $postTitle    = (string)$report['post_title'];

        // Xử lý hành động lên bài viết nếu có
        if ($status === 'reviewed') {
            if ($postAction === 'hide') {
                $pdo->prepare("UPDATE posts SET moderation_status = 'blocked', moderated_by = ?, moderated_at = NOW() WHERE id = ?")
                    ->execute([$adminId, $postId]);
                NotificationHelper::sendSystemModerationNotice(
                    $postAuthorId,
                    'post',
                    $postTitle,
                    'đã bị khóa do vi phạm tiêu chuẩn cộng đồng sau khi kiểm duyệt báo cáo',
                    $adminNote ?: 'Nội dung không phù hợp với tiêu chuẩn cộng đồng.',
                    "/post/{$postId}"
                );
            } elseif ($postAction === 'delete') {
                (new Post())->deleteAny($postId);
                NotificationHelper::sendSystemModerationNotice(
                    $postAuthorId,
                    'post',
                    $postTitle,
                    'đã bị xóa vĩnh viễn do vi phạm nghiêm trọng tiêu chuẩn cộng đồng',
                    $adminNote ?: 'Nội dung bị xóa theo quyết định của ban quản trị.',
                    '/'
                );
            } else {
                // Chỉ duyệt báo cáo mà không thay đổi bài viết
                NotificationHelper::sendSystemModerationNotice(
                    $postAuthorId,
                    'post',
                    $postTitle,
                    'đã được quản trị viên xem xét và xử lý theo báo cáo vi phạm',
                    $adminNote ?: 'Vui lòng lưu ý tuân thủ tiêu chuẩn cộng đồng.',
                    "/post/{$postId}"
                );
            }
        }

        $this->json([
            'message' => 'Đã cập nhật trạng thái báo cáo bài viết thành công.',
            'status'  => $status,
        ]);
    }

    // ----------------------------------------------------------------
    // 2. Báo cáo bình luận (comment_reports)
    // ----------------------------------------------------------------

    /**
     * GET /api/admin/comment-reports
     * Hỗ trợ phân trang: page, per_page, status, reason, q.
     */
    public function commentReports(): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }
        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Chỉ quản trị viên.'], 403);
        }

        $filters = [];
        if (!empty($_GET['status'])) {
            $filters['status'] = (string)$_GET['status'];
        }
        if (!empty($_GET['reason'])) {
            $filters['reason'] = (string)$_GET['reason'];
        }
        if (!empty($_GET['q'])) {
            $filters['q'] = trim((string)$_GET['q']);
        }

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 15)));

        $result = (new CommentReport())->list($filters, $page, $perPage);
        $this->json($result);
    }

    /**
     * POST /api/admin/comment-reports/:id/status
     * Body JSON: { "status": "reviewed|rejected", "admin_note": "...", "comment_action": "none|hide|delete" }
     */
    public function updateCommentReportStatus(string $id): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }
        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Chỉ quản trị viên.'], 403);
        }

        $raw           = file_get_contents('php://input');
        $data          = json_decode($raw ?: '', true) ?: $_POST;
        $status        = (string)($data['status'] ?? '');
        $note          = trim((string)($data['admin_note'] ?? '')) ?: null;
        $commentAction = (string)($data['comment_action'] ?? 'none');
        $adminId       = (int)Auth::id();

        if (!in_array($status, ['pending', 'reviewed', 'rejected'], true)) {
            $this->json(['error' => 'Trạng thái báo cáo không hợp lệ.'], 422);
        }

        $pdo = Database::getInstance()->getConnection();

        // Lấy thông tin bình luận và tác giả
        $cStmt = $pdo->prepare('
            SELECT cr.*, c.user_id AS comment_author_id, c.content AS comment_content, c.post_id, c.is_deleted AS comment_is_deleted
            FROM comment_reports cr
            JOIN comments c ON c.id = cr.comment_id
            WHERE cr.id = ?
        ');
        $cStmt->execute([(int)$id]);
        $cRow = $cStmt->fetch();

        if (!$cRow) {
            $this->json(['error' => 'Không tìm thấy báo cáo bình luận.'], 404);
        }

        if (!(new CommentReport())->updateStatus((int)$id, $status, $adminId, $note)) {
            $this->json(['error' => 'Không thể cập nhật báo cáo.'], 500);
        }

        $commentId        = (int)$cRow['comment_id'];
        $commentAuthorId  = (int)$cRow['comment_author_id'];
        $commentContent   = (string)$cRow['comment_content'];
        $postId           = (int)$cRow['post_id'];
        $wasAlreadyDeleted = !empty($cRow['comment_is_deleted']);

        if ($status === 'reviewed') {
            if ($commentAction === 'hide') {
                $pdo->prepare("UPDATE comments SET moderation_status = 'blocked' WHERE id = ?")->execute([$commentId]);
                NotificationHelper::sendSystemModerationNotice(
                    $commentAuthorId,
                    'comment',
                    $commentContent,
                    'đã bị ẩn do vi phạm tiêu chuẩn cộng đồng sau khi ban quản trị kiểm duyệt',
                    $note ?: 'Bình luận vi phạm tiêu chuẩn cộng đồng.',
                    "/post/{$postId}"
                );
            } elseif ($commentAction === 'delete') {
                $deleteResult = (new Comment())->deleteWithHierarchy($commentId, $adminId, true);
                if ($deleteResult['ok'] && !$wasAlreadyDeleted) {
                    NotificationHelper::sendSystemModerationNotice(
                        $commentAuthorId,
                        'comment',
                        $commentContent,
                        'đã bị xóa vĩnh viễn do vi phạm nghiêm trọng',
                        $note ?: 'Bình luận bị xóa theo quyết định của ban quản trị.',
                        "/post/{$postId}"
                    );
                }
            } else {
                NotificationHelper::sendSystemModerationNotice(
                    $commentAuthorId,
                    'comment',
                    $commentContent,
                    'đã được quản trị viên xử lý theo báo cáo vi phạm',
                    $note ?: 'Vui lòng lưu ý ngôn từ và chuẩn mực cộng đồng.',
                    "/post/{$postId}"
                );
            }
        }

        $this->json([
            'message' => 'Đã cập nhật trạng thái báo cáo bình luận thành công.',
            'status'  => $status,
        ]);
    }

    // ----------------------------------------------------------------
    // 3. Khiếu nại mở khóa bài viết (post_appeals)
    // ----------------------------------------------------------------

    /**
     * GET /api/admin/appeals
     * Hỗ trợ phân trang: page, per_page, status, q.
     */
    public function appeals(): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }
        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Chỉ quản trị viên mới có quyền xem khiếu nại.'], 403);
        }

        $filters = [];
        if (!empty($_GET['status'])) {
            $filters['status'] = (string)$_GET['status'];
        }
        if (!empty($_GET['q'])) {
            $filters['q'] = trim((string)$_GET['q']);
        }

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 15)));

        $result = (new PostAppeal())->list($filters, $page, $perPage);
        $this->json($result);
    }

    /**
     * POST /api/admin/appeals/:id/resolve
     * Body JSON: { "action": "approved|rejected", "admin_response": "..." }
     */
    public function resolveAppeal(string $id): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }
        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Chỉ quản trị viên mới có quyền xử lý khiếu nại.'], 403);
        }

        $raw           = file_get_contents('php://input');
        $data          = json_decode($raw ?: '', true) ?: $_POST;
        $action        = (string)($data['action'] ?? '');
        $adminResponse = trim((string)($data['admin_response'] ?? '')) ?: null;
        $adminId       = (int)Auth::id();

        if (!in_array($action, ['approved', 'rejected'], true)) {
            $this->json(['error' => 'Hành động không hợp lệ. Chọn "approved" hoặc "rejected".'], 422);
        }

        try {
            (new PostAppeal())->resolve((int)$id, $action, $adminId, $adminResponse);
            $msg = $action === 'approved'
                ? 'Đã chấp nhận khiếu nại. Bài viết đã được khôi phục về trạng thái an toàn.'
                : 'Đã từ chối khiếu nại bài viết.';
            $this->json([
                'message' => $msg,
                'action'  => $action,
            ]);
        } catch (DomainException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['error' => 'Lỗi xử lý khiếu nại: ' . $e->getMessage()], 500);
        }
    }
}
