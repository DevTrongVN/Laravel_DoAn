<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Interaction;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PostQueryService;
use App\Services\ReputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * API Controller: Bảng tin, Phân trang Feed, Chi tiết bài viết, Share, Repost, Báo cáo bài viết.
 */
class ApiPostController extends Controller
{
    public function __construct(
        private readonly PostQueryService $postQuery,
        private readonly ReputationService $reputationService
    ) {}

    public function feed(Request $request): JsonResponse
    {
        $filters = [];
        $type = $request->query('post_type') ?? $request->query('type');
        if ($type === 'news' || $type === 'blog') {
            $filters['type'] = $type;
        }
        $hasImage = $request->query('has_image');
        if ($hasImage !== null && $hasImage !== '' && $hasImage !== 'all') {
            $filters['has_image'] = (string) $hasImage;
        }
        if ($request->filled('user_id')) {
            $filters['user_id'] = (int) $request->query('user_id');
        }
        if ($request->filled('following') && $request->query('following') !== '0') {
            $filters['following'] = 1;
        }
        if ($request->filled('hashtag')) {
            $filters['hashtag'] = (string) $request->query('hashtag');
        }
        if ($request->filled('author_username')) {
            $filters['author_username'] = (string) $request->query('author_username');
        } elseif ($request->filled('author')) {
            $filters['author_username'] = (string) $request->query('author');
        }
        if ($request->filled('q')) {
            $qVal = (string) $request->query('q');
            if (str_starts_with($qVal, '@')) {
                $filters['author_username'] = ltrim($qVal, '@');
            } else {
                $filters['q'] = $qVal;
            }
        }

        $viewerId = Auth::id();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));

        $mode = (string) ($request->query('mode') ?? $request->query('tab') ?? 'all');
        if ($mode === 'feed' || $mode === 'home') {
            $mode = 'all';
        }
        if (in_array($mode, ['all', 'for_you', 'following', 'default'], true)) {
            $filters['mode'] = $mode;
        }
        if ($mode === 'following') {
            $filters['following'] = 1;
        }
        if (in_array((string) $request->query('sort', ''), ['latest', 'popular'], true)) {
            $filters['sort'] = (string) $request->query('sort');
        }
        if ($request->filled('seed') && is_numeric($request->query('seed'))) {
            $filters['seed'] = (int) $request->query('seed');
        }

        $result = $this->postQuery->list($filters, $viewerId, $page, $perPage);

        return response()->json([
            'data' => $result['data'],
            'pagination' => [
                'page' => $result['page'],
                'per_page' => $perPage,
                'total' => $result['total'],
                'last_page' => $result['last_page'],
                'has_more' => $result['page'] < $result['last_page'],
                'seed' => $result['seed'] ?? ($filters['seed'] ?? null),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $postId = (int) $id;
        $viewerId = Auth::id();
        $this->postQuery->recordView($postId, $viewerId, $request->ip(), $request->userAgent());
        $post = $this->postQuery->find($postId, $viewerId);
        if (!$post) {
            return response()->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }
        return response()->json(['data' => $post]);
    }

    public function share(string $id): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }

        $postId = (int) $id;
        $post = $this->postQuery->find($postId, $userId);
        if (!$post) {
            return response()->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }
        if ($userId === (int) $post['user_id']) {
            return response()->json(['error' => 'Bạn không thể tự chia sẻ bài viết của chính mình.'], 422);
        }

        $alreadyShared = Interaction::where('user_id', $userId)->where('post_id', $postId)->where('type', 'share')->exists()
            || $this->postQuery->hasReposted($userId, $postId);

        DB::insert("INSERT IGNORE INTO interactions (user_id, post_id, type, created_at) VALUES (?, ?, 'share', NOW())", [$userId, $postId]);

        if (!$alreadyShared) {
            $this->reputationService->calculate((int) $post['user_id']);
        }

        $updated = $this->postQuery->find($postId, $userId);
        $totalShares = (int) ($updated['shares_count'] ?? 0);

        return response()->json([
            'success' => true,
            'already_shared' => $alreadyShared,
            'is_liked' => $updated['is_liked'] ?? false,
            'likes_count' => $updated['likes_count'] ?? 0,
            'shares_count' => $totalShares,
            'total_shares' => $totalShares,
            'message' => $alreadyShared
                ? 'Bạn đã từng chia sẻ bài viết này. Đã sao chép liên kết!'
                : 'Đã sao chép liên kết bài viết!',
        ]);
    }

    public function repost(Request $request, string $id): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Đăng nhập để đăng lại bài viết.'], 401);
        }

        $postId = (int) $id;
        $original = $this->postQuery->find($postId, $userId);
        if (!$original) {
            return response()->json(['error' => 'Không tìm thấy bài viết gốc.'], 404);
        }
        if ((int) $original['user_id'] === $userId) {
            return response()->json(['error' => 'Bạn không thể tự đăng lại bài viết của chính mình.'], 422);
        }

        try {
            $newId = $this->postQuery->repost($userId, $postId, (string) $request->input('comment', ''));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage() ?: 'Không thể đăng lại bài viết.'], 500);
        }

        try {
            $this->reputationService->calculate((int) $original['user_id']);
        } catch (Throwable $e) {
            Log::warning('Reputation calc after repost error: ' . $e->getMessage());
        }

        if ((int) $original['user_id'] !== $userId) {
            $actor = User::find($userId);
            $actorName = $actor?->full_name ?? 'Ai đó';
            NotificationService::send(
                (int) $original['user_id'],
                $userId,
                'repost',
                "{$actorName} đã đăng lại bài viết \"{$original['title']}\" của bạn",
                "/post/{$newId}"
            );
        }

        $updatedOriginal = $this->postQuery->find($postId, $userId);
        $totalShares = (int) ($updatedOriginal['shares_count'] ?? 0);

        return response()->json([
            'message' => 'Đã đăng lại bài viết lên trang cá nhân.',
            'total_shares' => $totalShares,
            'shares_count' => $totalShares,
            'data' => $this->postQuery->find($newId, $userId),
        ], 201);
    }

    public function report(Request $request, string $id): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập để báo cáo bài viết.'], 401);
        }

        $postId = (int) $id;
        $post = Post::find($postId);
        if (!$post) {
            return response()->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }
        if ((int) $post->user_id === $userId) {
            return response()->json(['error' => 'Bạn không thể tự báo cáo bài viết của chính mình.'], 422);
        }

        $reason = trim((string) $request->input('reason', ''));
        $details = trim((string) $request->input('details', ''));
        $allowedReasons = ['spam', 'hate_speech', 'misinformation', 'inappropriate', 'impersonation', 'harassment', 'copyright', 'other'];
        if ($reason === '' || !in_array($reason, $allowedReasons, true)) {
            return response()->json(['error' => 'Vui lòng chọn lý do báo cáo hợp lệ.'], 422);
        }

        $dup = PostReport::where('post_id', $postId)->where('user_id', $userId)->where('status', 'pending')->exists();
        if ($dup) {
            return response()->json(['error' => 'Bạn đã gửi báo cáo cho bài viết này và đang chờ xử lý.'], 422);
        }

        PostReport::create([
            'post_id' => $postId,
            'user_id' => $userId,
            'reason' => $reason,
            'details' => $details !== '' ? $details : null,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Đã gửi báo cáo đến quản trị viên.']);
    }
}
