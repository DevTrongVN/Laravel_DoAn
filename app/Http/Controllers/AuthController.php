<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\UnicodeHelper;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Controller xử lý Đăng ký, Đăng nhập, Đăng xuất, CSRF Token và Cài đặt tài khoản.
 */
class AuthController extends Controller
{
    public function csrfToken(Request $request): JsonResponse
    {
        return response()->json([
            'csrf_token' => $request->session()->token(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
    }

    public function register(Request $request): JsonResponse
    {
        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $fullName = (string) UnicodeHelper::toNfc(trim((string) $request->input('full_name', '')));
        $avatarInput = $request->input('avatar');

        if ($username === '' || !preg_match('/^[A-Za-z0-9_]{3,40}$/', $username)) {
            return response()->json(['error' => 'Tên đăng nhập cần 3-40 ký tự chữ, số hoặc dấu gạch dưới.'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['error' => 'Địa chỉ email không hợp lệ.'], 422);
        }
        if (strlen($password) < 8) {
            return response()->json(['error' => 'Mật khẩu phải có ít nhất 8 ký tự.'], 422);
        }
        if ($fullName === '' || mb_strlen($fullName) > 120) {
            return response()->json(['error' => 'Họ và tên bắt buộc và tối đa 120 ký tự.'], 422);
        }

        try {
            $avatar = ($avatarInput === null || trim((string) $avatarInput) === '')
                ? User::randomAvatar()
                : User::normalizeAvatar((string) $avatarInput);

            $user = User::create([
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($password),
                'full_name' => $fullName,
                'avatar' => $avatar,
                'role' => 'user',
                'is_verified' => false,
                'reputation_score' => 0,
            ]);

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => 'Đăng ký thành công.',
                'user' => UnicodeHelper::toNfc($user->toPublicArray()),
                'csrf_token' => $request->session()->token(),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return response()->json(['error' => 'Tên đăng nhập hoặc email đã được sử dụng.'], 409);
            }
            throw $e;
        }
    }

    public function login(Request $request): JsonResponse
    {
        $login = trim((string) ($request->input('login') ?? $request->input('username') ?? $request->input('email') ?? ''));
        $password = (string) $request->input('password', '');

        if ($login === '' || $password === '') {
            return response()->json(['error' => 'Tên đăng nhập/email hoặc mật khẩu không đúng.'], 401);
        }

        $user = User::where('username', $login)->orWhere('email', $login)->first();
        if (!$user || !password_verify($password, (string) $user->password)) {
            return response()->json(['error' => 'Tên đăng nhập/email hoặc mật khẩu không đúng.'], 401);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Đăng nhập thành công.',
            'user' => UnicodeHelper::toNfc($user->toPublicArray()),
            'csrf_token' => $request->session()->token(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Đã đăng xuất.',
            'csrf_token' => $request->session()->token(),
        ]);
    }

    public function me(): JsonResponse
    {
        $user = Auth::user();
        return response()->json([
            'user' => $user ? UnicodeHelper::toNfc($user->toPublicArray()) : null,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Vui lòng đăng nhập để thực hiện.'], 401);
        }

        $username = trim((string) ($request->input('username', $user->username)));
        $fullName = (string) UnicodeHelper::toNfc(trim((string) ($request->input('full_name', $user->full_name))));
        $bio = $request->has('bio') ? (string) UnicodeHelper::toNfc(trim((string) $request->input('bio'))) : $user->bio;

        if (!preg_match('/^[A-Za-z0-9_]{3,40}$/', $username)) {
            return response()->json(['error' => 'Tên đăng nhập cần 3-40 ký tự, chỉ gồm chữ, số và dấu gạch dưới (_).'], 422);
        }
        if ($fullName === '' || mb_strlen($fullName) > 120) {
            return response()->json(['error' => 'Họ và tên bắt buộc và tối đa 120 ký tự.'], 422);
        }

        $dup = User::where('username', $username)->where('id', '!=', $user->id)->exists();
        if ($dup) {
            return response()->json(['error' => 'Tên đăng nhập này đã có người sử dụng.'], 422);
        }

        $newPassword = (string) $request->input('new_password', '');
        $currentPassword = (string) $request->input('current_password', '');
        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                return response()->json(['error' => 'Mật khẩu mới phải có ít nhất 8 ký tự.'], 422);
            }
            if (!password_verify($currentPassword, (string) $user->password)) {
                return response()->json(['error' => 'Mật khẩu hiện tại không chính xác.'], 422);
            }
            $user->password = Hash::make($newPassword);
        }

        $avatarInput = $request->input('avatar');
        if ($avatarInput !== null && trim((string) $avatarInput) !== '') {
            try {
                $user->avatar = User::normalizeAvatar((string) $avatarInput);
            } catch (InvalidArgumentException $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

        $user->username = $username;
        $user->full_name = $fullName;
        $user->bio = $bio;
        $user->save();

        return response()->json([
            'message' => 'Cập nhật thông tin tài khoản thành công.',
            'user' => UnicodeHelper::toNfc($user->toPublicArray()),
        ]);
    }
}
