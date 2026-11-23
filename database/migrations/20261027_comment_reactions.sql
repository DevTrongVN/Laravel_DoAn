-- Run once after the main reactions/reposts migration.
ALTER TABLE comment_likes
    ADD COLUMN reaction ENUM('like', 'love', 'haha', 'sad', 'angry') NOT NULL DEFAULT 'like' AFTER user_id;
