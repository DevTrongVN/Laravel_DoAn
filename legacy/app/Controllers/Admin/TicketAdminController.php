<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../Models/Ticket.php';

/** Quản trị viên: Xét duyệt Ticket nâng cấp bài viết thành Tin tức. */
class TicketAdminController extends Controller
{
    private Ticket $ticketModel;

    public function __construct()
    {
        $this->ticketModel = new Ticket();
    }

    public function index(): void
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập để tiếp tục.'], 401);
        }
        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Chỉ quản trị viên mới có quyền xem tickets.'], 403);
        }

        $tickets = $this->ticketModel->listAll();
        $requestedRoute = trim((string)($_GET['url'] ?? ''), '/');
        $isApiRequest = str_starts_with($requestedRoute, 'api/')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || isset($_GET['api']);
        if ($isApiRequest) {
            $this->json(['data' => $tickets]);
        }

        try {
            $this->render('admin/tickets/index', ['tickets' => $tickets], 'admin');
        } catch (Throwable) {
            $this->json(['data' => $tickets]);
        }
    }

    public function review(string $id): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập để tiếp tục.'], 401);
        }
        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Chỉ quản trị viên mới có quyền duyệt ticket.'], 403);
        }

        $data = $this->input();
        $action = (string)($data['action'] ?? $data['status'] ?? '');
        $adminNote = trim((string)($data['admin_note'] ?? ''));

        if (!in_array($action, ['approved', 'rejected'], true)) {
            $this->json(['error' => 'Hành động duyệt không hợp lệ.'], 422);
        }

        try {
            $this->ticketModel->resolve((int)$id, $action, $adminNote);
            $msg = $action === 'approved' ? 'Đã duyệt bài viết thành Tin tức.' : 'Đã từ chối ticket.';
            $this->json(['message' => $msg]);
        } catch (DomainException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        }
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
        }
        return $_POST;
    }
}
