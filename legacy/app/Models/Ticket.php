<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/User.php';

/** Ticket xin nâng bài lên Tin tức và xử lý của quản trị viên. */
final class Ticket
{
    private PDO $pdo;
    private int $threshold;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
        $config = require __DIR__ . '/../../config/app.php';
        $this->threshold = (int)$config['reputation']['ticket_threshold'];
    }

    public function createUpgrade(int $userId, int $postId): int
    {
        $post = $this->pdo->prepare('SELECT p.user_id,p.post_type,u.reputation_score FROM posts p JOIN users u ON u.id=p.user_id WHERE p.id=?');
        $post->execute([$postId]); $row = $post->fetch();
        if (!$row || (int)$row['user_id'] !== $userId) throw new RuntimeException('Bạn chỉ có thể gửi ticket cho bài viết của mình.');
        if ($row['post_type'] === 'news') throw new RuntimeException('Bài viết đã là Tin tức.');
        if ((int)$row['reputation_score'] < $this->threshold) throw new DomainException('Điểm uy tín chưa đạt ngưỡng ' . $this->threshold . '.');
        $pending = $this->pdo->prepare("SELECT id FROM tickets WHERE post_id=? AND ticket_type='upgrade' AND status='pending' LIMIT 1");
        $pending->execute([$postId]);
        if ($pending->fetchColumn()) throw new DomainException('Bài viết đã có ticket đang chờ xử lý.');
        $stmt = $this->pdo->prepare("INSERT INTO tickets (user_id,post_id,ticket_type,author_reputation_snapshot) VALUES (?,?,'upgrade',?)");
        $stmt->execute([$userId,$postId,(int)$row['reputation_score']]);
        return (int)$this->pdo->lastInsertId();
    }

    public function listAll(): array
    {
        return $this->pdo->query('SELECT t.id,t.user_id,t.post_id,t.ticket_type,t.author_reputation_snapshot,t.status,t.admin_note,t.created_at,u.full_name user_name,p.title post_title FROM tickets t JOIN users u ON u.id=t.user_id JOIN posts p ON p.id=t.post_id ORDER BY t.created_at DESC')->fetchAll();
    }

    public function resolve(int $ticketId, string $status, ?string $note = null): void
    {
        if (!in_array($status, ['approved','rejected'], true)) throw new InvalidArgumentException('Trạng thái ticket không hợp lệ.');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT post_id,user_id,ticket_type,status FROM tickets WHERE id=? FOR UPDATE');
            $stmt->execute([$ticketId]); $ticket = $stmt->fetch();
            if (!$ticket) throw new RuntimeException('Không tìm thấy ticket.');
            if ($ticket['status'] !== 'pending') throw new DomainException('Ticket này đã được xử lý.');
            $this->pdo->prepare('UPDATE tickets SET status=?,admin_note=? WHERE id=?')->execute([$status,$note,$ticketId]);
            if ($ticket['ticket_type'] === 'upgrade' && $status === 'approved') {
                $this->pdo->prepare("UPDATE posts SET post_type='news' WHERE id=?")->execute([(int)$ticket['post_id']]);
            }
            $message = $status === 'approved' ? 'Yêu cầu của bạn đã được chấp nhận.' : 'Yêu cầu của bạn đã bị từ chối.';
            $this->pdo->prepare('INSERT INTO notifications (user_id,type,content,target_url) VALUES (?,?,?,?)')->execute([(int)$ticket['user_id'],'ticket_' . $status,$message,'index.html']);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function toggleModeration(int $postId, string $status): void
    {
        if (!in_array($status, ['safe','flagged','blocked'], true)) throw new InvalidArgumentException('Trạng thái kiểm duyệt không hợp lệ.');
        $stmt = $this->pdo->prepare('UPDATE posts SET moderation_status=? WHERE id=?');
        $stmt->execute([$status,$postId]);
        if ($stmt->rowCount() === 0) throw new RuntimeException('Không tìm thấy bài viết hoặc trạng thái không đổi.');
    }
}
