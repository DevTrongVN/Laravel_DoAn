<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/Post.php';
require_once __DIR__ . '/../Models/Follow.php';
require_once __DIR__ . '/../Services/ReputationService.php';

/** Trang cá nhân, Cập nhật Bio/Avatar & Theo dõi người dùng. */
class ProfileController extends Controller
{
    private User $userModel;
    private Post $postModel;
    private Follow $followModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->postModel = new Post();
        $this->followModel = new Follow();
    }

    public function show(string $id): void
    {
        $cleanId = ltrim($id, '@');
        if (is_numeric($cleanId)) {
            $user = $this->userModel->findById((int)$cleanId);
        } else {
            $user = $this->userModel->findByLogin($cleanId);
        }

        if (!$user) {
            http_response_code(404);
            $this->json(['error' => 'Không tìm thấy người dùng.'], 404);
        }

        $userId = (int)$user['id'];

        $viewerId = Auth::id();
        if ($viewerId !== null && $viewerId !== $userId) {
            $this->followModel->trackVisit($viewerId, $userId);
        }

        // The owner can review every post and its moderation state; other visitors only see safe posts.
        $postFilters = ['user_id' => $userId];
        if ($viewerId !== null && $viewerId === $userId) $postFilters['include_moderated'] = true;
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 50)));
        $result  = $this->postModel->list($postFilters, $viewerId, $page, $perPage);
        $posts   = $result['data'];
        $followersCount = $this->followModel->countFollowers($userId);
        $followingCount = $this->followModel->countFollowing($userId);
        $isFollowing = $viewerId !== null ? $this->followModel->isFollowing($viewerId, $userId) : false;

        // Đảm bảo điểm uy tín hiển thị trên trang cá nhân luôn khớp dữ liệu tương tác thực tế
        $repService = new ReputationService();
        $reputationBreakdown = $repService->calculateBreakdown($userId);
        if ((int)($user['reputation_score'] ?? 0) !== (int)$reputationBreakdown['score']) {
            $repService->calculate($userId);
            $user['reputation_score'] = (int)$reputationBreakdown['score'];
        } else {
            $user['reputation_score'] = (int)$reputationBreakdown['score'];
        }

        $payload = [
            'user' => $user,
            'posts' => $posts,
            'followers_count' => $followersCount,
            'following_count' => $followingCount,
            'is_following' => $isFollowing,
            'reputation_breakdown' => $reputationBreakdown,
        ];

        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || isset($_GET['api'])) {
            $this->json($payload);
        }

        try {
            $this->render('profile/index', $payload);
        } catch (Throwable) {
            require __DIR__ . '/../../public/profile.html';
        }
    }

    public function follow(string $id): never
    {
        $followerId = Auth::id();
        if ($followerId === null) {
            $this->json(['error' => 'Vui lòng đăng nhập để theo dõi.'], 401);
        }

        $followingId = (int)$id;
        if ($followerId === $followingId) {
            $this->json(['error' => 'Bạn không thể tự theo dõi chính mình.'], 422);
        }

        $isFollowing = $this->followModel->toggle($followerId, $followingId);
        $reputationScore = (new ReputationService())->calculate($followingId);

        $this->json([
            'is_following' => $isFollowing,
            'followers_count' => $this->followModel->countFollowers($followingId),
            'reputation_score' => $reputationScore,
        ]);
    }
}
