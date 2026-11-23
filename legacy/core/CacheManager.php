<?php
declare(strict_types=1);

/** Quản lý Lưu / Đọc / Xóa Cache (Hỗ trợ File-based Cache). */
final class CacheManager
{
    private static string $cacheDir = __DIR__ . '/../storage/cache';

    public static function get(string $key, mixed $default = null): mixed
    {
        $filePath = self::getFilePath($key);
        if (!file_exists($filePath)) {
            return $default;
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            return $default;
        }

        $data = @unserialize($content);
        if (!is_array($data) || !isset($data['expires_at'], $data['payload'])) {
            @unlink($filePath);
            return $default;
        }

        if (time() > $data['expires_at']) {
            @unlink($filePath);
            return $default;
        }

        return $data['payload'];
    }

    public static function set(string $key, mixed $value, int $ttlSeconds = 900): bool
    {
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0777, true);
        }

        $data = [
            'expires_at' => time() + $ttlSeconds,
            'payload' => $value,
        ];

        return (bool)@file_put_contents(self::getFilePath($key), serialize($data), LOCK_EX);
    }

    public static function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $cached = self::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $fresh = $callback();
        self::set($key, $fresh, $ttlSeconds);
        return $fresh;
    }

    public static function forget(string $key): bool
    {
        $filePath = self::getFilePath($key);
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }
        return true;
    }

    public static function flush(): bool
    {
        if (!is_dir(self::$cacheDir)) return true;
        $files = glob(self::$cacheDir . '/*.cache');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) @unlink($file);
            }
        }
        return true;
    }

    private static function getFilePath(string $key): string
    {
        return self::$cacheDir . '/' . md5($key) . '.cache';
    }
}
