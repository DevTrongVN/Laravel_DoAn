<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\ReputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * API Controller: Theo dõi / Bỏ theo dõi người dùng & Danh sách đang theo dõi / Tác giả nổi bật.
 */
class ApiFollowController extends Controller
{
    public function __construct(
        private readonly ReputationService $reputationService
    ) {}

    public function toggle(string $id): JsonResponse
    {
        $followerId = Auth::id();
        if ($followerId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }

        $followingId = (int) $id;
        if ($followerId === $followingId) {
            return response()->json(['error' => 'Bạn không thể tự theo dõi chính mình.'], 422);
        }

        $exists = DB::table('follows')
            ->where('follower_id', $followerId)
            ->where('following_id', $followingId)
            ->exists();

        if ($exists) {
            DB::delete('DELETE FROM follows WHERE follower_id = ? AND following_id = ?', [$followerId, $followingId]);
            $isFollowing = false;
        } else {
            DB::insert('INSERT INTO follows (follower_id, following_id, visit_count, created_at) VALUES (?, ?, 0, NOW())', [$followerId, $followingId]);
            $isFollowing = true;
        }

        $this->reputationService->calculate($followingId);

        if ($isFollowing) {
            $actor = User::find($followerId);
            $actorName = $actor?->full_name ?? 'Ai đó';
            NotificationService::send(
                $followingId,
                $followerId,
                'follow',
                "{$actorName} đã bắt đầu theo dõi bạn",
                "/profile/{$followerId}"
            );
        }

        $followersCount = (int) DB::table('follows')->where('following_id', $followingId)->count();

        return response()->json([
            'is_following' => $isFollowing,
            'followers_count' => $followersCount,
        ]);
    }

    public function followingList(): JsonResponse
    {
        $followerId = Auth::id();
        if ($followerId === null) {
            $topAuthors = User::select('id', 'username', 'full_name', 'avatar', 'is_verified', 'reputation_score')
                ->whereIn('role', ['user', 'admin'])
                ->orderByDesc('reputation_score')
                ->orderBy('id')
                ->limit(5)
                ->get()
                ->toArray();

            return response()->json([
                'is_guest' => true,
                'title' => 'Tác giả nổi bật',
                'data' => $topAuthors,
            ]);
        }

        $channels = array_map(static fn($r) => (array) $r, DB::select("
            SELECT u.id, u.username, u.full_name, u.avatar, u.is_verified, COALESCE(u.reputation_score, 0) AS reputation_score, f.visit_count
            FROM follows f
            JOIN users u ON u.id = f.following_id
            WHERE f.follower_id = ?
            ORDER BY f.visit_count DESC, f.created_at DESC
            LIMIT 15
        ", [$followerId]));

        return response()->json([
            'is_guest' => false,
            'title' => 'Những người mình theo dõi',
            'data' => $channels,
        ]);
    }
}
