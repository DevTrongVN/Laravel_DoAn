@php
  $id = (int)($post['id'] ?? 0);
  $title = (string)($post['title'] ?? '');
  $summary = (string)($post['summary'] ?? '');
  $author = (string)($post['full_name'] ?? $post['username'] ?? '');
  $avatar = \App\Support\UnicodeHelper::avatarUrl($post['avatar'] ?? null);
  $type = (string)($post['post_type'] ?? 'blog');
  $likes = (int)($post['likes_count'] ?? $post['like_count'] ?? 0);
  $shares = (int)($post['shares_count'] ?? $post['share_count'] ?? 0);
  $commentsCount = (int)($post['comments_count'] ?? $post['comment_count'] ?? 0);
  $views = (int)($post['views_count'] ?? $post['views'] ?? 0);
  $myReaction = $post['my_reaction'] ?? (!empty($post['is_liked']) ? 'like' : null);
  $isBookmarked = !empty($post['is_bookmarked']);
  $viewerId = auth()->id();
  $isOwner = $viewerId !== null && $viewerId === (int)($post['user_id'] ?? 0);
  $reactionCounts = $post['reaction_counts'] ?? ['like' => 0, 'love' => 0, 'haha' => 0, 'sad' => 0, 'angry' => 0];

  $reactionConfig = [
      'like'  => ['emoji' => '👍', 'label' => 'Đã thích'],
      'love'  => ['emoji' => '❤️', 'label' => 'Yêu thích'],
      'haha'  => ['emoji' => '😄', 'label' => 'Haha'],
      'sad'   => ['emoji' => '😢', 'label' => 'Buồn'],
      'angry' => ['emoji' => '😠', 'label' => 'Phẫn nộ'],
  ];
  $activeReaction = $myReaction && isset($reactionConfig[$myReaction]) ? $reactionConfig[$myReaction] : null;
  $reactedClass = $activeReaction ? 'active is-reacted' : '';

  $breakdownParts = [];
  foreach ($reactionConfig as $rKey => $rCfg) {
      $c = (int)($reactionCounts[$rKey] ?? 0);
      if ($c > 0) {
          $breakdownParts[] = "{$rCfg['emoji']} {$c}";
      }
  }
  $breakdownText = !empty($breakdownParts) ? implode(' · ', $breakdownParts) : 'Chưa có tương tác';
@endphp

<article class="post-card" id="post-card-{{ $id }}" data-post-id="{{ $id }}">
  <header class="post-header">
    <a href="{{ url('/profile?id=' . (int)($post['user_id'] ?? 1)) }}" class="post-user-info">
      <img src="{{ $avatar }}" class="post-avatar" alt="{{ $author }}">
      <div>
        <strong class="post-author-name">{{ $author }}</strong>
        <span class="post-time">{{ $post['created_at'] ?? '' }}</span>
        @if(!empty($post['bookmarked_at']))
          <span class="post-bookmarked-time" style="font-size: 11.5px; color: #0d9488; font-weight: 500; margin-left: 6px;">
            • Đã lưu {{ date('d/m/Y H:i', strtotime((string)$post['bookmarked_at'])) }}
          </span>
        @endif
      </div>
    </a>
    <span class="badge badge-{{ $type }}">{{ $type === 'news' ? 'Tin tức' : 'Blog' }}</span>
  </header>

  <div class="post-body cursor-pointer" data-post-id="{{ $id }}">
    <a href="{{ url('/post/' . $id) }}" class="post-card-link" data-post-id="{{ $id }}">
      <h3 class="post-title">{{ $title }}</h3>
    </a>
    <p class="post-summary">{{ $summary }}</p>
    @if(!empty($post['thumbnail']))
      <a href="{{ url('/post/' . $id) }}" class="post-card-link post-thumbnail-link" data-post-id="{{ $id }}">
        <img src="{{ $post['thumbnail'] }}" class="post-thumbnail" alt="{{ $title }}">
      </a>
    @endif
    @if(!empty($post['hashtags']))
      <div class="post-card-hashtags" style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px;">
        @foreach($post['hashtags'] as $tag)
          <a href="{{ url('/search?q=' . urlencode('#' . $tag)) }}" class="hashtag-pill" style="font-size: 12px; padding: 2px 8px; border-radius: 12px; background: #f0fdfa; color: #0f766e; border: 1px solid #ccfbf1; text-decoration: none;">#{{ $tag }}</a>
        @endforeach
      </div>
    @endif
  </div>

  <footer class="post-actions">
    <div class="reaction-control" data-post-id="{{ $id }}"
         onmouseenter="handleReactionControlEnter(event)"
         onmouseleave="handleReactionControlLeave(event)"
         onpointerdown="startReactionPress(event)"
         onpointerup="endReactionPress(event)"
         onpointercancel="endReactionPress(event)">
      <button type="button" class="action-btn stat-btn reaction-trigger {{ $reactedClass }}" @if($myReaction) data-reaction="{{ $myReaction }}" @endif onclick="event.preventDefault(); event.stopPropagation(); toggleLike({{ $id }}, this, event)" title="{{ $breakdownText }}" aria-label="Cảm xúc bài viết">
        <span class="reaction-icon">{{ $activeReaction ? $activeReaction['emoji'] : '👍' }}</span>
        <span class="likes-count">{{ $likes }}</span>
        <span class="reaction-label">{{ $activeReaction ? $activeReaction['label'] : 'Thích' }}</span>
      </button>
      <div class="reaction-picker" role="group" aria-label="Chọn cảm xúc">
        @foreach($reactionConfig as $rKey => $rItem)
          <button type="button" class="reaction-choice" title="{{ $rItem['label'] }}" aria-label="{{ $rItem['label'] }}" onclick="event.preventDefault(); event.stopPropagation(); setPostReaction({{ $id }}, '{{ $rKey }}', event)">{{ $rItem['emoji'] }}</button>
        @endforeach
      </div>
      <div class="reaction-tooltip">{{ $breakdownText }}</div>
    </div>

    <a href="{{ url('/post/' . $id) }}" class="action-btn stat-btn" aria-label="Bình luận" style="text-decoration:none;">
      <svg class="btn-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <span>{{ $commentsCount }}</span>
    </a>

    <button type="button" class="action-btn stat-btn stat-btn-share" data-post-id="{{ $id }}" onclick="event.preventDefault(); event.stopPropagation(); sharePost({{ $id }}, event)" aria-label="Chia sẻ">
      <svg class="btn-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
      <span class="shares-count">{{ $shares }}</span>
    </button>

    <button type="button" class="action-btn action-btn-bookmark stat-btn stat-btn-bookmark {{ $isBookmarked ? 'bookmarked active' : '' }}" data-post-id="{{ $id }}" title="{{ $isBookmarked ? 'Bỏ lưu bài viết' : 'Lưu bài viết' }}" onclick="event.preventDefault(); event.stopPropagation(); toggleBookmark({{ $id }}, this, event)">
      <svg class="btn-icon-svg icon-bookmark-svg" width="15" height="15" viewBox="0 0 24 24" fill="{{ $isBookmarked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
      <span class="bookmark-label">{{ $isBookmarked ? 'Đã lưu' : 'Lưu' }}</span>
    </button>

    <span class="stat-btn" style="cursor:default; opacity:0.8; margin-left:auto; font-size:12px;">{{ number_format($views) }} lượt xem</span>
  </footer>
</article>
