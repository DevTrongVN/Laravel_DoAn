<?php
declare(strict_types=1);

require_once __DIR__ . '/UnicodeHelper.php';

/** Controller cơ sở: render view và trả response JSON. */
class Controller
{
    /** Render view trong thư mục views/, với dữ liệu có sẵn thành biến. */
    protected function render(string $view, array $__viewData = [], string $layout = 'main'): void
    {
        $base = realpath(__DIR__ . '/../views');
        $viewFile = realpath(__DIR__ . '/../views/' . ltrim($view, '/') . '.php');
        if ($base === false || $viewFile === false || !str_starts_with($viewFile, $base . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            throw new RuntimeException('Không tìm thấy view hợp lệ: ' . $view);
        }

        if ($layout === 'main') {
            if (!isset($__viewData['followingUsers']) || !isset($__viewData['topNews']) || !isset($__viewData['topHashtags'])) {
                $viewerId = class_exists('Auth') ? Auth::id() : null;
                $sidebarData = $this->getSidebarData($viewerId);
                $__viewData = array_merge($sidebarData, $__viewData);
            }
        }

        $__viewData = UnicodeHelper::toNfc($__viewData);
        extract($__viewData, EXTR_OVERWRITE);
        ob_start();
        try {
            require $viewFile;
            $content = (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
        if ($layout === '') { echo $content; return; }
        $layoutFile = realpath(__DIR__ . '/../views/layouts/' . basename($layout) . '.php');
        if ($layoutFile === false) { echo $content; return; }
        require $layoutFile;
    }

    /** Chuẩn bị dữ liệu thời gian thực đầy đủ cho Cột Trái & Cột Phải của layout 'main' */
    protected function getSidebarData(?int $viewerId = null): array
    {
        require_once __DIR__ . '/../app/Models/Follow.php';
        require_once __DIR__ . '/../app/Models/Tag.php';
        require_once __DIR__ . '/../app/Models/Post.php';
        require_once __DIR__ . '/../app/Models/User.php';
        require_once __DIR__ . '/Database.php';

        $pdo = Database::getInstance()->getConnection();
        $isGuest = ($viewerId === null || $viewerId <= 0);

        if ($isGuest) {
            $stmt = $pdo->query('
                SELECT id, username, full_name, avatar, is_verified, COALESCE(reputation_score, 0) AS reputation_score
                FROM users
                WHERE role IN ("user", "admin")
                ORDER BY reputation_score DESC, id ASC
                LIMIT 5
            ');
            $followingUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $followingTitle = 'Tác giả nổi bật';
        } else {
            $followModel = new Follow();
            $followingUsers = $followModel->getFollowingWithVisitRank($viewerId, 15);
            $followingTitle = 'Những người mình theo dõi';
        }

        $postModel = new Post();
        $topNews = $postModel->topNews(15);

        $tagModel = new Tag();
        $topHashtags = $tagModel->top(12);

        $aboutAuthor = null;
        try {
            if (!$isGuest) {
                $aboutAuthor = (new User())->findById((int)$viewerId);
            } else {
                $aboutAuthor = (new User())->findPrimaryAdmin();
            }
        } catch (Throwable) {}

        return [
            'followingUsers'   => $followingUsers,
            'followingTitle'   => $followingTitle,
            'isGuestFollowing' => $isGuest,
            'topNews'          => $topNews,
            'topHashtags'      => $topHashtags,
            'aboutAuthor'      => $aboutAuthor,
        ];
    }

    /** Gửi JSON kèm HTTP status code và kết thúc request. */
    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        try {
            $data = UnicodeHelper::toNfc($data);
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            http_response_code(500);
            echo '{"status":"error","message":"Không thể tạo phản hồi JSON."}';
        }
        exit;
    }
}
