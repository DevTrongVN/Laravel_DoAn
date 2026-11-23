<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/RateLimiter.php';
require_once __DIR__ . '/../../Services/AIService.php';
require_once __DIR__ . '/../../Models/Post.php';

/** API Endpoints: AI Sửa văn phong, Quét kiểm duyệt, Đánh giá tiềm năng Tin tức. */
class ApiAIController extends Controller
{
    private AIService $aiService;

    public function __construct()
    {
        $this->aiService = new AIService();
    }

    /**
     * POST /api/ai/improve
     * POST /api/ai/rewrite
     * Body JSON: { "content": "...", "mode"|"tone": "spellcheck|natural|concise|expand|improve|professional|literary|friendly" }
     */
    public function improveStyle(): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập để sử dụng AI Editor.'], 401);
        }

        $raw     = file_get_contents('php://input');
        $data    = json_decode($raw ?: '', true) ?: $_POST;
        $content = trim((string)($data['content'] ?? ''));
        $mode    = trim((string)($data['mode'] ?? ($data['tone'] ?? 'improve')));

        if ($content === '') {
            $this->json(['error' => 'Vui lòng cung cấp nội dung cần chỉnh sửa.'], 422);
        }

        if (mb_strlen($content) > 10000) {
            $this->json(['error' => 'Nội dung tối đa 10.000 ký tự cho mỗi lần biên tập AI.'], 422);
        }

        if (!in_array($mode, AIService::SUPPORTED_EDIT_MODES, true)) {
            $mode = 'improve';
        }

        $result = $this->aiService->improveStyleWithMeta($content, $mode);
        $improved = $result['improved'];

        $this->json([
            'original'        => $content,
            'improved'        => $improved,
            'rewritten'       => $improved,
            'mode'            => $result['mode'],
            'tone'            => $result['mode'],
            'engine'          => $result['engine'],
            'model'           => $result['model'],
            'notice'          => $result['notice'],
            'finish_reason'   => $result['finish_reason'] ?? null,
            'usage'           => $result['usage'] ?? null,
            'length_original' => mb_strlen($content),
            'length_improved' => mb_strlen($improved),
        ]);
    }

    /**
     * POST /api/ai/rewrite (Alias cho improveStyle)
     */
    public function rewrite(): never
    {
        $this->improveStyle();
    }

    /**
     * POST /api/ai/moderate
     * Body JSON: { "content": "..." }
     * Yêu cầu đăng nhập (Session + CSRF) và áp dụng giới hạn tần suất phía server.
     */
    public function moderate(): never
    {
        $userId = Auth::id();
        if ($userId === null) {
            $this->json(['error' => 'Vui lòng đăng nhập để sử dụng API kiểm duyệt AI.'], 401);
        }

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        if (!isset($data['content']) || !is_string($data['content'])) {
            $this->json(['error' => 'Trường content không hợp lệ hoặc bị thiếu.'], 422);
        }

        $content = trim($data['content']);
        if ($content === '') {
            $this->json(['error' => 'Vui lòng cung cấp nội dung cần kiểm duyệt.'], 422);
        }

        if (mb_strlen($content, 'UTF-8') > 10000) {
            $this->json(['error' => 'Nội dung kiểm duyệt tối đa 10.000 ký tự.'], 422);
        }

        // Kiểm tra rate limit phía server (theo user ID và theo IP) trước khi gọi Gemini
        $aiConfig  = require __DIR__ . '/../../../config/ai_config.php';
        $rlCfg     = $aiConfig['rate_limit'] ?? [];
        $windowSec = max(1, (int)($rlCfg['moderate_window_seconds'] ?? 60));
        $isAdmin   = Auth::isAdmin();
        $userLimit = $isAdmin
            ? max(1, (int)($rlCfg['moderate_admin_max'] ?? 60))
            : max(1, (int)($rlCfg['moderate_user_max'] ?? 15));
        $ipLimit   = max(1, (int)($rlCfg['moderate_ip_max'] ?? 30));
        if ($isAdmin) {
            $ipLimit = max($ipLimit, $userLimit);
        }

        $limiter   = new RateLimiter();
        $userCheck = $limiter->attempt("ai_moderate:user:{$userId}", $userLimit, $windowSec);
        if (!$userCheck['allowed']) {
            header('Retry-After: ' . $userCheck['retry_after']);
            header('X-RateLimit-Limit: ' . $userCheck['limit']);
            header('X-RateLimit-Remaining: 0');
            $this->json([
                'error'       => "Bạn gửi yêu cầu kiểm duyệt AI quá nhanh. Vui lòng thử lại sau {$userCheck['retry_after']} giây.",
                'retry_after' => $userCheck['retry_after'],
            ], 429);
        }

        $ip      = RateLimiter::clientIp();
        $ipCheck = $limiter->attempt("ai_moderate:ip:{$ip}", $ipLimit, $windowSec);
        if (!$ipCheck['allowed']) {
            header('Retry-After: ' . $ipCheck['retry_after']);
            header('X-RateLimit-Limit: ' . $ipCheck['limit']);
            header('X-RateLimit-Remaining: 0');
            $this->json([
                'error'       => "Địa chỉ IP của bạn vượt quá giới hạn kiểm duyệt AI. Vui lòng thử lại sau {$ipCheck['retry_after']} giây.",
                'retry_after' => $ipCheck['retry_after'],
            ], 429);
        }

        header('X-RateLimit-Limit: ' . $userCheck['limit']);
        header('X-RateLimit-Remaining: ' . $userCheck['remaining']);

        $scan = $this->aiService->scanContent($content);
        $this->json($scan);
    }

    /**
     * POST /api/ai/news-potential
     * Body JSON: { "post_id": 123 }  — hoặc gọi trực tiếp sau khi tạo bài
     * Đánh giá & ghi nhận kết quả vào cột ai_news_suggested của bảng posts.
     */
    public function suggestNews(): never
    {
        $userId = Auth::id();
        if ($userId === null) {
            $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }

        $raw    = file_get_contents('php://input');
        $data   = json_decode($raw, true) ?: $_POST;
        $postId = (int)($data['post_id'] ?? 0);

        if ($postId <= 0) {
            $this->json(['error' => 'Thiếu post_id.'], 422);
        }

        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT id, title, content, user_id FROM posts WHERE id = ? AND moderation_status <> 'blocked' LIMIT 1");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();

        if (!$post) {
            $this->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }

        // Chỉ tác giả hoặc Admin mới được đánh giá
        if ((int)$post['user_id'] !== $userId && !Auth::isAdmin()) {
            $this->json(['error' => 'Bạn không có quyền đánh giá bài viết này.'], 403);
        }

        $result = $this->aiService->evaluateNewsPotential((string)$post['title'], (string)$post['content']);

        // Ghi nhận vào posts
        $update = $pdo->prepare(
            'UPDATE posts SET ai_news_suggested = ?, ai_news_reason = ? WHERE id = ?'
        );
        $update->execute([$result['is_news_worthy'] ? 1 : 0, $result['reason'], $postId]);

        $this->json(array_merge($result, ['post_id' => $postId]));
    }
}
