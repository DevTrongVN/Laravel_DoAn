<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';

/** Dịch vụ tính toán điểm uy tín tập trung (Single Source of Truth) dựa trên dữ liệu tương tác thực tế. */
final class ReputationService
{
    private PDO $pdo;
    private array $weights;
    private int $ticketThreshold;
    private static ?bool $commentsHasIsDeleted = null;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $appConfig = require __DIR__ . '/../../config/app.php';
        $this->weights = $appConfig['reputation'] ?? [];
        $this->ticketThreshold = (int)($this->weights['ticket_threshold'] ?? 100);
    }

    private function commentsHasDeletedFlag(): bool
    {
        if (self::$commentsHasIsDeleted !== null) {
            return self::$commentsHasIsDeleted;
        }
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM comments LIKE 'is_deleted'");
            self::$commentsHasIsDeleted = (bool)$stmt->fetch();
        } catch (Throwable) {
            self::$commentsHasIsDeleted = false;
        }
        return self::$commentsHasIsDeleted;
    }

    /** Trả về bảng phân rã chi tiết nguồn gốc điểm uy tín của một tài khoản (không tạo điểm ảo). */
    public function calculateBreakdown(int $userId): array
    {
        $wLike    = (int)($this->weights['W_like'] ?? 2);
        $wShare   = (int)($this->weights['W_share'] ?? 5);
        $wComment = (int)($this->weights['W_comment'] ?? 0);
        $wFollow  = (int)($this->weights['W_follow'] ?? 10);

        // 1. Tổng lượt thích/cảm xúc trên các bài viết an toàn của user (loại trừ tự thích)
        $stmtLikes = $this->pdo->prepare("
            SELECT COUNT(*) FROM post_reactions i
            JOIN posts p ON p.id = i.post_id
            WHERE p.user_id = ? AND p.moderation_status = 'safe' AND i.user_id <> p.user_id
        ");
        $stmtLikes->execute([$userId]);
        $totalLikes = (int)$stmtLikes->fetchColumn();

        // 2. Tổng lượt chia sẻ/đăng lại duy nhất theo (bài viết, người chia sẻ), loại trừ tự chia sẻ
        $stmtShares = $this->pdo->prepare("
            SELECT COUNT(DISTINCT CONCAT(s.post_id, ':', s.user_id))
            FROM (
                SELECT user_id, post_id FROM interactions WHERE type = 'share'
                UNION ALL
                SELECT user_id, shared_post_id AS post_id FROM posts WHERE shared_post_id IS NOT NULL AND moderation_status = 'safe'
            ) s
            JOIN posts p ON p.id = s.post_id
            WHERE p.user_id = ? AND p.moderation_status = 'safe' AND s.user_id <> p.user_id
        ");
        $stmtShares->execute([$userId]);
        $totalShares = (int)$stmtShares->fetchColumn();

        // 3. Tổng bình luận hợp lệ trên các bài viết an toàn của user (loại trừ tự bình luận & bình luận đã xóa mềm)
        $notDeletedCond = $this->commentsHasDeletedFlag() ? ' AND c.is_deleted = 0' : '';
        $stmtComments = $this->pdo->prepare("
            SELECT COUNT(*) FROM comments c
            JOIN posts p ON p.id = c.post_id
            WHERE p.user_id = ? AND p.moderation_status = 'safe' AND c.moderation_status = 'safe'{$notDeletedCond} AND c.user_id <> p.user_id
        ");
        $stmtComments->execute([$userId]);
        $totalComments = (int)$stmtComments->fetchColumn();

        // 4. Tổng người theo dõi (loại trừ tự theo dõi)
        $stmtFollows = $this->pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ? AND follower_id <> following_id");
        $stmtFollows->execute([$userId]);
        $totalFollowers = (int)$stmtFollows->fetchColumn();

        $score = max(0, ($totalLikes * $wLike) + ($totalShares * $wShare) + ($totalComments * $wComment) + ($totalFollowers * $wFollow));

        return [
            'likes'     => $totalLikes,
            'shares'    => $totalShares,
            'comments'  => $totalComments,
            'followers' => $totalFollowers,
            'weights'   => [
                'like'    => $wLike,
                'share'   => $wShare,
                'comment' => $wComment,
                'follow'  => $wFollow,
            ],
            'score'     => $score,
        ];
    }

    /**
     * Tính lại và đồng bộ điểm uy tín của một tài khoản từ dữ liệu nguồn thực tế.
     * Idempotent & an toàn đồng thời (khóa dòng users.id bằng FOR UPDATE trong transaction).
     */
    public function calculate(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $startedTx = !$this->pdo->inTransaction();
        if ($startedTx) {
            $this->pdo->beginTransaction();
        }

        try {
            // Khóa dòng người dùng mục tiêu để tránh race condition khi nhiều request cập nhật đồng thời
            $lock = $this->pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
            $lock->execute([$userId]);
            if (!$lock->fetchColumn()) {
                if ($startedTx && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return 0;
            }

            $breakdown = $this->calculateBreakdown($userId);
            $score = (int)$breakdown['score'];

            $update = $this->pdo->prepare('UPDATE users SET reputation_score = ? WHERE id = ?');
            $update->execute([$score, $userId]);

            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return $score;
        } catch (Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("[ReputationService] Lỗi đồng bộ điểm uy tín cho user_id={$userId}: " . $e->getMessage());
            throw $e;
        }
    }

    /** Đồng bộ toàn bộ users.reputation_score theo đúng dữ liệu tương tác gốc (an toàn, idempotent). */
    public function recalculateAll(): array
    {
        $wLike    = (int)($this->weights['W_like'] ?? 2);
        $wShare   = (int)($this->weights['W_share'] ?? 5);
        $wComment = (int)($this->weights['W_comment'] ?? 0);
        $wFollow  = (int)($this->weights['W_follow'] ?? 10);
        $notDeletedCond = $this->commentsHasDeletedFlag() ? ' AND c.is_deleted = 0' : '';

        $sql = "
            UPDATE users u
            LEFT JOIN (
                SELECT p.user_id, COUNT(*) AS cnt
                FROM post_reactions pr
                JOIN posts p ON p.id = pr.post_id
                WHERE p.moderation_status = 'safe' AND pr.user_id <> p.user_id
                GROUP BY p.user_id
            ) lk ON lk.user_id = u.id
            LEFT JOIN (
                SELECT p.user_id, COUNT(DISTINCT CONCAT(s.post_id, ':', s.user_id)) AS cnt
                FROM (
                    SELECT user_id, post_id FROM interactions WHERE type = 'share'
                    UNION ALL
                    SELECT user_id, shared_post_id AS post_id FROM posts WHERE shared_post_id IS NOT NULL AND moderation_status = 'safe'
                ) s
                JOIN posts p ON p.id = s.post_id
                WHERE p.moderation_status = 'safe' AND s.user_id <> p.user_id
                GROUP BY p.user_id
            ) sh ON sh.user_id = u.id
            LEFT JOIN (
                SELECT p.user_id, COUNT(*) AS cnt
                FROM comments c
                JOIN posts p ON p.id = c.post_id
                WHERE p.moderation_status = 'safe' AND c.moderation_status = 'safe'{$notDeletedCond} AND c.user_id <> p.user_id
                GROUP BY p.user_id
            ) cm ON cm.user_id = u.id
            LEFT JOIN (
                SELECT following_id AS user_id, COUNT(*) AS cnt
                FROM follows
                WHERE follower_id <> following_id
                GROUP BY following_id
            ) fl ON fl.user_id = u.id
            SET u.reputation_score = (
                COALESCE(lk.cnt, 0) * {$wLike}
                + COALESCE(sh.cnt, 0) * {$wShare}
                + COALESCE(cm.cnt, 0) * {$wComment}
                + COALESCE(fl.cnt, 0) * {$wFollow}
            )
        ";

        $this->pdo->beginTransaction();
        try {
            $affected = $this->pdo->exec($sql);
            $this->pdo->commit();
            $total = (int)$this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            return ['total_users' => $total, 'updated_rows' => (int)$affected];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function canSubmitTicket(int $userId): bool
    {
        $score = $this->calculate($userId);
        return $score >= $this->ticketThreshold;
    }

    public function getThreshold(): int
    {
        return $this->ticketThreshold;
    }
}
