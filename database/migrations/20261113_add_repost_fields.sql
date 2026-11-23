-- Migration: Add repost fields to posts table
-- Safe and idempotent migration for Repost support
-- Workspace: C:\laragon\www\DoAnMaNguonMo

SET @dbname = DATABASE();

-- 1. Add shared_post_id column if not exists
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'shared_post_id'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE posts ADD COLUMN shared_post_id BIGINT UNSIGNED NULL AFTER moderation_status', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Add share_comment column if not exists
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'posts' AND COLUMN_NAME = 'share_comment'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE posts ADD COLUMN share_comment TEXT NULL AFTER shared_post_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Add foreign key fk_posts_shared_post if not exists
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'posts' AND CONSTRAINT_NAME = 'fk_posts_shared_post'
);
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE posts ADD CONSTRAINT fk_posts_shared_post FOREIGN KEY (shared_post_id) REFERENCES posts(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
