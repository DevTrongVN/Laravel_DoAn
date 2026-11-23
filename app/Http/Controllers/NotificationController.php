<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use App\Support\UnicodeHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Controller Trung tâm thông báo người dùng (Web & API).
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly FeedController $feedController
    ) {}

    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        $userId = Auth::id();
        $isApiRequest = $request->is('api/*') || $request->expectsJson() || $request->query('api') !== null || $request->query('format') === 'json';

        if ($userId === null) {
            if ($isApiRequest) {
                return response()->json(['error' => 'Vui lòng đăng nhập để xem thông báo.'], 401);
            }
            return redirect('/account?mode=login');
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $offset = ($page - 1) * $perPage;

        $total = (int) Notification::where('user_id', $userId)->count();
        $unreadCount = (int) Notification::where('user_id', $userId)->where('is_read', 0)->count();

        $rows = array_map(static fn($r) => (array) $r, DB::select("
            SELECT n.id, n.user_id, n.actor_id, n.type, n.content, n.target_url, n.is_read, n.created_at,
                   a.full_name AS actor_name, a.username AS actor_username, a.avatar AS actor_avatar
            FROM notifications n
            LEFT JOIN users a ON a.id = n.actor_id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ", [$userId]));

        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['is_read'] = (bool) (int) $r['is_read'];
        }
        unset($r);

        $payload = UnicodeHelper::toNfc([
            'notifications' => $rows,
            'data' => $rows,
            'unread_count' => $unreadCount,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'pageTitle' => 'Trung tâm thông báo — Góc Nhỏ',
            'activeTab' => 'notifications',
        ]);

        if ($isApiRequest) {
            return response()->json($payload);
        }

        return view('notifications.index', array_merge($payload, $this->feedController->sidebarData($userId)));
    }

    public function markRead(string $id): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }

        $notifId = (int) $id;
        if ($notifId > 0) {
            NotificationService::markAsRead($notifId, $userId);
        }

        return response()->json([
            'message' => 'Đã đánh dấu là đã đọc.',
            'unread_count' => (int) Notification::where('user_id', $userId)->where('is_read', 0)->count(),
        ]);
    }

    public function markReadBody(Request $request): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }

        $id = (int) $request->input('id', 0);
        if ($id > 0) {
            NotificationService::markAsRead($id, $userId);
        }

        return response()->json([
            'message' => 'Đã đánh dấu là đã đọc.',
            'unread_count' => (int) Notification::where('user_id', $userId)->where('is_read', 0)->count(),
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }

        NotificationService::markAllAsRead($userId);

        return response()->json([
            'message' => 'Đã đánh dấu tất cả thông báo là đã đọc.',
            'unread_count' => 0,
        ]);
    }
}
