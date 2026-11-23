<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../Services/CacheService.php';

/** API Endpoints: Lấy Top Tin HOT (Widget Pagination) & Top Hashtags HOT. */
class ApiWidgetController extends Controller
{
    private CacheService $cacheService;

    public function __construct()
    {
        $this->cacheService = new CacheService();
    }

    public function topNews(): never
    {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
        $news = $this->cacheService->getTopNews($limit);
        $this->json(['data' => $news]);
    }

    public function topHashtags(): never
    {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $hashtags = $this->cacheService->getTopHashtags($limit);
        $this->json(['data' => $hashtags]);
    }
}
