<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiModerationItem;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use App\Services\AIService;
use App\Services\MentionService;
use App\Services\NotificationService;
use App\Services\PostQueryService;
use App\Services\ReputationService;
use App\Support\UnicodeHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * API Controller: Bình luận đa cấp, AI kiểm duyệt bình luận, Cảm xúc bình luận, Báo cáo bình luận.
 */
class ApiCommentController extends Controller
{
    public function __construct(
        private readonly PostQueryService $postQuery,
        private readonly AIService $aiService,
        private readonly ReputationService $reputationService,
        private readonly MentionService $mentionService
    ) {}

    public function searchMentionUsers(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $limit = max(1, min(15, (int) $request->input('limit', 8)));
        $users = $this->mentionService->searchUsers($q, $limit, Auth::id());

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function index(string $postId): JsonResponse
    {
        $id = (int) $postId;
        $userId = Auth::id();
        $post = $this->postQuery->find($id, $userId);
        if (!$post) {
            return response()->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }

        $comments = $post['comments'] ?? [];

        return response()->json([
            'success' => true,
            'data' => $comments,
            'count' => count($comments),
        ]);
    }

    public function create(Request $request, string $postId): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập để bình luận.'], 401);
        }

        $id = (int) $postId;
        $post = $this->postQuery->find($id, $userId);
        if (!$post) {
            return response()->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }

        $rawContent = (string) UnicodeHelper::toNfc(trim((string) $request->input('content', '')));
        if ($rawContent === '' || mb_strlen($rawContent) > 5000) {
            return response()->json(['error' => 'Nội dung bình luận bắt buộc và tối đa 5.000 ký tự.'], 422);
        }

        $mentionResult = $this->mentionService->prepareContentAndMentions($rawContent);
        $content = $mentionResult['content'];
        $mentionedUserIds = $mentionResult['mentioned_user_ids'];
        if ($content === '') {
            return response()->json(['error' => 'Nội dung bình luận không hợp lệ.'], 422);
        }

        $parentId = $request->filled('parent_id') ? (int) $request->input('parent_id') : null;
        if ($parentId !== null) {
            $parent = Comment::find($parentId);
            if (!$parent || (int) $parent->post_id !== $id || $parent->moderation_status !== 'safe') {
                return response()->json(['error' => 'Bình luận được trả lời không hợp lệ.'], 422);
            }
        }

        $plainForMod = $this->mentionService->toPlainTextForModeration($content);
        $scan = $this->aiService->moderateContent($plainForMod, 'comment');
        if ($scan['status'] === 'blocked') {
            return response()->json(['error' => 'Bình luận bị từ chối do vi phạm tiêu chuẩn cộng đồng: ' . $scan['ai_reason']], 422);
        }

        $comment = Comment::create([
            'post_id' => $id,
            'user_id' => $userId,
            'parent_id' => $parentId,
            'content' => $content,
            'moderation_status' => $scan['status'],
            'created_at' => now(),
            'ai_severity' => $scan['is_flagged'] ? $scan['severity'] : null,
            'ai_violation_type' => $scan['is_flagged'] ? $scan['violation_type'] : null,
            'ai_reason' => $scan['is_flagged'] ? $scan['ai_reason'] : null,
            'ai_status' => $scan['is_flagged'] ? 'pending' : null,
        ]);

        $this->mentionService->syncCommentMentions((int) $comment->id, $mentionedUserIds);

        if ($scan['is_flagged']) {
            try {
                AiModerationItem::create([
                    'item_type' => 'comment',
                    'item_id' => $comment->id,
                    'post_id' => $id,
                    'user_id' => $userId,
                    'violation_type' => $scan['violation_type'],
                    'severity' => $scan['severity'],
                    'ai_reason' => $scan['ai_reason'],
                    'suggested_action' => $scan['suggested_action'],
                    'status' => 'pending',
                    'created_at' => now(),
                ]);
            } catch (Throwable $e) {
                Log::warning('[ApiCommentController] Lỗi lưu ai_moderation_items: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Bình luận đã được gửi và đang chờ Ban Quản Trị xét duyệt do AI phát hiện nội dung cần rà soát.',
                'is_flagged' => true,
                'moderation_status' => $scan['status'],
                'ai_reason' => $scan['ai_reason'],
                'data' => $this->formatComment($comment),
            ], 201);
        }

        $this->reputationService->calculate((int) $post['user_id']);

        if ((int) $post['user_id'] !== $userId) {
            $actor = User::find($userId);
            $actorName = $actor?->full_name ?? 'Ai đó';
            NotificationService::send(
                (int) $post['user_id'],
                $userId,
                'comment',
                "{$actorName} đã bình luận vào bài viết \"{$post['title']}\"",
                "/post/{$id}"
            );
        }

        $this->mentionService->notifyMentionedUsers((int) $comment->id, $id, (string) ($post['title'] ?? ''), $userId, $mentionedUserIds);

        return response()->json([
            'message' => 'Bình luận thành công.',
            'data' => $this->formatComment($comment),
        ], 201);
    }

    public function update(Request $request, string $commentId): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }

        $rawContent = (string) UnicodeHelper::toNfc(trim((string) $request->input('content', '')));
        if ($rawContent === '' || mb_strlen($rawContent) > 5000) {
            return response()->json(['error' => 'Nội dung bình luận bắt buộc, tối đa 5.000 ký tự.'], 422);
        }

        $comment = Comment::find((int) $commentId);
        if (!$comment) {
            return response()->json(['error' => 'Không tìm thấy bình luận.'], 404);
        }
        if ((int) $comment->user_id !== $userId) {
            return response()->json(['error' => 'Bạn chỉ được sửa bình luận của mình.'], 403);
        }

        $mentionResult = $this->mentionService->prepareContentAndMentions($rawContent);
        $content = $mentionResult['content'];
        $mentionedUserIds = $mentionResult['mentioned_user_ids'];
        if ($content === '') {
            return response()->json(['error' => 'Nội dung bình luận không hợp lệ.'], 422);
        }

        $plainForMod = $this->mentionService->toPlainTextForModeration($content);
        $scan = $this->aiService->moderateContent($plainForMod, 'comment');
        if ($scan['status'] === 'blocked') {
            return response()->json(['error' => 'Nội dung chỉnh sửa bị từ chối do vi phạm tiêu chuẩn cộng đồng: ' . $scan['ai_reason']], 422);
        }

        $comment->content = $content;
        $comment->edited_at = now();
        $comment->moderation_status = $scan['status'];
        if ($scan['is_flagged']) {
            $comment->ai_severity = $scan['severity'];
            $comment->ai_violation_type = $scan['violation_type'];
            $comment->ai_reason = $scan['ai_reason'];
            $comment->ai_status = 'pending';
        }
        $comment->save();

        $this->mentionService->syncCommentMentions((int) $comment->id, $mentionedUserIds);

        if ($scan['is_flagged']) {
            try {
                AiModerationItem::create([
                    'item_type' => 'comment',
                    'item_id' => $comment->id,
                    'post_id' => $comment->post_id,
                    'user_id' => $userId,
                    'violation_type' => $scan['violation_type'],
                    'severity' => $scan['severity'],
                    'ai_reason' => $scan['ai_reason'],
                    'suggested_action' => $scan['suggested_action'],
                    'status' => 'pending',
                    'created_at' => now(),
                ]);
            } catch (Throwable) {}

            return response()->json([
                'message' => 'Bình luận đã sửa nhưng đang chờ Ban Quản Trị rà soát nội dung.',
                'is_flagged' => true,
                'moderation_status' => $scan['status'],
                'data' => $this->formatComment($comment),
            ]);
        }

        return response()->json([
            'message' => 'Đã sửa bình luận.',
            'data' => $this->formatComment($comment),
        ]);
    }

    public function delete(string $commentId): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }

        $comment = Comment::with('post')->find((int) $commentId);
        if (!$comment) {
            return response()->json(['error' => 'Không tìm thấy bình luận.'], 404);
        }

        $isCommentOwner = (int) $comment->user_id === $userId;
        $isPostOwner = $comment->post && (int) $comment->post->user_id === $userId;
        if (!$isCommentOwner && !$isPostOwner && Auth::user()?->role !== 'admin') {
            return response()->json(['error' => 'Chỉ người viết bình luận hoặc tác giả bài viết mới có quyền xóa.'], 403);
        }

        $postId = (int) $comment->post_id;
        $postAuthorId = $comment->post ? (int) $comment->post->user_id : null;
        $cid = (int) $commentId;

        // 1. Thu thập toàn bộ ID của bình luận cha và tất cả nhánh con/cháu/chắt đệ quy
        $deletedIds = Comment::collectDescendantIds($cid);

        // 2. Xóa toàn bộ dữ liệu liên quan trong cùng một Database Transaction
        DB::transaction(function () use ($deletedIds) {
            DB::table('comment_likes')->whereIn('comment_id', $deletedIds)->delete();
            DB::table('comment_reports')->whereIn('comment_id', $deletedIds)->delete();
            DB::table('comment_mentions')->whereIn('comment_id', $deletedIds)->delete();
            DB::table('comments')->whereIn('id', $deletedIds)->delete();
        });

        // 3. Tính toán lại điểm uy tín tác giả bài viết nếu có
        if ($postAuthorId !== null) {
            $this->reputationService->calculate($postAuthorId);
        }

        // 4. Lấy số lượng bình luận an toàn mới nhất của bài viết
        $freshCommentCount = (int) DB::table('comments')
            ->where('post_id', $postId)
            ->where('moderation_status', 'safe')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa bình luận và toàn bộ phản hồi liên quan.',
            'deleted_id' => $cid,
            'deleted_ids' => $deletedIds,
            'comment_count' => $freshCommentCount,
            'post_id' => $postId,
        ]);
    }

    public function like(Request $request, string $commentId): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập để thả cảm xúc.'], 401);
        }

        $cid = (int) $commentId;
        $comment = Comment::find($cid);
        if (!$comment) {
            return response()->json(['error' => 'Không tìm thấy bình luận.'], 404);
        }

        $reactionInput = $request->has('reaction')
            ? ($request->input('reaction') === null || $request->input('reaction') === '' ? null : (string) $request->input('reaction'))
            : 'like';

        if ($reactionInput !== null && !in_array($reactionInput, Reaction::VALID_REACTIONS, true)) {
            return response()->json(['error' => 'Loại cảm xúc không hợp lệ.'], 422);
        }

        $existing = DB::table('comment_likes')
            ->where('comment_id', $cid)
            ->where('user_id', $userId)
            ->value('reaction');

        $active = null;
        if ($reactionInput === null || $existing === $reactionInput) {
            DB::delete('DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?', [$cid, $userId]);
            $active = null;
        } else {
            DB::insert(
                'INSERT INTO comment_likes (comment_id, user_id, reaction, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE reaction = VALUES(reaction)',
                [$cid, $userId, $reactionInput]
            );
            $active = $reactionInput;
        }

        $counts = array_fill_keys(Reaction::VALID_REACTIONS, 0);
        $rows = DB::table('comment_likes')
            ->select('reaction', DB::raw('COUNT(*) AS total'))
            ->where('comment_id', $cid)
            ->groupBy('reaction')
            ->get();
        foreach ($rows as $row) {
            if (isset($counts[$row->reaction])) {
                $counts[$row->reaction] = (int) $row->total;
            }
        }
        $totalLikes = array_sum($counts);

        if ($active !== null && (int) $comment->user_id !== $userId) {
            $actor = User::find($userId);
            $actorName = $actor?->full_name ?? 'Ai đó';
            $emoji = match ($active) {
                'love' => '❤️',
                'haha' => '😄',
                'sad' => '😢',
                'angry' => '😠',
                default => '👍',
            };
            NotificationService::send(
                (int) $comment->user_id,
                $userId,
                'reaction',
                "{$actorName} đã thả cảm xúc {$emoji} vào bình luận của bạn",
                "/post/{$comment->post_id}"
            );
        }

        return response()->json([
            'success' => true,
            'reaction' => $active,
            'my_reaction' => $active,
            'counts' => $counts,
            'reaction_counts' => $counts,
            'total' => $totalLikes,
            'likes_count' => $totalLikes,
            'is_liked' => !empty($active),
        ]);
    }

    public function report(Request $request, string $commentId): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập để báo cáo bình luận.'], 401);
        }

        $cid = (int) $commentId;
        $comment = Comment::find($cid);
        if (!$comment) {
            return response()->json(['error' => 'Không tìm thấy bình luận.'], 404);
        }
        if ((int) $comment->user_id === $userId) {
            return response()->json(['error' => 'Bạn không thể tự báo cáo bình luận của chính mình.'], 422);
        }

        $reason = trim((string) $request->input('reason', ''));
        $details = trim((string) $request->input('details', ''));
        $allowed = ['offensive', 'spam', 'inappropriate', 'misinformation', 'harassment', 'other'];
        if ($reason === '' || !in_array($reason, $allowed, true)) {
            return response()->json(['error' => 'Vui lòng chọn lý do báo cáo bình luận hợp lệ.'], 422);
        }

        $dup = CommentReport::where('comment_id', $cid)->where('user_id', $userId)->where('status', 'pending')->exists();
        if ($dup) {
            return response()->json(['error' => 'Bạn đã báo cáo bình luận này rồi.'], 422);
        }

        CommentReport::create([
            'comment_id' => $cid,
            'user_id' => $userId,
            'reason' => $reason,
            'details' => $details !== '' ? $details : null,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Đã gửi báo cáo bình luận đến quản trị viên.']);
    }

    private function formatComment(Comment $comment): array
    {
        $comment->loadMissing('user');
        $data = [
            'id' => (int) $comment->id,
            'post_id' => (int) $comment->post_id,
            'user_id' => (int) $comment->user_id,
            'parent_id' => $comment->parent_id !== null ? (int) $comment->parent_id : null,
            'content' => $comment->content,
            'moderation_status' => $comment->moderation_status,
            'edited_at' => $comment->edited_at,
            'created_at' => $comment->created_at,
            'likes_count' => 0,
            'my_reaction' => null,
            'user' => $comment->user ? $comment->user->toPublicArray() : null,
        ];
        $hydrated = $this->mentionService->hydrateComments([$data]);
        return UnicodeHelper::toNfc($hydrated[0] ?? $data);
    }
}
