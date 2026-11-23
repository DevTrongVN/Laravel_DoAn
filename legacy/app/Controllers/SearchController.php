<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../Models/Post.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/Tag.php';

/** Logic tìm kiếm đa năng: Nhận diện tự động #hashtag, @author hoặc từ khóa nội dung. */
class SearchController extends Controller
{
    private Post $postModel;
    private User $userModel;
    private Tag $tagModel;

    public function __construct()
    {
        $this->postModel = new Post();
        $this->userModel = new User();
        $this->tagModel  = new Tag();
    }

    public function index(): void
    {
        $q       = trim((string)($_GET['q'] ?? ''));
        $tab     = (string)($_GET['tab'] ?? 'all');
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(50, (int)($_GET['per_page'] ?? 10)));
        $viewerId = Auth::id();

        // Lọc nâng cao: Loại bài (type / category), Có ảnh (has_image), Sắp xếp (sort)
        $rawType = trim((string)($_GET['type'] ?? $_GET['category'] ?? 'all'));
        $type = in_array($rawType, ['blog', 'news'], true) ? $rawType : 'all';

        $rawHasImage = trim((string)($_GET['has_image'] ?? 'all'));
        $hasImage = match ($rawHasImage) {
            '1', 'with_image', 'true' => '1',
            '0', 'no_image', 'false' => '0',
            default                  => 'all',
        };

        $rawSort = (string)($_GET['sort'] ?? 'latest');
        $sort = in_array($rawSort, ['latest', 'popular'], true) ? $rawSort : 'latest';

        $posts    = [];
        $users    = [];
        $tags     = [];
        $paginationMeta = ['page' => $page, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1];

        $filters = ['sort' => $sort];

        if ($type !== 'all') {
            $filters['type'] = $type;
        }

        if ($hasImage !== 'all') {
            $filters['has_image'] = $hasImage;
        }

        if ($q !== '') {
            // Tự động nhận diện cú pháp tìm kiếm: #hashtag, @author, hoặc từ khóa tự do
            if (str_starts_with($q, '#')) {
                $filters['hashtag'] = ltrim($q, '#');
            } elseif (str_starts_with($q, '@')) {
                $authorQuery = ltrim($q, '@');
                $users = $this->userModel->searchAuthors($authorQuery, 10, true);
                $filters['author_username'] = $authorQuery;
            } else {
                $users = $this->userModel->searchAuthors($q, 10, false);
                $filters['q'] = $q;
            }
        }

        if (!empty($_GET['author_username'])) {
            $filters['author_username'] = (string)$_GET['author_username'];
        } elseif (!empty($_GET['author'])) {
            $filters['author_username'] = (string)$_GET['author'];
        }

        // Thực hiện tìm kiếm bài viết khi có từ khóa hoặc có bất kỳ bộ lọc nào được chọn
        $hasActiveCriteria = ($q !== '') || ($type !== 'all') || ($hasImage !== 'all') || !empty($filters['author_username']) || isset($_GET['page']);
        if ($hasActiveCriteria || $tab === 'posts' || $tab === 'all') {
            $result = $this->postModel->list($filters, $viewerId, $page, $perPage);
            $posts  = $result['data'];
            $paginationMeta = [
                'page'      => $result['page'],
                'per_page'  => $perPage,
                'total'     => $result['total'],
                'last_page' => $result['last_page'],
            ];
        }

        $payload = [
            'query'          => $q,
            'tab'            => $tab,
            'type'           => $type,
            'category'       => $type, // alias tương thích ngược
            'has_image'      => $hasImage,
            'sort'           => $sort,
            'posts'          => $posts,
            'users'          => $users,
            'matching_users' => $users,
            'tags'           => $tags,
            'pagination'     => $paginationMeta,
        ];

        $isApi = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || isset($_GET['api'])
            || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/search');

        if ($isApi) {
            $this->json($payload);
        }

        try {
            $this->render('search/index', $payload);
        } catch (Throwable) {
            $this->json($payload);
        }
    }
}
