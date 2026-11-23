<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Interaction;
use App\Services\PostQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Controller Bookmark (Lưu / Bỏ lưu bài viết).
 */
class BookmarkController extends Controller
{
    public function __construct(
        private readonly PostQueryService $postQuery,
        private readonly FeedController $feedController
    ) {}

    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        if (!Auth::check()) {
            if ($request->expectsJson() || $request->query('api') !== null) {
                return response()->json(['error' => 'Vui lòng đăng nhập để xem danh sách bài viết đã lưu.'], 401);
            }
            return redirect('/account?mode=login');
        }

        if ($request->expectsJson() || $request->query('api') !== null) {
            return $this->list($request);
        }

        $userId = (int) Auth::id();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));
        $posts = $this->postQuery->listBookmarked($userId, $page, $perPage);
        $total = $this->postQuery->countBookmarked($userId);

        return view('bookmarks.index', array_merge([
            'posts' => $posts,
            'total' => $total,
            'pageTitle' => 'Bài viết đã lưu — Góc Nhỏ',
            'activeTab' => 'bookmarks',
            'page' => $page,
            'perPage' => $perPage,
            'hasMore' => ($page * $perPage) < $total,
        ], $this->feedController->sidebarData($userId)));
    }

    public function list(Request $request): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập để xem danh sách bài viết đã lưu.'], 401);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));
        $posts = $this->postQuery->listBookmarked($userId, $page, $perPage);
        $total = $this->postQuery->countBookmarked($userId);

        return response()->json([
            'data' => $posts,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => ($page * $perPage) < $total,
            ],
        ]);
    }

    public function add(Request $request, string $id): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập để lưu bài viết.'], 401);
        }

        $postId = (int) $id;
        $post = $this->postQuery->find($postId, $userId);
        if (!$post || ($post['moderation_status'] ?? '') !== 'safe') {
            return response()->json(['error' => 'Không tìm thấy bài viết hoặc bài viết không khả dụng.'], 404);
        }

        $action = $request->input('action');
        if (in_array($action, ['remove', 'delete', 'unbookmark'], true)) {
            $this->removeBookmark($userId, $postId);
            return response()->json(['success' => true, 'is_bookmarked' => false, 'message' => 'Đã bỏ lưu bài viết.']);
        }

        if (in_array($action, ['add', 'save'], true)) {
            $this->addBookmark($userId, $postId);
            return response()->json(['success' => true, 'is_bookmarked' => true, 'message' => 'Đã lưu bài viết vào danh sách đánh dấu.']);
        }

        $exists = Interaction::where('user_id', $userId)
            ->where('post_id', $postId)
            ->where('type', 'bookmark')
            ->exists();

        if ($exists) {
            $this->removeBookmark($userId, $postId);
            return response()->json(['success' => true, 'is_bookmarked' => false, 'message' => 'Đã bỏ lưu bài viết.']);
        }

        $this->addBookmark($userId, $postId);
        return response()->json(['success' => true, 'is_bookmarked' => true, 'message' => 'Đã lưu bài viết vào danh sách đánh dấu.']);
    }

    public function remove(string $id): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập để tiếp tục.'], 401);
        }

        $this->removeBookmark($userId, (int) $id);
        return response()->json(['success' => true, 'is_bookmarked' => false, 'message' => 'Đã bỏ lưu bài viết.']);
    }

    private function addBookmark(int $userId, int $postId): void
    {
        DB::insert("INSERT IGNORE INTO interactions (user_id, post_id, type, created_at) VALUES (?, ?, 'bookmark', NOW())", [$userId, $postId]);
        DB::insert('INSERT IGNORE INTO bookmarks (user_id, post_id, created_at) VALUES (?, ?, NOW())', [$userId, $postId]);
    }

    private function removeBookmark(int $userId, int $postId): void
    {
        DB::delete("DELETE FROM interactions WHERE user_id = ? AND post_id = ? AND type = 'bookmark'", [$userId, $postId]);
        DB::delete('DELETE FROM bookmarks WHERE user_id = ? AND post_id = ?', [$userId, $postId]);
    }
}
