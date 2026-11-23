<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * API Controller: AI Sửa văn phong (3 phong cách), Kiểm duyệt nội dung, Đánh giá tiềm năng Tin tức.
 */
class ApiAIController extends Controller
{
    public function __construct(
        private readonly AIService $aiService
    ) {}

    public function improveStyle(Request $request): JsonResponse
    {
        $content = trim((string) $request->input('content', $request->input('text', '')));
        $tone = trim((string) $request->input('tone', $request->input('mode', $request->input('style', 'improve'))));

        if ($content === '') {
            return response()->json(['error' => 'Vui lòng cung cấp nội dung cần chỉnh sửa văn phong.'], 422);
        }

        $meta = $this->aiService->improveStyleWithMeta($content, $tone);

        return response()->json([
            'original'        => $content,
            'improved'        => $meta['improved'],
            'rewritten'       => $meta['improved'],
            'tone'            => $meta['mode'],
            'mode'            => $meta['mode'],
            'engine'          => $meta['engine'],
            'model'           => $meta['model'],
            'notice'          => $meta['notice'],
            'length_original' => mb_strlen($content),
            'length_improved' => mb_strlen($meta['improved']),
        ]);
    }

    public function moderate(Request $request): JsonResponse
    {
        $content = trim((string) $request->input('content', ''));
        return response()->json($this->aiService->scanContent($content));
    }

    public function suggestNews(Request $request): JsonResponse
    {
        $userId = Auth::id();
        if ($userId === null) {
            return response()->json(['error' => 'Vui lòng đăng nhập.'], 401);
        }

        $postId = (int) $request->input('post_id', 0);
        if ($postId <= 0) {
            return response()->json(['error' => 'Thiếu post_id.'], 422);
        }

        $post = Post::where('id', $postId)->where('moderation_status', '<>', 'blocked')->first();
        if (!$post) {
            return response()->json(['error' => 'Không tìm thấy bài viết.'], 404);
        }

        if ((int) $post->user_id !== $userId && Auth::user()?->role !== 'admin') {
            return response()->json(['error' => 'Bạn không có quyền đánh giá bài viết này.'], 403);
        }

        $result = $this->aiService->evaluateNewsPotential((string) $post->title, (string) $post->content);
        $post->ai_news_suggested = (bool) $result['is_news_worthy'];
        $post->ai_news_reason = $result['reason'];
        $post->save();

        return response()->json(array_merge($result, ['post_id' => $postId]));
    }
}
