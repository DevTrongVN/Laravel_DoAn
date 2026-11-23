-- Migration: Tạo bảng theo dõi lượt xem bài viết (Post View Tracking) với chu kỳ 7 ngày chống spam
CREATE TABLE IF NOT EXISTS post_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    viewer_hash VARCHAR(80) NOT NULL,
    last_viewed_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_post_viewer (post_id, viewer_hash),
    INDEX idx_post_views_user (user_id),
    INDEX idx_post_views_last (last_viewed_at),
    CONSTRAINT fk_post_views_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_post_views_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
