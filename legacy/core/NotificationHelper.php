<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/** Helper tạo & gửi thông báo nội bộ cho người dùng. */
final class NotificationHelper
{
    public static function send(
        int $userId,
        ?int $actorId,
        string $type,
        string $content,
        string $targetUrl = ''
    ): int {
        // Không tự gửi thông báo cho chính mình nếu actor == user
        if ($actorId !== null && $actorId === $userId && $type !== 'system') {
            return 0;
        }

        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare('
                INSERT INTO notifications (user_id, actor_id, `type`, content, target_url, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ');
            $stmt->execute([$userId, $actorId, $type, $content, $targetUrl]);
            return (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('Lỗi tạo thông báo: ' . $e->getMessage());
            return 0;
        }
    }

    public static function markAsRead(int $notificationId, int $userId): bool
    {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            return $stmt->execute([$notificationId, $userId]);
        } catch (Throwable) {
            return false;
        }
    }

    public static function markAllAsRead(int $userId): bool
    {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
            return $stmt->execute([$userId]);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Gửi thông báo hệ thống khi Admin xử lý bài viết / bình luận vi phạm.
     * Tuyệt đối ẩn danh người báo cáo: actor_id luôn để NULL, loại thông báo là 'system'.
     */
    public static function sendSystemModerationNotice(
        int $targetUserId,
        string $contentType, // 'post' | 'comment'
        string $itemTitleOrExcerpt,
        string $actionTaken, // 'bị tạm khóa' | 'bị cảnh báo' | 'đã được gỡ cảnh báo'
        string $reason = '',
        string $targetUrl = ''
    ): int {
        $prefix = $contentType === 'post' ? 'Bài viết' : 'Bình luận';
        $excerpt = mb_strlen($itemTitleOrExcerpt) > 60 ? mb_substr($itemTitleOrExcerpt, 0, 57) . '...' : $itemTitleOrExcerpt;
        $reasonText = $reason !== '' ? " Lý do: {$reason}." : '';
        $message = "Thông báo hệ thống: {$prefix} \"{$excerpt}\" của bạn {$actionTaken}.{$reasonText}";

        return self::send($targetUserId, null, 'system', $message, $targetUrl);
    }
}
