<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/NotificationHelper.php';
require_once __DIR__ . '/../../Models/Post.php';
require_once __DIR__ . '/../../Models/User.php';
require_once __DIR__ . '/../../Models/Interaction.php';
require_once __DIR__ . '/../../Models/PostReport.php';
require_once __DIR__ . '/../../Services/ReputationService.php';

/** API Endpoints: Bảng tin, Phân trang Feed, Thao tác Like, Share, Bookmark. */
class ApiPostController extends Controller
{
    private Post $postModel;
    private Interaction $interactionModel;

    public function __construct()
    {
        $this->postModel = new Post();
        $this->interactionModel = new Interaction();
    }

    public function feed(): never
    {
        $filters = [];
        $type = $_GET['post_type'] ?? $_GET['type'] ?? null;
        if ($type === 'news' || $type === 'blog') $filters['type'] = $type;
        $hasImage = $_GET['has_image'] ?? null;
        if ($hasImage !== null && $hasImage !== '' && $hasImage !== 'all') {
            $filters['has_image'] = (string)$hasImage;
        }
        if (!empty($_GET['user_id'])) $filters['user_id'] = (int)$_GET['user_id'];
        if (!empty($_GET['following'])) $filters['following'] = 1;
        if (!empty($_GET['hashtag'])) $filters['hashtag'] = (string)$_GET['hashtag'];
        if (!empty($_GET['author_username'])) {
            $filters['author_username'] = (string)$_GET['author_username'];
        } elseif (!empty($_GET['author'])) {
            $filters['author_username'] = (string)$_GET['author'];
        }
        if (!empty($_GET['q'])) {
            $qVal = (string)$_GET['q'];
            if (str_starts_with($qVal, '@')) {
                $filters['author_username'] = ltrim($qVal, '@');
            } else {
                $filters['q'] = $qVal;
            }
        }

        $viewerId = Auth::id();
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(50, (int)($_GET['per_page'] ?? 10)));

        // Chế độ feed: all (mặc định có trọng số), following, for_you
        $mode = (string)($_GET['mode'] ?? $_GET['tab'] ?? 'all');
        if ($mode === 'feed' || $mode === 'home') $mode = 'all';
        if (in_array($mode, ['all', 'for_you', 'following', 'default'], true)) {
            $filters['mode'] = $mode;
        }
        if ($mode === 'following') {
            $filters['following'] = 1;
        }
        if (!empty($_GET['sort']) && in_array($_GET['sort'], ['latest', 'popular'], true)) {
            $filters['sort'] = (string)$_GET['sort'];
        }
        if (isset($_GET['seed']) && is_numeric($_GET['seed'])) {
            $filters['seed'] = (int)$_GET['seed'];
        }

        $result = $this->postModel->list($filters, $viewerId, $page, $perPage);
        $this->json([
            'data'       => $result['data'],
            'pagination' => [
                'page'      => $result['page'],
                'per_page'  => $perPage,
                'total'     => $result['total'],
                'last_page' => $result['last_page'],
                'has_more'  => $result['page'] < $result['last_page'],
                'seed'      => $result['seed'] ?? ($filters['seed'] ?? null),
            ],
        ]);
    }

    public function show(string $id): never
    {
        $viewerId = Auth::id();
        $this->postModel->recordView((int)$id, $viewerId);
        $post = $this->postModel->find((int)$id, $viewerId);
        if (!$post) $this->json(['error' => 'Không tìm thấy bài viết.'], 404);
        $this->json(['data' => $post]);
    }

    public function interact(string $id, string $type): never
    {
        $userId = Auth::id();
        if ($userId === null) $this->json(['error' => 'Vui lòng đăng nhập.'], 401);

        $postId = (int)$id;
        $post = $this->postModel->find($postId, $userId);
        if (!$post) $this->json(['error' => 'Không tìm thấy bài viết.'], 404);

        if ($type === 'like') {
            $isLiked = $this->interactionModel->toggleLike($userId, $postId);
            (new ReputationService())->calculate((int)$post['user_id']);
            $updated = $this->postModel->find($postId, $userId);
            $this->json([
                'is_liked' => $isLiked,
                'likes_count' => $updated['likes_count'],
                'shares_count' => $updated['shares_count'],
            ]);
        } elseif ($type === 'share') {
            if ($userId === (int)$post['user_id']) {
                $this->json(['error' => 'Bạn không thể tự chia sẻ bài viết của chính mình.'], 422);
            }
            $alreadyShared = $this->interactionModel->hasSharedLink($userId, $postId) || $this->postModel->hasReposted($userId, $postId);
            $this->interactionModel->share($userId, $postId);
            if (!$alreadyShared) {
                (new ReputationService())->calculate((int)$post['user_id']);
            }
            $updated = $this->postModel->find($postId, $userId);
            $totalShares = (int)($updated['shares_count'] ?? 0);
            $this->json([
                'success' => true,
                'already_shared' => $alreadyShared,
                'is_liked' => $updated['is_liked'] ?? false,
                'likes_count' => $updated['likes_count'] ?? 0,
                'shares_count' => $totalShares,
                'total_shares' => $totalShares,
                'message' => $alreadyShared ? 'Bạn đã từng chia sẻ bài viết này. Đã sao chép liên kết!' : 'Đã sao chép liên kết bài viết!',
            ]);
        } elseif ($type === 'bookmark') {
            $isBookmarked = $this->interactionModel->toggleBookmark($userId, $postId);
            $this->json([
                'success' => true,
                'is_bookmarked' => $isBookmarked,
                'message' => $isBookmarked ? 'Đã lưu bài viết vào danh sách đánh dấu.' : 'Đã bỏ lưu bài viết.'
            ]);
        } else {
            $this->json(['error' => 'Loại tương tác không hợp lệ.'], 422);
        }
    }

    public function repost(string $id): never
    {
        $userId = Auth::id();
        if ($userId === null) $this->json(['error' => 'Đăng nhập để đăng lại bài viết.'], 401);
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true) ?: $_POST;
        $original = $this->postModel->find((int)$id, $userId);
        if (!$original) $this->json(['error' => 'Không tìm thấy bài viết gốc.'], 404);
        if ((int)$original['user_id'] === $userId) {
            $this->json(['error' => 'Bạn không thể tự đăng lại bài viết của chính mình.'], 422);
        }
        try {
            $newId = $this->postModel->repost($userId, (int)$id, (string)($data['comment'] ?? ''));
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['error' => $e->getMessage() ?: 'Không thể đăng lại bài viết.'], 500);
        }
        try {
            (new ReputationService())->calculate((int)$original['user_id']);
        } catch (Throwable $repError) {
            error_log('Lỗi tính điểm Reputation sau khi repost bài viết: ' . $repError->getMessage());
        }

        // Thông báo tác giả bài gốc
        if ((int)$original['user_id'] !== $userId) {
            $actor     = (new User())->findById($userId);
            $actorName = $actor['full_name'] ?? 'Ai đó';
            NotificationHelper::send(
                (int)$original['user_id'],
                $userId,
                'repost',
                "{$actorName} đã đăng lại bài viết \"{$original['title']}\" của bạn",
                "/post/{$newId}"
            );
        }

        $updatedOriginal = $this->postModel->find((int)$id, $userId);
        $totalShares = (int)($updatedOriginal['shares_count'] ?? 0);

        $this->json([
            'message' => 'Đã đăng lại bài viết lên trang cá nhân.',
            'total_shares' => $totalShares,
            'shares_count' => $totalShares,
            'data' => $this->postModel->find($newId, $userId)
        ], 201);
    }

    public function report(string $id): never
    {
        $userId = Auth::id();
        if ($userId === null) $this->json(['error' => 'Vui lòng đăng nhập để báo cáo bài viết.'], 401);
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) $data = $_POST;
        try {
            (new PostReport())->submit($userId, (int)$id, (string)($data['reason'] ?? ''), (string)($data['details'] ?? ''));
        } catch (InvalidArgumentException|DomainException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        }
        $this->json(['message' => 'Đã gửi báo cáo đến quản trị viên.']);
    }
}
