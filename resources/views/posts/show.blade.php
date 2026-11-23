@extends('layouts.app')

@section('title', $pageTitle ?? ($post['title'] . ' — Góc Nhỏ'))

@section('content')
@php
  $postId = (int)($post['id'] ?? 0);
  $isRestricted = !empty($post['is_restricted']);
  $moderationStatus = (string)($post['moderation_status'] ?? 'safe');
  $isModerated = ($moderationStatus !== 'safe');
  $isOwner = !empty($post['is_owner']);
  $blockReason = (string)($post['block_reason'] ?? 'Bài viết vi phạm tiêu chuẩn cộng đồng hoặc đang được tạm khóa để kiểm duyệt.');
  $latestAppeal = $post['latest_appeal'] ?? null;
  $author = (string)($post['full_name'] ?? $post['username'] ?? '');
  $avatar = \App\Support\UnicodeHelper::avatarUrl($post['avatar'] ?? null);
  $comments = $post['comments'] ?? [];
@endphp

@if($isRestricted)
  <div class="post-restricted-card">
    <h3 class="post-restricted-title">Bài viết không khả dụng</h3>
    <p class="post-restricted-desc">Bài viết này đã bị tạm khóa do vi phạm Tiêu chuẩn cộng đồng hoặc đang trong quá trình kiểm duyệt nội dung.</p>
    <div class="post-restricted-actions">
      <a href="{{ url('/') }}" class="btn-appeal-submit" style="text-decoration:none;">Về trang chủ</a>
      <button type="button" class="btn-appeal-submit" style="background:#f1f5f9; color:#334155;" onclick="history.back()">Quay lại</button>
    </div>
  </div>
@else
  <article class="post-card post-detail-card" id="post-detail" data-post-id="{{ $postId }}">
    @if($isModerated)
      <section class="blocked-alert-box" aria-label="Cảnh báo kiểm duyệt">
        <div class="blocked-alert-body">
          <h4 class="blocked-alert-title">Bài viết này đã bị tạm khóa do vi phạm tiêu chuẩn cộng đồng</h4>
          <div class="blocked-reason-card">
            <div class="blocked-reason-label"><span>Lý do từ Ban Quản Trị</span></div>
            <p class="blocked-reason-text">{{ $blockReason }}</p>
          </div>
          @if($isOwner)
            <div class="appeal-card">
              @if(!empty($latestAppeal) && ($latestAppeal['status'] ?? '') === 'pending')
                <div class="appeal-pending-box">
                  <span>Yêu cầu khiếu nại đang chờ Ban Quản Trị xem xét (gửi lúc {{ $latestAppeal['created_at'] ?? '' }})</span>
                  <div class="appeal-submitted-content">{!! nl2br(e((string)$latestAppeal['reason'])) !!}</div>
                </div>
              @else
                <form id="appeal-form" onsubmit="handlePostAppealSubmit(event, {{ $postId }})">
                  @csrf
                  <label for="appeal-reason" style="display:block;font-weight:600;margin-bottom:6px;">Gửi khiếu nại xem xét mở khóa bài viết:</label>
                  <textarea id="appeal-reason" class="input-field" rows="3" placeholder="Nhập giải trình của bạn..." required></textarea>
                  <button type="submit" class="action-btn action-btn-primary mt-2">Gửi khiếu nại</button>
                </form>
              @endif
            </div>
          @endif
        </div>
      </section>
    @endif

    <header class="post-header">
      <a href="{{ url('/profile?id=' . (int)$post['user_id']) }}" class="post-user-info" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">
        <img src="{{ $avatar }}" class="post-avatar avatar" alt="{{ $author }}">
        <div>
          <strong class="post-author-name">{{ $author }} @if(!empty($post['is_verified'])) <span class="verified-badge">✓</span> @endif</strong>
          <div class="post-time" style="font-size:12px;color:#7c827d;">{{ $post['created_at'] ?? '' }}</div>
        </div>
      </a>
      <span class="badge badge-{{ $post['post_type'] ?? 'blog' }}">
        {{ ($post['post_type'] ?? '') === 'news' ? 'Tin tức' : 'Blog' }}
      </span>
    </header>

    <h1 class="post-detail-title">{{ $post['title'] }}</h1>

    @if(!empty($post['thumbnail']))
      <img src="{{ $post['thumbnail'] }}" class="post-detail-hero-img" alt="{{ $post['title'] }}">
    @endif

    <div class="post-detail-content">
      {!! nl2br(e((string)($post['content'] ?? ''))) !!}
    </div>

    @if(!$isModerated)
      @php
        $dLikes = (int)($post['likes_count'] ?? 0);
        $dShares = (int)($post['shares_count'] ?? 0);
        $dIsBookmarked = !empty($post['is_bookmarked']);
        $dMyReaction = $post['my_reaction'] ?? null;
        $dReactionConfig = [
            'like'  => ['emoji' => '👍', 'label' => 'Đã thích'],
            'love'  => ['emoji' => '❤️', 'label' => 'Yêu thích'],
            'haha'  => ['emoji' => '😄', 'label' => 'Haha'],
            'sad'   => ['emoji' => '😢', 'label' => 'Buồn'],
            'angry' => ['emoji' => '😠', 'label' => 'Phẫn nộ'],
        ];
        $dActiveReaction = $dMyReaction && isset($dReactionConfig[$dMyReaction]) ? $dReactionConfig[$dMyReaction] : null;
        $dReactedClass = $dActiveReaction ? 'active is-reacted' : '';
      @endphp
      <div class="post-interactive-stats post-detail-actions" id="post-detail-actions" aria-label="Tương tác bài viết">
        <div class="reaction-control" data-post-id="{{ $postId }}"
             onmouseenter="handleReactionControlEnter(event)"
             onmouseleave="handleReactionControlLeave(event)"
             onpointerdown="startReactionPress(event)"
             onpointerup="endReactionPress(event)"
             onpointercancel="endReactionPress(event)">
          <button type="button" class="action-btn stat-btn reaction-trigger {{ $dReactedClass }}" @if($dMyReaction) data-reaction="{{ $dMyReaction }}" @endif onclick="toggleLike({{ $postId }}, this, event)" aria-label="Cảm xúc bài viết">
            <span class="reaction-icon">{{ $dActiveReaction ? $dActiveReaction['emoji'] : '👍' }}</span>
            <span class="likes-count">{{ $dLikes }}</span>
            <span class="reaction-label">{{ $dActiveReaction ? $dActiveReaction['label'] : 'Thích' }}</span>
          </button>
          <div class="reaction-picker" role="group" aria-label="Chọn cảm xúc">
            @foreach($dReactionConfig as $rKey => $rItem)
              <button type="button" class="reaction-choice" title="{{ $rItem['label'] }}" aria-label="{{ $rItem['label'] }}" onclick="setPostReaction({{ $postId }}, '{{ $rKey }}', event)">{{ $rItem['emoji'] }}</button>
            @endforeach
          </div>
        </div>

        <button type="button" class="action-btn stat-btn stat-btn-share" data-post-id="{{ $postId }}" onclick="sharePost({{ $postId }}, event)" aria-label="Chia sẻ">
          <svg class="btn-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
          <span class="shares-count">{{ $dShares }}</span> Chia sẻ
        </button>

        <button type="button" class="action-btn action-btn-bookmark stat-btn stat-btn-bookmark {{ $dIsBookmarked ? 'bookmarked active' : '' }}" data-post-id="{{ $postId }}" title="{{ $dIsBookmarked ? 'Bỏ lưu bài viết' : 'Lưu bài viết' }}" onclick="toggleBookmark({{ $postId }}, this, event)">
          <svg class="btn-icon-svg icon-bookmark-svg" width="15" height="15" viewBox="0 0 24 24" fill="{{ $dIsBookmarked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
          <span class="bookmark-label">{{ $dIsBookmarked ? 'Đã lưu' : 'Lưu' }}</span>
        </button>

        <div class="stat-btn stat-btn-views" title="Lượt xem bài viết" style="cursor:default; pointer-events:none; opacity:0.85; margin-left:auto;">
          <span>{{ number_format((int)($post['views_count'] ?? $post['views'] ?? 0)) }} lượt xem</span>
        </div>
      </div>
    @else
      <div class="post-paused-bar">
        <span>Tính năng bình luận và tương tác cảm xúc đang tạm dừng do bài viết bị khóa.</span>
      </div>
    @endif

    @if(!empty($post['hashtags']))
      <div class="post-detail-hashtags mt-4">
        @foreach($post['hashtags'] as $tag)
          <a href="{{ url('/search?q=' . urlencode('#' . $tag)) }}" class="hashtag-pill">#{{ $tag }}</a>
        @endforeach
      </div>
    @endif

    <div class="post-sep-line my-4"></div>

    @if(!$isModerated)
      @include('components.comment_section', ['comments' => $comments, 'postId' => $postId])
    @endif
  </article>

  <script>
    async function handlePostAppealSubmit(event, postId) {
      if (event) event.preventDefault();
      const textarea = document.getElementById('appeal-reason');
      if (!textarea) return;
      const reason = textarea.value.trim();
      if (!reason) { alert('Vui lòng nhập lý do khiếu nại.'); return; }
      try {
        await apiRequest(`api/posts/${postId}/appeal`, { method: 'POST', body: { reason } });
        alert('Gửi khiếu nại thành công! Ban Quản Trị sẽ xem xét sớm nhất.');
        window.location.reload();
      } catch (err) {
        alert(err.message || 'Không thể gửi khiếu nại.');
      }
    }
  </script>
@endif
@endsection
