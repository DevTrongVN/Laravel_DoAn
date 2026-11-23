<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/Database.php';

/** Quản trị viên: Thống kê tổng quan hệ thống & Bảo vệ trang quản trị phía máy chủ. */
class DashboardController extends Controller
{
    public function index(): void
    {
        // 1. Kiểm tra xác thực (Session Login)
        if (!Auth::check()) {
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                $this->json(['error' => 'Vui lòng đăng nhập để tiếp tục.'], 401);
            }
            $redirectUrl = defined('APP_URL') ? APP_URL . '/account.html' : 'account.html';
            header('Location: ' . $redirectUrl);
            exit;
        }

        // 2. Kiểm tra phân quyền Admin
        if (!Auth::isAdmin()) {
            http_response_code(403);
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                $this->json(['error' => 'Chỉ quản trị viên mới có quyền truy cập.'], 403);
            }
            $homeUrl = defined('APP_URL') ? APP_URL . '/' : 'index.html';
            $cssUrl = (defined('APP_URL') ? APP_URL : '') . '/assets/css/main.css';
            echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Cấm truy cập</title>
    <link rel="stylesheet" href="' . htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') . '">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f8fafc; color: #1e293b; }
        .error-card { background: #fff; padding: 32px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 440px; text-align: center; }
        h1 { font-size: 24px; color: #dc2626; margin-top: 0; }
        p { color: #64748b; font-size: 14px; line-height: 1.6; }
        a { display: inline-block; margin-top: 16px; padding: 8px 16px; background: #0284c7; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 14px; }
    </style>
</head>
<body>
    <div class="error-card">
        <h1>403 - Cấm truy cập</h1>
        <p>Tài khoản của bạn không có quyền quản trị viên (Admin) để truy cập trang này.</p>
        <a href="' . htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') . '">Quay lại trang chủ</a>
    </div>
</body>
</html>';
            exit;
        }

        // 3. Phục vụ API thống kê nếu client yêu cầu JSON hoặc có ?api=1
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || isset($_GET['api'])) {
            $pdo = Database::getInstance()->getConnection();
            $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $totalPosts = (int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
            
            // Thống kê chính xác đề xuất Tin tức chờ duyệt (bao gồm cả Ticket tác giả và AI gợi ý tiềm năng)
            $pendingTickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'pending' AND ticket_type = 'upgrade'")->fetchColumn();
            $pendingAiNews = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE ai_news_suggested = 1 AND (ai_status = 'pending' OR ai_status IS NULL)")->fetchColumn();
            $totalPendingNews = $pendingTickets + $pendingAiNews;

            // Thống kê chính xác nội dung AI cần rà soát (status = 'pending')
            try {
                $pendingAiItems = (int)$pdo->query("SELECT COUNT(*) FROM ai_moderation_items WHERE status = 'pending'")->fetchColumn();
            } catch (Throwable) {
                $pendingAiItems = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE moderation_status IN ('flagged', 'blocked') AND ai_severity IS NOT NULL AND (ai_status IS NULL OR ai_status = 'pending')")->fetchColumn();
            }

            // Thống kê chính xác các báo cáo và khiếu nại đang ở trạng thái 'pending'
            $pendingPostReports    = (int)$pdo->query("SELECT COUNT(*) FROM post_reports WHERE status = 'pending'")->fetchColumn();
            $pendingCommentReports = (int)$pdo->query("SELECT COUNT(*) FROM comment_reports WHERE status = 'pending'")->fetchColumn();
            $pendingAppeals        = (int)$pdo->query("SELECT COUNT(*) FROM post_appeals WHERE status = 'pending'")->fetchColumn();
            $totalPendingReports   = $pendingPostReports + $pendingCommentReports + $pendingAppeals;

            $stats = [
                'total_users'             => $totalUsers,
                'total_posts'             => $totalPosts,
                'pending_tickets'         => $totalPendingNews,
                'pending_news'            => $totalPendingNews,
                'flagged_posts'           => $pendingAiItems,
                'ai_moderation_pending'   => $pendingAiItems,
                'pending_reports'         => $totalPendingReports,
                'pending_post_reports'    => $pendingPostReports,
                'pending_comment_reports' => $pendingCommentReports,
                'pending_appeals'         => $pendingAppeals,
            ];
            $this->json(['data' => $stats]);
        }

        // 4. Nếu là Admin hợp lệ: nạp giao diện Admin an toàn từ PHP View bảo vệ
        $adminView = __DIR__ . '/../../../views/admin/index.php';
        if (file_exists($adminView)) {
            require $adminView;
        } else {
            echo "Admin dashboard template not found.";
        }
        exit;
    }
}
