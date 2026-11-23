<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../Models/Notification.php';

/** Quản lý & Hiển thị thông báo người dùng (có phân trang và đánh dấu đã đọc). */
class NotificationController extends Controller
{
    private Notification $notifModel;

    public function __construct()
    {
        $this->notifModel = new Notification();
    }

    /**
     * GET /notifications (Web) hoặc GET /api/notifications (API)
     */
    public function index(): void
    {
        $userId = Auth::id();
        $requestedRoute = trim((string)($_GET['url'] ?? ''), '/');
        $format = (string)($_GET['format'] ?? '');
        $isApi = isset($_GET['api']) || $format === 'json';
        $acceptHeader = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        $isJsonAccept = str_contains($acceptHeader, 'application/json') && !str_contains($acceptHeader, 'text/html');
        $isApiRoute = str_starts_with($requestedRoute, 'api/');
        $isApiRequest = $isApiRoute || $isApi || $isJsonAccept;

        if ($userId === null) {
            if ($isApiRequest) {
                $this->json(['error' => 'Vui lòng đăng nhập để xem thông báo.'], 401);
            }
            $redirectUrl = (defined('APP_URL') ? APP_URL : '') . '/account.html?mode=login';
            header('Location: ' . $redirectUrl);
            exit;
        }

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 15)));

        $result = $this->notifModel->getByUser($userId, $page, $perPage);

        $payload = [
            'notifications' => $result['data'],
            'data'         => $result['data'],
            'unread_count' => $result['unread_count'],
            'pagination'   => [
                'page'      => $result['page'],
                'per_page'  => $perPage,
                'total'     => $result['total'],
                'last_page' => $result['last_page'],
            ],
            'pageTitle'    => 'Trung tâm thông báo — Góc Nhỏ',
            'activeTab'    => 'notifications',
        ];

        if ($isApiRequest) {
            $this->json($payload);
        }

        $this->render('notifications/index', $payload);
    }

    /**
     * POST /api/notifications/:id/read
     */
    public function markRead(string $id): never
    {
        $userId = Auth::id();
        if ($userId === null) {
            $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }

        $notifId = (int)$id;
        if ($notifId > 0) {
            $this->notifModel->markAsRead($notifId, $userId);
        }
        $this->json([
            'message'      => 'Đã đánh dấu là đã đọc.',
            'unread_count' => $this->notifModel->countUnread($userId),
        ]);
    }

    /**
     * POST /api/notifications/read (Body JSON: { id: int })
     */
    public function markReadBody(): never
    {
        $userId = Auth::id();
        if ($userId === null) {
            $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true) ?: $_POST;
        $id   = (int)($data['id'] ?? 0);

        if ($id > 0) {
            $this->notifModel->markAsRead($id, $userId);
        }

        $this->json([
            'message'      => 'Đã đánh dấu là đã đọc.',
            'unread_count' => $this->notifModel->countUnread($userId),
        ]);
    }

    /**
     * POST /api/notifications/read-all
     */
    public function markAllRead(): never
    {
        $userId = Auth::id();
        if ($userId === null) {
            $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }

        $this->notifModel->markAllAsRead($userId);
        $this->json([
            'message'      => 'Đã đánh dấu tất cả thông báo là đã đọc.',
            'unread_count' => 0,
        ]);
    }
}
