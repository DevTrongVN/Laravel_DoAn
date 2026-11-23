<?php

declare(strict_types=1);

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Góc Nhỏ (Laravel Blade Views)
|--------------------------------------------------------------------------
*/

// Trang chủ & Bảng tin
Route::get('/', [FeedController::class, 'home'])->name('home');
Route::get('/index.html', [FeedController::class, 'home']);
Route::get('/news', [FeedController::class, 'news'])->name('feed.news');
Route::get('/following', [FeedController::class, 'following'])->name('feed.following');

// Bài viết & Lưu bài viết
Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
Route::get('/post/bookmarks', [BookmarkController::class, 'index']);
Route::get('/post/{id}', [PostController::class, 'show'])->whereNumber('id')->name('posts.show');

// Trang cá nhân
Route::get('/profile', [UserController::class, 'showPage'])->name('profile.index');
Route::get('/profile.html', [UserController::class, 'showPage']);
Route::get('/profile/{id}', [UserController::class, 'show'])->name('profile.show');

// Tìm kiếm & Thông báo
Route::get('/search', [SearchController::class, 'index'])->name('search.index');
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

// Tài khoản & Thống kê cá nhân
Route::get('/account', [FeedController::class, 'account'])->name('account.index');
Route::get('/account.html', [FeedController::class, 'account']);
Route::get('/accounts', [FeedController::class, 'accounts'])->name('account.devices');
Route::get('/accounts.html', [FeedController::class, 'accounts']);
Route::get('/account-devices', [FeedController::class, 'accounts']);
Route::get('/my-dashboard', [FeedController::class, 'myDashboard'])->name('dashboard.me');
Route::get('/my-dashboard.html', [FeedController::class, 'myDashboard']);

// Khu vực Quản trị viên (được bảo vệ bởi Middleware 'admin')
Route::middleware('admin')->group(function (): void {
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin.html', [AdminController::class, 'index']);
    Route::get('/admin/tickets', [AdminController::class, 'tickets'])->name('admin.tickets');
    Route::get('/admin/reports', [AdminController::class, 'userReports'])->name('admin.reports');
    Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
});
