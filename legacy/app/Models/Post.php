<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/UnicodeHelper.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Reaction.php';
require_once __DIR__ . '/PostAppeal.php';
require_once __DIR__ . '/Comment.php';

/** Bài viết, thẻ hashtag và thao tác like/share/comment. */
final class Post
{
    private PDO $pdo;

    public function __construct() { $this->pdo = Database::getInstance()->getConnection(); }

    /**
     * Lấy danh sách bài viết với trọng số ưu tiên + phân trang đầy đủ metadata.
     *
     * Chế độ (filters['mode']):
     *   - 'all'       : Feed tổng hợp – ưu tiên người đang theo dõi, kết hợp recency & reputation.
     *   - 'for_you'   : Dành cho bạn – gợi ý dựa trên top hashtag/danh mục 30 ngày gần nhất.
     *   - 'following' : Chỉ bài viết từ người đang theo dõi.
     *   - (mặc định) : ORDER BY created_at DESC.
     *
     * Trả về: ['data' => [...], 'total' => int, 'page' => int, 'last_page' => int]
     * Nếu truyền filters['legacy_array'] = true thì trả về array bài viết thuần (tương thích cũ).
     */
    public function list(array $filters = [], ?int $viewerId = null, int $page = 1, int $perPage = 10): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $where   = [];
        $params  = [];
        $joins   = '';

        if (empty($filters['include_moderated'])) {
            $where[] = "p.moderation_status = 'safe'";
        }

        // Lọc theo loại bài: blog | news (cũng nhận 'category' là alias)
        $typeFilter = $filters['type'] ?? $filters['category'] ?? null;
        if ($typeFilter !== null && $typeFilter !== '' && $typeFilter !== 'all' && in_array($typeFilter, ['blog', 'news'], true)) {
            $where[]        = 'p.post_type = :type';
            $params['type'] = $typeFilter;
        }

        // Lọc bài có kèm hình ảnh (has_image: 1 / with_image hoặc 0 / no_image)
        $hasImageFilter = $filters['has_image'] ?? null;
        if ($hasImageFilter !== null && $hasImageFilter !== '' && $hasImageFilter !== 'all') {
            if ($hasImageFilter === '1' || $hasImageFilter === 1 || $hasImageFilter === 'with_image' || $hasImageFilter === true) {
                $where[] = "((p.thumbnail IS NOT NULL AND TRIM(p.thumbnail) != '') OR p.content LIKE '%<img%' OR p.content LIKE '%![%]%')";
            } elseif ($hasImageFilter === '0' || $hasImageFilter === 0 || $hasImageFilter === 'no_image' || $hasImageFilter === false) {
                $where[] = "((p.thumbnail IS NULL OR TRIM(p.thumbnail) = '') AND p.content NOT LIKE '%<img%' AND p.content NOT LIKE '%![%]%')";
            }
        }

        if (!empty($filters['user_id'])) {
            $where[]           = 'p.user_id = :user_id';
            $params['user_id'] = (int)$filters['user_id'];
        }

        // Chế độ 'following': chỉ bài từ người đang theo dõi
        if (!empty($filters['following']) && $viewerId !== null) {
            $where[]                  = 'EXISTS (SELECT 1 FROM follows f WHERE f.follower_id = :follow_viewer AND f.following_id = p.user_id)';
            $params['follow_viewer']  = $viewerId;
        }

        if (!empty($filters['hashtag'])) {
            $where[]            = 'EXISTS (SELECT 1 FROM post_hashtags ph JOIN hashtags h ON h.id = ph.hashtag_id WHERE ph.post_id = p.id AND h.name = :hashtag)';
            $params['hashtag']  = strtolower(ltrim((string)$filters['hashtag'], '#'));
        }

        // Lọc bài viết theo username tác giả (chính xác hoặc gần đúng)
        if (!empty($filters['author_username'])) {
            $where[]                   = 'u.username LIKE :author_username';
            $params['author_username'] = '%' . ltrim((string)$filters['author_username'], '@') . '%';
        }

        // Tìm kiếm đa năng: Tiêu đề, nội dung, hashtag VÀ tên người viết (họ tên hoặc @username)
        if (!empty($filters['q'])) {
            $where[]  = '(p.title LIKE :q_title OR p.content LIKE :q_content OR u.full_name LIKE :q_author OR u.username LIKE :q_uname OR EXISTS (SELECT 1 FROM post_hashtags ph JOIN hashtags h ON h.id = ph.hashtag_id WHERE ph.post_id = p.id AND h.name LIKE :q_tag))';
            $needle   = '%' . (string)$filters['q'] . '%';
            $params  += [
                'q_title'   => $needle,
                'q_content' => $needle,
                'q_author'  => $needle,
                'q_uname'   => $needle,
                'q_tag'     => $needle,
            ];
        }

        // ---- ORDER BY logic ----
        $mode = $filters['mode'] ?? 'default';
        $sort = $filters['sort'] ?? null;
        $seedVal = isset($filters['seed']) && is_numeric($filters['seed']) ? (int)$filters['seed'] : null;
        $randExpr = $seedVal !== null ? "RAND({$seedVal})" : 'RAND()';
        $hasFyJoin = false;

        if (!empty($filters['user_id']) && empty($filters['sort']) && (empty($filters['mode']) || $filters['mode'] === 'default')) {
            $orderBy = 'p.created_at DESC, p.id DESC';
        } elseif ($sort === 'popular') {
            $orderBy = 'likes_count DESC, views_count DESC, p.created_at DESC';
        } elseif ($sort === 'latest') {
            $orderBy = 'p.created_at DESC, p.id DESC';
        } elseif ($mode === 'for_you') {
            // Dành cho bạn: boost bài có hashtag mà user đã tương tác 30 ngày qua + độ mới + ngẫu nhiên tươi mới
            $topTags = $viewerId !== null ? $this->getUserTopHashtags($viewerId, 30) : [];
            if (!empty($topTags)) {
                $tagIdsList = implode(',', array_map('intval', $topTags));
                $orderBy = "(fy_score * 40 + (u.reputation_score * 0.04) + (1 / (1 + TIMESTAMPDIFF(HOUR, p.created_at, NOW()) * 0.02)) * 60 + ({$randExpr} * 35)) DESC, p.id DESC";
                $hasFyJoin = true;
            } else {
                $orderBy = "((u.reputation_score * 0.04) + (1 / (1 + TIMESTAMPDIFF(HOUR, p.created_at, NOW()) * 0.02)) * 60 + ({$randExpr} * 35)) DESC, p.id DESC";
            }
        } elseif ($mode === 'all' || $mode === 'default' || $sort === null) {
            // Feed tổng hợp / Tất cả: kết hợp bài mới, uy tín tác giả, đang theo dõi (nếu có) và biến ngẫu nhiên tươi mới mỗi lần F5
            if ($viewerId !== null) {
                $joins .= ' LEFT JOIN follows flw ON flw.follower_id = ' . (int)$viewerId . ' AND flw.following_id = p.user_id';
                $orderBy = "(IF(flw.follower_id IS NOT NULL, 40, 0) + (u.reputation_score * 0.04) + (1 / (1 + TIMESTAMPDIFF(HOUR, p.created_at, NOW()) * 0.02)) * 50 + ({$randExpr} * 35)) DESC, p.id DESC";
            } else {
                $orderBy = "((u.reputation_score * 0.04) + (1 / (1 + TIMESTAMPDIFF(HOUR, p.created_at, NOW()) * 0.02)) * 50 + ({$randExpr} * 35)) DESC, p.id DESC";
            }
        } else {
            $orderBy = 'p.created_at DESC, p.id DESC';
        }

        // ---- Viewer subqueries ----
        $vId = $viewerId === null ? 'NULL' : (string)(int)$viewerId;
        $viewerReactionSelect  = "COALESCE((SELECT pr.reaction FROM post_reactions pr WHERE pr.post_id = p.id AND pr.user_id = {$vId} LIMIT 1), NULL)";
        $viewerBookmarkSelect  = $viewerId === null ? '0' : "(SELECT 1 FROM interactions ibk WHERE ibk.post_id = p.id AND ibk.user_id = {$vId} AND ibk.type = 'bookmark' LIMIT 1)";

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // ---- COUNT total ----
        $countSql  = "SELECT COUNT(*) FROM posts p JOIN users u ON u.id = p.user_id {$joins} {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);

        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        // ---- Main SELECT ----
        $sql = 'SELECT p.id, p.user_id, p.title, p.slug, p.summary, p.content, p.thumbnail, p.post_type, p.moderation_status, p.views_count, p.created_at, '
            . 'p.shared_post_id, p.share_comment, orig.id AS original_id, orig.title AS original_title, orig.summary AS original_summary, orig.content AS original_content, orig.thumbnail AS original_thumbnail, orig.user_id AS original_user_id, orig_u.full_name AS original_author, orig_u.username AS original_username, orig_u.avatar AS original_avatar, '
            . 'u.username, u.full_name, u.avatar, u.is_verified, u.reputation_score, '
            . '(SELECT COUNT(*) FROM post_reactions prc WHERE prc.post_id = p.id) AS likes_count, '
            . "(SELECT COUNT(DISTINCT s.user_id) FROM (SELECT user_id, post_id FROM interactions WHERE type = 'share' UNION ALL SELECT user_id, shared_post_id AS post_id FROM posts WHERE shared_post_id IS NOT NULL AND moderation_status = 'safe') s WHERE s.post_id = p.id AND s.user_id <> p.user_id) AS shares_count, "
            . "(SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.moderation_status = 'safe' AND c.is_deleted = 0) AS comments_count, "
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
            . ' LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll();

        // Hashtags & Reaction Breakdown – N+1 prevention
        $tagsByPost = [];
        $reactionsByPost = [];
        if ($posts) {
            $ids          = array_map(static fn(array $post): int => (int)$post['id'], $posts);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $tagStmt      = $this->pdo->prepare("SELECT ph.post_id, h.name FROM post_hashtags ph JOIN hashtags h ON h.id = ph.hashtag_id WHERE ph.post_id IN ({$placeholders}) ORDER BY h.name");
            $tagStmt->execute($ids);
            foreach ($tagStmt->fetchAll() as $tag) $tagsByPost[(int)$tag['post_id']][] = $tag['name'];

            $rStmt = $this->pdo->prepare("SELECT post_id, reaction, COUNT(*) AS cnt FROM post_reactions WHERE post_id IN ({$placeholders}) GROUP BY post_id, reaction");
            $rStmt->execute($ids);
            foreach ($rStmt->fetchAll() as $r) {
                $pid = (int)$r['post_id'];
                if (!isset($reactionsByPost[$pid])) {
                    $reactionsByPost[$pid] = ['like' => 0, 'love' => 0, 'haha' => 0, 'sad' => 0, 'angry' => 0];
                }
                $reactionsByPost[$pid][$r['reaction']] = (int)$r['cnt'];
            }
        }

        $mapped = array_map(function (array $post) use ($tagsByPost, $reactionsByPost): array {
            $post['hashtags'] = $tagsByPost[(int)$post['id']] ?? [];
            $post['reaction_counts'] = $reactionsByPost[(int)$post['id']] ?? ['like' => 0, 'love' => 0, 'haha' => 0, 'sad' => 0, 'angry' => 0];
            foreach (['id','user_id','likes_count','shares_count','comments_count','views_count','reputation_score'] as $field) {
                if (isset($post[$field])) $post[$field] = (int)$post[$field];
            }
            $commentsCount           = (int)($post['comments_count'] ?? 0);
            $likesCount              = (int)($post['likes_count'] ?? 0);
            $sharesCount             = (int)($post['shares_count'] ?? 0);
            $viewsCount              = (int)($post['views_count'] ?? 0);

            $post['comments_count']  = $commentsCount;
            $post['comment_count']   = $commentsCount;
            $post['total_comments']  = $commentsCount;

            $post['likes_count']     = $likesCount;
            $post['like_count']      = $likesCount;
            $post['total_reactions'] = $likesCount;
            $post['reaction_count']  = $likesCount;

            $post['shares_count']    = $sharesCount;
            $post['share_count']     = $sharesCount;
            $post['total_shares']    = $sharesCount;

            $post['views_count']     = $viewsCount;
            $post['views']           = $viewsCount;
            $post['view_count']      = $viewsCount;

            $post['is_liked']        = !empty($post['my_reaction']);
            $post['is_bookmarked'] = !empty($post['is_bookmarked']);
            $post['is_verified']  = (bool)($post['is_verified'] ?? false);
            $post['user']         = [
                'id'               => $post['user_id'],
                'username'         => $post['username'],
                'full_name'        => $post['full_name'],
                'avatar'           => $post['avatar'],
                'is_verified'      => $post['is_verified'],
                'reputation_score' => $post['reputation_score'] ?? 0,
            ];
            if (!empty($post['original_id'])) {
                $post['original_user'] = [
                    'id'        => (int)$post['original_user_id'],
                    'full_name' => $post['original_author'],
                    'username'  => $post['original_username'],
                    'avatar'    => $post['original_avatar'],
                ];
            }
            return $post;
        }, $posts);

        // Tương thích ngược: nếu caller không cần metadata thì trả mảng thuần
        if (!empty($filters['legacy_array'])) {
            return $mapped;
        }

        return [
            'data'      => $mapped,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int)ceil($total / $perPage)),
            'seed'      => $seedVal,
        ];
    }

    /**
     * Lấy top N hashtag_id mà user đã tương tác trong $days ngày gần nhất.
     * Dùng cho chế độ 'for_you'.
     */
    private function getUserTopHashtags(int $userId, int $days = 30, int $limit = 15): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ph.hashtag_id, COUNT(*) AS score
            FROM post_hashtags ph
            WHERE ph.post_id IN (
                SELECT DISTINCT post_id FROM (
                    SELECT post_id FROM post_reactions WHERE user_id = :uid AND created_at >= NOW() - INTERVAL :days DAY
                    UNION ALL
                    SELECT post_id FROM interactions WHERE user_id = :uid2 AND created_at >= NOW() - INTERVAL :days2 DAY
                    UNION ALL
                    SELECT post_id FROM comments WHERE user_id = :uid3 AND created_at >= NOW() - INTERVAL :days3 DAY
                ) sub
            )
            GROUP BY ph.hashtag_id
            ORDER BY score DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':uid',   $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid3',  $userId, PDO::PARAM_INT);
        $stmt->bindValue(':days',  $days,   PDO::PARAM_INT);
        $stmt->bindValue(':days2', $days,   PDO::PARAM_INT);
        $stmt->bindValue(':days3', $days,   PDO::PARAM_INT);
        $stmt->bindValue(':lim',   $limit,  PDO::PARAM_INT);
        $stmt->execute();
        $raw = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'hashtag_id');
        return array_values(array_filter(array_map('intval', $raw)));
    }

    public function find(int $id, ?int $viewerId = null): ?array
    {
        $stmt = $this->pdo->prepare("SELECT p.id, p.user_id, p.title, p.slug, p.summary, p.content, p.thumbnail, p.post_type, p.moderation_status, p.ai_severity, p.ai_violation_type, p.ai_reason, p.moderated_at, p.views_count, p.created_at, p.shared_post_id, p.share_comment, orig.id AS original_id, orig.title AS original_title, orig.summary AS original_summary, orig.content AS original_content, orig.thumbnail AS original_thumbnail, orig.user_id AS original_user_id, orig_u.full_name AS original_author, orig_u.username AS original_username, orig_u.avatar AS original_avatar, u.username, u.full_name, u.avatar, u.is_verified, u.reputation_score, (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id=p.id) likes_count, (SELECT COUNT(DISTINCT s.user_id) FROM (SELECT user_id, post_id FROM interactions WHERE type = 'share' UNION ALL SELECT user_id, shared_post_id AS post_id FROM posts WHERE shared_post_id IS NOT NULL AND moderation_status = 'safe') s WHERE s.post_id = p.id AND s.user_id <> p.user_id) shares_count, (SELECT COUNT(*) FROM comments c WHERE c.post_id=p.id AND c.moderation_status='safe' AND c.is_deleted=0) comments_count FROM posts p JOIN users u ON u.id=p.user_id LEFT JOIN posts orig ON orig.id=p.shared_post_id AND orig.moderation_status='safe' LEFT JOIN users orig_u ON orig_u.id=orig.user_id WHERE p.id=? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $isOwner = ($viewerId !== null && (int)$row['user_id'] === $viewerId);
        $isAdmin = class_exists('Auth') ? Auth::isAdmin() : false;
        $isRestricted = false;
        $canViewOriginal = true;
        $blockReason = null;
        $latestAppeal = null;

        if ($row['moderation_status'] !== 'safe') {
            // Lấy lý do khóa/ẩn (tuyệt đối không để lộ danh tính người báo cáo)
            $rptStmt = $this->pdo->prepare("SELECT admin_note FROM post_reports WHERE post_id = ? AND admin_note IS NOT NULL AND admin_note != '' ORDER BY id DESC LIMIT 1");
            $rptStmt->execute([$id]);
            $adminNote = $rptStmt->fetchColumn();
            if ($adminNote) {
                $blockReason = (string)$adminNote;
            } elseif (!empty($row['ai_reason'])) {
                $blockReason = (string)$row['ai_reason'];
            } else {
                $blockReason = 'Bài viết đã bị tạm khóa do vi phạm Tiêu chuẩn cộng đồng.';
            }

            $appealModel = new PostAppeal();
            $latestAppeal = $appealModel->findLatestByPost($id);

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
            $reaction = $this->pdo->prepare('SELECT reaction FROM post_reactions WHERE user_id=? AND post_id=?');
            $reaction->execute([$viewerId, $id]); $row['my_reaction'] = $reaction->fetchColumn() ?: null;

            $bmk = $this->pdo->prepare("SELECT 1 FROM interactions WHERE user_id=? AND post_id=? AND type='bookmark' LIMIT 1");
            $bmk->execute([$viewerId, $id]);
            $row['is_bookmarked'] = (bool)$bmk->fetchColumn();
        }
        $row = $this->withHashtags($row);
        $row['reaction_counts'] = (new Reaction())->counts($id);

        if ($isRestricted) {
            $row['comments'] = [];
            $row['comments_count'] = 0;
        } else {
            $row['comments'] = (new Comment())->getByPost($id, 'safe', $viewerId);
        }

        if (!empty($row['original_id'])) {
            $row['original_id'] = (int)$row['original_id'];
            $row['original_user'] = ['id' => (int)$row['original_user_id'], 'full_name' => $row['original_author'], 'username' => $row['original_username'], 'avatar' => $row['original_avatar']];
        } else {
            $row['original_user'] = null;
        }
        $row['total_shares'] = (int)($row['shares_count'] ?? 0);
        return $row;
    }

    private function withHashtags(array $post): array
    {
        $stmt = $this->pdo->prepare('SELECT h.name FROM hashtags h JOIN post_hashtags ph ON ph.hashtag_id=h.id WHERE ph.post_id=? ORDER BY h.name');
        $stmt->execute([(int)$post['id']]);
        $post['hashtags'] = array_column($stmt->fetchAll(), 'name');
        foreach (['id','user_id','likes_count','shares_count','comments_count','views_count','reputation_score'] as $field) if (isset($post[$field])) $post[$field] = (int)$post[$field];
        $commentsCount           = (int)($post['comments_count'] ?? 0);
        $likesCount              = (int)($post['likes_count'] ?? 0);
        $sharesCount             = (int)($post['shares_count'] ?? 0);
        $viewsCount              = (int)($post['views_count'] ?? 0);

        $post['comments_count']  = $commentsCount;
        $post['comment_count']   = $commentsCount;
        $post['total_comments']  = $commentsCount;

        $post['likes_count']     = $likesCount;
        $post['like_count']      = $likesCount;
        $post['total_reactions'] = $likesCount;
        $post['reaction_count']  = $likesCount;

        $post['shares_count']    = $sharesCount;
        $post['share_count']     = $sharesCount;
        $post['total_shares']    = $sharesCount;

        $post['views_count']     = $viewsCount;
        $post['views']           = $viewsCount;
        $post['view_count']      = $viewsCount;

        $post['is_liked']        = !empty($post['my_reaction']);
        $post['is_verified'] = (bool)($post['is_verified'] ?? false);
        $post['user'] = ['id' => $post['user_id'], 'username' => $post['username'], 'full_name' => $post['full_name'], 'avatar' => $post['avatar'], 'is_verified' => $post['is_verified'], 'reputation_score' => $post['reputation_score'] ?? 0];
        return $post;
    }

    /**
     * Lấy danh sách các bài viết đã được người dùng lưu (Bookmark).
     * Chỉ trả về bài viết hợp lệ (safe), có phân trang, sắp xếp theo thời điểm lưu mới nhất trước.
     * Không gây lỗi N+1 query.
     */
    public function listBookmarked(int $userId, int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));

        $sql = 'SELECT p.id, p.user_id, p.title, p.slug, p.summary, p.content, p.thumbnail, p.post_type, p.moderation_status, p.views_count, p.created_at, '
            . 'p.shared_post_id, p.share_comment, orig.id AS original_id, orig.title AS original_title, orig.summary AS original_summary, orig.content AS original_content, orig.thumbnail AS original_thumbnail, orig.user_id AS original_user_id, orig_u.full_name AS original_author, orig_u.username AS original_username, orig_u.avatar AS original_avatar, '
            . 'u.username, u.full_name, u.avatar, u.is_verified, u.reputation_score, '
            . '(SELECT COUNT(*) FROM post_reactions prc WHERE prc.post_id = p.id) AS likes_count, '
            . "(SELECT COUNT(DISTINCT s.user_id) FROM (SELECT user_id, post_id FROM interactions WHERE type = 'share' UNION ALL SELECT user_id, shared_post_id AS post_id FROM posts WHERE shared_post_id IS NOT NULL AND moderation_status = 'safe') s WHERE s.post_id = p.id AND s.user_id <> p.user_id) AS shares_count, "
            . "(SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.moderation_status = 'safe' AND c.is_deleted = 0) AS comments_count, "
            . '(SELECT pr.reaction FROM post_reactions pr WHERE pr.post_id = p.id AND pr.user_id = :viewer_id LIMIT 1) AS my_reaction, '
            . '1 AS is_bookmarked, '
            . 'i.created_at AS bookmarked_at '
            . 'FROM interactions i '
            . 'JOIN posts p ON p.id = i.post_id '
            . 'JOIN users u ON u.id = p.user_id '
            . "LEFT JOIN posts orig ON orig.id = p.shared_post_id AND orig.moderation_status = 'safe' "
            . 'LEFT JOIN users orig_u ON orig_u.id = orig.user_id '
            . "WHERE i.user_id = :user_id AND i.type = 'bookmark' AND p.moderation_status = 'safe' "
            . 'ORDER BY i.created_at DESC, i.id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll();
        if (!$posts) return [];

        // N+1 prevention: load all hashtags for this page in one query.
        $ids = array_map(static fn(array $post): int => (int)$post['id'], $posts);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $tagStmt = $this->pdo->prepare("SELECT ph.post_id, h.name FROM post_hashtags ph JOIN hashtags h ON h.id = ph.hashtag_id WHERE ph.post_id IN ($placeholders) ORDER BY h.name");
        $tagStmt->execute($ids);
        $tagsByPost = [];
        foreach ($tagStmt->fetchAll() as $tag) $tagsByPost[(int)$tag['post_id']][] = $tag['name'];

        return array_map(function (array $post) use ($tagsByPost): array {
            $post['hashtags'] = $tagsByPost[(int)$post['id']] ?? [];
            foreach (['id','user_id','likes_count','shares_count','comments_count','views_count','reputation_score'] as $field) if (isset($post[$field])) $post[$field] = (int)$post[$field];
            $commentsCount           = (int)($post['comments_count'] ?? 0);
            $likesCount              = (int)($post['likes_count'] ?? 0);
            $sharesCount             = (int)($post['shares_count'] ?? 0);
            $viewsCount              = (int)($post['views_count'] ?? 0);

            $post['comments_count']  = $commentsCount;
            $post['comment_count']   = $commentsCount;
            $post['total_comments']  = $commentsCount;

            $post['likes_count']     = $likesCount;
            $post['like_count']      = $likesCount;
            $post['total_reactions'] = $likesCount;
            $post['reaction_count']  = $likesCount;

            $post['shares_count']    = $sharesCount;
            $post['share_count']     = $sharesCount;
            $post['total_shares']    = $sharesCount;

            $post['views_count']     = $viewsCount;
            $post['views']           = $viewsCount;
            $post['view_count']      = $viewsCount;
            $post['total_shares'] = (int)($post['shares_count'] ?? 0);
            $post['is_liked'] = !empty($post['my_reaction']);
            $post['is_bookmarked'] = true;
            $post['is_verified'] = (bool)($post['is_verified'] ?? false);
            $post['user'] = ['id' => $post['user_id'], 'username' => $post['username'], 'full_name' => $post['full_name'], 'avatar' => $post['avatar'], 'is_verified' => $post['is_verified'], 'reputation_score' => $post['reputation_score'] ?? 0];
            if ($post['original_id'] !== null) $post['original_user'] = ['id' => (int)$post['original_user_id'], 'full_name' => $post['original_author'], 'username' => $post['original_username'], 'avatar' => $post['original_avatar']];
            return $post;
        }, $posts);
    }

    /**
     * Đếm tổng số bài viết an toàn đã lưu của người dùng.
     */
    public function countBookmarked(int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM interactions i JOIN posts p ON p.id = i.post_id WHERE i.user_id = ? AND i.type = 'bookmark' AND p.moderation_status = 'safe'");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function create(int $userId, array $data): int
    {
        $title = UnicodeHelper::toNfc(trim((string)($data['title'] ?? '')));
        $content = UnicodeHelper::toNfc((string)($data['content'] ?? ''));
        $slugBase = trim((string)preg_replace('/[^a-z0-9]+/i', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title)), '-');
        $slug = ($slugBase ?: 'post') . '-' . bin2hex(random_bytes(4));
        $rawSummary = trim((string)($data['summary'] ?? '')) ?: mb_substr($content, 0, 240);
        $summary = UnicodeHelper::toNfc($rawSummary);
        
        $moderationStatus = (string)($data['moderation_status'] ?? 'safe');
        if (!in_array($moderationStatus, ['safe', 'flagged', 'blocked', 'pending'], true)) {
            $moderationStatus = 'safe';
        }
        $aiSeverity = $data['ai_severity'] ?? null;
        $aiViolationType = $data['ai_violation_type'] ?? null;
        $aiReason = $data['ai_reason'] ?? null;
        $aiStatus = $data['ai_status'] ?? ($moderationStatus !== 'safe' ? 'pending' : null);

        $stmt = $this->pdo->prepare('INSERT INTO posts (user_id, title, slug, summary, content, thumbnail, post_type, moderation_status, ai_severity, ai_violation_type, ai_reason, ai_status) VALUES (:user_id, :title, :slug, :summary, :content, :thumbnail, :post_type, :moderation_status, :ai_severity, :ai_violation_type, :ai_reason, :ai_status)');
        $stmt->execute([
            'user_id'           => $userId,
            'title'             => $title,
            'slug'              => $slug,
            'summary'           => $summary,
            'content'           => $content,
            'thumbnail'         => $data['thumbnail'] ?? null,
            'post_type'         => $data['post_type'],
            'moderation_status' => $moderationStatus,
            'ai_severity'       => $aiSeverity,
            'ai_violation_type' => $aiViolationType,
            'ai_reason'         => $aiReason,
            'ai_status'         => $aiStatus,
        ]);
        $postId = (int)$this->pdo->lastInsertId();
        // Auto-extract hashtags from title/content, then merge any submitted tags.
        preg_match_all('/(?<![\pL\pN_])#([\pL\pN_]{1,80})/u', (string)$data['title'] . "\n" . (string)$data['content'], $matches);
        $tags = $matches[1] ?? [];
        $submittedTags = $data['hashtags'] ?? [];
        if (is_string($submittedTags)) $submittedTags = preg_split('/[,\s]+/', $submittedTags) ?: [];
        $tags = array_merge($tags, is_array($submittedTags) ? $submittedTags : []);
        foreach (array_unique(array_filter(array_map(static fn($tag): string => strtolower(ltrim(trim((string)$tag), '#')), $tags))) as $tag) {
            if (!preg_match('/^[\pL\pN_]{1,80}$/u', $tag)) continue;
            $this->pdo->prepare('INSERT INTO hashtags (name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$tag]);
            $tagId = (int)$this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT IGNORE INTO post_hashtags (post_id,hashtag_id) VALUES (?,?)')->execute([$postId,$tagId]);
        }
        return $postId;
    }

    public function updateOwned(int $postId, int $userId, array $data): bool
    {
        $title = UnicodeHelper::toNfc(trim((string)($data['title'] ?? '')));
        $content = UnicodeHelper::toNfc((string)($data['content'] ?? ''));
        $rawSummary = trim((string)($data['summary'] ?? '')) ?: mb_substr($content, 0, 240);
        $summary = UnicodeHelper::toNfc($rawSummary);
        $moderationStatus = (string)($data['moderation_status'] ?? 'safe');
        $aiSeverity = $data['ai_severity'] ?? null;
        $aiViolationType = $data['ai_violation_type'] ?? null;
        $aiReason = $data['ai_reason'] ?? null;
        $aiStatus = $data['ai_status'] ?? ($moderationStatus !== 'safe' ? 'pending' : 'approved');

        $stmt = $this->pdo->prepare('UPDATE posts SET title=?, summary=?, content=?, thumbnail=COALESCE(?, thumbnail), moderation_status=?, ai_severity=?, ai_violation_type=?, ai_reason=?, ai_status=? WHERE id=? AND user_id=?');
        $stmt->execute([
            $title,
            $summary,
            $content,
            $data['thumbnail'] ?? null,
            $moderationStatus,
            $aiSeverity,
            $aiViolationType,
            $aiReason,
            $aiStatus,
            $postId,
            $userId
        ]);
        if ($stmt->rowCount() === 0) {
            $check = $this->pdo->prepare('SELECT 1 FROM posts WHERE id=? AND user_id=?');
            $check->execute([$postId, $userId]);
            if (!$check->fetchColumn()) return false;
        }

        $this->pdo->prepare('DELETE FROM post_hashtags WHERE post_id=?')->execute([$postId]);
        $tags = [];
        preg_match_all('/(?<![\pL\pN_])#([\pL\pN_]{1,80})/u', (string)$data['title'] . "\n" . (string)$data['content'], $matches);
        foreach (array_unique(array_filter(array_map(static fn($tag): string => strtolower(ltrim(trim((string)$tag), '#')), $matches[1] ?? []))) as $tag) {
            $this->pdo->prepare('INSERT INTO hashtags (name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$tag]);
            $tagId = (int)$this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT IGNORE INTO post_hashtags (post_id,hashtag_id) VALUES (?,?)')->execute([$postId, $tagId]);
        }
        return true;
    }

    public function deleteOwned(int $postId, int $userId): bool
    {
        return $this->deletePostWithCleanup($postId, $userId);
    }

    public function deleteAny(int $postId): bool
    {
        return $this->deletePostWithCleanup($postId, null);
    }

    /**
     * Xóa bài viết an toàn trong transaction:
     * 1. Xác minh bài viết (và quyền sở hữu nếu $expectedOwnerId !== null).
     * 2. Xóa bản ghi bài viết trong CSDL.
     * 3. Đồng bộ lại reputation_score cho tác giả bài bị xóa (và tác giả bài gốc nếu là repost) từ dữ liệu nguồn.
     * 4. Sau khi commit thành công, xóa tệp thumbnail local nếu không còn bài viết nào tham chiếu.
     */
    private function deletePostWithCleanup(int $postId, ?int $expectedOwnerId): bool
    {
        if ($postId <= 0) {
            return false;
        }

        require_once __DIR__ . '/../Services/ReputationService.php';
        require_once __DIR__ . '/../Services/UploadService.php';

        $startedTx = !$this->pdo->inTransaction();
        if ($startedTx) {
            $this->pdo->beginTransaction();
        }

        $thumbnail = null;

        try {
            if ($expectedOwnerId !== null) {
                $find = $this->pdo->prepare('SELECT id, user_id, thumbnail, shared_post_id FROM posts WHERE id = ? AND user_id = ? FOR UPDATE');
                $find->execute([$postId, $expectedOwnerId]);
            } else {
                $find = $this->pdo->prepare('SELECT id, user_id, thumbnail, shared_post_id FROM posts WHERE id = ? FOR UPDATE');
                $find->execute([$postId]);
            }

            $postRow = $find->fetch();
            if (!$postRow) {
                if ($startedTx && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return false;
            }

            $authorId        = (int)$postRow['user_id'];
            $thumbnail       = isset($postRow['thumbnail']) ? (string)$postRow['thumbnail'] : null;
            $sharedPostId    = !empty($postRow['shared_post_id']) ? (int)$postRow['shared_post_id'] : null;
            $originalOwnerId = null;

            if ($sharedPostId !== null) {
                $origStmt = $this->pdo->prepare('SELECT user_id FROM posts WHERE id = ? LIMIT 1');
                $origStmt->execute([$sharedPostId]);
                $origUid = $origStmt->fetchColumn();
                if ($origUid !== false) {
                    $originalOwnerId = (int)$origUid;
                }
            }

            if ($expectedOwnerId !== null) {
                $delStmt = $this->pdo->prepare('DELETE FROM posts WHERE id = ? AND user_id = ?');
                $delStmt->execute([$postId, $expectedOwnerId]);
            } else {
                $delStmt = $this->pdo->prepare('DELETE FROM posts WHERE id = ?');
                $delStmt->execute([$postId]);
            }

            if ($delStmt->rowCount() === 0) {
                if ($startedTx && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return false;
            }

            // Đồng bộ lại điểm uy tín theo công thức dữ liệu nguồn ngay trong cùng transaction
            $reputationService = new ReputationService();
            $affectedUsers = [$authorId];
            if ($originalOwnerId !== null && $originalOwnerId !== $authorId) {
                $affectedUsers[] = $originalOwnerId;
            }
            foreach ($affectedUsers as $uid) {
                $reputationService->calculate($uid);
            }

            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("[Post] Lỗi xóa bài viết id={$postId}: " . $e->getMessage());
            throw $e;
        }

        // Chỉ dọn tệp thumbnail local SAU KHI giao dịch DB đã commit thành công
        if ($thumbnail !== null && trim($thumbnail) !== '') {
            try {
                UploadService::deletePostThumbnailSafely($thumbnail, $this->pdo);
            } catch (Throwable $e) {
                error_log("[Post] Lỗi kiểm tra/xóa tệp thumbnail sau khi xóa bài id={$postId}: " . $e->getMessage());
            }
        }

        return true;
    }

    public function repost(int $userId, int $originalPostId, string $comment = ''): int
    {
        $original = $this->find($originalPostId, $userId);
        if (!$original) throw new RuntimeException('Không tìm thấy bài viết gốc để đăng lại.');
        if ((int)$original['user_id'] === $userId) {
            throw new InvalidArgumentException('Bạn không thể tự đăng lại bài viết của chính mình.');
        }
        $comment = trim($comment);
        if (mb_strlen($comment) > 5000) throw new InvalidArgumentException('Lời dẫn đăng lại tối đa 5.000 ký tự.');
        $title = 'Đăng lại: ' . mb_substr((string)$original['title'], 0, 185);
        $slug = 'repost-' . $originalPostId . '-' . bin2hex(random_bytes(8));
        $summary = $comment !== '' ? mb_substr($comment, 0, 240) : null;
        $content = $comment;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO posts (user_id, title, slug, summary, content, thumbnail, post_type, moderation_status, shared_post_id, share_comment) VALUES (?, ?, ?, ?, ?, ?, 'blog', 'safe', ?, ?)");
            $stmt->execute([$userId, $title, $slug, $summary, $content, $original['thumbnail'] ?? null, $originalPostId, $comment !== '' ? $comment : null]);
            $newPostId = (int)$this->pdo->lastInsertId();

            // Lượt đăng lại được theo dõi tự động qua cột shared_post_id trong bảng posts.
            // Không chèn dư thừa vào bảng interactions để khi người dùng xóa bài repost mà chưa từng bấm sao chép liên kết,
            // lượt chia sẻ sẽ tự động giảm về 0 (nhờ COUNT(DISTINCT s.user_id) kết hợp UNION ALL giữa interactions và posts).

            $this->pdo->commit();
            return $newPostId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function toggleInteraction(int $userId, int $postId, string $type): bool
    {
        if (!in_array($type, ['like','share'], true)) throw new InvalidArgumentException('Loại tương tác không hợp lệ.');
        $post = $this->pdo->prepare('SELECT user_id FROM posts WHERE id=? AND moderation_status=\'safe\'');
        $post->execute([$postId]); $owner = $post->fetchColumn();
        if (!$owner) throw new RuntimeException('Không tìm thấy bài viết.');
        if ($type === 'like') {
            $active = (new Reaction())->set($userId, $postId, 'like') !== null;
            (new User())->updateReputation((int)$owner);
            return $active;
        }
        if ($type === 'share') {
            if ((int)$owner === $userId) {
                throw new InvalidArgumentException('Bạn không thể tự chia sẻ bài viết của chính mình.');
            }
            $this->pdo->prepare("INSERT IGNORE INTO interactions (user_id, post_id, type, created_at) VALUES (?, ?, 'share', NOW())")->execute([$userId, $postId]);
            (new User())->updateReputation((int)$owner);
            return true;
        }
        return false;
    }

    public function addComment(int $userId, int $postId, string $content): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO comments (user_id,post_id,content,moderation_status) SELECT ?,id,?,'safe' FROM posts WHERE id=? AND moderation_status='safe'");
        $stmt->execute([$userId,$content,$postId]);
        if ($stmt->rowCount() === 0) throw new RuntimeException('Không tìm thấy bài viết.');
        $owner = $this->pdo->prepare('SELECT user_id FROM posts WHERE id=?'); $owner->execute([$postId]);
        (new User())->updateReputation((int)$owner->fetchColumn());
        return (int)$this->pdo->lastInsertId();
    }

    public function topHashtags(int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare("
            SELECT h.id, h.name, COUNT(DISTINCT ph.post_id) AS usage_count
            FROM hashtags h
            JOIN post_hashtags ph ON ph.hashtag_id = h.id
            JOIN posts p ON p.id = ph.post_id
            WHERE p.moderation_status = 'safe'
            GROUP BY h.id, h.name
            ORDER BY usage_count DESC, h.name ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function topNews(int $limit = 15): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.user_id, p.title, p.slug, p.summary, p.thumbnail, p.post_type, p.moderation_status,
                   p.views_count, p.created_at,
                   u.username, u.full_name, u.avatar, u.is_verified, u.reputation_score
            FROM posts p
            JOIN users u ON u.id = p.user_id
            WHERE p.post_type = 'news' AND p.moderation_status = 'safe'
            ORDER BY p.views_count DESC, p.created_at DESC, p.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function adminList(): array
    {
        return $this->pdo->query('SELECT p.id, p.title, p.post_type, p.moderation_status, p.views_count, p.created_at, u.full_name author_name, (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.moderation_status = \'safe\') AS comments_count, (SELECT COUNT(*) FROM post_reactions pr WHERE pr.post_id = p.id) AS likes_count FROM posts p JOIN users u ON u.id=p.user_id ORDER BY p.created_at DESC LIMIT 200')->fetchAll();
    }

    public function hasReposted(int $userId, int $postId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM posts WHERE user_id = ? AND shared_post_id = ? AND moderation_status = 'safe' LIMIT 1");
        $stmt->execute([$userId, $postId]);
        return (bool)$stmt->fetchColumn();
    }

    public function getSharesCount(int $postId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT s.user_id) 
            FROM (
                SELECT user_id, post_id FROM interactions WHERE type = 'share'
                UNION ALL
                SELECT user_id, shared_post_id AS post_id FROM posts WHERE shared_post_id IS NOT NULL AND moderation_status = 'safe'
            ) s 
            JOIN posts p ON p.id = s.post_id
            WHERE s.post_id = ? AND s.user_id <> p.user_id");
        $stmt->execute([$postId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Ghi nhận và tăng lượt xem bài viết theo quy tắc chống spam và chu kỳ hồi lại 7 ngày (1 tuần).
     * Có thể gọi linh hoạt: $postModel->recordView(...) hoặc Post::recordView(...).
     *
     * Quy tắc nghiệp vụ:
     * 1. Không tính lượt xem khi chính tác giả tự mở bài viết của mình.
     * 2. Mỗi tài khoản (user_id nếu đã đăng nhập) hoặc khách vãng lai (theo IP + User-Agent)
     *    chỉ được tính tối đa +1 lượt xem cho một bài viết trong vòng 7 ngày (604.800 giây).
     * 3. Sau 7 ngày (last_viewed_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)), lần xem tiếp theo
     *    sẽ được tính +1 lượt xem mới và cập nhật lại mốc last_viewed_at = NOW().
     *
     * @param int $postId
     * @param int|null $userId ID người dùng đang đăng nhập (nếu có)
     * @param string|null $ip Địa chỉ IP (nếu null sẽ lấy $_SERVER['REMOTE_ADDR'])
     * @param string|null $userAgent User Agent (nếu null sẽ lấy $_SERVER['HTTP_USER_AGENT'])
     * @return bool Trả về true nếu được cộng +1 view, false nếu bị chặn (do chu kỳ / trùng lặp / là tác giả)
     */
    public static function recordView(int $postId, ?int $userId = null, ?string $ip = null, ?string $userAgent = null): bool
    {
        if ($postId <= 0) {
            return false;
        }

        $pdo = Database::getInstance()->getConnection();

        // 1. Kiểm tra bài viết tồn tại & tác giả
        $postStmt = $pdo->prepare("SELECT user_id, moderation_status FROM posts WHERE id = ? LIMIT 1");
        $postStmt->execute([$postId]);
        $postRow = $postStmt->fetch(PDO::FETCH_ASSOC);
        if (!$postRow) {
            return false;
        }

        // Không tính lượt xem khi chính tác giả tự mở bài viết của mình
        if ($userId !== null && $userId > 0 && (int)$postRow['user_id'] === (int)$userId) {
            return false;
        }

        // 2. Xác định định danh người xem (viewer_hash)
        $dbUserId = null;
        if ($userId !== null && $userId > 0) {
            $dbUserId = (int)$userId;
            $viewerHash = 'u_' . $dbUserId;
        } else {
            $clientIp = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $clientUa = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
            $viewerHash = 'g_' . hash('sha256', $clientIp . '|' . $clientUa);
        }

        // 3. Kiểm tra lịch sử xem trong post_views & áp dụng chu kỳ 7 ngày (604.800 giây)
        try {
            $viewStmt = $pdo->prepare("SELECT id, last_viewed_at, TIMESTAMPDIFF(SECOND, last_viewed_at, NOW()) AS elapsed_sec FROM post_views WHERE post_id = ? AND viewer_hash = ? LIMIT 1");
            $viewStmt->execute([$postId, $viewerHash]);
            $existing = $viewStmt->fetch(PDO::FETCH_ASSOC);

            $sevenDaysInSeconds = 7 * 86400; // 604,800 giây = 7 ngày

            if ($existing) {
                $elapsed = (int)($existing['elapsed_sec'] ?? 0);
                if ($elapsed < $sevenDaysInSeconds) {
                    // Còn trong chu kỳ 7 ngày -> Không tăng view
                    return false;
                }

                // Đã đủ 7 ngày -> Cập nhật mốc xem mới & tăng views_count
                $pdo->beginTransaction();
                $updStmt = $pdo->prepare("UPDATE post_views SET last_viewed_at = NOW(), user_id = COALESCE(?, user_id) WHERE id = ?");
                $updStmt->execute([$dbUserId, $existing['id']]);

                $incStmt = $pdo->prepare("UPDATE posts SET views_count = views_count + 1 WHERE id = ?");
                $incStmt->execute([$postId]);
                $pdo->commit();
                return true;
            }

            // Người xem lần đầu cho bài viết này -> Ghi nhận mới & tăng views_count
            $pdo->beginTransaction();
            $insStmt = $pdo->prepare("INSERT INTO post_views (post_id, user_id, viewer_hash, last_viewed_at, created_at) VALUES (?, ?, ?, NOW(), NOW())");
            $insStmt->execute([$postId, $dbUserId, $viewerHash]);

            $incStmt = $pdo->prepare("UPDATE posts SET views_count = views_count + 1 WHERE id = ?");
            $incStmt->execute([$postId]);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Post::recordView error: ' . $e->getMessage());
            return false;
        }
    }
}
