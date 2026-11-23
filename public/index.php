<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Tương thích ngược: Xử lý request dạng index.php?url=... từ Frontend JavaScript
if (isset($_GET['url']) && trim((string)$_GET['url']) !== '') {
    $legacyPath = '/' . ltrim((string)$_GET['url'], '/');
    unset($_GET['url']);
    $queryString = http_build_query($_GET);

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $scriptDir = str_replace('\\', '/', dirname($scriptName));
    $basePrefix = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');

    $_SERVER['REQUEST_URI'] = $basePrefix . $legacyPath . ($queryString !== '' ? '?' . $queryString : '');
    $_SERVER['QUERY_STRING'] = $queryString;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
