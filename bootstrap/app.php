<?php

use App\Http\Middleware\AuthenticateUser;
use App\Http\Middleware\DetectAppUrl;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Miễn trừ kiểm tra CSRF cho các API endpoint để tránh lỗi 419 khi gọi từ JS
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'index.php*',
        ]);

        // Tự động điều chỉnh APP_URL theo môi trường (Laragon hoặc artisan serve)
        $middleware->prependToGroup('web', DetectAppUrl::class);
        $middleware->prependToGroup('api', DetectAppUrl::class);

        $middleware->alias([
            'auth.user' => AuthenticateUser::class,
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
