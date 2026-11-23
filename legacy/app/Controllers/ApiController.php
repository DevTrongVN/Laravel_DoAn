<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/Post.php';
require_once __DIR__ . '/../Models/Ticket.php';

/** Endpoint JSON của ứng dụng. */
final class ApiController extends Controller
{
    private function input(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') return $_POST;
        $data = json_decode($raw, true);
        if (!is_array($data)) $this->json(['error' => 'Dữ liệu JSON không hợp lệ.'], 400);
        return $data;
    }

    private function requireLogin(): int
    {
        $id = Auth::id();
        if ($id === null) $this->json(['error' => 'Bạn cần đăng nhập để thực hiện thao tác này.'], 401);
        return $id;
    }

    private function requireAdmin(): void
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Vui lòng đăng nhập để truy cập tính năng quản trị.'], 401);
        }
        if (!Auth::isAdmin()) {
            $this->json(['error' => 'Tài khoản của bạn không có quyền quản trị.'], 403);
        }
    }

    public function feed(): never
    {
        $filters = [
            'type' => $_GET['type'] ?? null, 'q' => trim((string)($_GET['q'] ?? '')),
            'hashtag' => $_GET['hashtag'] ?? null,
            'following' => ($_GET['following'] ?? '') === '1',
        ];
        if ($filters['following'] && Auth::id() === null) $this->json(['error'=>'Đăng nhập để xem luồng bài viết đang theo dõi.'],401);
        if (!empty($_GET['user_id'])) $filters['user_id'] = (int)$_GET['user_id'];
        if (isset($_GET['seed']) && is_numeric($_GET['seed'])) {
            $filters['seed'] = (int)$_GET['seed'];
        }
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(50, (int)($_GET['per_page'] ?? 10)));
        $res = (new Post())->list($filters, Auth::id(), $page, $perPage);
        $this->json(['data' => $res['data'] ?? $res, 'pagination' => [
            'page'      => $res['page'] ?? $page,
            'per_page'  => $perPage,
            'total'     => $res['total'] ?? count($res['data'] ?? $res),
            'last_page' => $res['last_page'] ?? 1,
            'has_more'  => ($res['page'] ?? $page) < ($res['last_page'] ?? 1),
            'seed'      => $res['seed'] ?? ($filters['seed'] ?? null),
        ]]);
    }

    public function post(string $id): never
    {
        $viewerId = Auth::id();
        (new Post())->recordView((int)$id, $viewerId);
        $post = (new Post())->find((int)$id, $viewerId);
        if ($post === null) $this->json(['error' => 'Không tìm thấy bài viết.'], 404);
        $this->json(['data' => $post]);
    }

    public function register(): never
    {
        $data = $this->input();
        $username = trim((string)($data['username'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $fullName = trim((string)($data['full_name'] ?? ''));
        $password = (string)($data['password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_]{3,40}$/', $username)) $this->json(['error'=>'Tên đăng nhập cần 3-40 ký tự, chỉ gồm chữ, số và _.'], 422);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $this->json(['error'=>'Email không hợp lệ.'], 422);
        if ($fullName === '' || mb_strlen($fullName) > 120) $this->json(['error'=>'Vui lòng nhập họ tên (tối đa 120 ký tự).'], 422);
        if (strlen($password) < 8) $this->json(['error'=>'Mật khẩu cần ít nhất 8 ký tự.'], 422);
        try {
            $id = (new User())->register($username,$email,$fullName,$password,isset($data['avatar']) ? (string)$data['avatar'] : null);
            (new User())->login($username,$password);
            $this->json(['message'=>'Đăng ký thành công.','user'=>(new User())->findById($id)], 201);
        } catch (InvalidArgumentException $exception) {
            $this->json(['error'=>$exception->getMessage()], 422);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') $this->json(['error'=>'Tên đăng nhập hoặc email đã được sử dụng.'], 409);
            throw $exception;
        }
    }

    public function login(): never
    {
        $data = $this->input();
        $login = trim((string)($data['login'] ?? $data['username'] ?? $data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $user = (new User())->login($login,$password);
        if ($user === null) $this->json(['error'=>'Tên đăng nhập/email hoặc mật khẩu không đúng.'], 401);
        $this->json(['message'=>'Đăng nhập thành công.','user'=>$user]);
    }

    public function logout(): never
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $this->json(['message'=>'Đã đăng xuất.']);
    }

    public function me(): never
    {
        $id = Auth::id();
        $this->json(['user' => $id === null ? null : (new User())->findById($id)]);
    }

    public function updateProfile(): never
    {
        $userId = $this->requireLogin();
        $data = $this->input();
        try {
            $updatedUser = (new User())->updateSettings($userId, $data);
            $this->json([
                'message' => 'Cập nhật thông tin tài khoản thành công.',
                'user' => $updatedUser
            ]);
        } catch (DomainException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        }
    }

    public function createPost(): never
    {
        $userId = $this->requireLogin();
        $data = $this->input();
        $title = trim((string)($data['title'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));
        if ($title === '' || mb_strlen($title) > 200) $this->json(['error'=>'Tiêu đề bắt buộc và tối đa 200 ký tự.'], 422);
        if ($content === '' || mb_strlen($content) > 50000) $this->json(['error'=>'Nội dung bắt buộc và tối đa 50.000 ký tự.'], 422);
        $user = (new User())->findById($userId);
        $requestedType = (string)($data['post_type'] ?? 'blog');
        if (!in_array($requestedType,['blog','news'],true)) $this->json(['error'=>'Loại bài viết không hợp lệ.'],422);
        if ($requestedType === 'news' && $user['role'] !== 'admin' && !$user['is_verified']) {
            $this->json(['error'=>'Tài khoản thường cần tạo bài Blog rồi gửi ticket xét duyệt để đăng Tin tức.'],403);
        }
        $type = $requestedType;
        $postId = (new Post())->create($userId,[
            'title'=>$title,'content'=>$content,'summary'=>trim((string)($data['summary'] ?? '')),
            'thumbnail'=>$this->safeThumbnail($data['thumbnail'] ?? null),'post_type'=>$type,
            'hashtags'=>$data['hashtags'] ?? [],
        ]);
        $this->json(['message'=>'Đăng bài thành công.','data'=>(new Post())->find($postId,$userId)], 201);
    }

    public function uploadImage(): never
    {
        $this->requireLogin();
        $data = $this->input();
        $imageData = (string)($data['image'] ?? '');
        if (!preg_match('~^data:image/(png|jpeg|webp|gif);base64,([A-Za-z0-9+/=]+)$~', $imageData, $match)) {
            $this->json(['error'=>'Ảnh phải là PNG, JPG, WebP hoặc GIF hợp lệ.'],422);
        }
        $binary = base64_decode($match[2], true);
        if ($binary === false || strlen($binary) > 5 * 1024 * 1024) $this->json(['error'=>'Ảnh không hợp lệ hoặc vượt quá 5 MB.'],422);
        $image = getimagesizefromstring($binary);
        $mimeToExt = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif'];
        if (!$image || !isset($mimeToExt[$image['mime']])) $this->json(['error'=>'Không đọc được nội dung ảnh.'],422);
        $directory = __DIR__ . '/../../public/uploads/posts';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) $this->json(['error'=>'Không thể tạo thư mục ảnh tải lên.'],500);
        $filename = bin2hex(random_bytes(16)) . '.' . $mimeToExt[$image['mime']];
        if (file_put_contents($directory . '/' . $filename, $binary, LOCK_EX) === false) $this->json(['error'=>'Không thể lưu ảnh.'],500);
        $this->json(['url'=>'/DoAnMaNguonMo/public/uploads/posts/' . $filename],201);
    }

    private function safeThumbnail(mixed $thumbnail): ?string
    {
        if ($thumbnail === null || $thumbnail === '') return null;
        $thumbnail = trim((string)$thumbnail);
        if (strlen($thumbnail) > 500 || !preg_match('~^(https?://|/)[^\s]+$~i', $thumbnail)) return null;
        return $thumbnail;
    }

    public function interact(string $id, string $type): never
    {
        $userId = $this->requireLogin();
        $active = (new Post())->toggleInteraction($userId,(int)$id,$type);
        $this->json(['message'=>'Đã cập nhật tương tác.','active'=>$active]);
    }

    public function createComment(string $id): never
    {
        $userId = $this->requireLogin();
        $content = trim((string)($this->input()['content'] ?? ''));
        if ($content === '' || mb_strlen($content) > 2000) $this->json(['error'=>'Bình luận bắt buộc và tối đa 2.000 ký tự.'], 422);
        $commentId = (new Post())->addComment($userId,(int)$id,$content);
        $this->json(['message'=>'Đã gửi bình luận.','id'=>$commentId],201);
    }

    public function follow(string $id): never
    {
        $userId = $this->requireLogin();
        $following = (new User())->toggleFollow($userId,(int)$id);
        $this->json(['following'=>$following]);
    }

    public function profile(string $id): never
    {
        $viewer = Auth::id(); $targetId = (int)$id;
        $user = (new User())->findById($targetId);
        if ($user === null) $this->json(['error'=>'Không tìm thấy tài khoản.'],404);
        unset($user['email'], $user['role']);
        $stmt = Database::getInstance()->getConnection()->prepare('SELECT COUNT(*) FROM follows WHERE following_id=?');
        $stmt->execute([$targetId]); $user['followers_count']=(int)$stmt->fetchColumn();
        $stmt = Database::getInstance()->getConnection()->prepare('SELECT COUNT(*) FROM follows WHERE follower_id=?');
        $stmt->execute([$targetId]); $user['following_count']=(int)$stmt->fetchColumn();
        $following = false;
        if ($viewer !== null) {
            $stmt = Database::getInstance()->getConnection()->prepare('SELECT 1 FROM follows WHERE follower_id=? AND following_id=?');
            $stmt->execute([$viewer,$targetId]); $following=(bool)$stmt->fetchColumn();
        }
        $postsRes = (new Post())->list(['user_id' => $targetId], $viewer);
        $this->json(['user' => $user, 'is_following' => $following, 'posts' => $postsRes['data'] ?? $postsRes]);
    }

    public function createTicket(string $id): never
    {
        $ticketId = (new Ticket())->createUpgrade($this->requireLogin(),(int)$id);
        $this->json(['message'=>'Đã gửi ticket để quản trị viên xem xét.','id'=>$ticketId],201);
    }

    public function hashtags(): never { $this->json(['data'=>(new Post())->topHashtags()]); }

    public function notifications(): never
    {
        $id = $this->requireLogin();
        $stmt = Database::getInstance()->getConnection()->prepare('SELECT id,type,content,target_url,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50');
        $stmt->execute([$id]);
        $this->json(['data'=>$stmt->fetchAll()]);
    }

    public function personalDashboard(): never
    {
        $userId = $this->requireLogin();
        $pdo    = Database::getInstance()->getConnection();

        // Sắp xếp theo tham số ?sort=views|interactions|latest (mặc định latest)
        $sort    = (string)($_GET['sort'] ?? 'latest');
        $orderBy = match ($sort) {
            'views'        => 'p.views_count DESC, p.created_at DESC',
            'interactions' => '(likes_count + shares_count + comments_count) DESC, p.created_at DESC',
            default        => 'p.created_at DESC',
        };

        $postsStmt = $pdo->prepare("
            SELECT p.id, p.title, p.summary, p.content, p.thumbnail, p.post_type,
                   p.moderation_status, p.views_count, p.created_at,
                   (SELECT COUNT(*) FROM post_reactions i WHERE i.post_id = p.id) AS likes_count,
                   (SELECT COUNT(DISTINCT s.user_id) FROM (SELECT user_id, post_id FROM interactions WHERE type = 'share' UNION ALL SELECT user_id, shared_post_id AS post_id FROM posts WHERE shared_post_id IS NOT NULL AND moderation_status = 'safe') s WHERE s.post_id = p.id AND s.user_id <> p.user_id) AS shares_count,
                   (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.moderation_status = 'safe') AS comments_count,
                   (SELECT COUNT(*) FROM interactions ibk WHERE ibk.post_id = p.id AND ibk.type = 'bookmark') AS bookmarks_count
            FROM posts p
            WHERE p.user_id = ?
            ORDER BY {$orderBy}
            LIMIT 100
        ");
        $postsStmt->execute([$userId]);
        $posts = $postsStmt->fetchAll();
        foreach ($posts as &$post) {
            foreach (['id', 'views_count', 'likes_count', 'shares_count', 'comments_count', 'bookmarks_count'] as $field) {
                $post[$field] = (int)$post[$field];
            }
            $post['comment_count']   = $post['comments_count'];
            $post['total_comments']  = $post['comments_count'];
            $post['like_count']      = $post['likes_count'];
            $post['total_reactions'] = $post['likes_count'];
            $post['reaction_count']  = $post['likes_count'];
            $post['share_count']     = $post['shares_count'];
            $post['total_shares']    = $post['shares_count'];
            $post['views']           = $post['views_count'];
            $post['view_count']      = $post['views_count'];
        }
        unset($post);

        $activityStmt = $pdo->prepare('
            SELECT activity.type, activity.post_id, activity.created_at, activity.content,
                   p.title AS post_title, actor.id AS actor_id, actor.username AS actor_username,
                   actor.full_name AS actor_name, actor.avatar AS actor_avatar
            FROM (
                SELECT i.type, i.post_id, i.user_id AS actor_id, i.created_at, NULL AS content
                FROM interactions i
                WHERE i.type IN (\'like\', \'share\')
                UNION ALL
                SELECT \'comment\' AS type, c.post_id, c.user_id AS actor_id, c.created_at, c.content
                FROM comments c
                WHERE c.moderation_status = \'safe\'
            ) AS activity
            INNER JOIN posts p ON p.id = activity.post_id
            INNER JOIN users actor ON actor.id = activity.actor_id
            WHERE p.user_id = ? AND activity.actor_id <> ?
            ORDER BY activity.created_at DESC
            LIMIT 50
        ');
        $activityStmt->execute([$userId, $userId]);
        $activity = $activityStmt->fetchAll();

        $summary = ['posts' => count($posts), 'views' => 0, 'likes' => 0, 'shares' => 0, 'comments' => 0, 'bookmarks' => 0];
        foreach ($posts as $post) {
            $summary['views']     += $post['views_count'];
            $summary['likes']     += $post['likes_count'];
            $summary['shares']    += $post['shares_count'];
            $summary['comments']  += $post['comments_count'];
            $summary['bookmarks'] += $post['bookmarks_count'] ?? 0;
        }

        $this->json([
            'user' => (new User())->findById($userId),
            'summary' => $summary,
            'posts' => $posts,
            'activity' => $activity,
        ]);
    }

    public function primaryAuthor(): never
    {
        $author = (new User())->findPrimaryAdmin();
        $this->json(['user' => $author]);
    }

    public function adminTickets(): never { $this->requireAdmin(); $this->json(['data'=>(new Ticket())->listAll()]); }

    public function adminPosts(): never { $this->requireAdmin(); $this->json(['data'=>(new Post())->adminList()]); }

    public function adminResolveTicket(string $id): never
    {
        $this->requireAdmin(); $data=$this->input();
        (new Ticket())->resolve((int)$id,(string)($data['status'] ?? ''),isset($data['note'])?(string)$data['note']:null);
        $this->json(['message'=>'Đã cập nhật ticket.']);
    }

    public function adminModeratePost(string $id): never
    {
        $this->requireAdmin(); $data=$this->input();
        (new Ticket())->toggleModeration((int)$id,(string)($data['status'] ?? ''));
        $this->json(['message'=>'Đã cập nhật trạng thái kiểm duyệt.']);
    }

    public function adminDeletePost(string $id): never
    {
        $this->requireAdmin();
        if (!(new Post())->deleteAny((int)$id)) $this->json(['error' => 'Không tìm thấy bài viết.'], 404);
        $this->json(['message' => 'Đã xóa bài viết.']);
    }

    public function adminUsers(): never { $this->requireAdmin(); $this->json(['data'=>(new User())->listUsers()]); }

    public function adminVerifyUser(string $id): never
    {
        $this->requireAdmin(); $data=$this->input();
        (new User())->setVerified((int)$id,(bool)($data['is_verified'] ?? false));
        $this->json(['message'=>'Đã cập nhật tích xanh.']);
    }

    public function adminChangeRole(string $id): never
    {
        $this->requireAdmin();
        $data = $this->input();
        $role = trim((string)($data['role'] ?? ''));
        $targetId = (int)$id;
        $currentAdminId = Auth::id();
        if ($targetId === $currentAdminId && $role !== 'admin') {
            $this->json(['error' => 'Bạn không thể tự hạ quyền quản trị của chính mình.'], 422);
        }
        (new User())->setRole($targetId, $role);
        $this->json(['message' => 'Đã cập nhật vai trò người dùng thành ' . ($role === 'admin' ? 'Quản trị viên' : 'Người dùng') . '.']);
    }
}
