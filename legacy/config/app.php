<?php
declare(strict_types=1);

/** Cấu hình URL gốc và trọng số tính điểm uy tín. */
if (!defined('APP_URL')) {
    $envUrl = getenv('APP_URL');
    if ($envUrl !== false && $envUrl !== '') {
        $appUrl = rtrim((string)$envUrl, '/');
    } elseif (isset($_SERVER['SCRIPT_NAME'])) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $appUrl = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    } else {
        $appUrl = '/DoAnMaNguonMo/public';
    }
    define('APP_URL', $appUrl);
}

return [
    'app_url' => APP_URL,
    'reputation' => [
        'W_like' => 2,
        'W_share' => 5,
        'W_comment' => 0,
        'W_follow' => 10,
        'ticket_threshold' => 100,
        'vip_news_threshold' => 500,
    ],
];
