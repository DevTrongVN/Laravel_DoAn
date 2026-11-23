<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/NotificationHelper.php';

/**
 * Dịch vụ xử lý @Mention động theo user_id (giống Facebook):
 * - Lưu token chuẩn @[user:ID] kèm bảng comment_mentions
 * - Tự động cập nhật tên hiển thị khi user đổi tên
 * - Xác thực user_id thực tế ở backend (chống giả mạo mention)
 * - Escape XSS tuyệt đối khi render HTML
 * - Gửi thông báo không tự nhắc mình, không trùng lặp
 */
final class MentionService
{
    public const TOKEN_REGEX = '/@\[user:(\d+)\]/';
    private const MAX_MENTIONS_PER_COMMENT = 20;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /** Tìm kiếm người dùng cho autocomplete khi gõ @ */
    public function searchUsers(string $query, int $limit = 8, ?int $excludeUserId = null): array
    {
        $limit = max(1, min(15, $limit));
        $q = trim($query);

        $params = [];
        $where = ['1=1'];
        if ($excludeUserId !== null && $excludeUserId > 0) {
            // Cho phép vẫn tìm thấy người khác ưu tiên; không bắt buộc loại trừ vì người dùng có thể gõ tên mình (nhưng backend không tự thông báo)
        }

        if ($q !== '') {
            $likePrefix = $q . '%';
            $likeAny = '%' . $q . '%';
            $stmt = $this->pdo->prepare("
                SELECT id, username, full_name, avatar, is_verified
                FROM users
                WHERE full_name LIKE ? OR username LIKE ?
                ORDER BY
                    CASE
                        WHEN full_name LIKE ? OR username LIKE ? THEN 0
                        ELSE 1
                    END ASC,
                    is_verified DESC,
                    reputation_score DESC,
                    id ASC
                LIMIT ?
            ");
            $stmt->bindValue(1, $likeAny, PDO::PARAM_STR);
            $stmt->bindValue(2, $likeAny, PDO::PARAM_STR);
            $stmt->bindValue(3, $likePrefix, PDO::PARAM_STR);
            $stmt->bindValue(4, $likePrefix, PDO::PARAM_STR);
            $stmt->bindValue(5, $limit, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare("
                SELECT id, username, full_name, avatar, is_verified
                FROM users
                ORDER BY is_verified DESC, reputation_score DESC, id ASC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn(array $u): array => [
            'id'          => (int)$u['id'],
            'username'    => (string)$u['username'],
            'full_name'   => (string)$u['full_name'],
            'avatar'      => $u['avatar'],
            'is_verified' => (bool)$u['is_verified'],
        ], $rows);
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
            $uid = (int)$rawId;
            if ($uid > 0) {
                $candidateIds[$uid] = $uid;
            }
        }

        if (empty($candidateIds)) {
            $sanitized = (string)preg_replace(self::TOKEN_REGEX, '', $content);
            return ['content' => trim($sanitized), 'mentioned_user_ids' => []];
        }

        $candidateList = array_slice(array_values($candidateIds), 0, self::MAX_MENTIONS_PER_COMMENT);
        $placeholders = implode(',', array_fill(0, count($candidateList), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id IN ({$placeholders})");
        $stmt->execute($candidateList);
        $validSet = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $existingId) {
            $validSet[(int)$existingId] = true;
        }

        $validOrder = [];
        $sanitizedContent = (string)preg_replace_callback(self::TOKEN_REGEX, static function (array $m) use ($validSet, &$validOrder): string {
            $uid = (int)$m[1];
            if (isset($validSet[$uid])) {
                $validOrder[$uid] = $uid;
                return "@[user:{$uid}]";
            }
            // Vô hiệu hóa token giả mạo để không tạo liên kết hay thông báo
            return '';
        }, $content);

        return [
            'content'            => trim(preg_replace('/[ \t]{2,}/', ' ', $sanitizedContent) ?? $sanitizedContent),
            'mentioned_user_ids' => array_values($validOrder),
        ];
    }

    /** Chuyển nội dung chứa token @[user:ID] thành plain text có username thật (dùng cho AI moderation scan). */
    public function toPlainTextForModeration(string $content): string
    {
        $map = $this->resolveUsersFromContents([$content]);
        return (string)preg_replace_callback(self::TOKEN_REGEX, static function (array $m) use ($map): string {
            $uid = (string)(int)$m[1];
            if (isset($map[$uid]) && !empty($map[$uid]['exists'])) {
                $uname = (string)($map[$uid]['username'] ?: $map[$uid]['full_name']);
                return '@' . $uname;
            }
            return '';
        }, $content);
    }

    /** Lưu / đồng bộ bản ghi comment_mentions cho một bình luận. */
    public function syncCommentMentions(int $commentId, array $mentionedUserIds): void
    {
        $this->pdo->prepare('DELETE FROM comment_mentions WHERE comment_id = ?')->execute([$commentId]);
        if (empty($mentionedUserIds)) {
            return;
        }
        $ins = $this->pdo->prepare('INSERT IGNORE INTO comment_mentions (comment_id, mentioned_user_id, created_at) VALUES (?, ?, NOW())');
        foreach (array_unique(array_map('intval', $mentionedUserIds)) as $uid) {
            if ($uid > 0) {
                $ins->execute([$commentId, $uid]);
            }
        }
    }

    /** Nạp động bản đồ user hiện tại cho toàn bộ danh sách bình luận (1 truy vấn batch, không N+1). */
    public function resolveUsersFromContents(array $contents): array
    {
        $ids = [];
        foreach ($contents as $text) {
            if (!is_string($text) || $text === '') continue;
            if (preg_match_all(self::TOKEN_REGEX, $text, $matches)) {
                foreach ($matches[1] as $rawId) {
                    $uid = (int)$rawId;
                    if ($uid > 0) $ids[$uid] = $uid;
                }
            }
        }

        if (empty($ids)) {
            return [];
        }

        $idList = array_values($ids);
        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $stmt = $this->pdo->prepare("SELECT id, username, full_name, avatar, is_verified FROM users WHERE id IN ({$placeholders})");
        $stmt->execute($idList);

        $appUrl = defined('APP_URL') ? APP_URL : '/DoAnMaNguonMo/public';
        $found = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $uid = (int)$u['id'];
            $found[(string)$uid] = [
                'id'          => $uid,
                'username'    => (string)$u['username'],
                'full_name'   => (string)$u['full_name'],
                'avatar'      => $u['avatar'],
                'is_verified' => (bool)$u['is_verified'],
                'exists'      => true,
                'profile_url' => $appUrl . '/profile.html?id=' . $uid,
            ];
        }

        foreach ($idList as $uid) {
            $key = (string)$uid;
            if (!isset($found[$key])) {
                $found[$key] = [
                    'id'          => $uid,
                    'username'    => '',
                    'full_name'   => 'Người dùng không tồn tại',
                    'avatar'      => null,
                    'is_verified' => false,
                    'exists'      => false,
                    'profile_url' => null,
                ];
            }
        }

        return $found;
    }

    /** Gắn trường mentions_map động vào từng bình luận (cả dạng flat lẫn nested). */
    public function hydrateComments(array $comments): array
    {
        if (empty($comments)) {
            return $comments;
        }

        $contents = [];
        foreach ($comments as $c) {
            if (empty($c['is_deleted']) && isset($c['content'])) {
                $contents[] = (string)$c['content'];
            }
        }

        $globalMap = $this->resolveUsersFromContents($contents);

        foreach ($comments as &$c) {
            $cMap = [];
            if (empty($c['is_deleted']) && !empty($c['content']) && preg_match_all(self::TOKEN_REGEX, (string)$c['content'], $m)) {
                foreach ($m[1] as $rawId) {
                    $key = (string)(int)$rawId;
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

    /** Render HTML an toàn XSS cho bình luận có @[user:ID] trong PHP SSR (comment_section.php). */
    public static function renderHtmlPhp(string $content, array $mentionsMap = []): string
    {
        $parts = preg_split('/(@\[user:\d+\])/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
        }

        $appUrl = defined('APP_URL') ? APP_URL : '/DoAnMaNguonMo/public';
        $html = '';

        foreach ($parts as $part) {
            if (preg_match('/^@\[user:(\d+)\]$/', $part, $m)) {
                $uid = (int)$m[1];
                $key = (string)$uid;
                $info = $mentionsMap[$key] ?? null;
                if ($info && !empty($info['exists'])) {
                    $displayName = htmlspecialchars((string)($info['username'] ?: $info['full_name']), ENT_QUOTES, 'UTF-8');
                    $href = htmlspecialchars((string)($info['profile_url'] ?: ($appUrl . '/profile.html?id=' . $uid)), ENT_QUOTES, 'UTF-8');
                    $html .= '<a href="' . $href . '" class="comment-mention-link" data-mention-user-id="' . $uid . '">@' . $displayName . '</a>';
                } else {
                    $html .= '<span class="comment-mention-deleted">@Người dùng không tồn tại</span>';
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
     * - Không gửi trùng lặp theo (user_id, actor_id, type='mention', target_url).
     */
    public function notifyMentionedUsers(int $commentId, int $postId, string $postTitle, int $actorId, array $mentionedUserIds): int
    {
        if (empty($mentionedUserIds)) {
            return 0;
        }

        $actorStmt = $this->pdo->prepare('SELECT full_name, username FROM users WHERE id = ? LIMIT 1');
        $actorStmt->execute([$actorId]);
        $actorRow = $actorStmt->fetch(PDO::FETCH_ASSOC);
        $actorName = $actorRow ? ((string)($actorRow['full_name'] ?: $actorRow['username'])) : 'Ai đó';

        $shortTitle = mb_strlen($postTitle) > 80 ? (mb_substr($postTitle, 0, 80) . '...') : $postTitle;
        $targetUrl = "/post/{$postId}#comment-{$commentId}";
        $dupCheck = $this->pdo->prepare("SELECT 1 FROM notifications WHERE user_id = ? AND actor_id = ? AND type = 'mention' AND target_url = ? LIMIT 1");

        $sent = 0;
        foreach (array_unique(array_map('intval', $mentionedUserIds)) as $targetUserId) {
            if ($targetUserId <= 0 || $targetUserId === $actorId) {
                continue; // Không gửi thông báo cho chính mình
            }
            $dupCheck->execute([$targetUserId, $actorId, $targetUrl]);
            if ($dupCheck->fetchColumn()) {
                continue; // Chống thông báo trùng lặp
            }
            NotificationHelper::send(
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
