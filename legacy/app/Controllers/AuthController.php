<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Csrf.php';
require_once __DIR__ . '/../Models/User.php';

/** Xử lý Đăng ký, Đăng nhập, Đăng xuất và Hồ sơ người dùng. */
class AuthController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function register(): never
    {
        $data = $this->input();
        $username = trim((string)($data['username'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $fullName = trim((string)($data['full_name'] ?? ''));
        $avatar = isset($data['avatar']) ? (string)$data['avatar'] : null;

        if ($username === '' || !preg_match('/^[A-Za-z0-9_]{3,40}$/', $username)) {
            $this->json(['error' => 'Tên đăng nhập cần 3-40 ký tự chữ, số hoặc dấu gạch dưới.'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'Địa chỉ email không hợp lệ.'], 422);
        }
        if (strlen($password) < 8) {
            $this->json(['error' => 'Mật khẩu phải có ít nhất 8 ký tự.'], 422);
        }
        if ($fullName === '' || mb_strlen($fullName) > 120) {
            $this->json(['error' => 'Họ và tên bắt buộc và tối đa 120 ký tự.'], 422);
        }

        try {
            $id = $this->userModel->register($username, $email, $fullName, $password, $avatar);
            $user = $this->userModel->login($username, $password);
            $token = Csrf::refreshToken();
            $this->json(['message' => 'Đăng ký thành công.', 'user' => $user, 'csrf_token' => $token], 201);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->json(['error' => 'Tên đăng nhập hoặc email đã được sử dụng.'], 409);
            }
            throw $e;
        }
    }

    public function login(): never
    {
        $data = $this->input();
        $login = trim((string)($data['login'] ?? $data['username'] ?? $data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        $user = $this->userModel->login($login, $password);
        if ($user === null) {
            $this->json(['error' => 'Tên đăng nhập/email hoặc mật khẩu không đúng.'], 401);
        }
        $token = Csrf::refreshToken();
        $this->json(['message' => 'Đăng nhập thành công.', 'user' => $user, 'csrf_token' => $token]);
    }

    public function logout(): never
    {
        Session::destroy();
        $token = Csrf::refreshToken();
        $this->json(['message' => 'Đã đăng xuất.', 'csrf_token' => $token]);
    }

    public function me(): never
    {
        $id = Auth::id();
        $this->json(['user' => $id === null ? null : $this->userModel->findById($id)]);
    }

    public function updateProfile(): never
    {
        $userId = Auth::id();
        if ($userId === null) {
            $this->json(['error' => 'Vui lòng đăng nhập để thực hiện.'], 401);
        }

        $data = $this->input();
        try {
            $updatedUser = $this->userModel->updateSettings($userId, $data);
            $this->json([
                'message' => 'Cập nhật thông tin tài khoản thành công.',
                'user' => $updatedUser
            ]);
        } catch (DomainException|InvalidArgumentException $e) {
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
