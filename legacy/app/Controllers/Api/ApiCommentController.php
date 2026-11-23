<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/NotificationHelper.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../Models/Comment.php';
require_once __DIR__ . '/../../Models/Post.php';
require_once __DIR__ . '/../../Models/User.php';
require_once __DIR__ . '/../../Models/CommentReport.php';
require_once __DIR__ . '/../../Services/AIService.php';
require_once __DIR__ . '/../../Services/ReputationService.php';
require_once __DIR__ . '/../../Services/MentionService.php';

/** API Endpoints: Lấy danh sách bình luận, @Mention & Gửi/Sửa/Xóa bình luận. */
class ApiCommentController extends Controller
{
    private Comment $commentModel;
    private Post $postModel;
    private AIService $aiService;
    private MentionService $mentionService;

    public function __construct()
    {
        $this->commentModel = new Comment();
        $this->postModel = new Post();
        $this->aiService = new AIService();
        $this->mentionService = new MentionService();
    }

    /** GET /api/users/search?q=...&limit=8 — Autocomplete danh sách tài khoản cho @Mention */
    public function searchMentionUsers(): never
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $limit = max(1, min(15, (int)($_GET['limit'] ?? 8)));
        $users = $this->mentionService->searchUsers($q, $limit, Auth::id());
        $this->json(['data' => $users]);
    }

    public function list(string $postId): never
    {
        $comments = $this->commentModel->getByPost((int)$postId, 'safe', Auth::id());
        $this->json(['data' => $comments]);
    }

    public function create(string $postId): never
    {
        $userId = Auth::id();
        if ($userId === null) $this->json(['error' => 'Vui lòng đăng nhập để bình luận.'], 401);

        $id = (int)$postId;
        $post = $this->postModel->find($id, $userId);
        if (!$post) $this->json(['error' => 'Không tìm thấy bài viết.'], 404);

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        $rawContent = trim((string)($data['content'] ?? ''));

        if ($rawContent === '' || mb_strlen($rawContent) > 5000) {
            $this->json(['error' => 'Nội dung bình luận bắt buộc và tối đa 5.000 ký tự.'], 422);
        }

        // Xác thực & chuẩn hóa @Mention ở backend (loại bỏ mọi token user_id giả mạo)
        $mentionResult = $this->mentionService->prepareContentAndMentions($rawContent);
        $content = $mentionResult['content'];
        $mentionedUserIds = $mentionResult['mentioned_user_ids'];

        if ($content === '') {
            $this->json(['error' => 'Nội dung bình luận không hợp lệ.'], 422);
        }

        $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' && $data['parent_id'] !== null ? (int)$data['parent_id'] : null;
        if ($parentId !== null && $parentId <= 0) {
            $parentId = null;
        }
        if ($parentId !== null) {
            $parent = $this->commentModel->find($parentId);
            if (!$parent || (int)$parent['post_id'] !== $id || $parent['moderation_status'] !== 'safe' || !empty($parent['is_deleted'])) {
                $this->json(['error' => 'Bình luận được trả lời không hợp lệ hoặc đã bị xóa.'], 422);
            }
        }

        // Tự động quét kiểm duyệt AI trên nội dung đã giải mã tên hiển thị
        $plainForMod = $this->mentionService->toPlainTextForModeration($content);
        $scan = $this->aiService->moderateContent($plainForMod, 'comment');
        if ($scan['status'] === 'blocked') {
            $this->json(['error' => 'Bình luận bị từ chối do vi phạm tiêu chuẩn cộng đồng: ' . $scan['ai_reason']], 422);
        }

        try {
            $commentId = $this->commentModel->create($id, $userId, $content, $scan['status'], $parentId);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        }
        $this->mentionService->syncCommentMentions($commentId, $mentionedUserIds);
        
        // Nếu bị gắn cờ: lưu bản ghi vào ai_moderation_items và cập nhật trạng thái AI cho bình luận
        if ($scan['is_flagged']) {
            try {
                $pdo = Database::getInstance()->getConnection();
                $pdo->prepare("UPDATE comments SET ai_severity = ?, ai_violation_type = ?, ai_reason = ?, ai_status = 'pending' WHERE id = ?")
                    ->execute([$scan['severity'], $scan['violation_type'], $scan['ai_reason'], $commentId]);

                $aimStmt = $pdo->prepare("
                    INSERT INTO ai_moderation_items 
                    (item_type, item_id, post_id, user_id, violation_type, severity, ai_reason, suggested_action, status, created_at)
                    VALUES ('comment', ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $aimStmt->execute([
                    $commentId,
                    $id,
                    $userId,
                    $scan['violation_type'],
                    $scan['severity'],
                    $scan['ai_reason'],
                    $scan['suggested_action'],
                ]);
            } catch (Throwable $e) {
                error_log('[ApiCommentController] Lỗi lưu ai_moderation_items: ' . $e->getMessage());
            }

            $created = $this->commentModel->find($commentId);
            $this->json([
                'message' => 'Bình luận đã được gửi và đang chờ Ban Quản Trị xét duyệt do AI phát hiện nội dung cần rà soát.',
                'is_flagged' => true,
                'moderation_status' => $scan['status'],
                'ai_reason' => $scan['ai_reason'],
                'data' => $created
            ], 201);
        }

        (new ReputationService())->calculate((int)$post['user_id']);

        // Gửi thông báo @Mention (tự bỏ qua bản thân & chống trùng lặp)
        if (!empty($mentionedUserIds)) {
            $this->mentionService->notifyMentionedUsers($commentId, $id, (string)($post['title'] ?? ''), $userId, $mentionedUserIds);
        }

        // Thông báo tác giả bài viết (không tự thông báo, không trùng nếu tác giả vừa nhận thông báo mention của chính bình luận này)
        $postOwnerId = (int)$post['user_id'];
        if ($postOwnerId !== $userId && !in_array($postOwnerId, $mentionedUserIds, true)) {
            $actor     = (new User())->findById($userId);
            $actorName = $actor['full_name'] ?? 'Ai đó';
            NotificationHelper::send(
                $postOwnerId,
                $userId,
                'comment',
                "{$actorName} đã bình luận vào bài viết \"{$post['title']}\"",
                "/post/{$id}#comment-{$commentId}"
            );
        }

        $created = $this->commentModel->find($commentId);
        $this->json(['message' => 'Bình luận thành công.', 'data' => $created], 201);
    }

    public function update(string $commentId): never
    {
        $userId = Auth::id();
        if ($userId === null) $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        $data = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
        $rawContent = trim((string)($data['content'] ?? ''));
        if ($rawContent === '' || mb_strlen($rawContent) > 5000) $this->json(['error' => 'Nội dung bình luận bắt buộc, tối đa 5.000 ký tự.'], 422);
        $comment = $this->commentModel->find((int)$commentId);
        if (!$comment || !empty($comment['is_deleted'])) $this->json(['error' => 'Không tìm thấy bình luận.'], 404);
        if ((int)$comment['user_id'] !== $userId) $this->json(['error' => 'Bạn chỉ được sửa bình luận của mình.'], 403);

        $mentionResult = $this->mentionService->prepareContentAndMentions($rawContent);
        $content = $mentionResult['content'];
        $mentionedUserIds = $mentionResult['mentioned_user_ids'];
        if ($content === '') $this->json(['error' => 'Nội dung bình luận không hợp lệ.'], 422);

        $plainForMod = $this->mentionService->toPlainTextForModeration($content);
        $scan = $this->aiService->moderateContent($plainForMod, 'comment');
        if ($scan['status'] === 'blocked') {
            $this->json(['error' => 'Nội dung chỉnh sửa bị từ chối do vi phạm tiêu chuẩn cộng đồng: ' . $scan['ai_reason']], 422);
        }

        if (!$this->commentModel->updateContent((int)$commentId, $userId, $content)) {
            $this->json(['error' => 'Không thể cập nhật bình luận.'], 409);
        }
        $this->mentionService->syncCommentMentions((int)$commentId, $mentionedUserIds);

        if ($scan['is_flagged']) {
            $this->commentModel->updateModerationStatus((int)$commentId, $scan['status']);
            try {
                $pdo = Database::getInstance()->getConnection();
                $pdo->prepare("UPDATE comments SET ai_severity = ?, ai_violation_type = ?, ai_reason = ?, ai_status = 'pending' WHERE id = ?")
                    ->execute([$scan['severity'], $scan['violation_type'], $scan['ai_reason'], (int)$commentId]);

                $aimStmt = $pdo->prepare("
                    INSERT INTO ai_moderation_items 
                    (item_type, item_id, post_id, user_id, violation_type, severity, ai_reason, suggested_action, status, created_at)
                    VALUES ('comment', ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $aimStmt->execute([
                    (int)$commentId,
                    (int)$comment['post_id'],
                    $userId,
                    $scan['violation_type'],
                    $scan['severity'],
                    $scan['ai_reason'],
                    $scan['suggested_action'],
                ]);
            } catch (Throwable $e) {}

            $this->json([
                'message' => 'Bình luận đã sửa nhưng đang chờ Ban Quản Trị rà soát nội dung.',
                'is_flagged' => true,
                'moderation_status' => $scan['status'],
                'data' => $this->commentModel->find((int)$commentId)
            ]);
        }

        // Gửi thông báo cho các mention mới thêm khi chỉnh sửa (chống trùng lặp tự động)
        if (!empty($mentionedUserIds)) {
            $post = $this->postModel->find((int)$comment['post_id'], $userId);
            $this->mentionService->notifyMentionedUsers((int)$commentId, (int)$comment['post_id'], (string)($post['title'] ?? ''), $userId, $mentionedUserIds);
        }

        $this->json(['message' => 'Đã sửa bình luận.', 'data' => $this->commentModel->find((int)$commentId)]);
    }

    public function delete(string $commentId): never
    {
        $userId = Auth::id();
        if ($userId === null) $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        $comment = $this->commentModel->find((int)$commentId);
        if (!$comment) $this->json(['error' => 'Không tìm thấy bình luận.'], 404);
        $del = $this->commentModel->deleteWithHierarchy((int)$commentId, $userId, Auth::isAdmin());
        if (!$del['ok']) {
            $this->json(['error' => 'Chỉ người viết bình luận hoặc tác giả bài viết mới có quyền xóa.'], 403);
        }
        $post = $this->postModel->find((int)$comment['post_id'], $userId);
        if ($post) (new ReputationService())->calculate((int)$post['user_id']);
        $this->json([
            'message'      => 'Đã xóa bình luận.',
            'deleted_id'   => (int)$commentId,
            'soft_deleted' => $del['soft_deleted'],
        ]);
    }

    public function like(string $commentId): never
    {
        $userId = Auth::id();
        if ($userId === null) $this->json(['error' => 'Vui lòng đăng nhập để thả cảm xúc.'], 401);
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) $data = $_POST;
        $reaction = array_key_exists('reaction', $data) ? ($data['reaction'] === null || $data['reaction'] === '' ? null : (string)$data['reaction']) : 'like';
        try { 
            $active = $this->commentModel->setReaction((int)$commentId, $userId, $reaction); 
        } catch (RuntimeException $e) { 
            $this->json(['error' => $e->getMessage()], 404); 
        } catch (InvalidArgumentException $e) { 
            $this->json(['error' => $e->getMessage()], 422); 
        }

        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT reaction, COUNT(*) AS total FROM comment_likes WHERE comment_id = ? GROUP BY reaction');
        $stmt->execute([(int)$commentId]);
        $counts = array_fill_keys(['like', 'love', 'haha', 'sad', 'angry'], 0);
        foreach ($stmt->fetchAll() as $row) {
            if (isset($counts[$row['reaction']])) {
                $counts[$row['reaction']] = (int)$row['total'];
            }
        }
        $totalLikes = array_sum($counts);

        // Gửi thông báo cho tác giả bình luận nếu người tương tác khác tác giả
        if ($active !== null) {
            $cRow = $this->commentModel->find((int)$commentId);
            if ($cRow && (int)$cRow['user_id'] !== $userId) {
                $actor = (new User())->findById($userId);
                $actorName = $actor['full_name'] ?? 'Ai đó';
                $emoji = match ($active) {
                    'love'  => '❤️',
                    'haha'  => '😄',
                    'sad'   => '😢',
                    'angry' => '😠',
                    default => '👍',
                };
                NotificationHelper::send(
                    (int)$cRow['user_id'],
                    $userId,
                    'reaction',
                    "{$actorName} đã thả cảm xúc {$emoji} vào bình luận của bạn",
                    "/post/{$cRow['post_id']}"
                );
            }
        }

        $this->json([
            'success'         => true,
            'reaction'        => $active,
            'my_reaction'     => $active,
            'counts'          => $counts,
            'reaction_counts' => $counts,
            'total'           => $totalLikes,
            'likes_count'     => $totalLikes,
            'is_liked'        => !empty($active),
        ]);
    }

    /**
     * POST /api/comments/:id/report
     * Body JSON: { "reason": "offensive|spam|inappropriate|misinformation|other", "details": "..." }
     */
    public function report(string $commentId): never
    {
        $userId = Auth::id();
        if ($userId === null) $this->json(['error' => 'Vui lòng đăng nhập để báo cáo bình luận.'], 401);

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) $data = $_POST;

        $reason  = trim((string)($data['reason'] ?? ''));
        $details = trim((string)($data['details'] ?? '')) ?: null;

        try {
            (new CommentReport())->create((int)$commentId, $userId, $reason, $details);
        } catch (InvalidArgumentException|DomainException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        }

        $this->json(['message' => 'Đã gửi báo cáo bình luận đến quản trị viên.']);
    }
}
