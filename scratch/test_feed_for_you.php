<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Models/Post.php';

$postModel = new Post();

echo "1. Test mode=for_you, type=news, viewerId=null:\n";
try {
    $res = $postModel->list([
        'mode' => 'for_you',
        'type' => 'news',
    ], null, 1, 10);
    echo "SUCCESS: Found " . count($res['data']) . " posts. Total: " . $res['total'] . "\n";
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}

echo "\n2. Test mode=for_you, type=news, viewerId=1:\n";
try {
    $res = $postModel->list([
        'mode' => 'for_you',
        'type' => 'news',
    ], 1, 1, 10);
    echo "SUCCESS: Found " . count($res['data']) . " posts. Total: " . $res['total'] . "\n";
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}

echo "\n3. Test mode=all, viewerId=null:\n";
try {
    $res = $postModel->list([
        'mode' => 'all',
    ], null, 1, 10);
    echo "SUCCESS: Found " . count($res['data']) . " posts. Total: " . $res['total'] . "\n";
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
