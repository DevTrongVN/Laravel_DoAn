<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/NotificationHelper.php';
require_once __DIR__ . '/../../Models/Follow.php';
require_once __DIR__ . '/../../Models/User.php';
require_once __DIR__ . '/../../Services/ReputationService.php';

/** API Endpoints: Bấm Follow/Unfollow, Cập nhật tần suất click truy cập kênh. */
class ApiFollowController extends Controller
{
    private Follow $followModel;

    public function __construct()
    {
        $this->followModel = new Follow();
    }

    public function toggle(string $id): never
    {
        $followerId = Auth::id();
        if ($followerId === null) {
            $this->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }

        $followingId = (int)$id;
        if ($followerId === $followingId) {
            $this->json(['error' => 'Bạn không thể tự theo dõi chính mình.'], 422);
        }

        $isFollowing = $this->followModel->toggle($followerId, $followingId);
        $reputationScore = (new ReputationService())->calculate($followingId);

        // Thông báo khi bắt đầu theo dõi (không thông báo khi bỏ theo dõi)
        if ($isFollowing) {
            $actor     = (new User())->findById($followerId);
            $actorName = $actor['full_name'] ?? 'Ai đó';
            NotificationHelper::send(
                $followingId,
                $followerId,
                'follow',
                "{$actorName} đã bắt đầu theo dõi bạn",
                "/profile/{$followerId}"
            );
        }

        $this->json([
            'is_following'     => $isFollowing,
            'followers_count'  => $this->followModel->countFollowers($followingId),
            'reputation_score' => $reputationScore,
        ]);
    }

    public function trackClick(string $id): never
    {
        $followerId  = Auth::id();
        $followingId = (int)$id;
        if ($followerId !== null && $followerId !== $followingId) {
            $this->followModel->trackVisit($followerId, $followingId);
        }
        $this->json(['status' => 'tracked']);
    }

    public function followingList(): never
    {
        $followerId = Auth::id();
        if ($followerId === null) {
            // Khách chưa đăng nhập: Trả về danh sách tác giả nổi bật từ cơ sở dữ liệu
            $stmt = Database::getInstance()->getConnection()->query('
                SELECT id, username, full_name, avatar, is_verified, COALESCE(reputation_score, 0) AS reputation_score
                FROM users
                WHERE role IN ("user", "admin")
                ORDER BY reputation_score DESC, id ASC
                LIMIT 5
            ');
            $topAuthors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->json([
                'is_guest' => true,
                'title'    => 'Tác giả nổi bật',
                'data'     => $topAuthors,
            ]);
        }

        $channels = $this->followModel->getFollowingWithVisitRank($followerId, 15);
        $this->json([
            'is_guest' => false,
            'title'    => 'Những người mình theo dõi',
            'data'     => $channels,
        ]);
    }
}

