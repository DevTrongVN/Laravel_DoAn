<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PostQueryService;
use App\Support\UnicodeHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Controller Tìm kiếm nâng cao: Tự động nhận diện #hashtag, @author, từ khóa, lọc Blog/Tin tức, Có/Không ảnh.
 */
class SearchController extends Controller
{
    public function __construct(
        private readonly PostQueryService $postQuery,
        private readonly FeedController $feedController
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $tab = (string) $request->query('tab', 'all');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));
        $viewerId = Auth::id();

        $rawType = trim((string) ($request->query('type') ?? $request->query('category') ?? 'all'));
        $type = in_array($rawType, ['blog', 'news'], true) ? $rawType : 'all';

        $rawHasImage = trim((string) $request->query('has_image', 'all'));
        $hasImage = match ($rawHasImage) {
            '1', 'with_image', 'true' => '1',
            '0', 'no_image', 'false' => '0',
            default => 'all',
        };

        $sort = in_array((string) $request->query('sort', 'latest'), ['latest', 'popular'], true)
            ? (string) $request->query('sort', 'latest')
            : 'latest';

        $posts = [];
        $users = [];
        $tags = [];
        $paginationMeta = ['page' => $page, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1];

        $filters = ['sort' => $sort];
        if ($type !== 'all') {
            $filters['type'] = $type;
        }
        if ($hasImage !== 'all') {
            $filters['has_image'] = $hasImage;
        }

        if ($q !== '') {
            if (str_starts_with($q, '#')) {
                $filters['hashtag'] = ltrim($q, '#');
            } elseif (str_starts_with($q, '@')) {
                $authorQuery = ltrim($q, '@');
                $users = $this->searchAuthors($authorQuery, 10, true);
                $filters['author_username'] = $authorQuery;
            } else {
                $users = $this->searchAuthors($q, 10, false);
                $filters['q'] = $q;
            }
        }

        if ($request->filled('author_username')) {
            $filters['author_username'] = (string) $request->query('author_username');
        } elseif ($request->filled('author')) {
            $filters['author_username'] = (string) $request->query('author');
        }

        $hasActiveCriteria = ($q !== '') || ($type !== 'all') || ($hasImage !== 'all') || !empty($filters['author_username']) || $request->has('page');
        if ($hasActiveCriteria || $tab === 'posts' || $tab === 'all') {
            $result = $this->postQuery->list($filters, $viewerId, $page, $perPage);
            $posts = $result['data'];
            $paginationMeta = [
                'page' => $result['page'],
                'per_page' => $perPage,
                'total' => $result['total'],
                'last_page' => $result['last_page'],
            ];
        }

        $payload = UnicodeHelper::toNfc([
            'query' => $q,
            'tab' => $tab,
            'type' => $type,
            'category' => $type,
            'has_image' => $hasImage,
            'sort' => $sort,
            'posts' => $posts,
            'users' => $users,
            'matching_users' => $users,
            'tags' => $tags,
            'pagination' => $paginationMeta,
        ]);

        if ($request->is('api/*') || $request->expectsJson() || $request->query('api') !== null) {
            return response()->json($payload);
        }

        return view('search.index', array_merge($payload, [
            'pageTitle' => ($q !== '' ? "Tìm kiếm: {$q}" : 'Tìm kiếm nâng cao') . ' — Góc Nhỏ',
        ], $this->feedController->sidebarData($viewerId)));
    }

    private function searchAuthors(string $query, int $limit = 10, bool $usernameOnly = false): array
    {
        $like = '%' . $query . '%';
        $qb = User::select('id', 'username', 'full_name', 'avatar', 'bio', 'is_verified', 'reputation_score');
        if ($usernameOnly) {
            $qb->where('username', 'LIKE', $like);
        } else {
            $qb->where(function ($sub) use ($like) {
                $sub->where('username', 'LIKE', $like)->orWhere('full_name', 'LIKE', $like);
            });
        }
        return $qb->orderByDesc('reputation_score')->limit($limit)->get()->toArray();
    }
}
