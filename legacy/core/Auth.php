<?php
declare(strict_types=1);

require_once __DIR__ . '/Session.php';

/** Tiện ích xác thực dùng session của ứng dụng. */
final class Auth
{
    private static function startSession(): void
    {
        Session::start();
    }

    public static function check(): bool
    {
        self::startSession();
        return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
    }

    public static function isLoggedIn(): bool { return self::check(); }

    public static function id(): ?int
    {
        self::startSession();
        return self::check() ? (int) $_SESSION['user_id'] : null;
    }

    public static function currentUserId(): ?int { return self::id(); }

    public static function role(): ?string
    {
        self::startSession();
        if (!self::check()) return null;
        if (empty($_SESSION['role'])) {
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare('SELECT `role` FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([(int)$_SESSION['user_id']]);
                $_SESSION['role'] = (string)($stmt->fetchColumn() ?: 'user');
            } catch (Throwable) {
                $_SESSION['role'] = 'user';
            }
        }
        return $_SESSION['role'] ?? 'user';
    }

    /** Tài khoản quản trị hiện được nhận diện bằng role = admin. */
    public static function isAdmin(): bool
    {
        self::startSession();
        return self::check() && (self::role() === 'admin');
    }

    /** Kiểm tra cờ tích xanh từ user lấy ở DB hoặc từ session. */
    public static function isVerified(?array $user = null): bool
    {
        self::startSession();
        $value = $user['is_verified'] ?? $_SESSION['is_verified'] ?? false;
        return $value === true || $value === 1 || $value === '1';
    }

    /** Băm mật khẩu trước khi lưu vào cơ sở dữ liệu. */
    public static function hashPassword(string $password): string
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) throw new RuntimeException('Không thể mã hóa mật khẩu.');
        return $hash;
    }

    /** So sánh mật khẩu người dùng nhập với hash đã lưu. */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
