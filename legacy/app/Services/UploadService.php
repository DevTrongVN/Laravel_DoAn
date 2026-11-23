<?php
declare(strict_types=1);

/** Dịch vụ tải lên Ảnh/Media an toàn cho bài viết và avatar. */
final class UploadService
{
    private string $uploadBaseDir;
    private array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private int $maxSizeBytes = 5242880; // 5MB

    public function __construct()
    {
        $this->uploadBaseDir = __DIR__ . '/../../public/uploads';
    }

    public function uploadImage(array $file, string $subfolder = 'posts'): string
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('Tệp tải lên không hợp lệ.');
        }

        if ($file['size'] > $this->maxSizeBytes) {
            throw new InvalidArgumentException('Kích thước ảnh tối đa cho phép là 5MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $this->allowedMimes, true)) {
            throw new InvalidArgumentException('Chỉ chấp nhận các định dạng ảnh: JPEG, PNG, WEBP, GIF.');
        }

        $targetDir = $this->uploadBaseDir . '/' . trim($subfolder, '/');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg'
        };

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = $targetDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('Không thể lưu tệp ảnh lên máy chủ.');
        }

        return 'uploads/' . trim($subfolder, '/') . '/' . $filename;
    }

    /**
     * Chuẩn hóa và xác thực đường dẫn thumbnail bài viết nằm trong thư mục upload được phép.
     * Từ chối URL bên ngoài, ký tự null, path traversal (..) và mọi đường dẫn ngoài uploads/posts/.
     */
    public static function normalizePostThumbnailPath(?string $rawPath): ?string
    {
        if ($rawPath === null) {
            return null;
        }
        $trimmed = trim($rawPath);
        if ($trimmed === '' || str_contains($trimmed, "\0")) {
            return null;
        }
        if (preg_match('#^(https?://|data:|//)#i', $trimmed)) {
            return null;
        }
        if (str_contains($trimmed, '..')) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $trimmed), '/');
        $normalized = preg_replace('#^DoAnMaNguonMo/public/#i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('#^public/#i', '', $normalized) ?? $normalized;

        // Chỉ cho phép xóa tệp thuộc thư mục upload ảnh bài viết (uploads/posts/<tên tệp hợp lệ>)
        if (!preg_match('#^uploads/posts/[A-Za-z0-9._-]+$#', $normalized)) {
            return null;
        }

        return $normalized;
    }

    /**
     * Xóa an toàn tệp thumbnail bài viết sau khi bản ghi DB đã bị xóa:
     * 1. Xác minh tệp nằm chắc chắn bên trong public/uploads/posts (chống Path Traversal / symlink escape).
     * 2. Đếm tham chiếu còn lại trong posts.thumbnail (kể cả bài đăng lại / repost dùng chung ảnh).
     * 3. Chỉ gọi unlink() khi số tham chiếu = 0; nếu xóa thất bại thì ghi log an toàn.
     *
     * @return array{deleted: bool, reason: string, path: ?string, ref_count: int}
     */
    public static function deletePostThumbnailSafely(?string $rawPath, PDO $pdo): array
    {
        $norm = self::normalizePostThumbnailPath($rawPath);
        if ($norm === null) {
            return ['deleted' => false, 'reason' => 'not_allowed_local_thumbnail', 'path' => null, 'ref_count' => 0];
        }

        $baseDirReal = realpath(__DIR__ . '/../../public/uploads/posts');
        if ($baseDirReal === false || !is_dir($baseDirReal)) {
            return ['deleted' => false, 'reason' => 'upload_dir_missing', 'path' => $norm, 'ref_count' => 0];
        }

        $filename       = basename($norm);
        $candidatePath  = $baseDirReal . DIRECTORY_SEPARATOR . $filename;
        $targetRealPath = realpath($candidatePath);

        if ($targetRealPath === false || !is_file($targetRealPath)) {
            return ['deleted' => false, 'reason' => 'file_not_found', 'path' => $norm, 'ref_count' => 0];
        }

        $basePrefix = rtrim(str_replace('\\', '/', $baseDirReal), '/') . '/';
        $targetNorm = str_replace('\\', '/', $targetRealPath);
        if (!str_starts_with($targetNorm, $basePrefix)) {
            error_log("[UploadService] Từ chối xóa tệp ngoài thư mục upload cho phép: {$norm}");
            return ['deleted' => false, 'reason' => 'outside_allowed_dir', 'path' => $norm, 'ref_count' => 0];
        }

        // Kiểm tra xem còn bài viết nào (kể cả repost hoặc lưu theo dạng đường dẫn khác prefix) tham chiếu cùng tệp không
        $escapedFile = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filename);
        $refStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM posts WHERE thumbnail = ? OR thumbnail = ? OR thumbnail = ? OR thumbnail LIKE ? ESCAPE '\\\\'"
        );
        $refStmt->execute([
            $norm,
            '/' . $norm,
            '/DoAnMaNguonMo/public/' . $norm,
            '%/uploads/posts/' . $escapedFile,
        ]);
        $remainingRefs = (int)$refStmt->fetchColumn();

        if ($remainingRefs > 0) {
            return ['deleted' => false, 'reason' => 'still_referenced', 'path' => $norm, 'ref_count' => $remainingRefs];
        }

        if (!@unlink($targetRealPath)) {
            error_log("[UploadService] Không thể xóa tệp thumbnail mồ côi: {$norm}");
            return ['deleted' => false, 'reason' => 'unlink_failed', 'path' => $norm, 'ref_count' => 0];
        }

        return ['deleted' => true, 'reason' => 'deleted', 'path' => $norm, 'ref_count' => 0];
    }
}
