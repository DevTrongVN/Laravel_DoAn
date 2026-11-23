<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiModerationItem;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Post;
use App\Models\PostAppeal;
use App\Models\PostReport;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VerifiedBadgeLog;
use App\Services\NotificationService;
use App\Support\UnicodeHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Controller Quản trị viên (Admin):
 * Bảo vệ bằng Middleware 'admin', phục vụ trang Dashboard và toàn bộ 6 Module API Quản trị.
 */
class AdminController extends Controller
{
    /**
     * GET /admin (Web View hoặc JSON stats khi gọi ?api=1)
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->expectsJson() || $request->query('api') !== null) {
            $totalUsers = (int) User::count();
            $totalPosts = (int) Post::count();

            $pendingTickets = (int) Ticket::where('status', 'pending')->where('ticket_type', 'upgrade')->count();
            $pendingAiNews = (int) Post::where('ai_news_suggested', 1)
                ->where(fn($q) => $q->where('ai_status', 'pending')->orWhereNull('ai_status'))
                ->count();
            $totalPendingNews = $pendingTickets + $pendingAiNews;

            try {
                $pendingAiItems = (int) AiModerationItem::where('status', 'pending')->count();
            } catch (Throwable) {
                $pendingAiItems = (int) Post::whereIn('moderation_status', ['flagged', 'blocked'])
                    ->whereNotNull('ai_severity')
                    ->where(fn($q) => $q->whereNull('ai_status')->orWhere('ai_status', 'pending'))
                    ->count();
            }

            $pendingPostReports = (int) PostReport::where('status', 'pending')->count();
            $pendingCommentReports = (int) CommentReport::where('status', 'pending')->count();
            $pendingAppeals = (int) PostAppeal::where('status', 'pending')->count();
            $totalPendingReports = $pendingPostReports + $pendingCommentReports + $pendingAppeals;

            return response()->json([
                'data' => [
                    'total_users' => $totalUsers,
                    'total_posts' => $totalPosts,
                    'pending_tickets' => $totalPendingNews,
                    'pending_news' => $totalPendingNews,
                    'flagged_posts' => $pendingAiItems,
                    'ai_moderation_pending' => $pendingAiItems,
                    'pending_reports' => $totalPendingReports,
                    'pending_post_reports' => $pendingPostReports,
                    'pending_comment_reports' => $pendingCommentReports,
                    'pending_appeals' => $pendingAppeals,
                ],
            ]);
        }

        return view('admin.index', [
            'pageTitle' => 'Quản trị hệ thống — Góc Nhỏ',
        ]);
    }

    // =========================================================================
    // MODULE 1: NGƯỜI DÙNG & TÍCH XANH
    // =========================================================================

    public function users(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $q = trim((string) $request->query('q', ''));
        $role = trim((string) $request->query('role', ''));
        $isVerified = $request->query('is_verified');
        $eligible = $request->query('eligible');
        $sort = (string) $request->query('sort', 'reputation_desc');

        $query = User::select('id', 'username', 'email', 'full_name', 'avatar', 'role', 'is_verified', 'reputation_score', 'created_at');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(fn($sub) => $sub->where('username', 'LIKE', $like)->orWhere('full_name', 'LIKE', $like)->orWhere('email', 'LIKE', $like));
        }
        if (in_array($role, ['admin', 'user'], true)) {
            $query->where('role', $role);
        }
        if ($isVerified !== null && $isVerified !== '') {
            $query->where('is_verified', (int) $isVerified);
        }
        if ($eligible !== null && $eligible !== '') {
            if ((int) $eligible === 1) {
                $query->where('reputation_score', '>=', 100);
            } else {
                $query->where('reputation_score', '<', 100);
            }
        }

        match ($sort) {
            'reputation_asc' => $query->orderBy('reputation_score')->orderBy('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'name_asc' => $query->orderBy('full_name'),
            default => $query->orderByDesc('reputation_score')->orderBy('id'),
        };

        $total = (clone $query)->count();
        $users = $query->offset(($page - 1) * $perPage)->limit($perPage)->get()->map(static function (User $u): array {
            return [
                'id' => (int) $u->id,
                'username' => $u->username,
                'email' => $u->email,
                'full_name' => $u->full_name,
                'avatar' => $u->avatar,
                'role' => $u->role,
                'is_verified' => (bool) $u->is_verified,
                'reputation_score' => (int) $u->reputation_score,
                'is_eligible' => (int) $u->reputation_score >= 100,
                'created_at' => $u->created_at ? $u->created_at->format('Y-m-d H:i:s') : null,
            ];
        })->all();

        return response()->json(UnicodeHelper::toNfc([
            'data' => $users,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'current_admin_id' => (int) Auth::id(),
        ]));
    }

    public function toggleVerified(Request $request, string $id): JsonResponse
    {
        $user = User::find((int) $id);
        if (!$user) {
            return response()->json(['error' => 'Không tìm thấy người dùng.'], 404);
        }

        $newVal = $request->has('is_verified')
            ? filter_var($request->input('is_verified'), FILTER_VALIDATE_BOOLEAN)
            : !$user->is_verified;

        $action = $newVal ? 'grant' : 'revoke';
        $reason = trim((string) $request->input('reason', '')) ?: null;

        $user->is_verified = $newVal;
        $user->save();

        try {
            VerifiedBadgeLog::create([
                'user_id' => $user->id,
                'admin_id' => (int) Auth::id(),
                'action' => $action,
                'reason' => $reason,
                'created_at' => now(),
            ]);
        } catch (Throwable) {}

        return response()->json([
            'message' => $newVal ? 'Đã cấp tích xanh thành công.' : 'Đã thu hồi tích xanh.',
            'is_verified' => $newVal,
            'action' => $action,
            'reason' => $reason,
        ]);
    }

    public function badgeLogs(string $id): JsonResponse
    {
        $rows = array_map(static fn($r) => (array) $r, DB::select("
            SELECT vbl.id, vbl.action, vbl.reason, vbl.created_at,
                   adm.full_name AS admin_name, adm.username AS admin_username
            FROM verified_badge_logs vbl
            JOIN users adm ON adm.id = vbl.admin_id
            WHERE vbl.user_id = ?
            ORDER BY vbl.created_at DESC
            LIMIT 50
        ", [(int) $id]));

        return response()->json(['data' => UnicodeHelper::toNfc($rows)]);
    }

    public function changeRole(Request $request, string $id): JsonResponse
    {
        $role = trim((string) $request->input('role', ''));
        if (!in_array($role, ['admin', 'user'], true)) {
            return response()->json(['error' => 'Vai trò không hợp lệ.'], 422);
        }

        $targetId = (int) $id;
        if ($targetId === (int) Auth::id() && $role !== 'admin') {
            return response()->json(['error' => 'Bạn không thể tự hạ quyền quản trị của chính mình.'], 422);
        }

        User::where('id', $targetId)->update(['role' => $role]);

        return response()->json([
            'message' => 'Đã cập nhật vai trò người dùng thành ' . ($role === 'admin' ? 'Quản trị viên (Admin)' : 'Người dùng (User)') . '.',
            'role' => $role,
        ]);
    }

    // =========================================================================
    // MODULE 2: QUẢN LÝ BÀI VIẾT & BÌNH LUẬN
    // =========================================================================

    public function posts(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $q = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));
        $status = trim((string) $request->query('moderation_status', ''));
        $sort = (string) $request->query('sort', 'newest');

        $where = [];
        $bindings = [];
        if ($q !== '') {
            $where[] = '(p.title LIKE ? OR u.full_name LIKE ? OR u.username LIKE ? OR p.content LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bindings, $like, $like, $like, $like);
        }
        if (in_array($category, ['blog', 'news'], true)) {
            $where[] = 'p.post_type = ?';
            $bindings[] = $category;
        }
        if (in_array($status, ['safe', 'flagged', 'blocked', 'pending'], true)) {
            $where[] = 'p.moderation_status = ?';
            $bindings[] = $status;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $orderSql = match ($sort) {
            'oldest' => 'p.created_at ASC, p.id ASC',
            'views_desc' => 'p.views_count DESC, p.id DESC',
            'comments_desc' => 'comments_count DESC, p.id DESC',
            default => 'p.created_at DESC, p.id DESC',
        };

        $total = (int) (DB::selectOne("SELECT COUNT(*) AS cnt FROM posts p JOIN users u ON u.id = p.user_id {$whereSql}", $bindings)->cnt ?? 0);
        $offset = ($page - 1) * $perPage;
        $posts = array_map(static fn($r) => (array) $r, DB::select("
            SELECT p.id, p.title, p.slug, p.summary, p.post_type, p.moderation_status,
                   p.ai_severity, p.ai_violation_type, p.views_count, p.created_at,
                   u.id AS user_id, u.full_name AS author_name, u.username AS author_username,
                   (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comments_count,
                   (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id = p.id) AS likes_count
            FROM posts p
            JOIN users u ON u.id = p.user_id
            {$whereSql}
            ORDER BY {$orderSql}
            LIMIT {$perPage} OFFSET {$offset}
        ", $bindings));

        foreach ($posts as &$post) {
            $post['id'] = (int) $post['id'];
            $post['views_count'] = (int) $post['views_count'];
            $post['comments_count'] = (int) $post['comments_count'];
            $post['likes_count'] = (int) $post['likes_count'];
        }
        unset($post);

        return response()->json(UnicodeHelper::toNfc([
            'data' => $posts,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]));
    }

    public function updatePostModeration(Request $request, string $id): JsonResponse
    {
        $status = (string) $request->input('status', 'safe');
        $reason = trim((string) $request->input('reason', ''));
        if (!in_array($status, ['safe', 'flagged', 'blocked'], true)) {
            return response()->json(['error' => 'Trạng thái kiểm duyệt không hợp lệ.'], 422);
        }

        $post = Post::find((int) $id);
        if (!$post) {
            return response()->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }

        $post->moderation_status = $status;
        $post->moderated_by = (int) Auth::id();
        $post->moderated_at = now();
        $post->save();

        if (in_array($status, ['blocked', 'flagged'], true)) {
            $actionText = $status === 'blocked' ? 'bị khóa do vi phạm chính sách cộng đồng' : 'bị gắn cờ cảnh báo nội dung nhạy cảm';
            NotificationService::sendSystemModerationNotice(
                (int) $post->user_id,
                'post',
                (string) $post->title,
                $actionText,
                $reason ?: 'Quyết định kiểm duyệt từ Quản trị viên.',
                "/post/{$post->id}"
            );
        }

        return response()->json([
            'message' => "Đã cập nhật trạng thái bài viết thành: {$status}",
            'moderation_status' => $status,
        ]);
    }

    public function updatePostType(Request $request, string $id): JsonResponse
    {
        $postType = (string) $request->input('post_type', 'blog');
        if (!in_array($postType, ['blog', 'news'], true)) {
            return response()->json(['error' => 'Thể loại bài viết không hợp lệ.'], 422);
        }

        Post::where('id', (int) $id)->update(['post_type' => $postType]);

        return response()->json([
            'message' => 'Đã chuyển thể loại bài viết thành: ' . ($postType === 'news' ? 'Tin tức' : 'Blog cá nhân'),
            'post_type' => $postType,
        ]);
    }

    public function deletePost(string $id): JsonResponse
    {
        $post = Post::find((int) $id);
        if (!$post) {
            return response()->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }

        $authorId = (int) $post->user_id;
        $title = (string) $post->title;
        $post->delete();

        NotificationService::sendSystemModerationNotice(
            $authorId,
            'post',
            $title,
            'đã bị quản trị viên xóa vĩnh viễn khỏi hệ thống',
            'Bài viết vi phạm nghiêm trọng chính sách hoặc có yêu cầu gỡ bỏ.',
            '/'
        );

        return response()->json(['message' => 'Đã xóa bài viết thành công.']);
    }

    public function comments(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('moderation_status', ''));
        $sort = (string) $request->query('sort', 'newest');

        $where = [];
        $bindings = [];
        if ($q !== '') {
            $where[] = '(c.content LIKE ? OR u.full_name LIKE ? OR u.username LIKE ? OR p.title LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bindings, $like, $like, $like, $like);
        }
        if (in_array($status, ['safe', 'flagged', 'blocked', 'pending'], true)) {
            $where[] = 'c.moderation_status = ?';
            $bindings[] = $status;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $orderSql = $sort === 'oldest' ? 'c.created_at ASC, c.id ASC' : 'c.created_at DESC, c.id DESC';

        $total = (int) (DB::selectOne("SELECT COUNT(*) AS cnt FROM comments c JOIN users u ON u.id = c.user_id JOIN posts p ON p.id = c.post_id {$whereSql}", $bindings)->cnt ?? 0);
        $offset = ($page - 1) * $perPage;
        $comments = array_map(static fn($r) => (array) $r, DB::select("
            SELECT c.id, c.post_id, c.user_id, c.content, c.moderation_status, c.created_at,
                   u.full_name AS author_name, u.username AS author_username,
                   p.title AS post_title
            FROM comments c
            JOIN users u ON u.id = c.user_id
            JOIN posts p ON p.id = c.post_id
            {$whereSql}
            ORDER BY {$orderSql}
            LIMIT {$perPage} OFFSET {$offset}
        ", $bindings));

        foreach ($comments as &$c) {
            $c['id'] = (int) $c['id'];
            $c['post_id'] = (int) $c['post_id'];
            $c['user_id'] = (int) $c['user_id'];
        }
        unset($c);

        return response()->json(UnicodeHelper::toNfc([
            'data' => $comments,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]));
    }

    public function updateCommentModeration(Request $request, string $id): JsonResponse
    {
        $status = (string) $request->input('status', 'safe');
        $reason = trim((string) $request->input('reason', ''));
        if (!in_array($status, ['safe', 'blocked'], true)) {
            return response()->json(['error' => 'Trạng thái bình luận không hợp lệ.'], 422);
        }

        $comment = Comment::find((int) $id);
        if (!$comment) {
            return response()->json(['error' => 'Không tìm thấy bình luận.'], 404);
        }

        $comment->moderation_status = $status;
        $comment->save();

        if ($status === 'blocked') {
            NotificationService::sendSystemModerationNotice(
                (int) $comment->user_id,
                'comment',
                (string) $comment->content,
                'đã bị quản trị viên ẩn do vi phạm tiêu chuẩn cộng đồng',
                $reason ?: 'Bình luận không phù hợp với chuẩn mực thảo luận.',
                "/post/{$comment->post_id}"
            );
        }

        return response()->json([
            'message' => $status === 'blocked' ? 'Đã ẩn bình luận.' : 'Đã mở lại hiển thị bình luận.',
            'moderation_status' => $status,
        ]);
    }

    public function deleteComment(string $id): JsonResponse
    {
        $comment = Comment::find((int) $id);
        if (!$comment) {
            return response()->json(['error' => 'Không tìm thấy bình luận.'], 404);
        }

        $cid = (int) $id;
        $authorId = (int) $comment->user_id;
        $postId = (int) $comment->post_id;
        $content = (string) $comment->content;

        // Thu thập toàn bộ ID nhánh con/cháu/chắt đệ quy và xóa trong transaction
        $deletedIds = Comment::collectDescendantIds($cid);

        DB::transaction(function () use ($deletedIds) {
            DB::table('comment_likes')->whereIn('comment_id', $deletedIds)->delete();
            DB::table('comment_reports')->whereIn('comment_id', $deletedIds)->delete();
            DB::table('comment_mentions')->whereIn('comment_id', $deletedIds)->delete();
            DB::table('comments')->whereIn('id', $deletedIds)->delete();
        });

        $freshCommentCount = (int) DB::table('comments')
            ->where('post_id', $postId)
            ->where('moderation_status', 'safe')
            ->count();

        NotificationService::sendSystemModerationNotice(
            $authorId,
            'comment',
            $content,
            'đã bị quản trị viên xóa vĩnh viễn',
            'Bình luận vi phạm tiêu chuẩn cộng đồng.',
            "/post/{$postId}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa bình luận và toàn bộ phản hồi liên quan thành công.',
            'deleted_id' => $cid,
            'deleted_ids' => $deletedIds,
            'comment_count' => $freshCommentCount,
            'post_id' => $postId,
        ]);
    }

    // =========================================================================
    // MODULE 3 & 4: BÁO CÁO BÀI VIẾT, BÁO CÁO BÌNH LUẬN & KHIẾU NẠI (APPEALS)
    // =========================================================================

    public function userReports(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $status = trim((string) $request->query('status', ''));
        $reason = trim((string) $request->query('reason', ''));
        $q = trim((string) $request->query('q', ''));

        $where = [];
        $bindings = [];
        if ($status !== '' && in_array($status, ['pending', 'reviewed', 'dismissed'], true)) {
            $where[] = 'pr.status = ?';
            $bindings[] = $status;
        }
        if ($reason !== '') {
            $where[] = 'pr.reason = ?';
            $bindings[] = $reason;
        }
        if ($q !== '') {
            $where[] = '(p.title LIKE ? OR rep.full_name LIKE ? OR auth.full_name LIKE ? OR pr.details LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bindings, $like, $like, $like, $like);
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) (DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM post_reports pr
            JOIN posts p ON p.id = pr.post_id
            JOIN users rep ON rep.id = pr.user_id
            JOIN users auth ON auth.id = p.user_id
            {$whereSql}
        ", $bindings)->cnt ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows = array_map(static fn($r) => (array) $r, DB::select("
            SELECT pr.id, pr.post_id, pr.user_id AS reporter_id, pr.reason, pr.details, pr.status,
                   pr.admin_note, pr.reviewed_at, pr.created_at,
                   p.title AS post_title, p.moderation_status AS post_status,
                   rep.full_name AS reporter_name, rep.username AS reporter_username,
                   auth.full_name AS author_name, auth.username AS author_username
            FROM post_reports pr
            JOIN posts p ON p.id = pr.post_id
            JOIN users rep ON rep.id = pr.user_id
            JOIN users auth ON auth.id = p.user_id
            {$whereSql}
            ORDER BY (pr.status = 'pending') DESC, pr.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ", $bindings));

        return response()->json(UnicodeHelper::toNfc([
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]));
    }

    public function updateUserReportStatus(Request $request, string $id): JsonResponse
    {
        $status = (string) $request->input('status', '');
        $adminNote = trim((string) $request->input('admin_note', '')) ?: null;
        $postAction = (string) $request->input('post_action', 'none');
        $adminId = (int) Auth::id();

        if (!in_array($status, ['pending', 'reviewed', 'dismissed'], true)) {
            return response()->json(['error' => 'Trạng thái báo cáo không hợp lệ.'], 422);
        }

        $report = PostReport::with('post')->find((int) $id);
        if (!$report || !$report->post) {
            return response()->json(['error' => 'Không tìm thấy báo cáo bài viết.'], 404);
        }

        $report->status = $status;
        $report->reviewed_by = $adminId;
        $report->reviewed_at = now();
        $report->admin_note = $adminNote;
        $report->save();

        $post = $report->post;
        if ($status === 'reviewed') {
            if ($postAction === 'hide') {
                $post->moderation_status = 'blocked';
                $post->moderated_by = $adminId;
                $post->moderated_at = now();
                $post->save();
                NotificationService::sendSystemModerationNotice(
                    (int) $post->user_id,
                    'post',
                    (string) $post->title,
                    'đã bị khóa do vi phạm tiêu chuẩn cộng đồng sau khi kiểm duyệt báo cáo',
                    $adminNote ?: 'Nội dung không phù hợp với tiêu chuẩn cộng đồng.',
                    "/post/{$post->id}"
                );
            } elseif ($postAction === 'delete') {
                $authorId = (int) $post->user_id;
                $title = (string) $post->title;
                $post->delete();
                NotificationService::sendSystemModerationNotice(
                    $authorId,
                    'post',
                    $title,
                    'đã bị xóa vĩnh viễn do vi phạm nghiêm trọng tiêu chuẩn cộng đồng',
                    $adminNote ?: 'Nội dung bị xóa theo quyết định của ban quản trị.',
                    '/'
                );
            } else {
                NotificationService::sendSystemModerationNotice(
                    (int) $post->user_id,
                    'post',
                    (string) $post->title,
                    'đã được quản trị viên xem xét và xử lý theo báo cáo vi phạm',
                    $adminNote ?: 'Vui lòng lưu ý tuân thủ tiêu chuẩn cộng đồng.',
                    "/post/{$post->id}"
                );
            }
        }

        return response()->json(['message' => 'Đã cập nhật trạng thái báo cáo bài viết thành công.', 'status' => $status]);
    }

    public function commentReports(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $status = trim((string) $request->query('status', ''));
        $reason = trim((string) $request->query('reason', ''));
        $q = trim((string) $request->query('q', ''));

        $where = [];
        $bindings = [];
        if ($status !== '' && in_array($status, ['pending', 'reviewed', 'rejected'], true)) {
            $where[] = 'cr.status = ?';
            $bindings[] = $status;
        }
        if ($reason !== '') {
            $where[] = 'cr.reason = ?';
            $bindings[] = $reason;
        }
        if ($q !== '') {
            $where[] = '(c.content LIKE ? OR rep.full_name LIKE ? OR auth.full_name LIKE ? OR cr.details LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bindings, $like, $like, $like, $like);
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) (DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM comment_reports cr
            JOIN comments c ON c.id = cr.comment_id
            JOIN users rep ON rep.id = cr.user_id
            JOIN users auth ON auth.id = c.user_id
            {$whereSql}
        ", $bindings)->cnt ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows = array_map(static fn($r) => (array) $r, DB::select("
            SELECT cr.id, cr.comment_id, cr.user_id AS reporter_id, cr.reason, cr.details, cr.status,
                   cr.admin_note, cr.reviewed_at, cr.created_at,
                   c.content AS comment_content, c.post_id, c.moderation_status AS comment_status,
                   p.title AS post_title,
                   rep.full_name AS reporter_name, rep.username AS reporter_username,
                   auth.full_name AS author_name, auth.username AS author_username
            FROM comment_reports cr
            JOIN comments c ON c.id = cr.comment_id
            JOIN posts p ON p.id = c.post_id
            JOIN users rep ON rep.id = cr.user_id
            JOIN users auth ON auth.id = c.user_id
            {$whereSql}
            ORDER BY (cr.status = 'pending') DESC, cr.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ", $bindings));

        return response()->json(UnicodeHelper::toNfc([
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]));
    }

    public function updateCommentReportStatus(Request $request, string $id): JsonResponse
    {
        $status = (string) $request->input('status', '');
        $note = trim((string) $request->input('admin_note', '')) ?: null;
        $commentAction = (string) $request->input('comment_action', 'none');
        $adminId = (int) Auth::id();

        if (!in_array($status, ['pending', 'reviewed', 'rejected'], true)) {
            return response()->json(['error' => 'Trạng thái báo cáo không hợp lệ.'], 422);
        }

        $report = CommentReport::with('comment')->find((int) $id);
        if (!$report || !$report->comment) {
            return response()->json(['error' => 'Không tìm thấy báo cáo bình luận.'], 404);
        }

        $report->status = $status;
        $report->reviewed_by = $adminId;
        $report->reviewed_at = now();
        $report->admin_note = $note;
        $report->save();

        $comment = $report->comment;
        if ($status === 'reviewed') {
            if ($commentAction === 'hide') {
                $comment->moderation_status = 'blocked';
                $comment->save();
                NotificationService::sendSystemModerationNotice(
                    (int) $comment->user_id,
                    'comment',
                    (string) $comment->content,
                    'đã bị ẩn do vi phạm tiêu chuẩn cộng đồng sau khi ban quản trị kiểm duyệt',
                    $note ?: 'Bình luận vi phạm tiêu chuẩn cộng đồng.',
                    "/post/{$comment->post_id}"
                );
            } elseif ($commentAction === 'delete') {
                $authorId = (int) $comment->user_id;
                $postId = (int) $comment->post_id;
                $content = (string) $comment->content;
                $comment->delete();
                NotificationService::sendSystemModerationNotice(
                    $authorId,
                    'comment',
                    $content,
                    'đã bị xóa vĩnh viễn do vi phạm nghiêm trọng',
                    $note ?: 'Bình luận bị xóa theo quyết định của ban quản trị.',
                    "/post/{$postId}"
                );
            } else {
                NotificationService::sendSystemModerationNotice(
                    (int) $comment->user_id,
                    'comment',
                    (string) $comment->content,
                    'đã được quản trị viên xử lý theo báo cáo vi phạm',
                    $note ?: 'Vui lòng lưu ý ngôn từ và chuẩn mực cộng đồng.',
                    "/post/{$comment->post_id}"
                );
            }
        }

        return response()->json(['message' => 'Đã cập nhật trạng thái báo cáo bình luận thành công.', 'status' => $status]);
    }

    public function appeals(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $status = trim((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));

        $where = [];
        $bindings = [];
        if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $where[] = 'pa.status = ?';
            $bindings[] = $status;
        }
        if ($q !== '') {
            $where[] = '(p.title LIKE ? OR u.full_name LIKE ? OR pa.reason LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bindings, $like, $like, $like);
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) (DB::selectOne("SELECT COUNT(*) AS cnt FROM post_appeals pa JOIN posts p ON p.id = pa.post_id JOIN users u ON u.id = pa.user_id {$whereSql}", $bindings)->cnt ?? 0);
        $offset = ($page - 1) * $perPage;
        $rows = array_map(static fn($r) => (array) $r, DB::select("
            SELECT pa.id, pa.post_id, pa.user_id, pa.reason, pa.status, pa.admin_response, pa.reviewed_at, pa.created_at,
                   p.title AS post_title, p.moderation_status AS post_status,
                   u.full_name AS author_name, u.username AS author_username
            FROM post_appeals pa
            JOIN posts p ON p.id = pa.post_id
            JOIN users u ON u.id = pa.user_id
            {$whereSql}
            ORDER BY (pa.status = 'pending') DESC, pa.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ", $bindings));

        return response()->json(UnicodeHelper::toNfc([
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]));
    }

    public function resolveAppeal(Request $request, string $id): JsonResponse
    {
        $action = (string) $request->input('action', '');
        $adminResponse = trim((string) $request->input('admin_response', '')) ?: null;
        $adminId = (int) Auth::id();

        if (!in_array($action, ['approved', 'rejected'], true)) {
            return response()->json(['error' => 'Hành động không hợp lệ. Chọn "approved" hoặc "rejected".'], 422);
        }

        $appeal = PostAppeal::with('post')->find((int) $id);
        if (!$appeal || !$appeal->post) {
            return response()->json(['error' => 'Không tìm thấy yêu cầu khiếu nại.'], 404);
        }

        $appeal->status = $action;
        $appeal->admin_response = $adminResponse;
        $appeal->reviewed_by = $adminId;
        $appeal->reviewed_at = now();
        $appeal->save();

        if ($action === 'approved') {
            $appeal->post->moderation_status = 'safe';
            $appeal->post->moderated_by = $adminId;
            $appeal->post->moderated_at = now();
            $appeal->post->save();
            NotificationService::sendSystemModerationNotice(
                (int) $appeal->user_id,
                'post',
                (string) $appeal->post->title,
                'đã được mở khóa sau khi khiếu nại được chấp nhận',
                $adminResponse ?: 'Bài viết đã hiển thị lại bình thường.',
                "/post/{$appeal->post_id}"
            );
        } else {
            NotificationService::sendSystemModerationNotice(
                (int) $appeal->user_id,
                'post',
                (string) $appeal->post->title,
                'có khiếu nại bị từ chối và vẫn giữ nguyên trạng thái tạm khóa',
                $adminResponse ?: 'Nội dung vẫn chưa đáp ứng tiêu chuẩn cộng đồng.',
                "/post/{$appeal->post_id}"
            );
        }

        return response()->json([
            'message' => $action === 'approved'
                ? 'Đã chấp nhận khiếu nại. Bài viết đã được khôi phục về trạng thái an toàn.'
                : 'Đã từ chối khiếu nại bài viết.',
            'action' => $action,
        ]);
    }

    // =========================================================================
    // MODULE 5: ĐỀ XUẤT TIN TỨC (TICKET & AI)
    // =========================================================================

    public function newsSuggestions(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $source = trim((string) $request->query('source', 'all'));
        $status = trim((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));

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
            return response()->json(['data' => [], 'pagination' => ['page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1]]);
        }

        $unionSql = implode(' UNION ALL ', $queries);
        $where = [];
        $bindings = [];
        if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $where[] = 'sub.status = ?';
            $bindings[] = $status;
        }
        if ($q !== '') {
            $where[] = '(sub.post_title LIKE ? OR sub.author_name LIKE ? OR sub.author_username LIKE ? OR sub.suggestion_reason LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bindings, $like, $like, $like, $like);
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) (DB::selectOne("SELECT COUNT(*) AS cnt FROM ({$unionSql}) sub {$whereSql}", $bindings)->cnt ?? 0);
        $offset = ($page - 1) * $perPage;
        $items = array_map(static fn($r) => (array) $r, DB::select("
            SELECT sub.* FROM ({$unionSql}) sub {$whereSql}
            ORDER BY (sub.status = 'pending') DESC, sub.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ", $bindings));

        foreach ($items as &$item) {
            $item['raw_id'] = (int) $item['raw_id'];
            $item['post_id'] = (int) $item['post_id'];
            $item['user_id'] = (int) $item['user_id'];
            $item['author_reputation'] = (int) $item['author_reputation'];
        }
        unset($item);

        return response()->json(UnicodeHelper::toNfc([
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]));
    }

    public function reviewNewsSuggestion(Request $request, string $id): JsonResponse
    {
        $source = (string) $request->input('source', '');
        $action = (string) ($request->input('action') ?? $request->input('status') ?? '');
        $adminNote = trim((string) $request->input('admin_note', ''));
        $adminId = (int) Auth::id();

        if (!in_array($action, ['approved', 'rejected'], true)) {
            return response()->json(['error' => 'Hành động duyệt không hợp lệ. Chọn "approved" hoặc "rejected".'], 422);
        }

        if (str_starts_with($id, 'ticket_')) {
            $source = 'ticket';
            $rawId = (int) substr($id, 7);
        } elseif (str_starts_with($id, 'ai_')) {
            $source = 'ai';
            $rawId = (int) substr($id, 3);
        } else {
            $rawId = (int) $id;
        }

        if ($source === 'ticket') {
            return $this->resolveTicketRecord($rawId, $action, $adminNote !== '' ? $adminNote : null);
        }

        if ($source === 'ai') {
            $post = Post::find($rawId);
            if (!$post) {
                return response()->json(['error' => 'Không tìm thấy bài viết.'], 404);
            }

            if ($action === 'approved') {
                $post->post_type = 'news';
                $post->ai_status = 'approved';
                $post->moderated_by = $adminId;
                $post->moderated_at = now();
                $post->save();

                NotificationService::send(
                    (int) $post->user_id,
                    null,
                    'ticket_approved',
                    'Chúc mừng! Bài viết của bạn đã được quản trị viên duyệt nâng cấp thành Tin tức nổi bật.',
                    "/post/{$post->id}"
                );

                return response()->json(['message' => 'Đã duyệt bài viết do AI gợi ý thành Tin tức thành công.', 'action' => 'approved']);
            }

            $post->ai_status = 'rejected';
            $post->moderated_by = $adminId;
            $post->moderated_at = now();
            $post->save();

            return response()->json(['message' => 'Đã từ chối gợi ý tin tức từ AI.', 'action' => 'rejected']);
        }

        return response()->json(['error' => 'Nguồn đề xuất không xác định.'], 422);
    }

    public function tickets(): JsonResponse
    {
        $rows = array_map(static fn($r) => (array) $r, DB::select("
            SELECT t.id, t.user_id, t.post_id, t.ticket_type, t.author_reputation_snapshot, t.status, t.admin_note, t.created_at,
                   p.title AS post_title, p.post_type, u.full_name AS author_name, u.username AS author_username
            FROM tickets t
            JOIN posts p ON p.id = t.post_id
            JOIN users u ON u.id = t.user_id
            ORDER BY (t.status = 'pending') DESC, t.created_at DESC
            LIMIT 200
        "));

        return response()->json(['data' => UnicodeHelper::toNfc($rows)]);
    }

    public function reviewTicket(Request $request, string $id): JsonResponse
    {
        $action = (string) ($request->input('action') ?? $request->input('status') ?? '');
        $adminNote = trim((string) ($request->input('admin_note') ?? $request->input('note') ?? '')) ?: null;

        if (!in_array($action, ['approved', 'rejected'], true)) {
            return response()->json(['error' => 'Hành động duyệt không hợp lệ.'], 422);
        }

        return $this->resolveTicketRecord((int) $id, $action, $adminNote);
    }

    private function resolveTicketRecord(int $ticketId, string $action, ?string $adminNote): JsonResponse
    {
        $ticket = Ticket::with('post')->find($ticketId);
        if (!$ticket || !$ticket->post) {
            return response()->json(['error' => 'Không tìm thấy ticket.'], 404);
        }

        $ticket->status = $action;
        $ticket->admin_note = $adminNote;
        $ticket->save();

        if ($action === 'approved') {
            $ticket->post->post_type = 'news';
            $ticket->post->save();
            NotificationService::send(
                (int) $ticket->user_id,
                null,
                'ticket_approved',
                "Chúc mừng! Ticket nâng cấp bài \"{$ticket->post->title}\" thành Tin tức đã được duyệt.",
                "/post/{$ticket->post_id}"
            );
        } else {
            NotificationService::send(
                (int) $ticket->user_id,
                null,
                'system',
                "Ticket nâng cấp bài \"{$ticket->post->title}\" đã bị từ chối." . ($adminNote ? " Ghi chú: {$adminNote}" : ''),
                "/post/{$ticket->post_id}"
            );
        }

        return response()->json([
            'message' => $action === 'approved' ? 'Đã duyệt bài viết thành Tin tức.' : 'Đã từ chối ticket.',
            'action' => $action,
        ]);
    }

    // =========================================================================
    // MODULE 6: KIỂM DUYỆT NỘI DUNG AI
    // =========================================================================

    public function aiModeration(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $severity = trim((string) $request->query('ai_severity', ''));
        $violationType = trim((string) $request->query('ai_violation_type', ''));
        $aiStatus = trim((string) $request->query('ai_status', ''));
        $modStatus = trim((string) $request->query('moderation_status', ''));
        $q = trim((string) $request->query('q', ''));

        $where = ["(aim.id IS NOT NULL OR (p.moderation_status IN ('flagged', 'blocked') AND p.ai_severity IS NOT NULL))"];
        $bindings = [];

        if (in_array($severity, ['low', 'medium', 'high'], true)) {
            $where[] = 'COALESCE(aim.severity, p.ai_severity) = ?';
            $bindings[] = $severity;
        }
        if ($violationType !== '') {
            $where[] = 'COALESCE(aim.violation_type, p.ai_violation_type) = ?';
            $bindings[] = $violationType;
        }
        if (in_array($aiStatus, ['pending', 'approved', 'rejected', 'processed', 'dismissed'], true)) {
            $where[] = "COALESCE(aim.status, p.ai_status, 'pending') = ?";
            $bindings[] = $aiStatus;
        }
        if (in_array($modStatus, ['safe', 'flagged', 'blocked', 'pending'], true)) {
            $where[] = 'p.moderation_status = ?';
            $bindings[] = $modStatus;
        }
        if ($q !== '') {
            $where[] = '(p.title LIKE ? OR u.full_name LIKE ? OR u.username LIKE ? OR COALESCE(aim.ai_reason, p.ai_reason) LIKE ? OR p.content LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bindings, $like, $like, $like, $like, $like);
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $total = (int) (DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM posts p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN ai_moderation_items aim ON aim.item_id = p.id AND aim.item_type = 'post'
            {$whereSql}
        ", $bindings)->cnt ?? 0);

        $offset = ($page - 1) * $perPage;
        $items = array_map(static fn($r) => (array) $r, DB::select("
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
                CASE WHEN COALESCE(aim.status, p.ai_status, 'pending') = 'pending' THEN 0 ELSE 1 END ASC,
                CASE
                    WHEN p.moderation_status = 'blocked' THEN 1
                    WHEN p.moderation_status = 'flagged' THEN 2
                    WHEN COALESCE(aim.severity, p.ai_severity) = 'high' THEN 3
                    WHEN COALESCE(aim.severity, p.ai_severity) = 'medium' THEN 4
                    ELSE 5
                END ASC,
                p.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ", $bindings));

        foreach ($items as &$item) {
            $item['id'] = (int) $item['id'];
            $item['user_id'] = (int) $item['user_id'];
            $item['suggested_action_text'] = match ($item['ai_severity']) {
                'high' => 'Khóa bài viết ngay',
                'medium' => 'Tạm ẩn chờ kiểm tra',
                default => 'Cảnh báo nhẹ / Xem xét gỡ cờ',
            };
        }
        unset($item);

        return response()->json(UnicodeHelper::toNfc([
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]));
    }

    public function aiModerationDecision(Request $request, string $id): JsonResponse
    {
        $decision = (string) $request->input('decision', 'safe');
        $adminNote = trim((string) $request->input('admin_note', ''));
        $adminId = (int) Auth::id();

        if (!in_array($decision, ['safe', 'flagged', 'blocked', 'delete'], true)) {
            return response()->json(['error' => 'Quyết định kiểm duyệt không hợp lệ.'], 422);
        }

        $post = Post::find((int) $id);
        if (!$post) {
            return response()->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }

        $authorId = (int) $post->user_id;
        $title = (string) $post->title;

        if ($decision === 'delete') {
            AiModerationItem::where('item_type', 'post')->where('item_id', $post->id)->update([
                'status' => 'dismissed',
                'admin_note' => $adminNote,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'final_decision' => 'delete',
                'final_decision_at' => now(),
            ]);
            $post->delete();
            NotificationService::sendSystemModerationNotice(
                $authorId,
                'post',
                $title,
                'đã bị quản trị viên xóa vĩnh viễn sau quá trình rà soát kiểm duyệt AI',
                $adminNote ?: 'Nội dung vi phạm nghiêm trọng tiêu chuẩn cộng đồng.',
                '/'
            );
            return response()->json(['message' => 'Đã xóa bài viết khỏi hệ thống.', 'decision' => 'delete']);
        }

        $post->moderation_status = $decision;
        $post->ai_status = 'approved';
        $post->moderated_by = $adminId;
        $post->moderated_at = now();
        $post->final_decision = $decision;
        $post->save();

        $aimStatus = match ($decision) {
            'safe' => 'approved',
            'blocked' => 'rejected',
            default => 'processed',
        };
        AiModerationItem::where('item_type', 'post')->where('item_id', $post->id)->update([
            'status' => $aimStatus,
            'admin_note' => $adminNote,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'final_decision' => $decision,
            'final_decision_at' => now(),
        ]);

        if (in_array($decision, ['blocked', 'flagged'], true)) {
            $actionText = $decision === 'blocked' ? 'đã bị khóa do vi phạm tiêu chuẩn cộng đồng' : 'đã bị gắn cờ cảnh báo nội dung';
            NotificationService::sendSystemModerationNotice(
                $authorId,
                'post',
                $title,
                $actionText,
                $adminNote ?: 'Quyết định sau khi xem xét báo cáo kiểm duyệt AI.',
                "/post/{$post->id}"
            );
        } elseif ($decision === 'safe') {
            NotificationService::sendSystemModerationNotice(
                $authorId,
                'post',
                $title,
                'đã được quản trị viên duyệt an toàn và hiển thị bình thường',
                $adminNote ?: 'Nội dung đã được xác nhận phù hợp với tiêu chuẩn cộng đồng.',
                "/post/{$post->id}"
            );
        }

        return response()->json([
            'message' => "Đã cập nhật trạng thái kiểm duyệt thành: {$decision}",
            'moderation_status' => $decision,
            'ai_status' => 'approved',
        ]);
    }
}
