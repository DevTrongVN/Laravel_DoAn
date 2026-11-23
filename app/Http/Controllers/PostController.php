<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiModerationItem;
use App\Models\Post;
use App\Models\PostAppeal;
use App\Models\User;
use App\Services\AIService;
use App\Services\NotificationService;
use App\Services\PostQueryService;
use App\Services\ReputationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

/**
 * Controller CRUD Bài viết, Chi tiết bài viết & Khiếu nại mở khóa.
 */
class PostController extends Controller
{
    public function __construct(
        private readonly PostQueryService $postQuery,
        private readonly AIService $aiService,
        private readonly ReputationService $reputationService,
        private readonly FeedController $feedController
    ) {}

    public function show(Request $request, string $id): View|JsonResponse|Response
    {
        $postId = (int) $id;
        $viewerId = Auth::id();
        $this->postQuery->recordView($postId, $viewerId, $request->ip(), $request->userAgent());
        $post = $this->postQuery->find($postId, $viewerId);

        if (!$post) {
            if ($request->is('api/*') || $request->expectsJson() || $request->query('api') !== null) {
                return response()->json(['error' => 'Không tìm thấy bài viết.'], 404);
            }

            return response()->view('errors.post_not_found', array_merge([
                'pageTitle' => 'Bài viết không còn tồn tại — Góc Nhỏ',
                'postId' => $postId,
                'isModal' => $request->has('modal'),
            ], $this->feedController->sidebarData($viewerId)), 404);
        }

        if ($request->expectsJson() || $request->query('api') !== null) {
            return response()->json(['data' => $post]);
        }

        return view('posts.show', array_merge([
            'post' => $post,
            'pageTitle' => (string) $post['title'] . ' — Góc Nhỏ',
        ], $this->feedController->sidebarData($viewerId)));
    }

    public function create(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Vui lòng đăng nhập để đăng bài.'], 401);
        }
        $userId = (int) $user->id;

        $title = trim((string) $request->input('title', ''));
        $content = trim((string) $request->input('content', ''));
        if ($title === '' || mb_strlen($title) > 200) {
            return response()->json(['error' => 'Tiêu đề bắt buộc và tối đa 200 ký tự.'], 422);
        }
        if ($content === '' || mb_strlen($content) > 50000) {
            return response()->json(['error' => 'Nội dung bắt buộc và tối đa 50.000 ký tự.'], 422);
        }

        $requestedType = (string) $request->input('post_type', 'blog');
        if (!in_array($requestedType, ['blog', 'news'], true)) {
            return response()->json(['error' => 'Loại bài viết không hợp lệ.'], 422);
        }

        if ($requestedType === 'news' && $user->role !== 'admin') {
            $score = $this->reputationService->calculate($userId);
            $vipThreshold = (int) config('gocnho.reputation.vip_news_threshold', 500);
            if ($score < $vipThreshold) {
                $message = $score < 100
                    ? "Cần ít nhất 100 điểm uy tín mới được gửi Ticket; hiện bạn có {$score} điểm."
                    : "Bạn có thể đăng Blog và gửi Ticket để duyệt Tin tức. Đăng Tin trực tiếp cần {$vipThreshold} điểm.";
                return response()->json([
                    'error' => $message,
                    'current_score' => $score,
                    'ticket_threshold' => 100,
                    'vip_news_threshold' => $vipThreshold,
                ], 403);
            }
        }

        // 1. Kiểm duyệt nội dung bằng AIService
        $mod = $this->aiService->moderateContent($title . "\n" . $content, 'post');
        $modStatus = $mod['status'];

        $postId = $this->postQuery->create($userId, [
            'title' => $title,
            'content' => $content,
            'summary' => trim((string) $request->input('summary', '')),
            'thumbnail' => $request->input('thumbnail'),
            'post_type' => $requestedType,
            'hashtags' => $request->input('hashtags', []),
            'moderation_status' => $modStatus,
            'ai_severity' => $mod['severity'] !== 'none' ? $mod['severity'] : null,
            'ai_violation_type' => $mod['violation_type'] !== 'none' ? $mod['violation_type'] : null,
            'ai_reason' => $mod['is_flagged'] ? $mod['ai_reason'] : null,
            'ai_status' => $mod['is_flagged'] ? 'pending' : null,
            'ai_suggested_action' => $mod['suggested_action'] ?? ($mod['is_flagged'] ? 'flag' : 'pass'),
        ]);

        // 2. Nếu AI gắn cờ hoặc chặn: ghi vào ai_moderation_items & gửi thông báo
        if ($mod['is_flagged']) {
            try {
                AiModerationItem::create([
                    'item_type' => 'post',
                    'item_id' => $postId,
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'violation_type' => $mod['violation_type'],
                    'severity' => $mod['severity'],
                    'ai_reason' => $mod['ai_reason'],
                    'suggested_action' => $mod['suggested_action'],
                    'status' => 'pending',
                    'created_at' => now(),
                ]);
            } catch (Throwable $e) {
                Log::warning('[PostController] Lỗi ghi ai_moderation_items: ' . $e->getMessage());
            }

            NotificationService::sendSystemModerationNotice(
                $userId,
                'post',
                $title,
                $modStatus === 'blocked'
                    ? 'đã bị khóa tạm thời do vi phạm nghiêm trọng Tiêu chuẩn cộng đồng'
                    : 'đang được hệ thống AI tạm giữ để Ban Quản Trị rà soát',
                $mod['ai_reason'],
                "/post/{$postId}"
            );

            return response()->json([
                'message' => $modStatus === 'blocked'
                    ? 'Bài viết của bạn đã bị khóa tạm thời do vi phạm nghiêm trọng Tiêu chuẩn cộng đồng.'
                    : 'Bài viết đã được gửi và đang chờ Ban Quản Trị xét duyệt do AI phát hiện nội dung cần rà soát.',
                'is_flagged' => true,
                'moderation_status' => $modStatus,
                'ai_reason' => $mod['ai_reason'],
                'data' => $this->postQuery->find($postId, $userId),
            ], 201);
        }

        // 3. Đánh giá tiềm năng Tin tức tự động nếu là bài Blog an toàn
        if ($requestedType === 'blog' && $modStatus === 'safe') {
            try {
                $newsEval = $this->aiService->evaluateNewsPotential($title, $content);
                if ($newsEval['is_news_worthy']) {
                    Post::where('id', $postId)->update([
                        'ai_news_suggested' => 1,
                        'ai_news_reason' => $newsEval['reason'],
                        'ai_status' => 'pending',
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('[PostController] Lỗi đánh giá tin tức: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Đăng bài thành công.',
            'data' => $this->postQuery->find($postId, $userId),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập để sửa bài viết.'], 401);
        }

        $title = trim((string) $request->input('title', ''));
        $content = trim((string) $request->input('content', ''));
        if ($title === '' || mb_strlen($title) > 200) {
            return response()->json(['error' => 'Tiêu đề bắt buộc và tối đa 200 ký tự.'], 422);
        }
        if ($content === '' || mb_strlen($content) > 50000) {
            return response()->json(['error' => 'Nội dung bắt buộc và tối đa 50.000 ký tự.'], 422);
        }

        $postId = (int) $id;
        $mod = $this->aiService->moderateContent($title . "\n" . $content, 'post');
        $modStatus = $mod['status'];

        $updated = $this->postQuery->updateOwned($postId, $userId, [
            'title' => $title,
            'content' => $content,
            'summary' => trim((string) $request->input('summary', '')),
            'thumbnail' => $request->input('thumbnail'),
            'hashtags' => $request->input('hashtags', []),
            'moderation_status' => $modStatus,
            'ai_severity' => $mod['severity'] !== 'none' ? $mod['severity'] : null,
            'ai_violation_type' => $mod['violation_type'] !== 'none' ? $mod['violation_type'] : null,
            'ai_reason' => $mod['is_flagged'] ? $mod['ai_reason'] : null,
            'ai_status' => $mod['is_flagged'] ? 'pending' : 'approved',
            'ai_suggested_action' => $mod['suggested_action'] ?? ($mod['is_flagged'] ? 'flag' : 'pass'),
        ]);

        if (!$updated) {
            return response()->json(['error' => 'Không tìm thấy bài viết hoặc bạn không có quyền sửa bài này.'], 404);
        }

        if ($mod['is_flagged']) {
            try {
                AiModerationItem::create([
                    'item_type' => 'post',
                    'item_id' => $postId,
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'violation_type' => $mod['violation_type'],
                    'severity' => $mod['severity'],
                    'ai_reason' => $mod['ai_reason'],
                    'suggested_action' => $mod['suggested_action'],
                    'status' => 'pending',
                    'created_at' => now(),
                ]);
            } catch (Throwable) {}

            return response()->json([
                'message' => 'Bài viết đã cập nhật nhưng đang chờ Ban Quản Trị rà soát do AI phát hiện nội dung cần duyệt.',
                'is_flagged' => true,
                'moderation_status' => $modStatus,
                'ai_reason' => $mod['ai_reason'],
                'data' => $this->postQuery->find($postId, $userId),
            ]);
        }

        if ($modStatus === 'safe') {
            $postRow = Post::find($postId);
            if ($postRow && $postRow->post_type === 'blog') {
                try {
                    $newsEval = $this->aiService->evaluateNewsPotential($title, $content);
                    if ($newsEval['is_news_worthy']) {
                        DB::update("UPDATE posts SET ai_news_suggested = 1, ai_news_reason = ?, ai_status = COALESCE(ai_status, 'pending') WHERE id = ?", [$newsEval['reason'], $postId]);
                    }
                } catch (Throwable) {}
            }
        }

        return response()->json([
            'message' => 'Đã cập nhật bài viết.',
            'data' => $this->postQuery->find($postId, $userId),
        ]);
    }

    public function delete(string $id): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập để xóa bài viết.'], 401);
        }

        $postId = (int) $id;
        $postToDelete = $this->postQuery->find($postId, $userId);
        $deleted = Post::where('id', $postId)->where('user_id', $userId)->delete();
        if ($deleted === 0) {
            return response()->json(['error' => 'Không tìm thấy bài viết hoặc bạn không có quyền xóa bài này.'], 404);
        }

        if (!empty($postToDelete['original_user_id'])) {
            try {
                $this->reputationService->calculate((int) $postToDelete['original_user_id']);
            } catch (Throwable) {}
        }

        return response()->json(['message' => 'Đã xóa bài viết.']);
    }

    public function submitAppeal(Request $request, string $id): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập để gửi khiếu nại.'], 401);
        }

        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '' || mb_strlen($reason) > 2000) {
            return response()->json(['error' => 'Vui lòng nhập lý do / lời giải trình khiếu nại (tối đa 2.000 ký tự).'], 422);
        }

        $postId = (int) $id;
        $post = Post::find($postId);
        if (!$post) {
            return response()->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }
        if ((int) $post->user_id !== $userId) {
            return response()->json(['error' => 'Chỉ tác giả bài viết mới có thể gửi khiếu nại.'], 403);
        }
        if ($post->moderation_status === 'safe') {
            return response()->json(['error' => 'Bài viết hiện đang ở trạng thái an toàn, không cần khiếu nại.'], 422);
        }

        $pending = PostAppeal::where('post_id', $postId)->where('status', 'pending')->exists();
        if ($pending) {
            return response()->json(['error' => 'Bài viết này đã có một yêu cầu khiếu nại đang chờ xử lý.'], 422);
        }

        $appeal = PostAppeal::create([
            'post_id' => $postId,
            'user_id' => $userId,
            'reason' => $reason,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Gửi khiếu nại thành công! Ban Quản Trị sẽ xem xét và phản hồi sớm nhất.',
            'appeal_id' => (int) $appeal->id,
        ], 201);
    }
}
