<?php
// strict types

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Hashtag;
use App\Services\PostQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

echo "=== KIEM TRA TOAN DIEN DATABASE & CHUC NANG LARAVEL ===\n\n";

$errors = [];
$passes = 0;

$check = function(string $label, callable $fn) use (&$passes, &$errors) {
    try {
        $result = $fn();
        if ($result !== false) {
            echo " [PASS] $label\n";
            $passes++;
        } else {
            echo " [FAIL] $label (Returned false)\n";
            $errors[] = "$label (Returned false)";
        }
    } catch (\Throwable $e) {
        echo " [FAIL] $label: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
        $errors[] = "$label: " . $e->getMessage();
    }
};

// 1. Database Connection & Basic Counts
$check("Ket noi MySQL & Dem Users", function() {
    $count = User::count();
    echo "       (Tong so users: $count)\n";
    return $count > 0;
});

$check("Dem Posts", function() {
    $count = Post::count();
    echo "       (Tong so posts: $count)\n";
    return $count > 0;
});

$check("Dem Comments", function() {
    $count = Comment::count();
    echo "       (Tong so comments: $count)\n";
    return $count > 0;
});

// 2. PostQueryService Tests
$queryService = app(PostQueryService::class);

$check("PostQueryService::list (Feed mac dinh)", function() use ($queryService) {
    $res = $queryService->list([], null, 1, 10);
    return !empty($res['data']) && count($res['data']) > 0;
});

$check("PostQueryService::list (Loc type = news)", function() use ($queryService) {
    $res = $queryService->list(['type' => 'news'], null, 1, 5);
    return is_array($res['data']);
});

$check("PostQueryService::list (Loc type = blog)", function() use ($queryService) {
    $res = $queryService->list(['type' => 'blog'], null, 1, 5);
    return is_array($res['data']);
});

$check("PostQueryService::list (Loc hashtag = webdev)", function() use ($queryService) {
    $res = $queryService->list(['hashtag' => 'webdev'], null, 1, 5);
    return is_array($res['data']);
});

$check("PostQueryService::list (Sap xep popular)", function() use ($queryService) {
    $res = $queryService->list(['sort' => 'popular'], null, 1, 5);
    return !empty($res['data']);
});

$check("PostQueryService::list (Sap xep discussed)", function() use ($queryService) {
    $res = $queryService->list(['sort' => 'discussed'], null, 1, 5);
    return !empty($res['data']);
});

$check("PostQueryService::list (Loc has_image = yes)", function() use ($queryService) {
    $res = $queryService->list(['has_image' => 'yes'], null, 1, 5);
    return is_array($res['data']);
});

$check("PostQueryService::topNews", function() use ($queryService) {
    $res = $queryService->topNews(5);
    return is_array($res);
});

$check("PostQueryService::topHashtags", function() use ($queryService) {
    $res = $queryService->topHashtags(5);
    return is_array($res) && count($res) > 0;
});

// 3. Post Model & Relationships
$check("Post Model relationships (user, hashtags, comments, reactions)", function() {
    $post = Post::with(['user', 'hashtags', 'comments', 'reactions'])->first();
    if (!$post) return false;
    $hasUser = $post->user !== null;
    $hasTags = $post->hashtags !== null;
    $hasComments = $post->comments !== null;
    $hasReactions = $post->reactions !== null;
    return $hasUser && $hasTags && $hasComments && $hasReactions;
});

// 4. Comment Hierarchy & Mentions
$check("Comment Model relationships & Hierarchy columns", function() {
    $comment = Comment::whereNotNull('parent_id')->first();
    if ($comment) {
        $hasParent = $comment->parent !== null || $comment->parent_id > 0;
        $hasRoot = array_key_exists('root_id', $comment->getAttributes());
        return $hasRoot;
    }
    return true;
});

// 5. User Model & Auth methods
$check("User Model methods & attributes", function() {
    $user = User::first();
    if (!$user) return false;
    $arr = $user->toPublicArray();
    return isset($arr['id']) && isset($arr['username']) && isset($arr['reputation_score']);
});

// 6. HTTP API Dispatch Tests
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$apiEndpoints = [
    'GET /api/feed' => ['GET', '/api/feed', []],
    'GET /api/feed?sort=popular' => ['GET', '/api/feed', ['sort' => 'popular']],
    'GET /api/feed?type=news' => ['GET', '/api/feed', ['type' => 'news']],
    'GET /api/posts' => ['GET', '/api/posts', []],
    'GET /api/hashtags' => ['GET', '/api/hashtags', []],
    'GET /api/widgets/news' => ['GET', '/api/widgets/news', []],
    'GET /api/author' => ['GET', '/api/author', []],
    'GET /api/auth/me (khach)' => ['GET', '/api/auth/me', []],
    'GET /api/csrf-token' => ['GET', '/api/csrf-token', []],
    'GET /api/search?q=test' => ['GET', '/api/search', ['q' => 'test']],
];

$samplePost = Post::orderByDesc('id')->first();
if ($samplePost) {
    $apiEndpoints["GET /api/posts/{$samplePost->id}"] = ['GET', "/api/posts/{$samplePost->id}", []];
    $apiEndpoints["GET /api/posts/{$samplePost->id}/comments"] = ['GET', "/api/posts/{$samplePost->id}/comments", []];
}

$sampleUser = User::first();
if ($sampleUser) {
    $apiEndpoints["GET /api/users/{$sampleUser->id}"] = ['GET', "/api/users/{$sampleUser->id}", []];
}

foreach ($apiEndpoints as $name => $spec) {
    $check("API: $name", function() use ($httpKernel, $spec) {
        [$method, $uri, $params] = $spec;
        $req = Illuminate\Http\Request::create($uri, $method, $params);
        $resp = $httpKernel->handle($req);
        $status = $resp->getStatusCode();
        $httpKernel->terminate($req, $resp);
        return $status >= 200 && $status < 400;
    });
}

// 7. Blade Views Rendering
$bladeViews = [
    'home' => ['home', ['pageTitle' => 'Trang chu']],
    'account.index' => ['account.index', ['pageTitle' => 'Tai khoan']],
    'account.devices' => ['account.devices', ['pageTitle' => 'Thiet bi']],
    'dashboard.index' => ['dashboard.index', ['pageTitle' => 'Dashboard']],
    'profile.show' => ['profile.show', ['profileId' => 1, 'pageTitle' => 'Profile']],
];

foreach ($bladeViews as $name => $spec) {
    $check("Blade View: $name", function() use ($spec) {
        [$viewName, $data] = $spec;
        $html = view($viewName, $data)->render();
        return strlen($html) > 100;
    });
}

echo "\n============================================\n";
echo "KET QUA: $passes PASS, " . count($errors) . " FAIL\n";
if (!empty($errors)) {
    echo "Cac loi can khac phuc:\n";
    foreach ($errors as $e) {
        echo " - $e\n";
    }
} else {
    echo "TAT CA CAC TRUY VAN VA CHUC NANG DEU HOAT DONG HOAN HAO!\n";
}
echo "============================================\n";
