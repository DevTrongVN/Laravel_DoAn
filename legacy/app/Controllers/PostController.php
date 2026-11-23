<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/NotificationHelper.php';
require_once __DIR__ . '/../Models/Post.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Services/UploadService.php';
require_once __DIR__ . '/../Services/ReputationService.php';
require_once __DIR__ . '/../Services/AIService.php';

/** CRUD Bài viết & Chi tiết bài viết (Direct link / Modal content). */
class PostController extends Controller
{
    private Post $postModel;
    private AIService $aiService;

    public function __construct()
    {
        $this->postModel = new Post();
        $this->aiService = new AIService();
    }

    public function show(string $id): void
    {
        // Điều hướng an toàn nếu router vô tình nhận tham số 'bookmarks'
        if ($id === 'bookmarks') {
            require_once __DIR__ . '/BookmarkController.php';
            (new BookmarkController())->index();
            return;
        }

        $viewerId = Auth::id();
        $this->postModel->recordView((int)$id, $viewerId);
        $post = $this->postModel->find((int)$id, $viewerId);
        if (!$post) {
            http_response_code(404);
            $requestedRoute = trim((string)($_GET['url'] ?? ''), '/');
            $format = (string)($_GET['format'] ?? '');
            $isApi = isset($_GET['api']) || $format === 'json';
            $acceptHeader = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
            $isJsonAccept = str_contains($acceptHeader, 'application/json') && !str_contains($acceptHeader, 'text/html');
            $isApiRoute = str_starts_with($requestedRoute, 'api/');

            if ($isApiRoute || $isApi || $isJsonAccept) {
                $this->json(['error' => 'Không tìm thấy bài viết.'], 404);
            }

            if (isset($_GET['modal'])) {
                $this->render('errors/post_not_found', [
                    'pageTitle' => 'Bài viết không còn tồn tại — Góc Nhỏ',
                    'postId'    => (int)$id,
                    'isModal'   => true,
                ], '');
                return;
            }

            $this->render('errors/post_not_found', [
                'pageTitle' => 'Bài viết không còn tồn tại — Góc Nhỏ',
                'postId'    => (int)$id,
                'isModal'   => false,
            ], 'main');
            return;
        }

        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || isset($_GET['api'])) {
            $this->json(['data' => $post]);
        }

        if (isset($_GET['modal'])) {
            $this->render('post/post_modal_content', ['post' => $post], '');
            return;
        }

        $sidebarData = $this->getSidebarData($viewerId);
        $this->render('post/detail', array_merge([
            'post'      => $post,
            'pageTitle' => (string)$post['title'] . ' — Góc Nhỏ',
        ], $sidebarData));
    }

    public function create(): never
    {
        $userId = Auth::id();
        if ($userId === null) {
            $this->json(['error' => 'Vui lòng đăng nhập để đăng bài.'], 401);
        }

        $data = $this->input();
        $title = trim((string)($data['title'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));
        if ($title === '' || mb_strlen($title) > 200) {
            $this->json(['error' => 'Tiêu đề bắt buộc và tối đa 200 ký tự.'], 422);
        }
        if ($content === '' || mb_strlen($content) > 50000) {
            $this->json(['error' => 'Nội dung bắt buộc và tối đa 50.000 ký tự.'], 422);
        }

        $user = (new User())->findById($userId);
        $requestedType = (string)($data['post_type'] ?? 'blog');
        if (!in_array($requestedType, ['blog', 'news'], true)) {
            $this->json(['error' => 'Loại bài viết không hợp lệ.'], 422);
        }

        if ($requestedType === 'news' && $user['role'] !== 'admin') {
            $score = (new ReputationService())->calculate($userId);
            $appConfig = require __DIR__ . '/../../config/app.php';
            $vipThreshold = (int)($appConfig['reputation']['vip_news_threshold'] ?? 500);
            if ($score < $vipThreshold) {
                $message = $score < 100
                    ? 'Cần ít nhất 100 điểm uy tín mới được gửi Ticket; hiện bạn có ' . $score . ' điểm.'
                    : 'Bạn có thể đăng Blog và gửi Ticket để duyệt Tin tức. Đăng Tin trực tiếp cần ' . $vipThreshold . ' điểm.';
                $this->json(['error' => $message, 'current_score' => $score, 'ticket_threshold' => 100, 'vip_news_threshold' => $vipThreshold], 403);
            }
        }

        // 1. Quét kiểm duyệt nội dung tự động qua AIService
        $mod = $this->aiService->moderateContent($title . "\n" . $content, 'post');
        $modStatus = $mod['status']; // 'safe' | 'flagged' | 'blocked'

        $postId = $this->postModel->create($userId, [
            'title'             => $title,
            'content'           => $content,
            'summary'           => trim((string)($data['summary'] ?? '')),
            'thumbnail'         => $data['thumbnail'] ?? null,
            'post_type'         => $requestedType,
            'hashtags'          => $data['hashtags'] ?? [],
            'moderation_status' => $modStatus,
            'ai_severity'       => $mod['severity'] !== 'none' ? $mod['severity'] : null,
            'ai_violation_type' => $mod['violation_type'] !== 'none' ? $mod['violation_type'] : null,
            'ai_reason'         => $mod['is_flagged'] ? $mod['ai_reason'] : null,
            'ai_status'         => $mod['is_flagged'] ? 'pending' : null,
        ]);

        // Cập nhật thông tin AI bổ sung: ai_analyzed_at, ai_suggested_action
        try {
            $pdo = Database::getInstance()->getConnection();
            $pdo->prepare("UPDATE posts SET ai_analyzed_at = NOW(), ai_suggested_action = ? WHERE id = ?")
                ->execute([$mod['suggested_action'] ?? ($mod['is_flagged'] ? 'flag' : 'pass'), $postId]);
        } catch (Throwable $e) {}

        // 2. Nếu phát hiện vi phạm (flagged hoặc blocked): Ghi nhận vào ai_moderation_items và gửi thông báo
        if ($mod['is_flagged']) {
            try {
                $pdo = Database::getInstance()->getConnection();
                $aimStmt = $pdo->prepare("
                    INSERT INTO ai_moderation_items 
                    (item_type, item_id, post_id, user_id, violation_type, severity, ai_reason, suggested_action, status, created_at)
                    VALUES ('post', :item_id, :post_id, :user_id, :violation_type, :severity, :ai_reason, :suggested_action, 'pending', NOW())
                ");
                $aimStmt->execute([
                    ':item_id'          => $postId,
                    ':post_id'          => $postId,
                    ':user_id'          => $userId,
                    ':violation_type'   => $mod['violation_type'],
                    ':severity'         => $mod['severity'],
                    ':ai_reason'        => $mod['ai_reason'],
                    ':suggested_action' => $mod['suggested_action'],
                ]);
            } catch (Throwable $e) {
                error_log('[PostController] Lỗi ghi ai_moderation_items: ' . $e->getMessage());
            }

            // Gửi thông báo hệ thống đến tác giả
            NotificationHelper::sendSystemModerationNotice(
                $userId,
                'post',
                $title,
                $modStatus === 'blocked' ? 'đã bị khóa tạm thời do vi phạm nghiêm trọng Tiêu chuẩn cộng đồng' : 'đang được hệ thống AI tạm giữ để Ban Quản Trị rà soát',
                $mod['ai_reason'],
                "/post/{$postId}"
            );

            $this->json([
                'message'           => $modStatus === 'blocked'
                    ? 'Bài viết của bạn đã bị khóa tạm thời do vi phạm nghiêm trọng Tiêu chuẩn cộng đồng.'
                    : 'Bài viết đã được gửi và đang chờ Ban Quản Trị xét duyệt do AI phát hiện nội dung cần rà soát.',
                'is_flagged'        => true,
                'moderation_status' => $modStatus,
                'ai_reason'         => $mod['ai_reason'],
                'data'              => $this->postModel->find($postId, $userId)
            ], 201);
        }

        // 3. Đánh giá tiềm năng tin tức tự động nếu bài viết an toàn và là dạng Blog
        if ($requestedType === 'blog' && $modStatus === 'safe') {
            try {
                $newsEval = $this->aiService->evaluateNewsPotential($title, $content);
                if ($newsEval['is_news_worthy']) {
                    $pdo = Database::getInstance()->getConnection();
                    $pdo->prepare("UPDATE posts SET ai_news_suggested = 1, ai_news_reason = ?, ai_status = 'pending' WHERE id = ?")
                        ->execute([$newsEval['reason'], $postId]);
                }
            } catch (Throwable $e) {
                error_log('[PostController] Lỗi đánh giá tin tức: ' . $e->getMessage());
            }
        }

        $this->json(['message' => 'Đăng bài thành công.', 'data' => $this->postModel->find($postId, $userId)], 201);
    }

    public function update(string $id): never
    {
        $userId = Auth::id();
        if ($userId === null) $this->json(['error' => 'Vui lòng đăng nhập để sửa bài viết.'], 401);
        $data = $this->input();
        $title = trim((string)($data['title'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));
        if ($title === '' || mb_strlen($title) > 200) $this->json(['error' => 'Tiêu đề bắt buộc và tối đa 200 ký tự.'], 422);
        if ($content === '' || mb_strlen($content) > 50000) $this->json(['error' => 'Nội dung bắt buộc và tối đa 50.000 ký tự.'], 422);
        $postId = (int)$id;

        // Quét kiểm duyệt nội dung khi chỉnh sửa
        $mod = $this->aiService->moderateContent($title . "\n" . $content, 'post');
        $modStatus = $mod['status'];

        $updateSuccess = $this->postModel->updateOwned($postId, $userId, [
            'title'             => $title,
            'content'           => $content,
            'summary'           => trim((string)($data['summary'] ?? '')),
            'thumbnail'         => $data['thumbnail'] ?? null,
            'moderation_status' => $modStatus,
            'ai_severity'       => $mod['severity'] !== 'none' ? $mod['severity'] : null,
            'ai_violation_type' => $mod['violation_type'] !== 'none' ? $mod['violation_type'] : null,
            'ai_reason'         => $mod['is_flagged'] ? $mod['ai_reason'] : null,
            'ai_status'         => $mod['is_flagged'] ? 'pending' : 'approved',
        ]);

        if (!$updateSuccess) {
            $this->json(['error' => 'Không tìm thấy bài viết hoặc bạn không có quyền sửa bài này.'], 404);
        }

        // Cập nhật thông tin AI bổ sung: ai_analyzed_at, ai_suggested_action
        try {
            $pdo = Database::getInstance()->getConnection();
            $pdo->prepare("UPDATE posts SET ai_analyzed_at = NOW(), ai_suggested_action = ? WHERE id = ?")
                ->execute([$mod['suggested_action'] ?? ($mod['is_flagged'] ? 'flag' : 'pass'), $postId]);
        } catch (Throwable $e) {}

        if ($mod['is_flagged']) {
            try {
                $pdo = Database::getInstance()->getConnection();
                $aimStmt = $pdo->prepare("
                    INSERT INTO ai_moderation_items 
                    (item_type, item_id, post_id, user_id, violation_type, severity, ai_reason, suggested_action, status, created_at)
                    VALUES ('post', :item_id, :post_id, :user_id, :violation_type, :severity, :ai_reason, :suggested_action, 'pending', NOW())
                ");
                $aimStmt->execute([
                    ':item_id'          => $postId,
                    ':post_id'          => $postId,
                    ':user_id'          => $userId,
                    ':violation_type'   => $mod['violation_type'],
                    ':severity'         => $mod['severity'],
                    ':ai_reason'        => $mod['ai_reason'],
                    ':suggested_action' => $mod['suggested_action'],
                ]);
            } catch (Throwable $e) {}

            $this->json([
                'message'           => 'Bài viết đã cập nhật nhưng đang chờ Ban Quản Trị rà soát do AI phát hiện nội dung cần duyệt.',
                'is_flagged'        => true,
                'moderation_status' => $modStatus,
                'ai_reason'         => $mod['ai_reason'],
                'data'              => $this->postModel->find($postId, $userId)
            ]);
        }

        // Đánh giá lại tiềm năng tin tức nếu bài viết an toàn và là bài Blog
        if ($modStatus === 'safe') {
            try {
                $pdo = Database::getInstance()->getConnection();
                $checkType = $pdo->query("SELECT post_type FROM posts WHERE id = {$postId}")->fetchColumn();
                if ($checkType === 'blog') {
                    $newsEval = $this->aiService->evaluateNewsPotential($title, $content);
                    if ($newsEval['is_news_worthy']) {
                        $pdo->prepare("UPDATE posts SET ai_news_suggested = 1, ai_news_reason = ?, ai_status = COALESCE(ai_status, 'pending') WHERE id = ?")
                            ->execute([$newsEval['reason'], $postId]);
                    }
                }
            } catch (Throwable $e) {}
        }

        $this->json(['message' => 'Đã cập nhật bài viết.', 'data' => $this->postModel->find($postId, $userId)]);
    }

    public function delete(string $id): never
    {
        $userId = Auth::id();
        if ($userId === null) $this->json(['error' => 'Vui lòng đăng nhập để xóa bài viết.'], 401);
        if (!$this->postModel->deleteOwned((int)$id, $userId)) {
            $this->json(['error' => 'Không tìm thấy bài viết hoặc bạn không có quyền xóa bài này.'], 404);
        }
        $this->json(['message' => 'Đã xóa bài viết.']);
    }

    public function submitAppeal(string $id): never
    {
        $userId = Auth::id();
        if ($userId === null) {
            $this->json(['error' => 'Vui lòng đăng nhập để gửi khiếu nại.'], 401);
        }

        $data = $this->input();
        $reason = trim((string)($data['reason'] ?? ''));
        if ($reason === '') {
            $this->json(['error' => 'Vui lòng nhập lý do / lời giải trình khiếu nại.'], 422);
        }

        require_once __DIR__ . '/../Models/PostAppeal.php';
        $appealModel = new PostAppeal();

        try {
            $appealId = $appealModel->create((int)$id, $userId, $reason);
            $this->json([
                'message' => 'Gửi khiếu nại thành công! Ban Quản Trị sẽ xem xét và phản hồi sớm nhất.',
                'appeal_id' => $appealId,
            ], 201);
        } catch (DomainException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['error' => 'Không thể gửi khiếu nại: ' . $e->getMessage()], 500);
        }
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
        }
        return $_POST;
    }
}
