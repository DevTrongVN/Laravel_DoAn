-- =============================================================================
-- ĐỀ XUẤT MIGRATION P1 — BỔ SUNG KHÓA NGOẠI CHO bookmarks & ai_moderation_items
-- Dự án: Góc Nhỏ (DoAnMaNguonMo) — Database: doan_manguonmo_db (InnoDB)
-- Ngày chuẩn bị: 2026-11-18
--
-- [QUAN TRỌNG] TỆP NÀY CHỈ LÀ ĐỀ XUẤT — CHƯA ĐƯỢC THỰC THI TRÊN DATABASE THẬT.
-- Vui lòng sao lưu (mysqldump) và xác nhận phương án xử lý user_id trên
-- ai_moderation_items trước khi chạy thủ công.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- BƯỚC 0: KIỂM TRA TIỀN ĐIỀU KIỆN (READ-ONLY) TRƯỚC KHI CHẠY MIGRATION
-- Đảm bảo tất cả kết quả đếm mồ côi = 0 trước khi ADD CONSTRAINT.
-- -----------------------------------------------------------------------------
SELECT 'bookmarks_orphan_user' AS check_name, COUNT(*) AS cnt
FROM bookmarks b LEFT JOIN users u ON u.id = b.user_id WHERE u.id IS NULL
UNION ALL
SELECT 'bookmarks_orphan_post', COUNT(*)
FROM bookmarks b LEFT JOIN posts p ON p.id = b.post_id WHERE p.id IS NULL
UNION ALL
SELECT 'aimod_orphan_user', COUNT(*)
FROM ai_moderation_items a LEFT JOIN users u ON u.id = a.user_id WHERE a.user_id IS NOT NULL AND u.id IS NULL
UNION ALL
SELECT 'aimod_orphan_post', COUNT(*)
FROM ai_moderation_items a LEFT JOIN posts p ON p.id = a.post_id WHERE a.post_id IS NOT NULL AND p.id IS NULL
UNION ALL
SELECT 'aimod_orphan_reviewer', COUNT(*)
FROM ai_moderation_items a LEFT JOIN users u ON u.id = a.reviewed_by WHERE a.reviewed_by IS NOT NULL AND u.id IS NULL;

-- -----------------------------------------------------------------------------
-- BƯỚC 1: KHÓA NGOẠI CHO BẢNG bookmarks
-- Hiện trạng đã kiểm toán (18/11/2026): 6 bản ghi, 0 mồ côi, 0 trùng lặp,
-- đã có sẵn UNIQUE uq_user_post(user_id, post_id), idx_bookmarks_user, idx_bookmarks_post.
-- Kiểu cột: user_id BIGINT UNSIGNED NOT NULL, post_id BIGINT UNSIGNED NOT NULL.
-- Quy tắc ON DELETE CASCADE: Khi xóa user hoặc bài viết, bookmark không còn ý nghĩa
-- nghiệp vụ và cần tự động xóa đồng bộ với bảng interactions (ON DELETE CASCADE).
-- -----------------------------------------------------------------------------
ALTER TABLE bookmarks
    ADD CONSTRAINT fk_bookmarks_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    ADD CONSTRAINT fk_bookmarks_post
        FOREIGN KEY (post_id) REFERENCES posts(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE;

-- -----------------------------------------------------------------------------
-- BƯỚC 2: KHÓA NGOẠI CHO BẢNG ai_moderation_items
-- Hiện trạng đã kiểm toán (18/11/2026): 197 bản ghi (100% item_type='post'),
-- 0 NULL, 0 mồ côi, 0 trùng lặp.
--
-- Lưu ý nghiệp vụ QUAN TRỌNG:
-- 1. item_id là khóa ĐA HÌNH (polymorphic): tham chiếu posts.id khi item_type='post'
--    và comments.id khi item_type='comment' => KHÔNG thể gắn 1 FK SQL trực tiếp lên item_id.
--    Tiếp tục quản lý toàn vẹn qua composite index idx_aimod_item(item_type, item_id).
-- 2. post_id (BIGINT UNSIGNED NULL): Dùng ON DELETE SET NULL để BẢO TOÀN LỊCH SỬ
--    KIỂM DUYỆT sau khi Admin xóa bài viết vi phạm tại AiModerationAdminController::decision('delete')
--    (vì controller ghi status='dismissed', final_decision='delete' ngay trước khi xóa bài).
-- 3. reviewed_by (BIGINT UNSIGNED NULL): Dùng ON DELETE SET NULL để bảo toàn lịch sử duyệt.
-- 4. user_id (hiện là BIGINT UNSIGNED NOT NULL):
--    - PHƯƠNG ÁN KHUYẾN NGHỊ (bảo toàn lịch sử kiểm duyệt): chuyển thành NULL + ON DELETE SET NULL.
--    - PHƯƠNG ÁN THAY THẾ (xóa sạch log khi xóa user): giữ NOT NULL + ON DELETE CASCADE.
-- -----------------------------------------------------------------------------

-- Phương án khuyến nghị (giữ lịch sử kiểm duyệt bằng ON DELETE SET NULL):
ALTER TABLE ai_moderation_items
    MODIFY COLUMN user_id BIGINT UNSIGNED NULL,
    ADD INDEX idx_aimod_post (post_id),
    ADD INDEX idx_aimod_reviewer (reviewed_by),
    ADD CONSTRAINT fk_aimod_post
        FOREIGN KEY (post_id) REFERENCES posts(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    ADD CONSTRAINT fk_aimod_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    ADD CONSTRAINT fk_aimod_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;

-- (Tùy chọn dọn index trùng lặp: idx_aim_type_item trùng hệt idx_aimod_item(item_type, item_id))
-- ALTER TABLE ai_moderation_items DROP INDEX idx_aim_type_item;
