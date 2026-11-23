<?php
declare(strict_types=1);

require_once __DIR__ . '/Session.php';

/**
 * Quản lý CSRF Token: Sinh, lưu trữ trong Session, cấp Cookie XSRF-TOKEN và xác thực.
 * Độc lập hoàn toàn với Authentication / Authorization.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const COOKIE_NAME = 'XSRF-TOKEN';

    /**
     * Lấy token CSRF hiện tại trong Session, sinh mới nếu chưa có.
     * Tự động gửi cookie XSRF-TOKEN về trình duyệt để frontend (fetch/AJAX) có thể đọc được.
     */
    public static function getToken(): string
    {
        Session::start();

        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        self::syncCookie((string)$_SESSION[self::SESSION_KEY]);

        return (string)$_SESSION[self::SESSION_KEY];
    }

    /**
     * Làm mới token CSRF (dùng sau khi đăng nhập, đăng xuất hoặc tái tạo session).
     */
    public static function refreshToken(): string
    {
        Session::start();
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        self::syncCookie((string)$_SESSION[self::SESSION_KEY]);
        return (string)$_SESSION[self::SESSION_KEY];
    }

    /**
     * Đồng bộ token vào Cookie XSRF-TOKEN cho client-side JavaScript.
     * Cấu hình httponly = false để script cùng domain đọc được.
     */
    private static function syncCookie(string $token): void
    {
        if (headers_sent()) {
            return;
        }

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => 0, // Session cookie (hết hạn khi đóng trình duyệt)
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => false, // Cho phép JavaScript đọc cookie để gắn vào header X-CSRF-TOKEN
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Xác thực request hiện tại.
     * Bỏ qua các phương thức an toàn: GET, HEAD, OPTIONS.
     * Yêu cầu token hợp lệ cho: POST, PUT, PATCH, DELETE.
     */
    public static function validateRequest(): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        Session::start();
        $sessionToken = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        $sentToken = self::extractTokenFromRequest();
        if ($sentToken === null || $sentToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $sentToken);
    }

    /**
     * Trích xuất token từ request theo thứ tự ưu tiên:
     * 1. Header HTTP_X_CSRF_TOKEN hoặc HTTP_X_XSRF_TOKEN
     * 2. Header HTTP_CSRF_TOKEN
     * 3. Trường _csrf_token hoặc csrf_token trong body JSON
     * 4. Trường _csrf_token hoặc csrf_token trong $_POST
     */
    private static function extractTokenFromRequest(): ?string
    {
        // 1. Kiểm tra HTTP Headers (chuẩn cho REST API / Fetch)
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return trim((string)$_SERVER['HTTP_X_CSRF_TOKEN']);
        }
        if (!empty($_SERVER['HTTP_X_XSRF_TOKEN'])) {
            return trim((string)$_SERVER['HTTP_X_XSRF_TOKEN']);
        }
        if (!empty($_SERVER['HTTP_CSRF_TOKEN'])) {
            return trim((string)$_SERVER['HTTP_CSRF_TOKEN']);
        }

        // 2. Kiểm tra JSON payload trong php://input
        $raw = @file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $data = @json_decode($raw, true);
            if (is_array($data)) {
                if (!empty($data['_csrf_token']) && is_string($data['_csrf_token'])) {
                    return trim($data['_csrf_token']);
                }
                if (!empty($data['csrf_token']) && is_string($data['csrf_token'])) {
                    return trim($data['csrf_token']);
                }
            }
        }

        // 3. Kiểm tra $_POST truyền thống
        if (!empty($_POST['_csrf_token']) && is_string($_POST['_csrf_token'])) {
            return trim($_POST['_csrf_token']);
        }
        if (!empty($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
            return trim($_POST['csrf_token']);
        }

        return null;
    }

    /**
     * Chặn request ngay lập tức và trả về HTTP 403 nếu token không hợp lệ.
     */
    public static function abortIfInvalid(): void
    {
        if (!self::validateRequest()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'CSRF token không hợp lệ hoặc đã hết hạn. Vui lòng làm mới trang.',
                'code'  => 'CSRF_TOKEN_INVALID',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
