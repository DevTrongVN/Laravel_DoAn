<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../Models/Post.php';
require_once __DIR__ . '/../Models/Interaction.php';

/**
 * Điều phối chức năng Bookmark (Lưu bài viết):
 * - Xem danh sách bài viết đã lưu (/bookmarks, /api/bookmarks)
 * - Lưu bài viết (POST /api/posts/:id/bookmark)
 * - Bỏ lưu bài viết (DELETE /api/posts/:id/bookmark)
 */
class BookmarkController extends Controller
{
    private Post $postModel;
    private Interaction $interactionModel;

    public function __construct()
    {
        $this->postModel = new Post();
        $this->interactionModel = new Interaction();
    }

    /**
     * Giao diện danh sách bài viết đã lưu (/bookmarks)
     */
    public function index(): void
    {
        if (!Auth::check()) {
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                $this->json(['error' => 'Vui lòng đăng nhập để xem danh sách bài viết đã lưu.'], 401);
            }
            $redirectUrl = defined('APP_URL') ? APP_URL . '/account.html?mode=login' : 'account.html?mode=login';
            header('Location: ' . $redirectUrl);
            exit;
        }

        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || isset($_GET['api'])) {
            $this->list();
        }

        $userId = (int)Auth::id();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(50, (int)($_GET['per_page'] ?? 10)));
        $posts = $this->postModel->listBookmarked($userId, $page, $perPage);
        $total = $this->postModel->countBookmarked($userId);

        try {
            $this->render('bookmarks/index', [
                'posts' => $posts,
                'total' => $total,
                'pageTitle' => 'Bài viết đã lưu — Góc Nhỏ',
                'activeTab' => 'bookmarks',
                'page' => $page,
                'perPage' => $perPage,
                'hasMore' => ($page * $perPage) < $total
            ]);
        } catch (Throwable) {
            $this->list();
        }
    }

    /**
     * API: Lấy danh sách bài viết đã lưu của người dùng hiện tại (GET /api/bookmarks)
     */
    public function list(): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập để xem danh sách bài viết đã lưu.'], 401);
        }

        $userId = (int)Auth::id();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(50, (int)($_GET['per_page'] ?? 10)));
        $posts = $this->postModel->listBookmarked($userId, $page, $perPage);
        $total = $this->postModel->countBookmarked($userId);

        $this->json([
            'data' => $posts,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => ($page * $perPage) < $total,
            ]
        ]);
    }

    /**
     * API: Lưu / Bỏ lưu / Hoán đổi trạng thái Bookmark bài viết (POST /api/posts/:id/bookmark)
     */
    public function add(string $id): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập để lưu bài viết.'], 401);
        }

        $userId = (int)Auth::id();
        $postId = (int)$id;

        $post = $this->postModel->find($postId, $userId);
        if (!$post || ($post['moderation_status'] ?? '') !== 'safe') {
            $this->json(['error' => 'Không tìm thấy bài viết hoặc bài viết không khả dụng.'], 404);
        }

        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $action = $input['action'] ?? null;
        if ($action === 'remove' || $action === 'delete' || $action === 'unbookmark') {
            $this->interactionModel->removeBookmark($userId, $postId);
            $this->json([
                'success' => true,
                'is_bookmarked' => false,
                'message' => 'Đã bỏ lưu bài viết.'
            ]);
        }

        if ($action === 'add' || $action === 'save') {
            $this->interactionModel->addBookmark($userId, $postId);
            $this->json([
                'success' => true,
                'is_bookmarked' => true,
                'message' => 'Đã lưu bài viết vào danh sách đánh dấu.'
            ]);
        }

        // Mặc định hỗ trợ Toggle 2 chiều: nếu đã lưu thì gỡ, chưa lưu thì thêm
        $isBookmarked = $this->interactionModel->toggleBookmark($userId, $postId);

        $this->json([
            'success' => true,
            'is_bookmarked' => $isBookmarked,
            'message' => $isBookmarked ? 'Đã lưu bài viết vào danh sách đánh dấu.' : 'Đã bỏ lưu bài viết.'
        ]);
    }

    /**
     * API: Bỏ lưu bài viết (DELETE /api/posts/:id/bookmark hoặc POST /api/posts/:id/unbookmark)
     */
    public function remove(string $id): never
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập để tiếp tục.'], 401);
        }

        $userId = (int)Auth::id();
        $postId = (int)$id;

        // Idempotent: xóa nếu có, nếu chưa lưu thì không gây lỗi
        $this->interactionModel->removeBookmark($userId, $postId);

        $this->json([
            'success' => true,
            'is_bookmarked' => false,
            'message' => 'Đã bỏ lưu bài viết.'
        ]);
    }
}
