@extends('layouts.app')

@section('title', $pageTitle ?? 'Đang theo dõi — Góc Nhỏ')

@section('content')
  <div class="post-card" style="margin-bottom: 16px;">
    <h2 style="margin:0 0 6px;">Bài viết từ người bạn đang theo dõi</h2>
    <p class="text-muted" style="margin:0; font-size:13.5px;">Cập nhật mới nhất từ những tác giả bạn quan tâm.</p>
  </div>

  @if(!auth()->check())
    <div class="post-card" style="text-align:center; padding:40px 20px;">
      <p class="text-muted">Vui lòng đăng nhập để xem bài viết từ những người bạn đang theo dõi.</p>
      <a href="{{ url('/account?mode=login') }}" class="action-btn action-btn-primary" style="text-decoration:none;">Đăng nhập ngay</a>
    </div>
  @else
    @forelse($posts as $post)
      @include('components.post_card', ['post' => $post])
    @empty
      <div class="post-card" style="text-align:center; padding:40px 20px;">
        <p class="text-muted">Chưa có bài viết nào từ người bạn đang theo dõi.</p>
      </div>
    @endforelse
  @endif
@endsection
