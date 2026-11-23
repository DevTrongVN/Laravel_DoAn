<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Comment;
use App\Models\Interaction;
use App\Models\Post;
use App\Models\PostAppeal;
use App\Models\PostView;
use App\Models\Reaction;
use App\Models\Tag;
use App\Support\UnicodeHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Dịch vụ truy vấn & thao tác bài viết chuẩn SSOT (Single Source of Truth)
 * sử dụng Laravel DB / Eloquent Models.
 */
final class PostQueryService
{
    public function __construct(
        private readonly ?MentionService $mentionService = null
    ) {}

    private function getMentionService(): MentionService
    {
        return $this->mentionService ?? app(MentionService::class);
    }
    /**
     * Lấy danh sách bài viết với bộ lọc nâng cao, trọng số feed, SSOT counts và phân trang.
     */
    public function list(array $filters = [], ?int $viewerId = null, int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $where = [];
        $bindings = [];
        $joins = '';

        if (empty($filters['include_moderated'])) {
            $where[] = "p.moderation_status = 'safe'";
        }

        $typeFilter = $filters['type'] ?? $filters['category'] ?? null;
        if ($typeFilter !== null && $typeFilter !== '' && $typeFilter !== 'all' && in_array($typeFilter, ['blog', 'news'], true)) {
            $where[] = 'p.post_type = :type';
            $bindings['type'] = $typeFilter;
        }

        $hasImageFilter = $filters['has_image'] ?? null;
        if ($hasImageFilter !== null && $hasImageFilter !== '' && $hasImageFilter !== 'all') {
            if ($hasImageFilter === '1' || $hasImageFilter === 1 || $hasImageFilter === 'with_image' || $hasImageFilter === true) {
                $where[] = "((p.thumbnail IS NOT NULL AND TRIM(p.thumbnail) != '') OR p.content LIKE '%<img%' OR p.content LIKE '%![%]%')";
            } elseif ($hasImageFilter === '0' || $hasImageFilter === 0 || $hasImageFilter === 'no_image' || $hasImageFilter === false) {
                $where[] = "((p.thumbnail IS NULL OR TRIM(p.thumbnail) = '') AND p.content NOT LIKE '%<img%' AND p.content NOT LIKE '%![%]%')";
            }
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'p.user_id = :user_id';
            $bindings['user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['following']) && $viewerId !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM follows f WHERE f.follower_id = :follow_viewer AND f.following_id = p.user_id)';
            $bindings['follow_viewer'] = $viewerId;
        }

        if (!empty($filters['hashtag'])) {
            $where[] = 'EXISTS (SELECT 1 FROM post_hashtags ph JOIN hashtags h ON h.id = ph.hashtag_id WHERE ph.post_id = p.id AND h.name = :hashtag)';
            $bindings['hashtag'] = strtolower(ltrim((string) $filters['hashtag'], '#'));
        }

        if (!empty($filters['author_username'])) {
            $where[] = 'u.username LIKE :author_username';
            $bindings['author_username'] = '%' . ltrim((string) $filters['author_username'], '@') . '%';
        }

        if (!empty($filters['q'])) {
            $where[] = '(p.title LIKE :q_title OR p.content LIKE :q_content OR u.full_name LIKE :q_author OR u.username LIKE :q_uname OR EXISTS (SELECT 1 FROM post_hashtags ph JOIN hashtags h ON h.id = ph.hashtag_id WHERE ph.post_id = p.id AND h.name LIKE :q_tag))';
            $needle = '%' . (string) $filters['q'] . '%';
            $bindings['q_title'] = $needle;
            $bindings['q_content'] = $needle;
            $bindings['q_author'] = $needle;
            $bindings['q_uname'] = $needle;
            $bindings['q_tag'] = $needle;
        }

        $mode = $filters['mode'] ?? 'default';
        $sort = $filters['sort'] ?? null;
        $seedVal = isset($filters['seed']) && is_numeric($filters['seed']) ? (int) $filters['seed'] : null;
        $randExpr = $seedVal !== null ? "RAND({$seedVal})" : 'RAND()';
        $hasFyJoin = false;

        if (!empty($filters['user_id']) && empty($filters['sort']) && (empty($filters['mode']) || $filters['mode'] === 'default')) {
            $orderBy = 'p.created_at DESC, p.id DESC';
        } elseif ($sort === 'popular') {
            $orderBy = 'likes_count DESC, views_count DESC, p.created_at DESC';
        } elseif ($sort === 'latest') {
            $orderBy = 'p.created_at DESC, p.id DESC';
        } elseif ($mode === 'for_you') {
            $topTags = $viewerId !== null ? $this->getUserTopHashtags($viewerId, 30) : [];
            if (!empty($topTags)) {
                $tagIdsList = implode(',', array_map('intval', $topTags));
                $orderBy = "(fy_score * 40 + (u.reputation_score * 0.04) + (1 / (1 + TIMESTAMPDIFF(HOUR, p.created_at, NOW()) * 0.02)) * 60 + ({$randExpr} * 35)) DESC, p.id DESC";
                $hasFyJoin = true;
            } else {
                $orderBy = "((u.reputation_score * 0.04) + (1 / (1 + TIMESTAMPDIFF(HOUR, p.created_at, NOW()) * 0.02)) * 60 + ({$randExpr} * 35)) DESC, p.id DESC";
            }
        } elseif ($mode === 'all' || $mode === 'default' || $sort === null) {
            if ($viewerId !== null) {
                $joins .= ' LEFT JOIN follows flw ON flw.follower_id = ' . (int) $viewerId . ' AND flw.following_id = p.user_id';
                $orderBy = "(IF(flw.follower_id IS NOT NULL, 40, 0) + (u.reputation_score * 0.04) + (1 / (1 + TIMESTAMPDIFF(HOUR, p.created_at, NOW()) * 0.02)) * 50 + ({$randExpr} * 35)) DESC, p.id DESC";
            } else {
                $orderBy = "((u.reputation_score * 0.04) + (1 / (1 + TIMESTAMPDIFF(HOUR, p.created_at, NOW()) * 0.02)) * 50 + ({$randExpr} * 35)) DESC, p.id DESC";
            }
        } else {
            $orderBy = 'p.created_at DESC, p.id DESC';
        }

        $vId = $viewerId === null ? 'NULL' : (string) (int) $viewerId;
        $viewerReactionSelect = "COALESCE((SELECT pr.reaction FROM post_reactions pr WHERE pr.post_id = p.id AND pr.user_id = {$vId} LIMIT 1), NULL)";
        $viewerBookmarkSelect = $viewerId === null ? '0' : "(SELECT 1 FROM interactions ibk WHERE ibk.post_id = p.id AND ibk.user_id = {$vId} AND ibk.type = 'bookmark' LIMIT 1)";

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countRow = DB::selectOne("SELECT COUNT(*) AS cnt FROM posts p JOIN users u ON u.id = p.user_id {$joins} {$whereClause}", $bindings);
        $total = (int) ($countRow->cnt ?? 0);

        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT p.id, p.user_id, p.title, p.slug, p.summary, p.content, p.thumbnail, p.post_type, p.moderation_status, p.views_count, p.created_at, '
            . 'p.shared_post_id, p.share_comment, orig.id AS original_id, orig.title AS original_title, orig.summary AS original_summary, orig.content AS original_content, orig.thumbnail AS original_thumbnail, orig.user_id AS original_user_id, orig_u.full_name AS original_author, orig_u.username AS original_username, orig_u.avatar AS original_avatar, '
            . 'u.username, u.full_name, u.avatar, u.is_verified, u.reputation_score, '
            . '(SELECT COUNT(*) FROM post_reactions prc WHERE prc.post_id = p.id) AS likes_count, '
            . "(SELECT COUNT(DISTINCT s.user_id) FROM (SELECT user_id, post_id FROM interactions WHERE type = 'share' UNION ALL SELECT user_id, shared_post_id AS post_id FROM posts WHERE shared_post_id IS NOT NULL AND moderation_status = 'safe') s WHERE s.post_id = p.id AND s.user_id <> p.user_id) AS shares_count, "
            . "(SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.moderation_status = 'safe') AS comments_count, "
            . $viewerReactionSelect . ' AS my_reaction, '
            . $viewerBookmarkSelect . ' AS is_bookmarked'
            . ($hasFyJoin ? ", (SELECT COUNT(*) FROM post_hashtags ph_fy WHERE ph_fy.post_id = p.id AND ph_fy.hashtag_id IN ({$tagIdsList})) AS fy_score" : ($mode === 'for_you' ? ', 0 AS fy_score' : ''))
            . ' FROM posts p '
            . 'JOIN users u ON u.id = p.user_id '
            . "LEFT JOIN posts orig ON orig.id = p.shared_post_id AND orig.moderation_status = 'safe' "
            . 'LEFT JOIN users orig_u ON orig_u.id = orig.user_id '
            . $joins . ' '
            . $whereClause
            . ' ORDER BY ' . $orderBy
            . " LIMIT {$perPage} OFFSET {$offset}";

        $rows = array_map(static fn($r) => (array) $r, DB::select($sql, $bindings));

        $tagsByPost = [];
        $reactionsByPost = [];
        if ($rows) {
            $ids = array_map(static fn(array $post): int => (int) $post['id'], $rows);
            $tagRows = DB::table('post_hashtags as ph')
                ->join('hashtags as h', 'h.id', '=', 'ph.hashtag_id')
                ->whereIn('ph.post_id', $ids)
                ->orderBy('h.name')
                ->get(['ph.post_id', 'h.name']);
            foreach ($tagRows as $tag) {
                $tagsByPost[(int) $tag->post_id][] = $tag->name;
            }

            $rRows = DB::table('post_reactions')
                ->select('post_id', 'reaction', DB::raw('COUNT(*) AS cnt'))
                ->whereIn('post_id', $ids)
                ->groupBy('post_id', 'reaction')
                ->get();
            foreach ($rRows as $r) {
                $pid = (int) $r->post_id;
                if (!isset($reactionsByPost[$pid])) {
                    $reactionsByPost[$pid] = ['like' => 0, 'love' => 0, 'haha' => 0, 'sad' => 0, 'angry' => 0];
                }
                $reactionsByPost[$pid][$r->reaction] = (int) $r->cnt;
            }
        }

        $mapped = array_map(function (array $post) use ($tagsByPost, $reactionsByPost): array {
            $post['hashtags'] = $tagsByPost[(int) $post['id']] ?? [];
            $post['reaction_counts'] = $reactionsByPost[(int) $post['id']] ?? ['like' => 0, 'love' => 0, 'haha' => 0, 'sad' => 0, 'angry' => 0];
            return $this->normalizePostPayload($post);
        }, $rows);

        return [
            'data' => UnicodeHelper::toNfc($mapped),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'seed' => $seedVal,
        ];
    }

    /**
     * Chi tiết 1 bài viết chuẩn SSOT kèm bình luận, phản hồi cảm xúc và kiểm tra kiểm duyệt.
     */
    public function find(int $id, ?int $viewerId = null): ?array
    {
        $sql = "SELECT p.id, p.user_id, p.title, p.slug, p.summary, p.content, p.thumbnail, p.post_type, p.moderation_status, p.ai_severity, p.ai_violation_type, p.ai_reason, p.moderated_at, p.views_count, p.created_at, p.shared_post_id, p.share_comment,
            orig.id AS original_id, orig.title AS original_title, orig.summary AS original_summary, orig.content AS original_content, orig.thumbnail AS original_thumbnail, orig.user_id AS original_user_id, orig_u.full_name AS original_author, orig_u.username AS original_username, orig_u.avatar AS original_avatar,
            u.username, u.full_name, u.avatar, u.is_verified, u.reputation_score,
            (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id = p.id) AS likes_count,
            (SELECT COUNT(DISTINCT s.user_id) FROM (SELECT user_id, post_id FROM interactions WHERE type = 'share' UNION ALL SELECT user_id, shared_post_id AS post_id FROM posts WHERE shared_post_id IS NOT NULL AND moderation_status = 'safe') s WHERE s.post_id = p.id AND s.user_id <> p.user_id) AS shares_count,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.moderation_status = 'safe') AS comments_count
            FROM posts p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN posts orig ON orig.id = p.shared_post_id AND orig.moderation_status = 'safe'
            LEFT JOIN users orig_u ON orig_u.id = orig.user_id
            WHERE p.id = ? LIMIT 1";

        $obj = DB::selectOne($sql, [$id]);
        if (!$obj) {
            return null;
        }
        $row = (array) $obj;

        $isOwner = ($viewerId !== null && (int) $row['user_id'] === $viewerId);
        $isAdmin = Auth::check() && Auth::user()?->role === 'admin';
        $isRestricted = false;
        $canViewOriginal = true;
        $blockReason = null;
        $latestAppeal = null;

        if ($row['moderation_status'] !== 'safe') {
            $adminNote = DB::table('post_reports')
                ->where('post_id', $id)
                ->whereNotNull('admin_note')
                ->where('admin_note', '!=', '')
                ->orderByDesc('id')
                ->value('admin_note');

            if ($adminNote) {
                $blockReason = (string) $adminNote;
            } elseif (!empty($row['ai_reason'])) {
                $blockReason = (string) $row['ai_reason'];
            } else {
                $blockReason = 'Bài viết đã bị tạm khóa do vi phạm Tiêu chuẩn cộng đồng.';
            }

            $appeal = PostAppeal::where('post_id', $id)->orderByDesc('id')->first();
            $latestAppeal = $appeal ? $appeal->toArray() : null;

            if (!$isOwner && !$isAdmin) {
                $isRestricted = true;
                $canViewOriginal = false;
                $row['content'] = '';
                $row['summary'] = 'Bài viết này hiện không khả dụng do vi phạm tiêu chuẩn cộng đồng.';
                $row['thumbnail'] = null;
            }
        }

        $row['is_owner'] = $isOwner;
        $row['is_admin'] = $isAdmin;
        $row['is_restricted'] = $isRestricted;
        $row['can_view_original'] = $canViewOriginal;
        $row['block_reason'] = $blockReason;
        $row['latest_appeal'] = $latestAppeal;

        $row['my_reaction'] = null;
        $row['is_bookmarked'] = false;
        if ($viewerId !== null) {
            $row['my_reaction'] = DB::table('post_reactions')
                ->where('user_id', $viewerId)
                ->where('post_id', $id)
                ->value('reaction') ?: null;

            $row['is_bookmarked'] = DB::table('interactions')
                ->where('user_id', $viewerId)
                ->where('post_id', $id)
                ->where('type', 'bookmark')
                ->exists();
        }

        $row['hashtags'] = DB::table('hashtags as h')
            ->join('post_hashtags as ph', 'ph.hashtag_id', '=', 'h.id')
            ->where('ph.post_id', $id)
            ->orderBy('h.name')
            ->pluck('h.name')
            ->all();

        $row['reaction_counts'] = $this->getReactionCounts($id);

        if ($isRestricted) {
            $row['comments'] = [];
            $row['comments_count'] = 0;
        } else {
            $commentRows = DB::select(
                "SELECT c.id, c.parent_id, c.content, c.created_at, c.edited_at, u.id AS user_id, u.username, u.full_name, u.avatar, u.is_verified,
                (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS likes_count,
                (SELECT cl.reaction FROM comment_likes cl WHERE cl.comment_id = c.id AND cl.user_id = ? LIMIT 1) AS my_reaction,
                (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id AND cl.user_id = ?) AS liked_by_me
                FROM comments c
                JOIN users u ON u.id = c.user_id
                WHERE c.post_id = ? AND c.moderation_status = 'safe'
                ORDER BY c.created_at ASC",
                [$viewerId ?? 0, $viewerId ?? 0, $id]
            );
            // Lọc bỏ bình luận mồ côi (có parent_id nhưng parent_id không tồn tại trong danh sách comment an toàn)
            $validCommentIds = [];
            foreach ($commentRows as $cr) {
                $validCommentIds[(int) $cr->id] = true;
            }
            $cleanCommentRows = array_filter($commentRows, static function ($c) use ($validCommentIds): bool {
                $pId = $c->parent_id === null ? 0 : (int) $c->parent_id;
                return $pId === 0 || isset($validCommentIds[$pId]);
            });

            $rawComments = array_values(array_map(static function ($c): array {
                return [
                    'id' => (int) $c->id,
                    'parent_id' => $c->parent_id === null ? null : (int) $c->parent_id,
                    'content' => $c->content,
                    'created_at' => $c->created_at,
                    'edited_at' => $c->edited_at,
                    'likes_count' => (int) $c->likes_count,
                    'my_reaction' => $c->my_reaction,
                    'liked_by_me' => (bool) $c->liked_by_me,
                    'user' => [
                        'id' => (int) $c->user_id,
                        'username' => $c->username,
                        'full_name' => $c->full_name,
                        'avatar' => $c->avatar,
                        'is_verified' => (bool) $c->is_verified,
                    ],
                ];
            }, $cleanCommentRows));

            $row['comments'] = $this->getMentionService()->hydrateComments($rawComments);
        }

        return UnicodeHelper::toNfc($this->normalizePostPayload($row));
    }

    public function getReactionCounts(int $postId): array
    {
        $counts = array_fill_keys(Reaction::VALID_REACTIONS, 0);
        $rows = DB::table('post_reactions')
            ->select('reaction', DB::raw('COUNT(*) AS cnt'))
            ->where('post_id', $postId)
            ->groupBy('reaction')
            ->get();
        foreach ($rows as $row) {
            if (isset($counts[$row->reaction])) {
                $counts[$row->reaction] = (int) $row->cnt;
            }
        }
        return $counts;
    }

    /**
     * Ghi nhận lượt xem bài viết theo chu kỳ chống spam 7 ngày (1 tuần).
     */
    public function recordView(int $postId, ?int $userId = null, ?string $ip = null, ?string $userAgent = null): bool
    {
        if ($postId <= 0) {
            return false;
        }

        $postRow = Post::select('id', 'user_id', 'moderation_status')->find($postId);
        if (!$postRow) {
            return false;
        }

        // Không tính lượt xem khi chính tác giả tự mở bài viết của mình
        if ($userId !== null && $userId > 0 && (int) $postRow->user_id === $userId) {
            return false;
        }

        $dbUserId = null;
        if ($userId !== null && $userId > 0) {
            $dbUserId = $userId;
            $viewerHash = 'u_' . $dbUserId;
        } else {
            $clientIp = $ip ?? request()->ip() ?? '127.0.0.1';
            $clientUa = $userAgent ?? request()->userAgent() ?? 'Unknown';
            $viewerHash = 'g_' . hash('sha256', $clientIp . '|' . $clientUa);
        }

        try {
            $existing = DB::selectOne(
                'SELECT id, last_viewed_at, TIMESTAMPDIFF(SECOND, last_viewed_at, NOW()) AS elapsed_sec FROM post_views WHERE post_id = ? AND viewer_hash = ? LIMIT 1',
                [$postId, $viewerHash]
            );

            $sevenDaysInSeconds = 7 * 86400; // 604,800 giây

            if ($existing) {
                $elapsed = (int) ($existing->elapsed_sec ?? 0);
                if ($elapsed < $sevenDaysInSeconds) {
                    return false;
                }

                return DB::transaction(function () use ($dbUserId, $existing, $postId): bool {
                    DB::update('UPDATE post_views SET last_viewed_at = NOW(), user_id = COALESCE(?, user_id) WHERE id = ?', [$dbUserId, $existing->id]);
                    DB::update('UPDATE posts SET views_count = views_count + 1 WHERE id = ?', [$postId]);
                    return true;
                });
            }

            return DB::transaction(function () use ($postId, $dbUserId, $viewerHash): bool {
                PostView::create([
                    'post_id' => $postId,
                    'user_id' => $dbUserId,
                    'viewer_hash' => $viewerHash,
                    'last_viewed_at' => now(),
                    'created_at' => now(),
                ]);
                DB::update('UPDATE posts SET views_count = views_count + 1 WHERE id = ?', [$postId]);
                return true;
            });
        } catch (Throwable $e) {
            Log::warning('PostQueryService::recordView error: ' . $e->getMessage());
            return false;
        }
    }

    public function listBookmarked(int $userId, int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT p.id, p.user_id, p.title, p.slug, p.summary, p.content, p.thumbnail, p.post_type, p.moderation_status, p.views_count, p.created_at,
            p.shared_post_id, p.share_comment, orig.id AS original_id, orig.title AS original_title, orig.summary AS original_summary, orig.content AS original_content, orig.thumbnail AS original_thumbnail, orig.user_id AS original_user_id, orig_u.full_name AS original_author, orig_u.username AS original_username, orig_u.avatar AS original_avatar,
            u.username, u.full_name, u.avatar, u.is_verified, u.reputation_score,
            (SELECT COUNT(*) FROM post_reactions prc WHERE prc.post_id = p.id) AS likes_count,
            (SELECT COUNT(DISTINCT s.user_id) FROM (SELECT user_id, post_id FROM interactions WHERE type = 'share' UNION ALL SELECT user_id, shared_post_id AS post_id FROM posts WHERE shared_post_id IS NOT NULL AND moderation_status = 'safe') s WHERE s.post_id = p.id AND s.user_id <> p.user_id) AS shares_count,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.moderation_status = 'safe') AS comments_count,
            (SELECT pr.reaction FROM post_reactions pr WHERE pr.post_id = p.id AND pr.user_id = ? LIMIT 1) AS my_reaction,
            1 AS is_bookmarked,
            i.created_at AS bookmarked_at
            FROM interactions i
            JOIN posts p ON p.id = i.post_id
            JOIN users u ON u.id = p.user_id
            LEFT JOIN posts orig ON orig.id = p.shared_post_id AND orig.moderation_status = 'safe'
            LEFT JOIN users orig_u ON orig_u.id = orig.user_id
            WHERE i.user_id = ? AND i.type = 'bookmark' AND p.moderation_status = 'safe'
            ORDER BY i.created_at DESC, i.id DESC LIMIT {$perPage} OFFSET {$offset}";

        $rows = array_map(static fn($r) => (array) $r, DB::select($sql, [$userId, $userId]));
        if (!$rows) {
            return [];
        }

        $ids = array_map(static fn(array $p): int => (int) $p['id'], $rows);
        $tagsByPost = [];
        foreach (DB::table('post_hashtags as ph')->join('hashtags as h', 'h.id', '=', 'ph.hashtag_id')->whereIn('ph.post_id', $ids)->orderBy('h.name')->get(['ph.post_id', 'h.name']) as $t) {
            $tagsByPost[(int) $t->post_id][] = $t->name;
        }

        return UnicodeHelper::toNfc(array_map(function (array $post) use ($tagsByPost): array {
            $post['hashtags'] = $tagsByPost[(int) $post['id']] ?? [];
            $post['is_bookmarked'] = true;
            return $this->normalizePostPayload($post);
        }, $rows));
    }

    public function countBookmarked(int $userId): int
    {
        return (int) DB::table('interactions as i')
            ->join('posts as p', 'p.id', '=', 'i.post_id')
            ->where('i.user_id', $userId)
            ->where('i.type', 'bookmark')
            ->where('p.moderation_status', 'safe')
            ->count();
    }

    public function create(int $userId, array $data): int
    {
        $title = (string) UnicodeHelper::toNfc(trim((string) ($data['title'] ?? '')));
        $content = (string) UnicodeHelper::toNfc((string) ($data['content'] ?? ''));
        $slugBase = trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title)), '-');
        $slug = ($slugBase ?: 'post') . '-' . bin2hex(random_bytes(4));
        $rawSummary = trim((string) ($data['summary'] ?? '')) ?: mb_substr($content, 0, 240);
        $summary = (string) UnicodeHelper::toNfc($rawSummary);

        $moderationStatus = (string) ($data['moderation_status'] ?? 'safe');
        if (!in_array($moderationStatus, ['safe', 'flagged', 'blocked', 'pending'], true)) {
            $moderationStatus = 'safe';
        }

        $post = Post::create([
            'user_id' => $userId,
            'title' => $title,
            'slug' => $slug,
            'summary' => $summary,
            'content' => $content,
            'thumbnail' => $data['thumbnail'] ?? null,
            'post_type' => $data['post_type'] ?? 'blog',
            'moderation_status' => $moderationStatus,
            'ai_severity' => $data['ai_severity'] ?? null,
            'ai_violation_type' => $data['ai_violation_type'] ?? null,
            'ai_reason' => $data['ai_reason'] ?? null,
            'ai_status' => $data['ai_status'] ?? ($moderationStatus !== 'safe' ? 'pending' : null),
            'ai_analyzed_at' => now(),
            'ai_suggested_action' => $data['ai_suggested_action'] ?? null,
        ]);

        $this->syncHashtags((int) $post->id, $title, $content, $data['hashtags'] ?? []);
        return (int) $post->id;
    }

    public function updateOwned(int $postId, int $userId, array $data): bool
    {
        $post = Post::where('id', $postId)->where('user_id', $userId)->first();
        if (!$post) {
            return false;
        }

        $title = (string) UnicodeHelper::toNfc(trim((string) ($data['title'] ?? '')));
        $content = (string) UnicodeHelper::toNfc((string) ($data['content'] ?? ''));
        $rawSummary = trim((string) ($data['summary'] ?? '')) ?: mb_substr($content, 0, 240);
        $summary = (string) UnicodeHelper::toNfc($rawSummary);

        $post->title = $title;
        $post->summary = $summary;
        $post->content = $content;
        if (!empty($data['thumbnail'])) {
            $post->thumbnail = $data['thumbnail'];
        }
        $post->moderation_status = (string) ($data['moderation_status'] ?? 'safe');
        $post->ai_severity = $data['ai_severity'] ?? null;
        $post->ai_violation_type = $data['ai_violation_type'] ?? null;
        $post->ai_reason = $data['ai_reason'] ?? null;
        $post->ai_status = $data['ai_status'] ?? ($post->moderation_status !== 'safe' ? 'pending' : 'approved');
        $post->ai_analyzed_at = now();
        $post->ai_suggested_action = $data['ai_suggested_action'] ?? null;
        $post->save();

        DB::table('post_hashtags')->where('post_id', $postId)->delete();
        $this->syncHashtags($postId, $title, $content, $data['hashtags'] ?? []);
        return true;
    }

    public function repost(int $userId, int $originalPostId, string $comment = ''): int
    {
        $original = $this->find($originalPostId, $userId);
        if (!$original) {
            throw new RuntimeException('Không tìm thấy bài viết gốc để đăng lại.');
        }
        if ((int) $original['user_id'] === $userId) {
            throw new InvalidArgumentException('Bạn không thể tự đăng lại bài viết của chính mình.');
        }

        $comment = trim($comment);
        if (mb_strlen($comment) > 5000) {
            throw new InvalidArgumentException('Lời dẫn đăng lại tối đa 5.000 ký tự.');
        }

        $title = 'Đăng lại: ' . mb_substr((string) $original['title'], 0, 185);
        $slug = 'repost-' . $originalPostId . '-' . bin2hex(random_bytes(8));

        $newPost = Post::create([
            'user_id' => $userId,
            'title' => $title,
            'slug' => $slug,
            'summary' => $comment !== '' ? mb_substr($comment, 0, 240) : null,
            'content' => $comment,
            'thumbnail' => $original['thumbnail'] ?? null,
            'post_type' => 'blog',
            'moderation_status' => 'safe',
            'shared_post_id' => $originalPostId,
            'share_comment' => $comment !== '' ? $comment : null,
        ]);

        return (int) $newPost->id;
    }

    public function topHashtags(int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        return array_map(static fn($r) => (array) $r, DB::select("
            SELECT h.id, h.name, COUNT(DISTINCT ph.post_id) AS usage_count
            FROM hashtags h
            JOIN post_hashtags ph ON ph.hashtag_id = h.id
            JOIN posts p ON p.id = ph.post_id
            WHERE p.moderation_status = 'safe'
            GROUP BY h.id, h.name
            ORDER BY usage_count DESC, h.name ASC
            LIMIT {$limit}
        "));
    }

    public function topNews(int $limit = 15): array
    {
        $limit = max(1, min(50, $limit));
        $rows = DB::select("
            SELECT p.id, p.user_id, p.title, p.slug, p.summary, p.thumbnail, p.post_type, p.moderation_status,
                   p.views_count, p.created_at,
                   u.username, u.full_name, u.avatar, u.is_verified, u.reputation_score
            FROM posts p
            JOIN users u ON u.id = p.user_id
            WHERE p.post_type = 'news' AND p.moderation_status = 'safe'
            ORDER BY p.views_count DESC, p.created_at DESC, p.id DESC
            LIMIT {$limit}
        ");
        return UnicodeHelper::toNfc(array_map(static fn($r) => (array) $r, $rows));
    }

    public function hasReposted(int $userId, int $postId): bool
    {
        return Post::where('user_id', $userId)
            ->where('shared_post_id', $postId)
            ->where('moderation_status', 'safe')
            ->exists();
    }

    private function syncHashtags(int $postId, string $title, string $content, mixed $submittedTags): void
    {
        preg_match_all('/(?<![\pL\pN_])#([\pL\pN_]{1,80})/u', $title . "\n" . $content, $matches);
        $tags = $matches[1] ?? [];
        if (is_string($submittedTags)) {
            $submittedTags = preg_split('/[,\s]+/', $submittedTags) ?: [];
        }
        $tags = array_merge($tags, is_array($submittedTags) ? $submittedTags : []);
        $cleanTags = array_unique(array_filter(array_map(static fn($t): string => strtolower(ltrim(trim((string) $t), '#')), $tags)));

        foreach ($cleanTags as $tagName) {
            if (!preg_match('/^[\pL\pN_]{1,80}$/u', $tagName)) {
                continue;
            }
            $tag = Tag::firstOrCreate(['name' => $tagName], ['created_at' => now()]);
            DB::insert('INSERT IGNORE INTO post_hashtags (post_id, hashtag_id) VALUES (?, ?)', [$postId, $tag->id]);
        }
    }

    private function getUserTopHashtags(int $userId, int $days = 30, int $limit = 15): array
    {
        $rows = DB::select("
            SELECT ph.hashtag_id, COUNT(*) AS score
            FROM post_hashtags ph
            WHERE ph.post_id IN (
                SELECT DISTINCT post_id FROM (
                    SELECT post_id FROM post_reactions WHERE user_id = ? AND created_at >= NOW() - INTERVAL ? DAY
                    UNION ALL
                    SELECT post_id FROM interactions WHERE user_id = ? AND created_at >= NOW() - INTERVAL ? DAY
                    UNION ALL
                    SELECT post_id FROM comments WHERE user_id = ? AND created_at >= NOW() - INTERVAL ? DAY
                ) sub
            )
            GROUP BY ph.hashtag_id
            ORDER BY score DESC
            LIMIT {$limit}
        ", [$userId, $days, $userId, $days, $userId, $days]);

        return array_values(array_filter(array_map(static fn($r) => (int) $r->hashtag_id, $rows)));
    }

    private function normalizePostPayload(array $post): array
    {
        foreach (['id', 'user_id', 'likes_count', 'shares_count', 'comments_count', 'views_count', 'reputation_score'] as $field) {
            if (isset($post[$field])) {
                $post[$field] = (int) $post[$field];
            }
        }
        $commentsCount = (int) ($post['comments_count'] ?? 0);
        $likesCount = (int) ($post['likes_count'] ?? 0);
        $sharesCount = (int) ($post['shares_count'] ?? 0);
        $viewsCount = (int) ($post['views_count'] ?? 0);

        $post['comments_count'] = $commentsCount;
        $post['comment_count'] = $commentsCount;
        $post['total_comments'] = $commentsCount;

        $post['likes_count'] = $likesCount;
        $post['like_count'] = $likesCount;
        $post['total_reactions'] = $likesCount;
        $post['reaction_count'] = $likesCount;

        $post['shares_count'] = $sharesCount;
        $post['share_count'] = $sharesCount;
        $post['total_shares'] = $sharesCount;

        $post['views_count'] = $viewsCount;
        $post['views'] = $viewsCount;
        $post['view_count'] = $viewsCount;

        $post['is_liked'] = !empty($post['my_reaction']);
        $post['is_bookmarked'] = !empty($post['is_bookmarked']);
        $post['is_verified'] = (bool) ($post['is_verified'] ?? false);
        $post['user'] = [
            'id' => (int) ($post['user_id'] ?? 0),
            'username' => $post['username'] ?? '',
            'full_name' => $post['full_name'] ?? '',
            'avatar' => $post['avatar'] ?? '',
            'is_verified' => $post['is_verified'],
            'reputation_score' => (int) ($post['reputation_score'] ?? 0),
        ];

        if (!empty($post['original_id'])) {
            $post['original_id'] = (int) $post['original_id'];
            $post['original_user'] = [
                'id' => (int) $post['original_user_id'],
                'full_name' => $post['original_author'],
                'username' => $post['original_username'],
                'avatar' => $post['original_avatar'],
            ];
        } else {
            $post['original_user'] = null;
        }

        return $post;
    }
}
