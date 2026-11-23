<?php
require_once __DIR__ . '/../core/Database.php';

$pdo = Database::getInstance()->getConnection();

// Create bookmarks table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS bookmarks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    post_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_post (user_id, post_id),
    KEY idx_bookmarks_user (user_id),
    KEY idx_bookmarks_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$pdo->exec($sql);
echo "Table bookmarks created or already exists.\n";

// Sync existing bookmarks from interactions table
$syncSql = "INSERT IGNORE INTO bookmarks (user_id, post_id, created_at)
            SELECT user_id, post_id, created_at FROM interactions WHERE type = 'bookmark'";
$count = $pdo->exec($syncSql);
echo "Synced {$count} existing bookmarks from interactions.\n";

// Verify bookmarks table
$stmt = $pdo->query("SHOW COLUMNS FROM bookmarks");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
