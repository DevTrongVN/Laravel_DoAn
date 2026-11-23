-- Run once on existing installations. This preserves existing likes by migrating them below.
ALTER TABLE posts
    ADD COLUMN shared_post_id BIGINT UNSIGNED NULL AFTER moderation_status,
    ADD COLUMN share_comment TEXT NULL AFTER shared_post_id,
    ADD CONSTRAINT fk_posts_shared_post FOREIGN KEY (shared_post_id) REFERENCES posts(id) ON DELETE SET NULL;

ALTER TABLE comments ADD COLUMN edited_at TIMESTAMP NULL DEFAULT NULL AFTER moderation_status;

ALTER TABLE interactions MODIFY type ENUM('like', 'share', 'bookmark') NOT NULL;

CREATE TABLE post_reactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    post_id BIGINT UNSIGNED NOT NULL,
    reaction ENUM('like', 'love', 'haha', 'sad', 'angry') NOT NULL DEFAULT 'like',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_post_reaction_user (user_id, post_id),
    INDEX idx_post_reaction_post (post_id, reaction),
    CONSTRAINT fk_post_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_post_reactions_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO post_reactions (user_id, post_id, reaction, created_at)
SELECT user_id, post_id, 'like', MIN(created_at)
FROM interactions WHERE type = 'like'
GROUP BY user_id, post_id
ON DUPLICATE KEY UPDATE reaction = VALUES(reaction);

DELETE FROM interactions WHERE type = 'like';

CREATE TABLE comment_likes (
    comment_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    reaction ENUM('like', 'love', 'haha', 'sad', 'angry') NOT NULL DEFAULT 'like',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comment_id, user_id),
    CONSTRAINT fk_comment_likes_comment FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_comment_likes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
