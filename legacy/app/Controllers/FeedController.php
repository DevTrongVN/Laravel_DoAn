<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../Models/Post.php';

/** Bảng tin chính & Điều phối bài viết (Blog, Tin tức, Đang theo dõi). */
class FeedController extends Controller
{
    private Post $postModel;

    public function __construct()
    {
        $this->postModel = new Post();
    }

    public function index(): void
    {
        $type = $_GET['type'] ?? null;
        $filters = [];
        if ($type === 'news' || $type === 'blog') {
            $filters['type'] = $type;
        }
        $hasImage = $_GET['has_image'] ?? null;
        if ($hasImage !== null && $hasImage !== '' && $hasImage !== 'all') {
            $filters['has_image'] = (string)$hasImage;
        }
        if (!empty($_GET['following'])) {
            $filters['following'] = 1;
        }
        if (!empty($_GET['hashtag'])) {
            $filters['hashtag'] = (string)$_GET['hashtag'];
        }
        if (!empty($_GET['q'])) {
            $filters['q'] = (string)$_GET['q'];
        }

        $viewerId = Auth::id();
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(50, (int)($_GET['per_page'] ?? 10)));

        // Truyền mode cho weighted feed
        $mode = (string)($_GET['mode'] ?? 'all');
        if (in_array($mode, ['all', 'for_you', 'following', 'default'], true)) {
            $filters['mode'] = $mode;
        }

        $result = $this->postModel->list($filters, $viewerId, $page, $perPage);
        $posts  = $result['data'];

        // Hỗ trợ cả trả về JSON nếu là request API và render View nếu là Web
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || isset($_GET['api'])) {
            $this->json([
                'data'       => $posts,
                'pagination' => [
                    'page'      => $result['page'],
                    'per_page'  => $perPage,
                    'total'     => $result['total'],
                    'last_page' => $result['last_page'],
                    'has_more'  => $result['page'] < $result['last_page'],
                ],
            ]);
        }

        try {
            $this->render('feed/index', ['posts' => $posts, 'activeTab' => $type ?: 'home']);
        } catch (Throwable) {
            require __DIR__ . '/../../public/index.html';
        }
    }

    public function news(): void
    {
        $_GET['type'] = 'news';
        $this->index();
    }

    public function following(): void
    {
        $_GET['following'] = '1';
        $this->index();
    }
}
