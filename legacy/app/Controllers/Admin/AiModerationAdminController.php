<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/NotificationHelper.php';
require_once __DIR__ . '/../../Models/Post.php';

/**
 * Quản trị viên: Quản lý Kiểm duyệt nội dung AI (AI Moderation Items)
 * Hiển thị đánh giá của AI (mức độ nghiêm trọng, loại vi phạm, lý do gắn cờ) và cho phép Admin ra quyết định cuối cùng.
 */
class AiModerationAdminController extends Controller
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
     * GET /api/admin/ai-moderation
     * Hỗ trợ lọc theo mức độ (severity), loại vi phạm (violation_type), trạng thái AI (ai_status), trạng thái bài (moderation_status), tìm kiếm và phân trang.
     */
    public function index(): never
    {
        $this->checkAdmin();

        $page          = max(1, (int)($_GET['page'] ?? 1));
        $perPage       = max(1, min(100, (int)($_GET['per_page'] ?? 15)));
        $severity      = trim((string)($_GET['ai_severity'] ?? ''));
        $violationType = trim((string)($_GET['ai_violation_type'] ?? ''));
        $aiStatus      = trim((string)($_GET['ai_status'] ?? ''));
        $modStatus     = trim((string)($_GET['moderation_status'] ?? ''));
        $q             = trim((string)($_GET['q'] ?? ''));

        $where  = [];
        $params = [];

        // Chỉ hiển thị các bài viết thực sự có bản ghi trong ai_moderation_items hoặc có phân tích rủi ro AI
        $where[] = "(aim.id IS NOT NULL OR (p.moderation_status IN ('flagged', 'blocked') AND p.ai_severity IS NOT NULL))";

        if ($severity !== '' && in_array($severity, ['low', 'medium', 'high'], true)) {
            $where[]            = 'COALESCE(aim.severity, p.ai_severity) = :severity';
            $params['severity'] = $severity;
        }

        if ($violationType !== '') {
            $where[]                  = 'COALESCE(aim.violation_type, p.ai_violation_type) = :violation_type';
            $params['violation_type'] = $violationType;
        }

        if ($aiStatus !== '' && in_array($aiStatus, ['pending', 'approved', 'rejected', 'processed', 'dismissed'], true)) {
            $where[]             = "COALESCE(aim.status, p.ai_status, 'pending') = :ai_status";
            $params['ai_status'] = $aiStatus;
        }

        if ($modStatus !== '' && in_array($modStatus, ['safe', 'flagged', 'blocked', 'pending'], true)) {
            $where[]              = 'p.moderation_status = :mod_status';
            $params['mod_status'] = $modStatus;
        }

        if ($q !== '') {
            $where[]      = '(p.title LIKE :q1 OR u.full_name LIKE :q2 OR u.username LIKE :q3 OR COALESCE(aim.ai_reason, p.ai_reason) LIKE :q4 OR p.content LIKE :q5)';
            $searchTerm   = '%' . $q . '%';
            $params['q1'] = $searchTerm;
            $params['q2'] = $searchTerm;
            $params['q3'] = $searchTerm;
            $params['q4'] = $searchTerm;
            $params['q5'] = $searchTerm;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        // Đếm tổng số
        $countSql = "
            SELECT COUNT(*)
            FROM posts p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN ai_moderation_items aim ON aim.item_id = p.id AND aim.item_type = 'post'
            {$whereSql}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue(':' . $k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        // Lấy danh sách kiểm duyệt
        $sql = "
            SELECT p.id, p.title, p.summary, p.post_type, p.moderation_status,
                   COALESCE(aim.severity, p.ai_severity, 'low') AS ai_severity,
                   COALESCE(aim.violation_type, p.ai_violation_type, 'inappropriate') AS ai_violation_type,
                   COALESCE(aim.ai_reason, p.ai_reason, 'Phát hiện nội dung cần xem xét') AS ai_reason,
                   COALESCE(aim.suggested_action, IF(p.moderation_status = 'blocked', 'block', 'flag')) AS suggested_action,
                   COALESCE(aim.status, p.ai_status, 'pending') AS ai_status,
                   COALESCE(p.moderated_at, aim.reviewed_at) AS moderated_at,
                   COALESCE(p.moderated_by, aim.reviewed_by) AS moderated_by,
                   p.created_at,
                   u.id AS user_id, u.full_name AS author_name, u.username AS author_username,
                   admin.full_name AS reviewer_name
            FROM posts p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN ai_moderation_items aim ON aim.item_id = p.id AND aim.item_type = 'post'
            LEFT JOIN users admin ON admin.id = COALESCE(p.moderated_by, aim.reviewed_by)
            {$whereSql}
            ORDER BY 
                CASE 
                    WHEN COALESCE(aim.status, p.ai_status, 'pending') = 'pending' THEN 0
                    ELSE 1
                END ASC,
                CASE 
                    WHEN p.moderation_status = 'blocked' THEN 1
                    WHEN p.moderation_status = 'flagged' THEN 2
                    WHEN COALESCE(aim.severity, p.ai_severity) = 'high' THEN 3
                    WHEN COALESCE(aim.severity, p.ai_severity) = 'medium' THEN 4
                    ELSE 5
                END ASC,
                p.created_at DESC
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
            $item['id']      = (int)$item['id'];
            $item['user_id'] = (int)$item['user_id'];
            $sev = $item['ai_severity'];
            $item['suggested_action_text'] = match ($sev) {
                'high'   => 'Khóa bài viết ngay',
                'medium' => 'Tạm ẩn chờ kiểm tra',
                default  => 'Cảnh báo nhẹ / Xem xét gỡ cờ',
            };
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
     * POST /api/admin/ai-moderation/:id/decision
     * Body JSON: { "decision": "safe"|"flagged"|"blocked"|"delete", "admin_note"?: string }
     */
    public function decision(string $id): never
    {
        $this->checkAdmin();

        $raw       = file_get_contents('php://input');
        $data      = json_decode($raw ?: '', true) ?: $_POST;
        $decision  = (string)($data['decision'] ?? 'safe');
        $adminNote = trim((string)($data['admin_note'] ?? ''));
        $adminId   = (int)Auth::id();

        if (!in_array($decision, ['safe', 'flagged', 'blocked', 'delete'], true)) {
            $this->json(['error' => 'Quyết định kiểm duyệt không hợp lệ.'], 422);
        }

        $postId = (int)$id;
        $find   = $this->pdo->prepare('SELECT user_id, title FROM posts WHERE id = ?');
        $find->execute([$postId]);
        $post = $find->fetch();

        if (!$post) {
            $this->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }

        $postAuthorId = (int)$post['user_id'];
        $postTitle    = (string)$post['title'];

        if ($decision === 'delete') {
            // Cập nhật đồng bộ vào bảng ai_moderation_items trước khi xóa bài
            try {
                $aimUpdate = $this->pdo->prepare("
                    UPDATE ai_moderation_items 
                    SET status = 'dismissed', admin_note = ?, reviewed_by = ?, reviewed_at = NOW(), final_decision = 'delete', final_decision_at = NOW() 
                    WHERE item_type = 'post' AND item_id = ?
                ");
                $aimUpdate->execute([$adminNote, $adminId, $postId]);
            } catch (Throwable $e) {}

            (new Post())->deleteAny($postId);
            NotificationHelper::sendSystemModerationNotice(
                $postAuthorId,
                'post',
                $postTitle,
                'đã bị quản trị viên xóa vĩnh viễn sau quá trình rà soát kiểm duyệt AI',
                $adminNote ?: 'Nội dung vi phạm nghiêm trọng tiêu chuẩn cộng đồng.',
                '/'
            );

            $this->json(['message' => 'Đã xóa bài viết khỏi hệ thống.', 'decision' => 'delete']);
        }

        // Cập nhật trạng thái moderation_status, ai_status và final_decision
        $stmt = $this->pdo->prepare("
            UPDATE posts 
            SET moderation_status = ?, ai_status = 'approved', moderated_by = ?, moderated_at = NOW(), final_decision = ? 
            WHERE id = ?
        ");
        $stmt->execute([$decision, $adminId, $decision, $postId]);

        // Cập nhật đồng bộ vào bảng ai_moderation_items
        try {
            $aimStatus = match ($decision) {
                'safe'     => 'approved',
                'blocked'  => 'rejected',
                'delete'   => 'dismissed',
                default    => 'processed',
            };
            $aimUpdate = $this->pdo->prepare("
                UPDATE ai_moderation_items 
                SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW(), final_decision = ?, final_decision_at = NOW() 
                WHERE item_type = 'post' AND item_id = ?
            ");
            $aimUpdate->execute([$aimStatus, $adminNote, $adminId, $decision, $postId]);
        } catch (Throwable $e) {}

        if (in_array($decision, ['blocked', 'flagged'], true)) {
            $actionText = $decision === 'blocked' ? 'đã bị khóa do vi phạm tiêu chuẩn cộng đồng' : 'đã bị gắn cờ cảnh báo nội dung';
            NotificationHelper::sendSystemModerationNotice(
                $postAuthorId,
                'post',
                $postTitle,
                $actionText,
                $adminNote ?: 'Quyết định sau khi xem xét báo cáo kiểm duyệt AI.',
                "/post/{$postId}"
            );
        } elseif ($decision === 'safe') {
            NotificationHelper::sendSystemModerationNotice(
                $postAuthorId,
                'post',
                $postTitle,
                'đã được quản trị viên duyệt an toàn và hiển thị bình thường',
                $adminNote ?: 'Nội dung đã được xác nhận phù hợp với tiêu chuẩn cộng đồng.',
                "/post/{$postId}"
            );
        }

        $this->json([
            'message'           => "Đã cập nhật trạng thái kiểm duyệt thành: {$decision}",
            'moderation_status' => $decision,
            'ai_status'         => 'approved',
        ]);
    }
}
