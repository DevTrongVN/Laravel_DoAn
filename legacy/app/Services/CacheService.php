<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/CacheManager.php';
require_once __DIR__ . '/../Models/Tag.php';
require_once __DIR__ . '/../Models/Post.php';

/** Dịch vụ quản lý Cache dữ liệu Widget & Feed để tối ưu hiệu năng. */
final class CacheService
{
    private array $cacheConfig;

    public function __construct()
    {
        $this->cacheConfig = require __DIR__ . '/../../config/cache.php';
    }

    public function getTopNews(int $limit = 10): array
    {
        $postModel = new Post();
        return $postModel->topNews(max(1, min(50, $limit)));
    }

    public function getTopHashtags(int $limit = 10): array
    {
        $tagModel = new Tag();
        return $tagModel->top(max(1, min(50, $limit)));
    }

    public function invalidateWidgets(): void
    {
        CacheManager::forget('widget_top_news_5');
        CacheManager::forget('widget_top_news_15');
        CacheManager::forget('widget_top_hashtags_10');
    }
}
