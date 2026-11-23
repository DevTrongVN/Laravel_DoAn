<?php

declare(strict_types=1);

namespace App\Services;

use finfo;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

/**
 * Dịch vụ tải lên ảnh an toàn cho bài viết và ảnh đại diện.
 */
final class UploadService
{
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    private const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5MB

    public function uploadImage(UploadedFile $file, string $subfolder = 'posts', ?string $prefix = null): string
    {
        if (!$file->isValid()) {
            throw new InvalidArgumentException('Tệp tải lên không hợp lệ.');
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES || $file->getSize() <= 0) {
            throw new InvalidArgumentException('Kích thước ảnh tối đa cho phép là 5MB.');
        }

        $realPath = $file->getRealPath();
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $realPath ? $finfo->file($realPath) : false;

        if (!is_string($mime) || !isset(self::ALLOWED_MIMES[$mime])) {
            throw new InvalidArgumentException('Chỉ chấp nhận các định dạng ảnh: JPEG, PNG, WEBP, GIF.');
        }

        $imgInfo = @getimagesize($realPath);
        if (!$imgInfo || ($imgInfo[0] ?? 0) <= 0 || ($imgInfo[1] ?? 0) <= 0) {
            throw new InvalidArgumentException('Dữ liệu file không phải là ảnh hợp lệ hoặc file đã bị hỏng.');
        }

        $ext = self::ALLOWED_MIMES[$mime];
        $sub = trim($subfolder, '/');
        $targetDir = public_path('uploads/' . $sub);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $filename = $prefix !== null
            ? sprintf('%s_%d_%s.%s', $prefix, time(), bin2hex(random_bytes(6)), $ext)
            : bin2hex(random_bytes(16)) . '.' . $ext;

        $file->move($targetDir, $filename);

        if (!file_exists($targetDir . DIRECTORY_SEPARATOR . $filename)) {
            throw new RuntimeException('Không thể lưu tệp ảnh lên máy chủ.');
        }

        return 'uploads/' . $sub . '/' . $filename;
    }
}
