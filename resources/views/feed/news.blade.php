@extends('layouts.app')

@section('title', $pageTitle ?? 'Tin tức — Góc Nhỏ')

@section('content')
  <div class="post-card" style="margin-bottom: 16px;">
    <h2 style="margin:0 0 6px;">Tin tức nổi bật</h2>
    <p class="text-muted" style="margin:0; font-size:13.5px;">Các bài phân tích, tin tức chuyên sâu đã được Ban Quản Trị phê duyệt.</p>
  </div>

  @forelse($posts as $post)
    @include('components.post_card', ['post' => $post])
  @empty
    <div class="post-card" style="text-align:center; padding:40px 20px;">
      <p class="text-muted">Chưa có bài Tin tức nào.</p>
    </div>
  @endforelse
@endsection
