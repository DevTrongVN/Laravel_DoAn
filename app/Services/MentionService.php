<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Dịch vụ xử lý @Mention động theo user_id:
 * - Lưu token chuẩn @[user:ID] kèm bảng comment_mentions
 * - Tự động cập nhật tên hiển thị khi user đổi username / full_name
 * - Xác thực user_id thực tế ở backend (chống giả mạo mention)
 * - Render HTML an toàn XSS
 * - Gửi thông báo không tự nhắc mình, không trùng lặp
 */
final class MentionService
{
    public const TOKEN_REGEX = '/@\[user:(\d+)\]/';
    private const MAX_MENTIONS_PER_COMMENT = 20;

    /**
     * Tìm kiếm người dùng cho autocomplete khi gõ @
     */
    public function searchUsers(string $query, int $limit = 8, ?int $excludeUserId = null): array
    {
        $limit = max(1, min(15, $limit));
        $q = trim($query);

        $queryBuilder = User::query()
            ->select('id', 'username', 'full_name', 'avatar', 'is_verified', 'reputation_score');

        if ($q !== '') {
            $queryBuilder->where(function ($sub) use ($q) {
                $sub->where('full_name', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%");
            })
            ->orderByRaw("CASE WHEN username LIKE ? OR full_name LIKE ? THEN 0 ELSE 1 END", ["{$q}%", "{$q}%"]);
        }

        $users = $queryBuilder
            ->orderByDesc('is_verified')
            ->orderByDesc('reputation_score')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $users->map(static fn(User $u): array => [
            'id' => (int) $u->id,
            'username' => (string) $u->username,
            'full_name' => (string) ($u->full_name ?: $u->username),
            'avatar' => $u->avatar,
            'is_verified' => (bool) $u->is_verified,
        ])->all();
    }

    /**
     * Xác thực các token @[user:ID] trong nội dung bình luận.
     * - Chỉ giữ token ứng với tài khoản thực sự tồn tại trong bảng users.
     * - Mọi token giả mạo (ID không tồn tại) bị vô hiệu hóa thành văn bản thường.
     *
     * @return array{content: string, mentioned_user_ids: int[]}
     */
    public function prepareContentAndMentions(string $content): array
    {
        if (!preg_match_all(self::TOKEN_REGEX, $content, $matches)) {
            return ['content' => $content, 'mentioned_user_ids' => []];
        }

        $candidateIds = [];
        foreach ($matches[1] as $rawId) {
            $uid = (int) $rawId;
            if ($uid > 0) {
                $candidateIds[$uid] = $uid;
            }
        }

        if (empty($candidateIds)) {
            $sanitized = (string) preg_replace(self::TOKEN_REGEX, '', $content);
            return ['content' => trim($sanitized), 'mentioned_user_ids' => []];
        }

        $candidateList = array_slice(array_values($candidateIds), 0, self::MAX_MENTIONS_PER_COMMENT);
        $existingIds = User::whereIn('id', $candidateList)->pluck('id')->all();
        $validSet = array_fill_keys(array_map('intval', $existingIds), true);

        $validOrder = [];
        $sanitizedContent = (string) preg_replace_callback(self::TOKEN_REGEX, static function (array $m) use ($validSet, &$validOrder): string {
            $uid = (int) $m[1];
            if (isset($validSet[$uid])) {
                $validOrder[$uid] = $uid;
                return "@[user:{$uid}]";
            }
            return '';
        }, $content);

        return [
            'content' => trim(preg_replace('/[ \t]{2,}/', ' ', $sanitizedContent) ?? $sanitizedContent),
            'mentioned_user_ids' => array_values($validOrder),
        ];
    }

    /**
     * Chuyển nội dung chứa token @[user:ID] thành plain text có username thật (dùng cho AI scan).
     */
    public function toPlainTextForModeration(string $content): string
    {
        $map = $this->resolveUsersFromContents([$content]);
        return (string) preg_replace_callback(self::TOKEN_REGEX, static function (array $m) use ($map): string {
            $uid = (string) (int) $m[1];
            if (isset($map[$uid]) && !empty($map[$uid]['exists'])) {
                $uname = (string) ($map[$uid]['username'] ?: $map[$uid]['full_name']);
                return '@' . $uname;
            }
            return '';
        }, $content);
    }

    /**
     * Lưu / đồng bộ bản ghi comment_mentions cho một bình luận.
     */
    public function syncCommentMentions(int $commentId, array $mentionedUserIds): void
    {
        try {
            DB::table('comment_mentions')->where('comment_id', $commentId)->delete();
            if (empty($mentionedUserIds)) {
                return;
            }

            $inserts = [];
            $now = now();
            foreach (array_unique(array_map('intval', $mentionedUserIds)) as $uid) {
                if ($uid > 0) {
                    $inserts[] = [
                        'comment_id' => $commentId,
                        'mentioned_user_id' => $uid,
                        'created_at' => $now,
                    ];
                }
            }

            if (!empty($inserts)) {
                DB::table('comment_mentions')->insertOrIgnore($inserts);
            }
        } catch (Throwable) {
            // Không làm gián đoạn luồng bình luận chính nếu bảng phụ lỗi
        }
    }

    /**
     * Nạp động bản đồ user hiện tại cho toàn bộ danh sách nội dung (1 truy vấn batch, không N+1).
     *
     * @return array<string, array{id: int, username: string, full_name: string, avatar: ?string, is_verified: bool, exists: bool, profile_url: string}>
     */
    public function resolveUsersFromContents(array $contents): array
    {
        $ids = [];
        foreach ($contents as $text) {
            if (!is_string($text) || $text === '') {
                continue;
            }
            if (preg_match_all(self::TOKEN_REGEX, $text, $matches)) {
                foreach ($matches[1] as $rawId) {
                    $uid = (int) $rawId;
                    if ($uid > 0) {
                        $ids[$uid] = $uid;
                    }
                }
            }
        }

        if (empty($ids)) {
            return [];
        }

        $users = User::whereIn('id', array_values($ids))
            ->select('id', 'username', 'full_name', 'avatar', 'is_verified')
            ->get();

        $appUrl = rtrim(config('app.url', ''), '/');
        $found = [];
        foreach ($users as $u) {
            $uid = (int) $u->id;
            $profileUrl = $appUrl ? "{$appUrl}/profile.html?id={$uid}" : "/profile.html?id={$uid}";
            $found[(string) $uid] = [
                'id' => $uid,
                'username' => (string) $u->username,
                'full_name' => (string) ($u->full_name ?: $u->username),
                'avatar' => $u->avatar,
                'is_verified' => (bool) $u->is_verified,
                'exists' => true,
                'profile_url' => $profileUrl,
            ];
        }

        // Bổ sung các ID không tìm thấy với exists = false
        foreach ($ids as $uid) {
            $key = (string) $uid;
            if (!isset($found[$key])) {
                $found[$key] = [
                    'id' => $uid,
                    'username' => 'user' . $uid,
                    'full_name' => 'Người dùng #' . $uid,
                    'avatar' => null,
                    'is_verified' => false,
                    'exists' => false,
                    'profile_url' => '',
                ];
            }
        }

        return $found;
    }

    /**
     * Gắn trường mentions_map động vào từng bình luận (cả dạng flat lẫn nested).
     */
    public function hydrateComments(array $comments): array
    {
        if (empty($comments)) {
            return $comments;
        }

        $contents = [];
        foreach ($comments as $c) {
            if (is_array($c) && !empty($c['content'])) {
                $contents[] = (string) $c['content'];
            }
        }

        $globalMap = $this->resolveUsersFromContents($contents);

        foreach ($comments as &$c) {
            if (!is_array($c)) {
                continue;
            }
            $cMap = [];
            if (!empty($c['content']) && preg_match_all(self::TOKEN_REGEX, (string) $c['content'], $m)) {
                foreach ($m[1] as $rawId) {
                    $key = (string) (int) $rawId;
                    if (isset($globalMap[$key])) {
                        $cMap[$key] = $globalMap[$key];
                    }
                }
            }
            $c['mentions_map'] = $cMap;
        }
        unset($c);

        return $comments;
    }

    /**
     * Render HTML an toàn XSS cho bình luận có @[user:ID] trong Blade / PHP SSR.
     */
    public static function renderHtmlPhp(string $content, array $mentionsMap = []): string
    {
        $parts = preg_split('/(@\[user:\d+\])/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
        }

        $appUrl = rtrim(config('app.url', ''), '/');
        $html = '';

        foreach ($parts as $part) {
            if (preg_match('/^@\[user:(\d+)\]$/', $part, $m)) {
                $uid = (int) $m[1];
                $key = (string) $uid;
                $info = $mentionsMap[$key] ?? null;
                if ($info && !empty($info['exists'])) {
                    $displayName = htmlspecialchars((string) ($info['username'] ?: $info['full_name']), ENT_QUOTES, 'UTF-8');
                    $href = htmlspecialchars((string) ($info['profile_url'] ?: ($appUrl . '/profile.html?id=' . $uid)), ENT_QUOTES, 'UTF-8');
                    $html .= '<a href="' . $href . '" class="comment-mention-link" data-mention-user-id="' . $uid . '">@' . $displayName . '</a>';
                } elseif ($info) {
                    $html .= '<span class="comment-mention-deleted">@' . htmlspecialchars((string) ($info['username'] ?? 'Người dùng'), ENT_QUOTES, 'UTF-8') . '</span>';
                } else {
                    $html .= '<a href="/profile.html?id=' . $uid . '" class="comment-mention-link" data-mention-user-id="' . $uid . '">@người dùng #' . $uid . '</a>';
                }
            } else {
                $html .= nl2br(htmlspecialchars($part, ENT_QUOTES, 'UTF-8'));
            }
        }

        return $html;
    }

    /**
     * Gửi thông báo cho các tài khoản được mention hợp lệ.
     * - Không tự thông báo khi tự mention mình.
     * - Không gửi trùng lặp.
     */
    public function notifyMentionedUsers(int $commentId, int $postId, string $postTitle, int $actorId, array $mentionedUserIds): int
    {
        if (empty($mentionedUserIds)) {
            return 0;
        }

        $actor = User::find($actorId);
        $actorName = $actor ? ($actor->full_name ?: $actor->username) : 'Ai đó';

        $shortTitle = mb_strlen($postTitle) > 80 ? (mb_substr($postTitle, 0, 80) . '...') : $postTitle;
        $targetUrl = "/post/{$postId}#comment-{$commentId}";

        $sent = 0;
        foreach (array_unique(array_map('intval', $mentionedUserIds)) as $targetUserId) {
            if ($targetUserId <= 0 || $targetUserId === $actorId) {
                continue;
            }

            // Kiểm tra trùng lặp
            $exists = DB::table('notifications')
                ->where('user_id', $targetUserId)
                ->where('actor_id', $actorId)
                ->where('type', 'mention')
                ->where('target_url', $targetUrl)
                ->exists();

            if ($exists) {
                continue;
            }

            NotificationService::send(
                $targetUserId,
                $actorId,
                'mention',
                "{$actorName} đã nhắc đến bạn trong một bình luận ở bài viết \"{$shortTitle}\"",
                $targetUrl
            );
            $sent++;
        }

        return $sent;
    }
}
