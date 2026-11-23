<?php
require_once __DIR__ . '/../core/Database.php';

$pdo = Database::getInstance()->getConnection();

$sql = "CREATE TABLE IF NOT EXISTS ai_moderation_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('post', 'comment') NOT NULL DEFAULT 'post',
    item_id BIGINT UNSIGNED NOT NULL,
    post_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    violation_type VARCHAR(50) NOT NULL DEFAULT 'inappropriate',
    severity ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'low',
    ai_reason TEXT NOT NULL,
    suggested_action VARCHAR(50) NOT NULL DEFAULT 'flag',
    status ENUM('pending', 'approved', 'rejected', 'dismissed', 'processed') NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_aimod_status (status),
    KEY idx_aimod_severity (severity),
    KEY idx_aimod_item (item_type, item_id),
    KEY idx_aimod_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$pdo->exec($sql);
echo "Table ai_moderation_items created or already exists.\n";

// Sync any existing flagged / blocked posts from posts table
$syncSql = "INSERT IGNORE INTO ai_moderation_items (item_type, item_id, post_id, user_id, violation_type, severity, ai_reason, suggested_action, status, created_at)
SELECT 'post', id, id, user_id, 
       COALESCE(ai_violation_type, 'inappropriate'), 
       COALESCE(ai_severity, 'medium'), 
       COALESCE(ai_reason, 'Nội dung bị gắn cờ cần kiểm duyệt'), 
       IF(moderation_status='blocked', 'block', 'flag'), 
       IF(ai_status IN ('approved','rejected','processed'), ai_status, 'pending'), 
       created_at
FROM posts
WHERE moderation_status IN ('flagged', 'blocked') OR ai_severity IS NOT NULL";

$synced = $pdo->exec($syncSql);
echo "Synced {$synced} existing flagged/blocked posts into ai_moderation_items.\n";

$stmt = $pdo->query("SHOW COLUMNS FROM ai_moderation_items");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
