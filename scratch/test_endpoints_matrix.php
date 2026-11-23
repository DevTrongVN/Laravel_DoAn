<?php
declare(strict_types=1);

$urls = [
    'http://localhost/DoAnMaNguonMo/public/api/posts?tab=for_you&type=news',
    'http://localhost/DoAnMaNguonMo/public/api/posts?tab=for_you&type=blog',
    'http://localhost/DoAnMaNguonMo/public/api/posts?tab=for_you',
    'http://localhost/DoAnMaNguonMo/public/api/posts?tab=home',
    'http://localhost/DoAnMaNguonMo/public/api/posts',
    'http://localhost/DoAnMaNguonMo/public/api/feed?mode=for_you&type=news',
    'http://localhost/DoAnMaNguonMo/public/api/feed?mode=for_you',
    'http://localhost/DoAnMaNguonMo/public/api/feed?mode=all',
    'http://localhost/DoAnMaNguonMo/public/api/feed',
];

echo "================================================================================\n";
echo "KIỂM TRA CÁC ENDPOINT FEED VÀ BỘ LỌC TỔNG HỢP\n";
echo "================================================================================\n";

foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode((string)$response, true);
    $dataCount = is_array($json['data'] ?? null) ? count($json['data']) : 0;
    $total = $json['pagination']['total'] ?? ($json['total'] ?? 'N/A');

    $status = ($httpCode === 200 && $dataCount > 0) ? '[200 OK]' : "[ERROR {$httpCode}]";
    echo sprintf("%-12s | Count: %2d | Total: %-5s | URL: %s\n", $status, $dataCount, (string)$total, $url);
}
