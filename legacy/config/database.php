<?php
declare(strict_types=1);

/** Cấu hình MySQL mặc định cho Laragon; có thể ghi đè bằng biến môi trường. */
return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'dbname' => getenv('DB_NAME') ?: 'doan_manguonmo_db',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];
