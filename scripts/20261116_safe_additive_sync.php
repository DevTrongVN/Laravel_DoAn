<?php
declare(strict_types=1);

/**
 * Migration Script: Safe Additive Synchronization for "Góc Nhỏ"
 * Date: 2026-11-16
 * Description: Safely adds missing columns and creates missing tables without modifying existing data.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$config = require __DIR__ . '/../config/database.php';
$host = $config['host'];
$port = (int)$config['port'];
$dbname = $config['dbname'];
$user = $config['username'];
$pass = $config['password'];

echo "=== STARTING SAFE ADDITIVE MIGRATION FOR {$dbname} ===\n\n";

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch();
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetch();
}

function addColumnSafe(PDO $pdo, string $table, string $column, string $definition): void {
    if (columnExists($pdo, $table, $column)) {
        echo "  [SKIP] Column `{$table}`.`{$column}` already exists.\n";
        return;
    }
    $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
    $pdo->exec($sql);
    echo "  [ADDED] Column `{$table}`.`{$column}` successfully added.\n";
}

// ------------------------------------------------------------------
// 1. ADD MISSING COLUMNS TO `posts`
// ------------------------------------------------------------------
echo "1. Checking columns for table `posts`...\n";
addColumnSafe($pdo, 'posts', 'ai_severity', "ENUM('low','medium','high') NULL DEFAULT NULL AFTER `moderation_status`");
addColumnSafe($pdo, 'posts', 'ai_violation_type', "VARCHAR(50) NULL DEFAULT NULL AFTER `ai_severity`");
addColumnSafe($pdo, 'posts', 'ai_reason', "TEXT NULL DEFAULT NULL AFTER `ai_violation_type`");
addColumnSafe($pdo, 'posts', 'ai_news_suggested', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `ai_reason`");
addColumnSafe($pdo, 'posts', 'ai_news_reason', "TEXT NULL DEFAULT NULL AFTER `ai_news_suggested`");
addColumnSafe($pdo, 'posts', 'ai_status', "ENUM('pending','approved','rejected','processed') NULL DEFAULT NULL AFTER `ai_news_reason`");
addColumnSafe($pdo, 'posts', 'moderated_by', "BIGINT UNSIGNED NULL DEFAULT NULL AFTER `ai_status`");
addColumnSafe($pdo, 'posts', 'moderated_at', "TIMESTAMP NULL DEFAULT NULL AFTER `moderated_by`");
addColumnSafe($pdo, 'posts', 'ai_analyzed_at', "TIMESTAMP NULL DEFAULT NULL AFTER `moderated_at`");
addColumnSafe($pdo, 'posts', 'ai_suggested_action', "VARCHAR(50) NULL DEFAULT NULL AFTER `ai_analyzed_at`");
addColumnSafe($pdo, 'posts', 'final_decision', "VARCHAR(20) NULL DEFAULT NULL AFTER `ai_suggested_action`");

// Check foreign key fk_posts_moderator
try {
    $fkCheck = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND CONSTRAINT_NAME = 'fk_posts_moderator'")->fetch();
    if (!$fkCheck) {
        $pdo->exec("ALTER TABLE `posts` ADD CONSTRAINT `fk_posts_moderator` FOREIGN KEY (`moderated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL");
        echo "  [ADDED] Foreign key `fk_posts_moderator` on `posts`.\n";
    } else {
        echo "  [SKIP] Foreign key `fk_posts_moderator` already exists.\n";
    }
} catch (Throwable $e) {
    echo "  [NOTE] Foreign key check note: " . $e->getMessage() . "\n";
}

// ------------------------------------------------------------------
// 2. ADD MISSING COLUMNS TO `comments`
// ------------------------------------------------------------------
echo "\n2. Checking columns for table `comments`...\n";
addColumnSafe($pdo, 'comments', 'ai_severity', "ENUM('low','medium','high') NULL DEFAULT NULL");
addColumnSafe($pdo, 'comments', 'ai_violation_type', "VARCHAR(50) NULL DEFAULT NULL");
addColumnSafe($pdo, 'comments', 'ai_reason', "TEXT NULL DEFAULT NULL");
addColumnSafe($pdo, 'comments', 'ai_status', "ENUM('pending','approved','rejected') NULL DEFAULT NULL");

// ------------------------------------------------------------------
// 3. CREATE TABLE `bookmarks`
// ------------------------------------------------------------------
echo "\n3. Checking table `bookmarks`...\n";
if (!tableExists($pdo, 'bookmarks')) {
    $pdo->exec("
        CREATE TABLE `bookmarks` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `post_id` BIGINT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_user_post` (`user_id`, `post_id`),
            KEY `idx_bookmarks_user` (`user_id`),
            KEY `idx_bookmarks_post` (`post_id`),
            CONSTRAINT `fk_bookmarks_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_bookmarks_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  [CREATED] Table `bookmarks`.\n";

    // Sync any existing bookmark interactions
    $synced = $pdo->exec("
        INSERT IGNORE INTO `bookmarks` (`user_id`, `post_id`, `created_at`)
        SELECT `user_id`, `post_id`, `created_at` FROM `interactions` WHERE `type` = 'bookmark'
    ");
    echo "  [SYNCED] {$synced} bookmark interactions copied to `bookmarks`.\n";
} else {
    echo "  [SKIP] Table `bookmarks` already exists.\n";
}

// ------------------------------------------------------------------
// 4. CREATE TABLE `post_reports`
// ------------------------------------------------------------------
echo "\n4. Checking table `post_reports`...\n";
if (!tableExists($pdo, 'post_reports')) {
    $pdo->exec("
        CREATE TABLE `post_reports` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `post_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `reason` ENUM('offensive','spam','inappropriate','misinformation','other','impersonation','harassment') NOT NULL,
            `details` VARCHAR(1000) NULL,
            `status` ENUM('pending', 'reviewed', 'dismissed') NOT NULL DEFAULT 'pending',
            `reviewed_by` BIGINT UNSIGNED NULL,
            `reviewed_at` TIMESTAMP NULL,
            `admin_note` VARCHAR(1000) NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_post_reports_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_post_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_post_reports_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            UNIQUE KEY `uq_post_report_user_post` (`user_id`, `post_id`),
            INDEX `idx_post_reports_status` (`status`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  [CREATED] Table `post_reports`.\n";
} else {
    echo "  [SKIP] Table `post_reports` already exists.\n";
    // Ensure all columns exist if it was partially created
    addColumnSafe($pdo, 'post_reports', 'reviewed_by', "BIGINT UNSIGNED NULL AFTER `status`");
    addColumnSafe($pdo, 'post_reports', 'reviewed_at', "TIMESTAMP NULL AFTER `reviewed_by`");
    addColumnSafe($pdo, 'post_reports', 'admin_note', "VARCHAR(1000) NULL AFTER `reviewed_at`");
}

// ------------------------------------------------------------------
// 5. CREATE TABLE `comment_reports`
// ------------------------------------------------------------------
echo "\n5. Checking table `comment_reports`...\n";
if (!tableExists($pdo, 'comment_reports')) {
    $pdo->exec("
        CREATE TABLE `comment_reports` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `comment_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `reason` ENUM('offensive','spam','inappropriate','misinformation','other') NOT NULL,
            `details` VARCHAR(1000) NULL,
            `status` ENUM('pending','reviewed','rejected') NOT NULL DEFAULT 'pending',
            `reviewed_by` BIGINT UNSIGNED NULL,
            `reviewed_at` TIMESTAMP NULL,
            `admin_note` VARCHAR(1000) NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_comment_report` (`user_id`, `comment_id`),
            INDEX `idx_cr_status` (`status`, `created_at`),
            CONSTRAINT `fk_cr_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_cr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_cr_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  [CREATED] Table `comment_reports`.\n";
} else {
    echo "  [SKIP] Table `comment_reports` already exists.\n";
}

// ------------------------------------------------------------------
// 6. CREATE TABLE `post_appeals`
// ------------------------------------------------------------------
echo "\n6. Checking table `post_appeals`...\n";
if (!tableExists($pdo, 'post_appeals')) {
    $pdo->exec("
        CREATE TABLE `post_appeals` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `post_id` BIGINT UNSIGNED NOT NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `reason` TEXT NOT NULL,
            `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            `admin_response` TEXT NULL,
            `reviewed_by` BIGINT UNSIGNED NULL,
            `reviewed_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT `fk_post_appeals_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_post_appeals_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_post_appeals_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_appeals_status` (`status`, `created_at`),
            INDEX `idx_appeals_post` (`post_id`, `user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  [CREATED] Table `post_appeals`.\n";
} else {
    echo "  [SKIP] Table `post_appeals` already exists.\n";
}

// ------------------------------------------------------------------
// 7. CREATE TABLE `verified_badge_logs`
// ------------------------------------------------------------------
echo "\n7. Checking table `verified_badge_logs`...\n";
if (!tableExists($pdo, 'verified_badge_logs')) {
    $pdo->exec("
        CREATE TABLE `verified_badge_logs` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `admin_id` BIGINT UNSIGNED NOT NULL,
            `action` ENUM('grant','revoke') NOT NULL,
            `reason` VARCHAR(500) NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_vbl_user` (`user_id`, `created_at`),
            CONSTRAINT `fk_vbl_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_vbl_admin` FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  [CREATED] Table `verified_badge_logs`.\n";
} else {
    echo "  [SKIP] Table `verified_badge_logs` already exists.\n";
}

// ------------------------------------------------------------------
// 8. CREATE TABLE `ai_moderation_items`
// ------------------------------------------------------------------
echo "\n8. Checking table `ai_moderation_items`...\n";
if (!tableExists($pdo, 'ai_moderation_items')) {
    $pdo->exec("
        CREATE TABLE `ai_moderation_items` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `item_type` ENUM('post', 'comment') NOT NULL DEFAULT 'post',
            `item_id` BIGINT UNSIGNED NOT NULL,
            `post_id` BIGINT UNSIGNED NULL,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `violation_type` VARCHAR(50) NOT NULL DEFAULT 'inappropriate',
            `severity` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'low',
            `ai_reason` TEXT NOT NULL,
            `suggested_action` VARCHAR(50) NOT NULL DEFAULT 'flag',
            `status` ENUM('pending', 'approved', 'rejected', 'dismissed', 'processed') NOT NULL DEFAULT 'pending',
            `admin_note` TEXT NULL,
            `reviewed_by` BIGINT UNSIGNED NULL,
            `reviewed_at` DATETIME NULL,
            `final_decision` VARCHAR(20) NULL DEFAULT NULL,
            `final_decision_at` DATETIME NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_aimod_status` (`status`),
            KEY `idx_aimod_severity` (`severity`),
            KEY `idx_aimod_item` (`item_type`, `item_id`),
            KEY `idx_aimod_user` (`user_id`),
            CONSTRAINT `fk_aimod_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  [CREATED] Table `ai_moderation_items`.\n";

    // Sync any existing flagged/blocked posts
    $syncedAi = $pdo->exec("
        INSERT IGNORE INTO `ai_moderation_items` (`item_type`, `item_id`, `post_id`, `user_id`, `violation_type`, `severity`, `ai_reason`, `suggested_action`, `status`, `created_at`)
        SELECT 'post', `id`, `id`, `user_id`,
               COALESCE(`ai_violation_type`, 'inappropriate'),
               COALESCE(`ai_severity`, 'medium'),
               COALESCE(`ai_reason`, 'Nội dung bị gắn cờ cần kiểm duyệt'),
               IF(`moderation_status` = 'blocked', 'block', 'flag'),
               IF(`ai_status` IN ('approved','rejected','processed'), `ai_status`, 'pending'),
               `created_at`
        FROM `posts`
        WHERE `moderation_status` IN ('flagged', 'blocked') OR `ai_severity` IS NOT NULL
    ");
    echo "  [SYNCED] {$syncedAi} items synced to `ai_moderation_items`.\n";
} else {
    echo "  [SKIP] Table `ai_moderation_items` already exists.\n";
}

echo "\n=== SAFE ADDITIVE MIGRATION COMPLETED SUCCESSFULLY! ===\n";
