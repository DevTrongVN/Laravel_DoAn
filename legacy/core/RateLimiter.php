<?php
declare(strict_types=1);

/**
 * Bộ giới hạn tần suất request phía server (File-based Sliding Window với LOCK_EX).
 * Lưu trạng thái trong storage/cache/ratelimit/ — không yêu cầu tạo thêm bảng CSDL.
 */
final class RateLimiter
{
    private string $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? (__DIR__ . '/../storage/cache/ratelimit');
    }

    /**
     * Kiểm tra và ghi nhận một lần gọi cho khóa định danh (VD: user ID hoặc IP).
     *
     * @return array{allowed: bool, remaining: int, limit: int, retry_after: int}
     */
    public function attempt(string $key, int $maxRequests, int $windowSeconds): array
    {
        $maxRequests   = max(1, $maxRequests);
        $windowSeconds = max(1, $windowSeconds);

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }

        $filePath = $this->storageDir . '/' . hash('sha256', $key) . '.json';
        $now      = time();
        $cutoff   = $now - $windowSeconds;

        $fp = @fopen($filePath, 'c+');
        if ($fp === false) {
            // Không thể mở tệp lưu trữ: cho phép đi tiếp để tránh làm sập toàn bộ tính năng nhưng ghi log cảnh báo
            error_log('[RateLimiter] Không thể mở tệp rate limit tại: ' . $this->storageDir);
            return [
                'allowed'     => true,
                'remaining'   => $maxRequests,
                'limit'       => $maxRequests,
                'retry_after' => 0,
            ];
        }

        try {
            flock($fp, LOCK_EX);
            rewind($fp);
            $raw = stream_get_contents($fp);
            $timestamps = [];

            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $ts) {
                        $tsInt = (int)$ts;
                        if ($tsInt > $cutoff && $tsInt <= $now + 5) {
                            $timestamps[] = $tsInt;
                        }
                    }
                }
            }

            if (count($timestamps) >= $maxRequests) {
                sort($timestamps);
                $oldest     = (int)$timestamps[0];
                $retryAfter = max(1, ($oldest + $windowSeconds) - $now);

                // Cập nhật lại danh sách đã lọc bản ghi hết hạn
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode(array_values($timestamps)));
                fflush($fp);

                return [
                    'allowed'     => false,
                    'remaining'   => 0,
                    'limit'       => $maxRequests,
                    'retry_after' => $retryAfter,
                ];
            }

            $timestamps[] = $now;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode(array_values($timestamps)));
            fflush($fp);

            return [
                'allowed'     => true,
                'remaining'   => max(0, $maxRequests - count($timestamps)),
                'limit'       => $maxRequests,
                'retry_after' => 0,
            ];
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Lấy địa chỉ IP client an toàn (ưu tiên REMOTE_ADDR để chống giả mạo X-Forwarded-For).
     */
    public static function clientIp(): string
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        return $ip !== '' ? $ip : '127.0.0.1';
    }
}
