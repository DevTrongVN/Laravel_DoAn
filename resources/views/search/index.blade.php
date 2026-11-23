@extends('layouts.app')

@section('title', $pageTitle ?? 'Tìm kiếm — Góc Nhỏ')

@section('content')
  <div class="post-card" style="margin-bottom: 16px;">
    <h2 style="margin:0 0 12px;">Tìm kiếm nâng cao</h2>
    <form method="GET" action="{{ url('/search') }}" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
      <input type="text" name="q" value="{{ $query ?? '' }}" class="input-field" placeholder="Tìm tiêu đề, nội dung, #hashtag hoặc @username..." style="flex:1; min-width:220px;">
      <select name="type" class="input-field" style="width:auto;">
        <option value="all" @selected(($type ?? 'all') === 'all')>Tất cả thể loại</option>
        <option value="blog" @selected(($type ?? '') === 'blog')>Bài viết (Blog)</option>
        <option value="news" @selected(($type ?? '') === 'news')>Tin tức (News)</option>
      </select>
      <select name="has_image" class="input-field" style="width:auto;">
        <option value="all" @selected(($has_image ?? 'all') === 'all')>Tất cả ảnh</option>
        <option value="1" @selected(($has_image ?? '') === '1')>Có hình ảnh</option>
        <option value="0" @selected(($has_image ?? '') === '0')>Không có hình ảnh</option>
      </select>
      <select name="sort" class="input-field" style="width:auto;">
        <option value="latest" @selected(($sort ?? 'latest') === 'latest')>Mới nhất</option>
        <option value="popular" @selected(($sort ?? '') === 'popular')>Phổ biến nhất</option>
      </select>
      <button type="submit" class="action-btn action-btn-primary">Tìm kiếm</button>
    </form>
    <p class="text-muted" style="margin:10px 0 0; font-size:13px;">
      Tìm thấy <strong>{{ (int)($pagination['total'] ?? count($posts ?? [])) }}</strong> bài viết phù hợp.
    </p>
  </div>

  @if(!empty($users))
    <div class="post-card" style="margin-bottom: 16px;">
      <h4 style="margin:0 0 10px;">Tác giả phù hợp</h4>
      <div style="display:flex; flex-wrap:wrap; gap:12px;">
        @foreach($users as $u)
          <a href="{{ url('/profile?id=' . (int)$u['id']) }}" style="display:flex; align-items:center; gap:8px; text-decoration:none; color:inherit; padding:6px 10px; border:1px solid #e5e7eb; border-radius:10px;">
            <img src="{{ \App\Support\UnicodeHelper::avatarUrl($u['avatar'] ?? null) }}" class="avatar avatar-sm" alt="">
            <div>
              <strong style="display:block; font-size:13px;">{{ $u['full_name'] ?? $u['username'] }}</strong>
              <small class="text-muted">@{{ $u['username'] }}</small>
            </div>
          </a>
        @endforeach
      </div>
    </div>
  @endif

  @forelse($posts as $post)
    @include('components.post_card', ['post' => $post])
  @empty
    <div class="post-card" style="text-align:center; padding:40px 20px;">
      <p class="text-muted">Không tìm thấy bài viết nào khớp với bộ lọc của bạn.</p>
    </div>
  @endforelse
@endsection
