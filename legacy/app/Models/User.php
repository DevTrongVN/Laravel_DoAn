<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/UnicodeHelper.php';

/** Truy vấn tài khoản, session đăng nhập, theo dõi và điểm uy tín. */
final class User
{
    private const AVATAR_PRESETS = [
        'user-admin.svg', 'user-demo.svg', 'user-hoangnam.svg',
        'user-thuha.svg', 'user-quocbao.svg', 'user-maianh.svg',
    ];

    private PDO $pdo;
    private array $appConfig;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->appConfig = require __DIR__ . '/../../config/app.php';
    }

    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, username, email, full_name, avatar, bio, `role`, is_verified, reputation_score, created_at FROM users WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        $user = $statement->fetch();
        return $user ?: null;
    }

    public function findByLogin(string $login): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE username = :username OR email = :email LIMIT 1');
        $statement->execute(['username' => $login, 'email' => $login]);
        $user = $statement->fetch();
        return $user ?: null;
    }

    public function register(string $username, string $email, string $fullName, string $password, ?string $avatar = null): int
    {
        $avatar = $avatar === null || trim($avatar) === ''
            ? self::randomAvatar()
            : self::normalizeAvatar($avatar);
        $statement = $this->pdo->prepare('INSERT INTO users (username, email, password, full_name, avatar) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$username, $email, Auth::hashPassword($password), $fullName, $avatar]);
        return (int) $this->pdo->lastInsertId();
    }

    public static function randomAvatar(): string
    {
        return '/assets/images/avatars/' . self::AVATAR_PRESETS[random_int(0, count(self::AVATAR_PRESETS) - 1)];
    }

    private static function normalizeAvatar(string $avatar): string
    {
        $avatar = '/' . ltrim(trim($avatar), '/');
        $allowed = array_map(static fn(string $file): string => '/assets/images/avatars/' . $file, self::AVATAR_PRESETS);
        if (in_array($avatar, $allowed, true)) {
            return $avatar;
        }
        // Cho phép avatar tùy chỉnh hợp lệ đã được lưu trong uploads/avatars/
        if (preg_match('~^/uploads/avatars/[a-zA-Z0-9_\-\.]+\.(jpe?g|png|webp|gif)$~i', $avatar)) {
            return $avatar;
        }
        throw new InvalidArgumentException('Avatar không hợp lệ. Hãy chọn một avatar hoạt hình có sẵn hoặc tải ảnh lên.');
    }

    /**
     * Cập nhật đường dẫn ảnh đại diện cho tài khoản người dùng và làm mới Session hiện tại.
     */
    public function updateAvatar(int $userId, string $avatar): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?');
        $success = $stmt->execute([$avatar, $userId]);
        if ($success && (int)($_SESSION['user_id'] ?? 0) === $userId) {
            $_SESSION['avatar'] = $avatar;
        }
        return $success;
    }

    public function login(string $login, string $password): ?array
    {
        $user = $this->findByLogin($login);
        if ($user === null || !Auth::verifyPassword($password, (string) $user['password'])) return null;
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['avatar'] = $user['avatar'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['is_verified'] = (bool) $user['is_verified'];
        unset($user['password']);
        return $user;
    }

    public function updateReputation(int $userId): void
    {
        require_once __DIR__ . '/../Services/ReputationService.php';
        (new ReputationService())->calculate($userId);
    }

    public function toggleFollow(int $followerId, int $followingId): bool
    {
        if ($followerId === $followingId) throw new InvalidArgumentException('Bạn không thể theo dõi chính mình.');
        $check = $this->pdo->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?');
        $check->execute([$followerId, $followingId]);
        if ($check->fetchColumn()) {
            $stmt = $this->pdo->prepare('DELETE FROM follows WHERE follower_id = ? AND following_id = ?');
            $stmt->execute([$followerId, $followingId]);
            $following = false;
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO follows (follower_id, following_id) VALUES (?, ?)');
            $stmt->execute([$followerId, $followingId]);
            $following = true;
        }
        $this->updateReputation($followingId);
        return $following;
    }

    public function listUsers(): array
    {
        return $this->pdo->query('SELECT id, username, full_name, avatar, `role`, is_verified, reputation_score FROM users ORDER BY created_at DESC')->fetchAll();
    }

    public function setVerified(int $id, bool $verified): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET is_verified = ? WHERE id = ?');
        $statement->execute([$verified ? 1 : 0, $id]);
        if ((int) ($_SESSION['user_id'] ?? 0) === $id) $_SESSION['is_verified'] = $verified;
    }

    public function setRole(int $id, string $role): void
    {
        if (!in_array($role, ['user', 'admin'], true)) {
            throw new InvalidArgumentException('Vai trò không hợp lệ (chỉ chấp nhận user hoặc admin).');
        }
        $statement = $this->pdo->prepare('UPDATE users SET `role` = ? WHERE id = ?');
        $statement->execute([$role, $id]);
        if ((int) ($_SESSION['user_id'] ?? 0) === $id) {
            $_SESSION['role'] = $role;
        }
    }

    public function updateSettings(int $id, array $data): array
    {
        $user = $this->findById($id);
        if ($user === null) throw new RuntimeException('Không tìm thấy tài khoản.');

        $updates = [];
        $params = [];

        if (isset($data['username'])) {
            $username = trim((string)$data['username']);
            if (!preg_match('/^[A-Za-z0-9_]{3,40}$/', $username)) {
                throw new InvalidArgumentException('Tên đăng nhập cần 3-40 ký tự, chỉ gồm chữ, số và dấu gạch dưới.');
            }
            if ($username !== $user['username']) {
                $check = $this->pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
                $check->execute([$username, $id]);
                if ($check->fetchColumn()) {
                    throw new DomainException('Tên đăng nhập này đã có người sử dụng.');
                }
                $updates[] = 'username = ?';
                $params[] = $username;
            }
        }

        if (isset($data['full_name'])) {
            $fullName = UnicodeHelper::toNfc(trim((string)$data['full_name']));
            if ($fullName === '' || mb_strlen($fullName) > 120) {
                throw new InvalidArgumentException('Vui lòng nhập họ và tên (tối đa 120 ký tự).');
            }
            if ($fullName !== $user['full_name']) {
                $updates[] = 'full_name = ?';
                $params[] = $fullName;
                $_SESSION['user_name'] = $fullName;
            }
        }

        if (array_key_exists('avatar', $data) && trim((string)$data['avatar']) !== '') {
            $avatar = self::normalizeAvatar((string)$data['avatar']);
            if ($avatar !== (string)($user['avatar'] ?? '')) {
                $updates[] = 'avatar = ?';
                $params[] = $avatar;
                $_SESSION['avatar'] = $avatar;
            }
        }

        if (array_key_exists('bio', $data)) {
            $bio = UnicodeHelper::toNfc(trim((string)$data['bio']));
            if (mb_strlen($bio) > 500) {
                throw new InvalidArgumentException('Tiểu sử tối đa 500 ký tự.');
            }
            if ($bio !== (string)($user['bio'] ?? '')) {
                $updates[] = 'bio = ?';
                $params[] = $bio !== '' ? $bio : null;
            }
        }

        if (!empty($data['new_password'])) {
            $currentPassword = (string)($data['current_password'] ?? '');
            $newPassword = (string)$data['new_password'];

            $stmt = $this->pdo->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $currentHash = (string)$stmt->fetchColumn();

            if (!Auth::verifyPassword($currentPassword, $currentHash)) {
                throw new DomainException('Mật khẩu hiện tại không chính xác.');
            }
            if (strlen($newPassword) < 8) {
                throw new InvalidArgumentException('Mật khẩu mới phải có ít nhất 8 ký tự.');
            }
            $updates[] = 'password = ?';
            $params[] = Auth::hashPassword($newPassword);
        }

        if (!empty($updates)) {
            $params[] = $id;
            $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $this->pdo->prepare($sql)->execute($params);
        }

        return $this->findById($id);
    }

    /**
     * Lấy thông tin chủ blog / tài khoản Admin chính của hệ thống.
     */
    public function findPrimaryAdmin(): ?array
    {
        $stmt = $this->pdo->query("SELECT id, username, full_name, email, avatar, bio, role, is_verified, reputation_score FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        $user = $stmt->fetch();
        if (!$user) {
            $user = $this->findById(1);
        }
        return $user ?: null;
    }

    /**
     * Tìm kiếm tác giả theo username hoặc họ tên hiển thị.
     * Trả về danh sách tác giả kèm tích xanh, điểm uy tín và tổng số bài viết.
     */
    public function searchAuthors(string $keyword, int $limit = 10, bool $usernameOnly = false): array
    {
        $clean = trim(ltrim($keyword, '@'));
        if ($clean === '') {
            return [];
        }

        $needle = '%' . $clean . '%';
        $prefix = $clean . '%';

        if ($usernameOnly) {
            $where = 'u.username LIKE :q_username';
        } else {
            $where = '(u.username LIKE :q_username OR u.full_name LIKE :q_fullname)';
        }

        $sql = "SELECT u.id, u.username, u.full_name, u.avatar, u.role, u.is_verified, u.reputation_score,
                       (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id AND p.moderation_status = 'safe') AS total_posts
                FROM users u
                WHERE {$where}
                ORDER BY (u.username = :exact_username OR u.full_name = :exact_fullname) DESC,
                         (u.username LIKE :prefix_username OR u.full_name LIKE :prefix_fullname) DESC,
                         u.reputation_score DESC,
                         total_posts DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':q_username', $needle, PDO::PARAM_STR);
        if (!$usernameOnly) {
            $stmt->bindValue(':q_fullname', $needle, PDO::PARAM_STR);
        }
        $stmt->bindValue(':exact_username', $clean, PDO::PARAM_STR);
        $stmt->bindValue(':exact_fullname', $clean, PDO::PARAM_STR);
        $stmt->bindValue(':prefix_username', $prefix, PDO::PARAM_STR);
        $stmt->bindValue(':prefix_fullname', $prefix, PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['is_verified'] = (bool)$row['is_verified'];
            $row['reputation_score'] = (int)$row['reputation_score'];
            $row['total_posts'] = (int)$row['total_posts'];
            try {
                $row['avatar'] = self::normalizeAvatar((string)($row['avatar'] ?? ''));
            } catch (\Throwable $e) {
                $row['avatar'] = '/assets/images/avatars/user-demo.svg';
            }
        }
        unset($row);

        return $rows;
    }
}
