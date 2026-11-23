-- Add visit ranking support for profiles already using the follows table.
-- Run once against doan_manguonmo_db if the column is not present yet.
ALTER TABLE follows
    ADD COLUMN visit_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER following_id,
    ADD INDEX idx_follows_visit_rank (follower_id, visit_count);
