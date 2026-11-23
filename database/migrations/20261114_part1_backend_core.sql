-- ============================================================
-- Migration: Phần 1 Backend Core
-- Tạo ngày: 2026-11-14
-- Mô tả: comment_reports, post_reports history, posts AI columns,
--         verified_badge_logs
-- Tương thích MySQL 8.0 / 8.4+
-- ============================================================

-- ----------------------------------------------------------------
-- 1. Bảng comment_reports
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comment_reports` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `comment_id`  BIGINT UNSIGNED NOT NULL,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `reason`      ENUM('offensive','spam','inappropriate','misinformation','other') NOT NULL,
    `details`     VARCHAR(1000)   NULL,
    `status`      ENUM('pending','reviewed','rejected') NOT NULL DEFAULT 'pending',
    `reviewed_by` BIGINT UNSIGNED NULL,
    `reviewed_at` TIMESTAMP       NULL,
    `admin_note`  VARCHAR(1000)   NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_comment_report` (`user_id`, `comment_id`),
    INDEX `idx_cr_status` (`status`, `created_at`),
    CONSTRAINT `fk_cr_comment`   FOREIGN KEY (`comment_id`)  REFERENCES `comments`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cr_user`      FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_cr_reviewer`  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 2. Bảng verified_badge_logs
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `verified_badge_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `admin_id`   BIGINT UNSIGNED NOT NULL,
    `action`     ENUM('grant','revoke') NOT NULL,
    `reason`     VARCHAR(500)    NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_vbl_user` (`user_id`, `created_at`),
    CONSTRAINT `fk_vbl_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vbl_admin` FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 3. Cột lịch sử xử lý cho post_reports
-- ----------------------------------------------------------------
SET @dbname = DATABASE();

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'post_reports' AND COLUMN_NAME = 'reviewed_by');
SET @s = IF(@col_exists = 0, 'ALTER TABLE `post_reports` ADD COLUMN `reviewed_by` BIGINT UNSIGNED NULL AFTER `status`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'post_reports' AND COLUMN_NAME = 'reviewed_at');
SET @s = IF(@col_exists = 0, 'ALTER TABLE `post_reports` ADD COLUMN `reviewed_at` TIMESTAMP NULL AFTER `reviewed_by`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'post_reports' AND COLUMN_NAME = 'admin_note');
SET @s = IF(@col_exists = 0, 'ALTER TABLE `post_reports` ADD COLUMN `admin_note` VARCHAR(1000) NULL AFTER `reviewed_at`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @dbname AND TABLE_NAME = 'post_reports' AND CONSTRAINT_NAME = 'fk_pr_reviewer');
SET @s = IF(@fk_exists = 0, 'ALTER TABLE `post_reports` ADD CONSTRAINT `fk_pr_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------
-- 4. Bổ sung cột AI vào bảng posts
-- ----------------------------------------------------------------
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'ai_severity');
SET @s = IF(@col_exists = 0, "ALTER TABLE `posts` ADD COLUMN `ai_severity` ENUM('low','medium','high') NULL AFTER `moderation_status`", 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'ai_violation_type');
SET @s = IF(@col_exists = 0, 'ALTER TABLE `posts` ADD COLUMN `ai_violation_type` VARCHAR(50) NULL AFTER `ai_severity`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'ai_reason');
SET @s = IF(@col_exists = 0, 'ALTER TABLE `posts` ADD COLUMN `ai_reason` TEXT NULL AFTER `ai_violation_type`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'ai_news_suggested');
SET @s = IF(@col_exists = 0, 'ALTER TABLE `posts` ADD COLUMN `ai_news_suggested` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ai_reason`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'ai_news_reason');
SET @s = IF(@col_exists = 0, 'ALTER TABLE `posts` ADD COLUMN `ai_news_reason` TEXT NULL AFTER `ai_news_suggested`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'ai_status');
SET @s = IF(@col_exists = 0, "ALTER TABLE `posts` ADD COLUMN `ai_status` ENUM('pending','approved','rejected','processed') NULL AFTER `ai_news_reason`", 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'moderated_by');
SET @s = IF(@col_exists = 0, 'ALTER TABLE `posts` ADD COLUMN `moderated_by` BIGINT UNSIGNED NULL AFTER `ai_status`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'moderated_at');
SET @s = IF(@col_exists = 0, 'ALTER TABLE `posts` ADD COLUMN `moderated_at` TIMESTAMP NULL AFTER `moderated_by`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @dbname AND TABLE_NAME = 'posts' AND CONSTRAINT_NAME = 'fk_posts_moderator');
SET @s = IF(@fk_exists = 0, 'ALTER TABLE `posts` ADD CONSTRAINT `fk_posts_moderator` FOREIGN KEY (`moderated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
