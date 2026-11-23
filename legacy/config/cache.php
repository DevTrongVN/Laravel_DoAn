<?php
declare(strict_types=1);

/** Cấu hình hệ thống Cache (File / Redis Cache). */
return [
    'driver' => getenv('CACHE_DRIVER') ?: 'file', // 'file' hoặc 'redis'
    'path' => __DIR__ . '/../storage/cache',
    'lifetime' => [
        'widgets' => 900,         // 15 phút cho Top Tin HOT & Top Hashtags
        'reputation' => 1800,     // 30 phút cho điểm uy tín
        'feed' => 300,            // 5 phút cho Feed chung
    ],
    'redis' => [
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('REDIS_PORT') ?: 6379),
        'password' => getenv('REDIS_PASSWORD') ?: null,
        'database' => (int)(getenv('REDIS_DB') ?: 0),
    ],
];
