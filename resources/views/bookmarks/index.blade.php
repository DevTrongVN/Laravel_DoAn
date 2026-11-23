@extends('layouts.app')

@section('title', $pageTitle ?? 'Bài viết đã lưu — Góc Nhỏ')

@section('content')
  <div class="post-card" style="margin-bottom: 16px; display:flex; justify-content:space-between; align-items:center;">
    <div>
      <h2 style="margin:0 0 4px;">Bài viết đã lưu</h2>
      <p class="text-muted" style="margin:0; font-size:13.5px;">Tổng cộng <strong>{{ (int)($total ?? 0) }}</strong> bài viết được đánh dấu.</p>
    </div>
    <a href="{{ url('/') }}" class="action-btn" style="text-decoration:none;">Về bảng tin</a>
  </div>

  @forelse($posts as $post)
    @include('components.post_card', ['post' => $post])
  @empty
    <div class="post-card" style="text-align:center; padding:48px 20px;">
      <p class="text-muted">Bạn chưa lưu bài viết nào.</p>
      <a href="{{ url('/') }}" class="action-btn action-btn-primary" style="text-decoration:none;">Khám phá bài viết</a>
    </div>
  @endforelse
@endsection
