<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dịch vụ tạo & quản lý thông báo nội bộ cho người dùng.
 */
final class NotificationService
{
    public static function send(
        int $userId,
        ?int $actorId,
        string $type,
        string $content,
        string $targetUrl = ''
    ): int {
        if ($actorId !== null && $actorId === $userId && $type !== 'system') {
            return 0;
        }

        try {
            $notification = Notification::create([
                'user_id' => $userId,
                'actor_id' => $actorId,
                'type' => $type,
                'content' => $content,
                'target_url' => $targetUrl,
                'is_read' => 0,
                'created_at' => now(),
            ]);
            return (int) $notification->id;
        } catch (Throwable $e) {
            Log::warning('Lỗi tạo thông báo: ' . $e->getMessage());
            return 0;
        }
    }

    public static function markAsRead(int $notificationId, int $userId): bool
    {
        return Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->update(['is_read' => 1]) >= 0;
    }

    public static function markAllAsRead(int $userId): bool
    {
        return Notification::where('user_id', $userId)
            ->update(['is_read' => 1]) >= 0;
    }

    public static function sendSystemModerationNotice(
        int $targetUserId,
        string $contentType,
        string $itemTitleOrExcerpt,
        string $actionTaken,
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
