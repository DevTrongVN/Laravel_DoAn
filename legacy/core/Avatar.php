<?php
declare(strict_types=1);

/** Build a stable app-root URL for a stored avatar path. */
function appAvatarUrl(?string $avatar): string
{
    $avatar = trim((string)$avatar);
    if ($avatar === '') $avatar = 'assets/images/avatars/default.svg';
    if (preg_match('~^(?:https?:)?//|^(?:data|blob):~i', $avatar)) return $avatar;

    $appPath = rtrim((string)APP_URL, '/');
    if (str_starts_with($avatar, $appPath . '/')) return $avatar;
    if (str_starts_with($avatar, '/assets/') || str_starts_with($avatar, '/uploads/')) $avatar = ltrim($avatar, '/');
    if (str_starts_with($avatar, '/')) return $avatar;
    return $appPath . '/' . ltrim($avatar, '/');
}

function appAvatarSrc(?string $avatar): string
{
    $url = appAvatarUrl($avatar);
    if (str_starts_with($url, rtrim((string)APP_URL, '/') . '/uploads/') || str_starts_with($url, rtrim((string)APP_URL, '/') . '/assets/images/avatars/')) {
        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . 'avatar_v=' . rawurlencode(sprintf('%.6f', microtime(true)));
    }
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}
