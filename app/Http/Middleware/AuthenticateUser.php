<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware kiểm tra phiên đăng nhập người dùng (Auth).
 */
class AuthenticateUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            if ($request->is('api/*') || $request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'error' => 'Vui lòng đăng nhập để thực hiện thao tác này.',
                ], 401);
            }

            return redirect('/account?mode=login');
        }

        return $next($request);
    }
}
