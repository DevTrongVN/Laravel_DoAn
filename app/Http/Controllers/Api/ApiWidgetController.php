<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PostQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API Controller: Widget Top Tin HOT & Top Hashtags HOT.
 */
class ApiWidgetController extends Controller
{
    public function __construct(
        private readonly PostQueryService $postQuery
    ) {}

    public function topNews(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->query('limit', 5)));
        return response()->json(['data' => $this->postQuery->topNews($limit)]);
    }

    public function topHashtags(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->query('limit', 12)));
        return response()->json(['data' => $this->postQuery->topHashtags($limit)]);
    }
}
