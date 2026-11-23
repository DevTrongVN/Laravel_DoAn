<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/Database.php';

/**
 * Quản trị viên: Quản lý người dùng, Phân quyền Role & Cấp / Thu hồi Tích xanh (Verified) có lưu lịch sử.
 */
class UserAdminController extends Controller
{
    /**
     * GET /api/admin/users
     * Hỗ trợ tìm kiếm, lọc theo vai trò, trạng thái tích xanh, đủ điều kiện, sắp xếp và phân trang Server-side.
     */
    public function index(): void
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập để tiếp tục.'], 401);
        }
        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Chỉ quản trị viên mới có quyền truy cập.'], 403);
        }

        $page       = max(1, (int)($_GET['page'] ?? 1));
        $perPage    = max(1, min(100, (int)($_GET['per_page'] ?? 15)));
        $q          = trim((string)($_GET['q'] ?? ''));
        $role       = trim((string)($_GET['role'] ?? ''));
        $isVerified = isset($_GET['is_verified']) && $_GET['is_verified'] !== '' ? (int)$_GET['is_verified'] : null;
        $eligible   = isset($_GET['eligible']) && $_GET['eligible'] !== '' ? (int)$_GET['eligible'] : null;
        $sort       = (string)($_GET['sort'] ?? 'reputation_desc');

        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[]     = '(username LIKE :q OR full_name LIKE :q OR email LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        if (in_array($role, ['admin', 'user'], true)) {
            $where[]        = 'role = :role';
            $params['role'] = $role;
        }

        if ($isVerified !== null) {
            $where[]               = 'is_verified = :is_verified';
            $params['is_verified'] = $isVerified;
        }

        if ($eligible !== null) {
            if ($eligible === 1) {
                $where[] = 'reputation_score >= 100';
            } else {
                $where[] = 'reputation_score < 100';
            }
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $orderSql = match ($sort) {
            'reputation_asc'  => 'reputation_score ASC, id ASC',
            'newest'          => 'created_at DESC, id DESC',
            'oldest'          => 'created_at ASC, id ASC',
            'name_asc'        => 'full_name ASC',
            default           => 'reputation_score DESC, id ASC',
        };

        $pdo = Database::getInstance()->getConnection();

        // 1. Tổng số dòng
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users {$whereSql}");
        foreach ($params as $k => $v) {
            $countStmt->bindValue(':' . $k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        // 2. Lấy dữ liệu phân trang
        $sql = "
            SELECT id, username, email, full_name, avatar, role, is_verified, reputation_score, created_at
            FROM users
            {$whereSql}
            ORDER BY {$orderSql}
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();

        foreach ($users as &$u) {
            $u['id']               = (int)$u['id'];
            $u['is_verified']      = (bool)(int)$u['is_verified'];
            $u['reputation_score'] = (int)$u['reputation_score'];
            $u['is_eligible']      = $u['reputation_score'] >= 100;
        }
        unset($u);

        $response = [
            'data'       => $users,
            'pagination' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => max(1, (int)ceil($total / $perPage)),
            ],
            'current_admin_id' => (int)Auth::id(),
        ];

        $requestedRoute = trim((string)($_GET['url'] ?? ''), '/');
        $isApiRequest   = str_starts_with($requestedRoute, 'api/')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || isset($_GET['api']);
        if ($isApiRequest) {
            $this->json($response);
        }

        try {
            $this->render('admin/users/index', ['users' => $users, 'pagination' => $response['pagination']], 'admin');
        } catch (Throwable) {
            $this->json($response);
        }
    }

    /**
     * POST /api/admin/users/:id/verify
     * Body JSON: { "is_verified"?: bool, "reason"?: string }
     */
    public function toggleVerified(string $id): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập để tiếp tục.'], 401);
        }
        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Chỉ quản trị viên.'], 403);
        }

        $userId  = (int)$id;
        $adminId = (int)Auth::id();
        $pdo     = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare('SELECT id, is_verified, full_name, reputation_score FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            $this->json(['error' => 'Không tìm thấy người dùng.'], 404);
        }

        $current = (int)$user['is_verified'];
        $raw     = file_get_contents('php://input') ?: '';
        $data    = json_decode($raw, true) ?: $_POST;

        if (isset($data['is_verified'])) {
            $newVal = filter_var($data['is_verified'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        } else {
            $newVal = $current === 1 ? 0 : 1;
        }

        $action = $newVal === 1 ? 'grant' : 'revoke';
        $reason = trim((string)($data['reason'] ?? '')) ?: null;

        // Cập nhật trạng thái tích xanh
        $pdo->prepare('UPDATE users SET is_verified = ? WHERE id = ?')->execute([$newVal, $userId]);

        // Ghi lịch sử vào verified_badge_logs
        try {
            $pdo->prepare(
                'INSERT INTO verified_badge_logs (user_id, admin_id, action, reason) VALUES (?, ?, ?, ?)'
            )->execute([$userId, $adminId, $action, $reason]);
        } catch (Throwable $e) {
            error_log('Lỗi ghi verified_badge_logs: ' . $e->getMessage());
        }

        $this->json([
            'message'     => $newVal === 1 ? 'Đã cấp tích xanh thành công.' : 'Đã thu hồi tích xanh.',
            'is_verified' => (bool)$newVal,
            'action'      => $action,
            'reason'      => $reason,
        ]);
    }

    /**
     * GET /api/admin/badge-logs/:id
     * Lịch sử cấp/thu hồi tích xanh của một user.
     */
    public function badgeLogs(string $id): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }
        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Chỉ quản trị viên.'], 403);
        }

        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('
            SELECT vbl.id, vbl.action, vbl.reason, vbl.created_at,
                   adm.full_name AS admin_name, adm.username AS admin_username
            FROM verified_badge_logs vbl
            JOIN users adm ON adm.id = vbl.admin_id
            WHERE vbl.user_id = ?
            ORDER BY vbl.created_at DESC
            LIMIT 50
        ');
        $stmt->execute([(int)$id]);
        $this->json(['data' => $stmt->fetchAll()]);
    }

    /**
     * POST /api/admin/users/:id/role
     * Body JSON: { "role": "admin"|"user" }
     */
    public function changeRole(string $id): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }
        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Chỉ quản trị viên.'], 403);
        }

        $raw  = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true) ?: $_POST;
        $role = trim((string)($data['role'] ?? ''));

        if (!in_array($role, ['admin', 'user'], true)) {
            $this->json(['error' => 'Vai trò không hợp lệ.'], 422);
        }

        $targetId       = (int)$id;
        $currentAdminId = (int)Auth::id();

        if ($targetId === $currentAdminId && $role !== 'admin') {
            $this->json(['error' => 'Bạn không thể tự hạ quyền quản trị của chính mình.'], 422);
        }

        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$role, $targetId]);

        $this->json([
            'message' => 'Đã cập nhật vai trò người dùng thành ' . ($role === 'admin' ? 'Quản trị viên (Admin)' : 'Người dùng (User)') . '.',
            'role'    => $role,
        ]);
    }
}
