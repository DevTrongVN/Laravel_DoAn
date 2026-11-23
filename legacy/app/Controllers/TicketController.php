<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../Models/Ticket.php';
require_once __DIR__ . '/../Models/Post.php';
require_once __DIR__ . '/../Services/ReputationService.php';

/** Kiểm tra điều kiện điểm Uy tín & Gửi Ticket xin duyệt Blog thành Tin tức. */
class TicketController extends Controller
{
    private Ticket $ticketModel;
    private ReputationService $reputationService;

    public function __construct()
    {
        $this->ticketModel = new Ticket();
        $this->reputationService = new ReputationService();
    }

    public function create(string $postId): never
    {
        $userId = Auth::id();
        if ($userId === null) {
            $this->json(['error' => 'Vui lòng đăng nhập để gửi ticket.'], 401);
        }

        $id = (int)$postId;
        $post = (new Post())->find($id, $userId);
        if (!$post || (int)$post['user_id'] !== $userId) {
            $this->json(['error' => 'Chỉ tác giả mới có thể gửi ticket duyệt bài này.'], 403);
        }

        $currentScore = $this->reputationService->calculate($userId);
        $threshold = $this->reputationService->getThreshold();

        if ($currentScore < $threshold) {
            $this->json([
                'error' => "Điểm uy tín của bạn là {$currentScore}, chưa đạt mức tối thiểu ({$threshold} điểm) để gửi ticket duyệt Tin tức.",
                'current_score' => $currentScore,
                'threshold' => $threshold,
            ], 403);
        }

        try {
            $ticketId = $this->ticketModel->createUpgrade($userId, $id);
            $this->json([
                'message' => 'Gửi ticket thành công. Quản trị viên sẽ sớm xem xét bài viết của bạn.',
                'ticket_id' => $ticketId,
            ], 201);
        } catch (DomainException $e) {
            $this->json(['error' => $e->getMessage()], 409);
        }
    }
}
