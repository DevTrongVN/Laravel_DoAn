<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Dịch vụ tính toán điểm uy tín & kiểm tra ngưỡng đề xuất Tin tức.
 */
final class ReputationService
{
    private array $weights;
    private int $ticketThreshold;

    public function __construct()
    {
        $this->weights = (array) config('gocnho.reputation', []);
        $this->ticketThreshold = (int) ($this->weights['ticket_threshold'] ?? 100);
    }

    public function calculate(int $userId): int
    {
        $wLike = (int) ($this->weights['W_like'] ?? 2);
        $wShare = (int) ($this->weights['W_share'] ?? 5);
        $wComment = (int) ($this->weights['W_comment'] ?? 0);
        $wFollow = (int) ($this->weights['W_follow'] ?? 10);

        $totalLikes = (int) DB::table('post_reactions as i')
            ->join('posts as p', 'p.id', '=', 'i.post_id')
            ->where('p.user_id', $userId)
            ->whereColumn('i.user_id', '<>', 'p.user_id')
            ->count();

        $shareRow = DB::selectOne("
            SELECT COUNT(DISTINCT CONCAT(s.post_id, ':', s.user_id)) AS cnt
            FROM (
                SELECT user_id, post_id FROM interactions WHERE type = 'share'
                UNION ALL
                SELECT user_id, shared_post_id AS post_id FROM posts WHERE shared_post_id IS NOT NULL AND moderation_status = 'safe'
            ) s
            JOIN posts p ON p.id = s.post_id
            WHERE p.user_id = ? AND s.user_id <> p.user_id
        ", [$userId]);
        $totalShares = (int) ($shareRow->cnt ?? 0);

        $totalComments = (int) DB::table('comments as c')
            ->join('posts as p', 'p.id', '=', 'c.post_id')
            ->where('p.user_id', $userId)
            ->where('c.moderation_status', 'safe')
            ->whereColumn('c.user_id', '<>', 'p.user_id')
            ->count();

        $totalFollowers = (int) DB::table('follows')
            ->where('following_id', $userId)
            ->whereColumn('follower_id', '<>', 'following_id')
            ->count();

        $score = ($totalLikes * $wLike) + ($totalShares * $wShare) + ($totalComments * $wComment) + ($totalFollowers * $wFollow);

        User::where('id', $userId)->update(['reputation_score' => $score]);

        return $score;
    }

    public function canSubmitTicket(int $userId): bool
    {
        return $this->calculate($userId) >= $this->ticketThreshold;
    }

    public function getThreshold(): int
    {
        return $this->ticketThreshold;
    }
}
