<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\UnicodeHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Controller cung cấp các API bổ trợ: Tác giả chính, Upload ảnh bài viết, Dashboard thống kê cá nhân.
 */
class ApiController extends Controller
{
    public function primaryAuthor(): JsonResponse
    {
        $author = User::where('role', 'admin')
            ->orderByDesc('reputation_score')
            ->orderBy('id')
            ->first();

        return response()->json([
            'user' => $author ? UnicodeHelper::toNfc($author->toPublicArray()) : null,
        ]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Bạn cần đăng nhập để tải ảnh lên.'], 401);
        }

        $imageData = (string) $request->input('image', '');
        if (!preg_match('~^data:image/(png|jpeg|webp|gif);base64,([A-Za-z0-9+/=]+)$~', $imageData, $match)) {
            return response()->json(['error' => 'Ảnh phải là PNG, JPG, WebP hoặc GIF hợp lệ.'], 422);
        }

        $binary = base64_decode($match[2], true);
        if ($binary === false || strlen($binary) > 5 * 1024 * 1024) {
            return response()->json(['error' => 'Ảnh không hợp lệ hoặc vượt quá 5 MB.'], 422);
        }

        $image = @getimagesizefromstring($binary);
        $mimeToExt = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!$image || !isset($mimeToExt[$image['mime']])) {
            return response()->json(['error' => 'Không đọc được nội dung ảnh.'], 422);
        }

        $directory = public_path('uploads/posts');
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            return response()->json(['error' => 'Không thể tạo thư mục ảnh tải lên.'], 500);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $mimeToExt[$image['mime']];
        if (file_put_contents($directory . DIRECTORY_SEPARATOR . $filename, $binary, LOCK_EX) === false) {
            return response()->json(['error' => 'Không thể lưu ảnh.'], 500);
        }

        return response()->json([
            'url' => asset('uploads/posts/' . $filename),
        ], 201);
    }

    public function personalDashboard(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Bạn cần đăng nhập để xem thống kê cá nhân.'], 401);
        }
        $userId = (int) $user->id;

        $sort = (string) $request->query('sort', 'latest');
        $orderBy = match ($sort) {
            'views' => 'p.views_count DESC, p.created_at DESC',
            'interactions' => '(likes_count + shares_count + comments_count) DESC, p.created_at DESC',
            default => 'p.created_at DESC',
        };

        $posts = array_map(static fn($r) => (array) $r, DB::select("
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
        ", [$userId]));

        $summary = ['posts' => count($posts), 'views' => 0, 'likes' => 0, 'shares' => 0, 'comments' => 0, 'bookmarks' => 0];
        foreach ($posts as &$post) {
            foreach (['id', 'views_count', 'likes_count', 'shares_count', 'comments_count', 'bookmarks_count'] as $field) {
                $post[$field] = (int) $post[$field];
            }
            $post['comment_count'] = $post['comments_count'];
            $post['total_comments'] = $post['comments_count'];
            $post['like_count'] = $post['likes_count'];
            $post['total_reactions'] = $post['likes_count'];
            $post['reaction_count'] = $post['likes_count'];
            $post['share_count'] = $post['shares_count'];
            $post['total_shares'] = $post['shares_count'];
            $post['views'] = $post['views_count'];
            $post['view_count'] = $post['views_count'];

            $summary['views'] += $post['views_count'];
            $summary['likes'] += $post['likes_count'];
            $summary['shares'] += $post['shares_count'];
            $summary['comments'] += $post['comments_count'];
            $summary['bookmarks'] += $post['bookmarks_count'];
        }
        unset($post);

        $activity = array_map(static fn($r) => (array) $r, DB::select("
            SELECT activity.type, activity.post_id, activity.created_at, activity.content,
                   p.title AS post_title, actor.id AS actor_id, actor.username AS actor_username,
                   actor.full_name AS actor_name, actor.avatar AS actor_avatar
            FROM (
                SELECT i.type, i.post_id, i.user_id AS actor_id, i.created_at, NULL AS content
                FROM interactions i
                WHERE i.type IN ('like', 'share')
                UNION ALL
                SELECT 'comment' AS type, c.post_id, c.user_id AS actor_id, c.created_at, c.content
                FROM comments c
                WHERE c.moderation_status = 'safe'
            ) AS activity
            INNER JOIN posts p ON p.id = activity.post_id
            INNER JOIN users actor ON actor.id = activity.actor_id
            WHERE p.user_id = ? AND activity.actor_id <> ?
            ORDER BY activity.created_at DESC
            LIMIT 50
        ", [$userId, $userId]));

        return response()->json(UnicodeHelper::toNfc([
            'user' => $user->toPublicArray(),
            'summary' => $summary,
            'posts' => $posts,
            'activity' => $activity,
        ]));
    }
}
