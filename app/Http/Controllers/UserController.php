<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PostQueryService;
use App\Services\UploadService;
use App\Support\UnicodeHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

/**
 * Controller Trang cá nhân, API thông tin người dùng & Tải lên / Khôi phục ảnh đại diện (Avatar).
 */
class UserController extends Controller
{
    public function __construct(
        private readonly PostQueryService $postQuery,
        private readonly UploadService $uploadService
    ) {}

    /**
     * Web View: GET /profile hoặc GET /profile/{id}
     */
    public function showPage(Request $request, ?string $id = null): View
    {
        return view('profile.show', [
            'profileId' => $id ?? $request->query('id'),
            'pageTitle' => 'Trang cá nhân — Góc Nhỏ',
        ]);
    }

    /**
     * API JSON: GET /api/users/{id}
     */
    public function show(Request $request, string $id): JsonResponse|View
    {
        $cleanId = ltrim($id, '@');
        $user = is_numeric($cleanId)
            ? User::find((int) $cleanId)
            : User::where('username', $cleanId)->orWhere('email', $cleanId)->first();

        if (!$user) {
            return response()->json(['error' => 'Không tìm thấy người dùng.'], 404);
        }

        $userId = (int) $user->id;
        $viewerId = Auth::id();

        if ($viewerId !== null && $viewerId !== $userId) {
            DB::update('UPDATE follows SET visit_count = visit_count + 1 WHERE follower_id = ? AND following_id = ?', [$viewerId, $userId]);
        }

        $postFilters = ['user_id' => $userId];
        if ($viewerId !== null && $viewerId === $userId) {
            $postFilters['include_moderated'] = true;
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));
        $result = $this->postQuery->list($postFilters, $viewerId, $page, $perPage);

        $followersCount = (int) DB::table('follows')->where('following_id', $userId)->count();
        $followingCount = (int) DB::table('follows')->where('follower_id', $userId)->count();
        $isFollowing = $viewerId !== null
            ? DB::table('follows')->where('follower_id', $viewerId)->where('following_id', $userId)->exists()
            : false;

        $payload = UnicodeHelper::toNfc([
            'user' => $user->toPublicArray(),
            'posts' => $result['data'],
            'followers_count' => $followersCount,
            'following_count' => $followingCount,
            'is_following' => $isFollowing,
        ]);

        if ($request->is('api/*') || $request->expectsJson() || $request->query('api') !== null) {
            return response()->json($payload);
        }

        return view('profile.show', [
            'profileId' => $userId,
            'pageTitle' => $user->full_name . ' — Góc Nhỏ',
        ]);
    }

    /**
     * POST /api/users/avatar hoặc /api/user/avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Vui lòng đăng nhập để cập nhật ảnh đại diện.'], 401);
        }

        $file = $request->file('avatar') ?? $request->file('image') ?? $request->file('file');
        if (!$file) {
            return response()->json(['error' => 'Vui lòng chọn một file ảnh để tải lên.'], 422);
        }

        try {
            $relativePath = $this->uploadService->uploadImage($file, 'avatars', 'avatar_' . $user->id);
            $storedAvatarPath = '/' . ltrim($relativePath, '/');
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Không thể lưu file ảnh lên máy chủ: ' . $e->getMessage()], 500);
        }

        $oldAvatar = (string) ($user->avatar ?? '');

        try {
            $user->avatar = $storedAvatarPath;
            $user->save();
        } catch (Throwable $e) {
            @unlink(public_path(ltrim($storedAvatarPath, '/')));
            return response()->json(['error' => 'Lỗi khi lưu ảnh đại diện vào cơ sở dữ liệu: ' . $e->getMessage()], 500);
        }

        $this->cleanOldAvatarFile($oldAvatar);

        $fullUrl = asset(ltrim($storedAvatarPath, '/'));

        return response()->json([
            'success' => true,
            'avatar_url' => $fullUrl,
            'avatar' => $storedAvatarPath,
            'url' => $fullUrl,
            'message' => 'Cập nhật ảnh đại diện thành công.',
        ]);
    }

    /**
     * DELETE /api/users/avatar hoặc POST /api/users/avatar/reset
     */
    public function resetAvatar(): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Vui lòng đăng nhập để thực hiện thao tác này.'], 401);
        }

        $oldAvatar = (string) ($user->avatar ?? '');
        $defaultAvatar = User::randomAvatar();

        $user->avatar = $defaultAvatar;
        $user->save();

        $this->cleanOldAvatarFile($oldAvatar);

        $fullUrl = asset(ltrim($defaultAvatar, '/'));

        return response()->json([
            'success' => true,
            'avatar_url' => $fullUrl,
            'avatar' => $defaultAvatar,
            'url' => $fullUrl,
            'message' => 'Đã khôi phục ảnh đại diện mặc định.',
        ]);
    }

    private function cleanOldAvatarFile(string $oldAvatar): void
    {
        $oldAvatar = trim($oldAvatar);
        if (str_starts_with($oldAvatar, '/uploads/avatars/') || str_starts_with($oldAvatar, 'uploads/avatars/')) {
            $basename = basename($oldAvatar);
            if ($basename !== '' && !str_contains($basename, '/') && !str_contains($basename, '\\')) {
                $path = public_path('uploads/avatars/' . $basename);
                if (file_exists($path) && is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }
}
