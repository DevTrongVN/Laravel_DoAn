@extends('layouts.app')

@section('title', 'Bài viết không còn tồn tại — Góc Nhỏ')

@section('content')
  <div class="post-card" style="text-align:center; padding: 48px 24px;">
    <h2 style="margin-top:0; color:#1e293b;">Bài viết không còn tồn tại</h2>
    <p class="text-muted" style="margin-bottom:20px;">Bài viết #{{ $postId ?? '' }} có thể đã bị xóa hoặc đường dẫn không hợp lệ.</p>
    <a href="{{ url('/') }}" class="action-btn action-btn-primary" style="text-decoration:none;">Quay lại trang chủ</a>
  </div>
@endsection
