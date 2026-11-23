<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/NotificationHelper.php';
require_once __DIR__ . '/../../Models/Post.php';
require_once __DIR__ . '/../../Models/Reaction.php';
require_once __DIR__ . '/../../Models/User.php';
require_once __DIR__ . '/../../Services/ReputationService.php';

final class ApiReactionController extends Controller
{
    public function set(string $postId): never
    {
        $userId = Auth::id();
        if ($userId === null) $this->json(['error' => 'Đăng nhập để thả cảm xúc.'], 401);

        $post = (new Post())->find((int)$postId, $userId);
        if (!$post) $this->json(['error' => 'Không tìm thấy bài viết.'], 404);

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) $data = $_POST;
        $reaction = array_key_exists('reaction', $data)
            ? ($data['reaction'] === null || $data['reaction'] === '' ? null : (string)$data['reaction'])
            : 'like';

        try {
            $active = (new Reaction())->set($userId, (int)$postId, $reaction);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        }

        (new ReputationService())->calculate((int)$post['user_id']);

        // Gửi thông báo cho tác giả bài viết (không tự gửi cho mình)
        if ($active !== null && (int)$post['user_id'] !== $userId) {
            $actor = (new User())->findById($userId);
            $actorName = $actor['full_name'] ?? 'Ai đó';
            $emoji = match ($active) {
                'love'  => '❤️',
                'haha'  => '😄',
                'sad'   => '😢',
                'angry' => '😠',
                default => '👍',
            };
            NotificationHelper::send(
                (int)$post['user_id'],
                $userId,
                'reaction',
                "{$actorName} đã thả cảm xúc {$emoji} vào bài viết \"{$post['title']}\"",
                "/post/{$postId}"
            );
        }

        $counts = (new Reaction())->counts((int)$postId);
        $totalLikes = array_sum($counts);

        $this->json([
            'success'         => true,
            'my_reaction'     => $active,
            'reaction_counts' => $counts,
            'likes_count'     => $totalLikes,
            'is_liked'        => !empty($active),
        ]);
    }

    public function commentLike(string $commentId): never
    {
        require_once __DIR__ . '/ApiCommentController.php';
        (new ApiCommentController())->like($commentId);
    }
}

