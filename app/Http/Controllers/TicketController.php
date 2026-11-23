<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Ticket;
use App\Services\ReputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Controller gửi Ticket đề xuất nâng cấp bài Blog thành Tin tức nổi bật.
 */
class TicketController extends Controller
{
    public function __construct(
        private readonly ReputationService $reputationService
    ) {}

    public function create(string $postId): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập để gửi ticket.'], 401);
        }

        $id = (int) $postId;
        $post = Post::find($id);
        if (!$post || (int) $post->user_id !== $userId) {
            return response()->json(['error' => 'Chỉ tác giả mới có thể gửi ticket duyệt bài này.'], 403);
        }
        if ($post->post_type === 'news') {
            return response()->json(['error' => 'Bài viết này đã là Tin tức rồi.'], 409);
        }
        if ($post->moderation_status !== 'safe') {
            return response()->json(['error' => 'Bài viết đang trong trạng thái kiểm duyệt, không thể gửi ticket.'], 422);
        }

        $currentScore = $this->reputationService->calculate($userId);
        $threshold = $this->reputationService->getThreshold();

        if ($currentScore < $threshold) {
            return response()->json([
                'error' => "Điểm uy tín của bạn là {$currentScore}, chưa đạt mức tối thiểu ({$threshold} điểm) để gửi ticket duyệt Tin tức.",
                'current_score' => $currentScore,
                'threshold' => $threshold,
            ], 403);
        }

        $exists = Ticket::where('post_id', $id)->where('status', 'pending')->exists();
        if ($exists) {
            return response()->json(['error' => 'Bài viết này đang có một ticket chờ duyệt.'], 409);
        }

        $ticket = Ticket::create([
            'user_id' => $userId,
            'post_id' => $id,
            'ticket_type' => 'upgrade',
            'author_reputation_snapshot' => $currentScore,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Gửi ticket thành công. Quản trị viên sẽ sớm xem xét bài viết của bạn.',
            'ticket_id' => (int) $ticket->id,
            'id' => (int) $ticket->id,
        ], 201);
    }
}
