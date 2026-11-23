-- Migration: 20261118_comments_hierarchy_and_mentions.sql
-- Additive schema changes for:
--   1. Multi-level comment hierarchy & soft-delete preservation (comments.root_id, is_deleted, deleted_at, deleted_by)
--   2. Dynamic @Mention storage linked to real user_id (comment_mentions)
-- Safe execution via idempotent PHP runner: php scripts/20261118_comments_hierarchy_and_mentions.php

ALTER TABLE comments
    ADD COLUMN root_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER parent_id,
    ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER moderation_status,
    ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER is_deleted,
    ADD COLUMN deleted_by BIGINT UNSIGNED NULL DEFAULT NULL AFTER deleted_at,
    ADD INDEX idx_comments_root (root_id),
    ADD INDEX idx_comments_post_deleted (post_id, is_deleted, moderation_status),
    ADD CONSTRAINT fk_comments_root FOREIGN KEY (root_id) REFERENCES comments(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_comments_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS comment_mentions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    comment_id BIGINT UNSIGNED NOT NULL,
    mentioned_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_comment_mention (comment_id, mentioned_user_id),
    INDEX idx_cm_user (mentioned_user_id, created_at),
    CONSTRAINT fk_cm_comment FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_user FOREIGN KEY (mentioned_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ROLLBACK REFERENCE (only run if reverting this migration):
-- DROP TABLE IF EXISTS comment_mentions;
-- ALTER TABLE comments
--     DROP FOREIGN KEY fk_comments_root,
--     DROP FOREIGN KEY fk_comments_deleted_by,
--     DROP INDEX idx_comments_root,
--     DROP INDEX idx_comments_post_deleted,
--     DROP COLUMN deleted_by,
--     DROP COLUMN deleted_at,
--     DROP COLUMN is_deleted,
--     DROP COLUMN root_id;
