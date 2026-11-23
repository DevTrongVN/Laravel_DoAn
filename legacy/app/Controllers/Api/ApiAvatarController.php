<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../Models/User.php';

/**
 * Controller xử lý tải lên, cập nhật và khôi phục ảnh đại diện (Custom Avatar).
 * Bảo đảm an toàn tuyệt đối về file upload, phân quyền phía máy chủ và CSRF.
 */
class ApiAvatarController extends Controller
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
    private const MAX_DIMENSION = 6000; // 6000 px

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    private User $userModel;
    private string $avatarDir;

    public function __construct()
    {
        $this->userModel = new User();
        $this->avatarDir = dirname(__DIR__, 3) . '/public/uploads/avatars';
    }

    /**
     * POST /api/users/avatar
     * Tải lên và cập nhật ảnh đại diện tùy chỉnh cho tài khoản đang đăng nhập.
     */
    public function upload(): never
    {
        // 1. Xác thực đăng nhập
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập để cập nhật ảnh đại diện.'], 401);
        }

        $userId = (int)Auth::id();

        // 2. Kiểm tra file upload trong $_FILES
        $file = $_FILES['avatar'] ?? $_FILES['image'] ?? $_FILES['file'] ?? null;
        if (!$file || !is_array($file) || empty($file['tmp_name'])) {
            $this->json(['error' => 'Vui lòng chọn một file ảnh để tải lên.'], 422);
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errorCode = (int)$file['error'];
            $errorMsg = match ($errorCode) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Dung lượng file vượt quá giới hạn cho phép của máy chủ.',
                UPLOAD_ERR_NO_FILE => 'Chưa có file ảnh nào được chọn.',
                default => "Lỗi khi tải file lên máy chủ (mã lỗi: $errorCode).",
            };
            $this->json(['error' => $errorMsg], 422);
        }

        $tmpPath = (string)$file['tmp_name'];
        if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
            $this->json(['error' => 'Không thể đọc file tạm tải lên.'], 422);
        }

        // 3. Kiểm tra kích thước file (tối đa 5 MB, không rỗng)
        $fileSize = (int)filesize($tmpPath);
        if ($fileSize <= 0 || $fileSize > self::MAX_FILE_SIZE) {
            $this->json(['error' => 'File ảnh rỗng hoặc vượt quá dung lượng tối đa 5 MB.'], 422);
        }

        // 4. Xác minh MIME type thực tế bằng finfo_file (không tin MIME từ client)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? finfo_file($finfo, $tmpPath) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!is_string($detectedMime) || !isset(self::ALLOWED_MIME_TYPES[$detectedMime])) {
            $this->json(['error' => 'Định dạng file không được hỗ trợ. Chỉ chấp nhận ảnh JPEG, PNG, WebP hoặc GIF.'], 422);
        }

        // 5. Kiểm tra khả năng giải mã ảnh và kích thước bằng getimagesize()
        $imgInfo = @getimagesize($tmpPath);
        if (!$imgInfo || !is_array($imgInfo) || ($imgInfo[0] ?? 0) <= 0 || ($imgInfo[1] ?? 0) <= 0) {
            $this->json(['error' => 'Dữ liệu file không phải là ảnh hợp lệ hoặc file đã bị hỏng.'], 422);
        }

        $width = (int)$imgInfo[0];
        $height = (int)$imgInfo[1];
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            $this->json(['error' => 'Kích thước điểm ảnh quá lớn (tối đa ' . self::MAX_DIMENSION . 'x' . self::MAX_DIMENSION . 'px).'], 422);
        }

        if (($imgInfo['mime'] ?? '') !== $detectedMime) {
            $this->json(['error' => 'Nội dung file không khớp với định dạng ảnh MIME khai báo.'], 422);
        }

        // 6. Sinh tên file ngẫu nhiên an toàn duy nhất: avatar_{userId}_{timestamp}_{hash}.{ext}
        $ext = self::ALLOWED_MIME_TYPES[$detectedMime];
        $randomFilename = sprintf('avatar_%d_%d_%s.%s', $userId, time(), bin2hex(random_bytes(6)), $ext);

        if (!is_dir($this->avatarDir)) {
            @mkdir($this->avatarDir, 0777, true);
        }

        $targetPath = $this->avatarDir . '/' . $randomFilename;

        // 7. Di chuyển file vào thư mục lưu trữ
        $moved = false;
        if (is_uploaded_file($tmpPath)) {
            $moved = move_uploaded_file($tmpPath, $targetPath);
        } else {
            // Hỗ trợ môi trường test / CLI
            $moved = copy($tmpPath, $targetPath);
            @unlink($tmpPath);
        }

        if (!$moved || !file_exists($targetPath)) {
            $this->json(['error' => 'Không thể lưu file ảnh vào thư mục máy chủ.'], 500);
        }

        // 8. Cập nhật Database cho user hiện tại
        $currentUser = $this->userModel->findById($userId);
        $oldAvatar = (string)($currentUser['avatar'] ?? '');
        $storedAvatarPath = '/uploads/avatars/' . $randomFilename;

        try {
            $this->userModel->updateAvatar($userId, $storedAvatarPath);
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['avatar'] = $storedAvatarPath;
            }
        } catch (Throwable $e) {
            // Nếu Database lỗi: xóa ngay file mới vừa lưu để tránh file mồ côi
            @unlink($targetPath);
            $this->json(['error' => 'Lỗi khi lưu ảnh đại diện vào cơ sở dữ liệu: ' . $e->getMessage()], 500);
        }

        // 9. Xóa file avatar cũ nếu là custom file trong thư mục uploads/avatars/
        $this->cleanOldAvatarFile($oldAvatar);

        // 10. Trả về kết quả thành công
        $appUrl = defined('APP_URL') ? APP_URL : '';
        $fullUrl = $appUrl . $storedAvatarPath;

        $this->json([
            'success'    => true,
            'avatar_url' => $fullUrl,
            'avatar'     => $storedAvatarPath,
            'url'        => $fullUrl,
            'message'    => 'Cập nhật ảnh đại diện thành công.'
        ], 200);
    }

    /**
     * DELETE /api/users/avatar hoặc POST /api/users/avatar/reset
     * Khôi phục ảnh đại diện về preset mặc định và dọn dẹp file cũ nếu có.
     */
    public function reset(): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập để thực hiện thao tác này.'], 401);
        }

        $userId = (int)Auth::id();
        $currentUser = $this->userModel->findById($userId);
        $oldAvatar = (string)($currentUser['avatar'] ?? '');

        // Lấy avatar ngẫu nhiên từ danh sách preset có sẵn
        $defaultAvatar = User::randomAvatar();

        try {
            $this->userModel->updateAvatar($userId, $defaultAvatar);
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['avatar'] = $defaultAvatar;
            }
        } catch (Throwable $e) {
            $this->json(['error' => 'Không thể khôi phục ảnh đại diện mặc định: ' . $e->getMessage()], 500);
        }

        // Xóa file avatar tùy chỉnh cũ nếu có
        $this->cleanOldAvatarFile($oldAvatar);

        $appUrl = defined('APP_URL') ? APP_URL : '';
        $fullUrl = $appUrl . $defaultAvatar;

        $this->json([
            'success'    => true,
            'avatar_url' => $fullUrl,
            'avatar'     => $defaultAvatar,
            'url'        => $fullUrl,
            'message'    => 'Đã khôi phục ảnh đại diện mặc định.'
        ], 200);
    }

    /**
     * Dọn dẹp file avatar cũ chỉ khi file đó thuộc thư mục uploads/avatars/ do hệ thống quản lý.
     * Tuyệt đối không xóa file trong assets/ hay file ngoài thư mục avatar.
     */
    private function cleanOldAvatarFile(string $oldAvatar): void
    {
        $oldAvatar = trim($oldAvatar);
        if (str_starts_with($oldAvatar, '/uploads/avatars/') || str_starts_with($oldAvatar, 'uploads/avatars/')) {
            $basename = basename($oldAvatar);
            if ($basename !== '' && !str_contains($basename, '/') && !str_contains($basename, '\\')) {
                $filePath = $this->avatarDir . '/' . $basename;
                if (file_exists($filePath) && is_file($filePath)) {
                    @unlink($filePath);
                }
            }
        }
    }
}
