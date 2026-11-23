<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reaction;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PostQueryService;
use App\Services\ReputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * API Controller: Thả / Đổi / Hủy cảm xúc 5 trạng thái trên bài viết.
 */
class ApiReactionController extends Controller
{
    public function __construct(
        private readonly PostQueryService $postQuery,
        private readonly ReputationService $reputationService
    ) {}

    public function set(Request $request, string $postId): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Đăng nhập để thả cảm xúc.'], 401);
        }

        $numericPostId = (int) $postId;
        $post = $this->postQuery->find($numericPostId, $userId);
        if (!$post) {
            return response()->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }

        $reactionInput = $request->has('reaction')
            ? ($request->input('reaction') === null || $request->input('reaction') === '' ? null : (string) $request->input('reaction'))
            : 'like';

        if ($reactionInput !== null && !in_array($reactionInput, Reaction::VALID_REACTIONS, true)) {
            return response()->json(['error' => 'Loại cảm xúc không hợp lệ.'], 422);
        }

        $existing = DB::table('post_reactions')
            ->where('user_id', $userId)
            ->where('post_id', $numericPostId)
            ->value('reaction');

        $active = null;
        if ($reactionInput === null || $existing === $reactionInput) {
            DB::delete('DELETE FROM post_reactions WHERE user_id = ? AND post_id = ?', [$userId, $numericPostId]);
            DB::delete("DELETE FROM interactions WHERE user_id = ? AND post_id = ? AND type = 'like'", [$userId, $numericPostId]);
            $active = null;
        } else {
            DB::insert(
                'INSERT INTO post_reactions (user_id, post_id, reaction, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE reaction = VALUES(reaction), updated_at = NOW()',
                [$userId, $numericPostId, $reactionInput]
            );
            DB::insert("INSERT IGNORE INTO interactions (user_id, post_id, type, created_at) VALUES (?, ?, 'like', NOW())", [$userId, $numericPostId]);
            $active = $reactionInput;
        }

        $this->reputationService->calculate((int) $post['user_id']);

        if ($active !== null && (int) $post['user_id'] !== $userId) {
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
                (int) $post['user_id'],
                $userId,
                'reaction',
                "{$actorName} đã thả cảm xúc {$emoji} vào bài viết \"{$post['title']}\"",
                "/post/{$numericPostId}"
            );
        }

        $counts = $this->postQuery->getReactionCounts($numericPostId);
        $totalLikes = array_sum($counts);

        return response()->json([
            'success' => true,
            'my_reaction' => $active,
            'reaction_counts' => $counts,
            'likes_count' => $totalLikes,
            'is_liked' => !empty($active),
        ]);
    }
}
