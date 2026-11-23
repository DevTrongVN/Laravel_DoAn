<?php

declare(strict_types=1);

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\ApiAIController;
use App\Http\Controllers\Api\ApiCommentController;
use App\Http\Controllers\Api\ApiFollowController;
use App\Http\Controllers\Api\ApiPostController;
use App\Http\Controllers\Api\ApiReactionController;
use App\Http\Controllers\Api\ApiWidgetController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Góc Nhỏ (dùng chung Session & CSRF qua middleware 'web')
|--------------------------------------------------------------------------
*/

Route::middleware('web')->group(function (): void {
    // 1. Xác thực & CSRF Token
    Route::get('/csrf-token', [AuthController::class, 'csrfToken'])->name('api.csrf');
    Route::post('/auth/register', [AuthController::class, 'register'])->name('api.auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');

    // 2. Public Read APIs (Feed, Chi tiết bài, Tìm kiếm, Widgets, Hồ sơ công khai)
    Route::get('/feed', [ApiPostController::class, 'feed'])->name('api.feed');
    Route::get('/posts', [ApiPostController::class, 'feed'])->name('api.posts.index');
    Route::get('/posts/{id}', [ApiPostController::class, 'show'])->whereNumber('id')->name('api.posts.show');
    Route::get('/posts/{id}/comments', [ApiCommentController::class, 'index'])->whereNumber('id')->name('api.comments.index');
    Route::get('/users/search', [ApiCommentController::class, 'searchMentionUsers'])->name('api.users.search');
    Route::get('/users/{id}', [UserController::class, 'show'])->whereNumber('id')->name('api.users.show');
    Route::get('/following', [ApiFollowController::class, 'followingList'])->name('api.following');
    Route::get('/author', [ApiController::class, 'primaryAuthor'])->name('api.author');
    Route::get('/search', [SearchController::class, 'index'])->name('api.search');
    Route::get('/hashtags', [ApiWidgetController::class, 'topHashtags'])->name('api.hashtags');
    Route::get('/widgets/news', [ApiWidgetController::class, 'topNews'])->name('api.widgets.news');

    // 3. AI Assistants (Sửa văn phong & Quét kiểm duyệt)
    Route::post('/ai/improve', [ApiAIController::class, 'improveStyle'])->name('api.ai.improve');
    Route::post('/ai/rewrite', [ApiAIController::class, 'improveStyle'])->name('api.ai.rewrite');
    Route::post('/ai/moderate', [ApiAIController::class, 'moderate'])->name('api.ai.moderate');

    // 4. Các API yêu cầu đăng nhập (Middleware 'auth.user')
    Route::middleware('auth.user')->group(function (): void {
        Route::post('/auth/profile', [AuthController::class, 'updateProfile'])->name('api.auth.profile');

        // Bài viết & Upload ảnh
        Route::post('/posts/create', [PostController::class, 'create'])->name('api.posts.create');
        Route::post('/posts/{id}/edit', [PostController::class, 'update'])->whereNumber('id')->name('api.posts.update');
        Route::delete('/posts/{id}', [PostController::class, 'delete'])->whereNumber('id')->name('api.posts.destroy');
        Route::post('/posts/{id}/delete', [PostController::class, 'delete'])->whereNumber('id')->name('api.posts.delete');
        Route::post('/posts/{id}/report', [ApiPostController::class, 'report'])->whereNumber('id')->name('api.posts.report');
        Route::post('/posts/{id}/appeal', [PostController::class, 'submitAppeal'])->whereNumber('id')->name('api.posts.appeal');
        Route::post('/uploads/image', [ApiController::class, 'uploadImage'])->name('api.uploads.image');

        // Cảm xúc, Chia sẻ, Đăng lại, Lưu bài viết
        Route::post('/posts/{id}/like', [ApiReactionController::class, 'set'])->whereNumber('id')->name('api.posts.like');
        Route::post('/posts/{id}/reaction', [ApiReactionController::class, 'set'])->whereNumber('id')->name('api.posts.reaction');
        Route::post('/posts/{id}/react', [ApiReactionController::class, 'set'])->whereNumber('id');
        Route::post('/posts/{id}/share', [ApiPostController::class, 'share'])->whereNumber('id')->name('api.posts.share');
        Route::post('/posts/{id}/repost', [ApiPostController::class, 'repost'])->whereNumber('id')->name('api.posts.repost');
        Route::get('/bookmarks', [BookmarkController::class, 'list'])->name('api.bookmarks.index');
        Route::post('/posts/{id}/bookmark', [BookmarkController::class, 'add'])->whereNumber('id')->name('api.bookmarks.add');
        Route::delete('/posts/{id}/bookmark', [BookmarkController::class, 'remove'])->whereNumber('id')->name('api.bookmarks.remove');
        Route::post('/posts/{id}/unbookmark', [BookmarkController::class, 'remove'])->whereNumber('id');

        // Bình luận
        Route::post('/posts/{id}/comments', [ApiCommentController::class, 'create'])->whereNumber('id')->name('api.comments.create');
        Route::post('/comments/{id}/edit', [ApiCommentController::class, 'update'])->whereNumber('id')->name('api.comments.update');
        Route::post('/comments/{id}/delete', [ApiCommentController::class, 'delete'])->whereNumber('id')->name('api.comments.delete');
        Route::post('/comments/{id}/like', [ApiCommentController::class, 'like'])->whereNumber('id')->name('api.comments.like');
        Route::post('/comments/{id}/reaction', [ApiCommentController::class, 'like'])->whereNumber('id');
        Route::post('/comments/{id}/react', [ApiCommentController::class, 'like'])->whereNumber('id');
        Route::post('/comments/{id}/report', [ApiCommentController::class, 'report'])->whereNumber('id')->name('api.comments.report');

        // Ticket nâng cấp Tin tức & Đánh giá AI
        Route::post('/posts/{id}/tickets', [TicketController::class, 'create'])->whereNumber('id')->name('api.tickets.create');
        Route::post('/ai/news-potential', [ApiAIController::class, 'suggestNews'])->name('api.ai.news-potential');

        // Theo dõi & Ảnh đại diện (Avatar)
        Route::post('/users/{id}/follow', [ApiFollowController::class, 'toggle'])->whereNumber('id')->name('api.users.follow');
        Route::post('/users/avatar', [UserController::class, 'uploadAvatar'])->name('api.users.avatar.upload');
        Route::delete('/users/avatar', [UserController::class, 'resetAvatar'])->name('api.users.avatar.reset');
        Route::post('/users/avatar/reset', [UserController::class, 'resetAvatar']);
        Route::post('/user/avatar', [UserController::class, 'uploadAvatar']);
        Route::delete('/user/avatar', [UserController::class, 'resetAvatar']);
        Route::post('/user/avatar/reset', [UserController::class, 'resetAvatar']);

        // Thông báo & Dashboard cá nhân
        Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('api.notifications.read-all');
        Route::post('/notifications/read', [NotificationController::class, 'markReadBody'])->name('api.notifications.read-body');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id')->name('api.notifications.read');
        Route::get('/dashboard/me', [ApiController::class, 'personalDashboard'])->name('api.dashboard.me');
    });

    // 5. Khu vực Quản trị viên — 6 Module Admin API (Middleware 'admin')
    Route::prefix('admin')->middleware('admin')->name('api.admin.')->group(function (): void {
        Route::get('/stats', [AdminController::class, 'index'])->name('stats');

        // Module 1: Người dùng & Tích xanh
        Route::get('/users', [AdminController::class, 'users'])->name('users.index');
        Route::post('/users/{id}/verify', [AdminController::class, 'toggleVerified'])->whereNumber('id')->name('users.verify');
        Route::get('/badge-logs/{id}', [AdminController::class, 'badgeLogs'])->whereNumber('id')->name('users.badge-logs');
        Route::post('/users/{id}/role', [AdminController::class, 'changeRole'])->whereNumber('id')->name('users.role');

        // Module 2: Quản lý Bài viết & Bình luận
        Route::get('/posts', [AdminController::class, 'posts'])->name('posts.index');
        Route::post('/posts/{id}/moderation', [AdminController::class, 'updatePostModeration'])->whereNumber('id')->name('posts.moderation');
        Route::post('/posts/{id}/type', [AdminController::class, 'updatePostType'])->whereNumber('id')->name('posts.type');
        Route::post('/posts/{id}/delete', [AdminController::class, 'deletePost'])->whereNumber('id')->name('posts.delete');
        Route::get('/comments', [AdminController::class, 'comments'])->name('comments.index');
        Route::post('/comments/{id}/moderation', [AdminController::class, 'updateCommentModeration'])->whereNumber('id')->name('comments.moderation');
        Route::post('/comments/{id}/delete', [AdminController::class, 'deleteComment'])->whereNumber('id')->name('comments.delete');

        // Module 3: Báo cáo Bài viết & Khiếu nại (Appeals)
        Route::get('/user-reports', [AdminController::class, 'userReports'])->name('user-reports.index');
        Route::post('/user-reports/{id}/status', [AdminController::class, 'updateUserReportStatus'])->whereNumber('id')->name('user-reports.status');
        Route::get('/appeals', [AdminController::class, 'appeals'])->name('appeals.index');
        Route::post('/appeals/{id}/resolve', [AdminController::class, 'resolveAppeal'])->whereNumber('id')->name('appeals.resolve');
        Route::get('/reports', [AdminController::class, 'userReports'])->name('reports.index');
        Route::post('/reports/{id}/status', [AdminController::class, 'updateUserReportStatus'])->whereNumber('id')->name('reports.status');

        // Module 4: Báo cáo Bình luận
        Route::get('/comment-reports', [AdminController::class, 'commentReports'])->name('comment-reports.index');
        Route::post('/comment-reports/{id}/status', [AdminController::class, 'updateCommentReportStatus'])->whereNumber('id')->name('comment-reports.status');

        // Module 5: Đề xuất Tin tức (Ticket & AI)
        Route::get('/news-suggestions', [AdminController::class, 'newsSuggestions'])->name('news-suggestions.index');
        Route::post('/news-suggestions/{id}/review', [AdminController::class, 'reviewNewsSuggestion'])->name('news-suggestions.review');
        Route::get('/tickets', [AdminController::class, 'tickets'])->name('tickets.index');
        Route::post('/tickets/{id}', [AdminController::class, 'reviewTicket'])->whereNumber('id')->name('tickets.review');

        // Module 6: Kiểm duyệt nội dung AI
        Route::get('/ai-moderation', [AdminController::class, 'aiModeration'])->name('ai-moderation.index');
        Route::post('/ai-moderation/{id}/decision', [AdminController::class, 'aiModerationDecision'])->whereNumber('id')->name('ai-moderation.decision');
    });
});
