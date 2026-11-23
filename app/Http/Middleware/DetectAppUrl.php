<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware tự động điều chỉnh APP_URL theo môi trường chạy.
 * Hỗ trợ cả Laragon (Apache) và php artisan serve (VS Code).
 */
class DetectAppUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        // Tự động xây dựng URL gốc từ request thực tế
        $scheme = $request->getScheme();     // http hoặc https
        $host   = $request->getHttpHost();   // 127.0.0.1:8000 hoặc localhost
        $base   = $request->getBaseUrl();    // '' (artisan serve) hoặc '/DoAnMaNguonMo_Laravel/public' (Apache)

        $detectedUrl = $scheme . '://' . $host . $base;

        // Chỉ override nếu khác với config hiện tại (tránh flush cache mỗi request)
        if (config('app.url') !== $detectedUrl) {
            config(['app.url' => $detectedUrl]);
            // Cập nhật URL root của Laravel (asset, url helpers)
            app('url')->forceRootUrl($detectedUrl);
        }

        return $next($request);
    }
}
