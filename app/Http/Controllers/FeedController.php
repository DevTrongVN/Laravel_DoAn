<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PostQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Controller hiển thị các trang Web chính (Trang chủ, Tin tức, Đang theo dõi, Tài khoản, Dashboard).
 */
class FeedController extends Controller
{
    public function __construct(
        private readonly PostQueryService $postQuery
    ) {}

    public function home(): View
    {
        return view('home', [
            'pageTitle' => 'Góc Nhỏ — Blog & Tản văn',
        ]);
    }

    public function news(Request $request): View
    {
        $viewerId = Auth::id();
        $page = max(1, (int) $request->query('page', 1));
        $result = $this->postQuery->list(['type' => 'news'], $viewerId, $page, 10);

        return view('feed.news', array_merge([
            'posts' => $result['data'],
            'pagination' => $result,
            'pageTitle' => 'Tin tức — Góc Nhỏ',
            'activeTab' => 'news',
        ], $this->sidebarData($viewerId)));
    }

    public function following(Request $request): View
    {
        $viewerId = Auth::id();
        $page = max(1, (int) $request->query('page', 1));
        $result = $viewerId
            ? $this->postQuery->list(['following' => 1], $viewerId, $page, 10)
            : ['data' => [], 'total' => 0, 'page' => 1, 'last_page' => 1];

        return view('feed.following', array_merge([
            'posts' => $result['data'],
            'pagination' => $result,
            'pageTitle' => 'Đang theo dõi — Góc Nhỏ',
            'activeTab' => 'following',
        ], $this->sidebarData($viewerId)));
    }

    public function account(): View
    {
        return view('account.index', [
            'pageTitle' => 'Tài khoản — Góc Nhỏ',
        ]);
    }

    public function accounts(): View
    {
        return view('account.devices', [
            'pageTitle' => 'Quản lý tài khoản trên thiết bị — Góc Nhỏ',
        ]);
    }

    public function myDashboard(): View
    {
        return view('dashboard.index', [
            'pageTitle' => 'Thống kê cá nhân — Góc Nhỏ',
        ]);
    }

    public function sidebarData(?int $viewerId): array
    {
        $topNews = $this->postQuery->topNews(15);
        $topHashtags = $this->postQuery->topHashtags(12);
        $followingChannels = [];
        if ($viewerId !== null) {
            $followingChannels = array_map(static fn($r) => (array) $r, DB::select("
                SELECT u.id, u.username, u.full_name, u.avatar, u.is_verified, u.reputation_score, f.visit_count
                FROM follows f
                JOIN users u ON u.id = f.following_id
                WHERE f.follower_id = ?
                ORDER BY f.visit_count DESC, f.created_at DESC
                LIMIT 15
            ", [$viewerId]));
        }

        $aboutAuthor = Auth::check()
            ? Auth::user()->toPublicArray()
            : (User::where('role', 'admin')->orderByDesc('reputation_score')->orderBy('id')->first()?->toPublicArray());

        return [
            'topNews' => $topNews,
            'topHashtags' => $topHashtags,
            'followingChannels' => $followingChannels,
            'aboutAuthor' => $aboutAuthor,
        ];
    }
}
