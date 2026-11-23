<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware kiểm tra phân quyền Quản trị viên (Admin).
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            if ($request->is('api/*') || $request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'error' => 'Vui lòng đăng nhập bằng tài khoản Quản trị viên.',
                ], 401);
            }

            return redirect('/account?mode=login');
        }

        if (Auth::user()?->role !== 'admin') {
            if ($request->is('api/*') || $request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'error' => 'Bạn không có quyền truy cập khu vực quản trị (chỉ dành cho Admin).',
                ], 403);
            }

            return response()->view('errors.forbidden', [], 403);
        }

        return $next($request);
    }
}
