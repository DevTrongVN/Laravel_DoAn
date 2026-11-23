@php
  $commentsList = $comments ?? [];
  $commentCount = count($commentsList);
  $commentReactionConfig = [
      'like'  => ['emoji' => '👍', 'label' => 'Thích'],
      'love'  => ['emoji' => '❤️', 'label' => 'Yêu thích'],
      'haha'  => ['emoji' => '😄', 'label' => 'Haha'],
      'sad'   => ['emoji' => '😢', 'label' => 'Buồn'],
      'angry' => ['emoji' => '😠', 'label' => 'Phẫn nộ'],
  ];

  $commentsById = [];
  foreach ($commentsList as $item) {
      if (isset($item['id'])) {
          $commentsById[(int)$item['id']] = $item;
      }
  }

  $collectDescendants = function (int $parentId) use (&$collectDescendants, $commentsList): array {
      $result = [];
      foreach ($commentsList as $item) {
          if ((int)($item['parent_id'] ?? 0) === $parentId) {
              $result[] = $item;
              $children = $collectDescendants((int)$item['id']);
              if (!empty($children)) {
                  $result = array_merge($result, $children);
              }
          }
      }
      return $result;
  };

  $renderCard = function (array $c, int $visualDepth) use ($postId, $commentReactionConfig, $commentsById): string {
      $cId = (int)($c['id'] ?? 0);
      $commentUser = $c['user'] ?? $c;
      $cMyReaction = $c['my_reaction'] ?? null;
      $cLikes = (int)($c['likes_count'] ?? 0);
      $cActiveCfg = $cMyReaction && isset($commentReactionConfig[$cMyReaction]) ? $commentReactionConfig[$cMyReaction] : null;
      $cReactedClass = $cActiveCfg ? 'active is-reacted' : '';
      $cReactionAttr = $cMyReaction ? 'data-reaction="' . e((string)$cMyReaction) . '"' : '';

      $authorId = (int)($commentUser['id'] ?? $c['user_id'] ?? 0);
      $rawAuthorUsername = (string)($commentUser['username'] ?? '');
      $authorUsernameAttr = e($rawAuthorUsername);
      $authorUsernameJs = addslashes($rawAuthorUsername);

      $replyToBadge = '';
      $pId = (int)($c['parent_id'] ?? 0);
      if ($visualDepth >= 2 && $pId > 0 && isset($commentsById[$pId])) {
          $parentUser = $commentsById[$pId]['user'] ?? $commentsById[$pId];
          $parentName = $parentUser['full_name'] ?? $parentUser['username'] ?? 'tác giả';
          $replyToBadge = '<div class="comment-reply-to-badge"><span class="reply-arrow">↳ Trả lời</span> @' . e((string)$parentName) . '</div>';
      }

      $choicesHtml = '';
      foreach ($commentReactionConfig as $rKey => $rItem) {
          $choicesHtml .= '<button type="button" class="reaction-choice" title="' . $rItem['label'] . '" aria-label="' . $rItem['label'] . '" onclick="event.preventDefault(); event.stopPropagation(); setCommentReaction(' . $cId . ', ' . (int)$postId . ', \'' . $rKey . '\', event, this)">' . $rItem['emoji'] . '</button>';
      }

      $activeEmoji = $cActiveCfg ? $cActiveCfg['emoji'] : '👍';
      $activeLabel = $cActiveCfg ? $cActiveCfg['label'] : 'Thích';
      $userName = e((string)($commentUser['full_name'] ?? $commentUser['username'] ?? 'Người dùng'));
      $mentionsMap = is_array($c['mentions_map'] ?? null) ? $c['mentions_map'] : [];
      $contentHtml = \App\Services\MentionService::renderHtmlPhp((string)($c['content'] ?? ''), $mentionsMap);
      $avatarSrc = e(\App\Support\UnicodeHelper::avatarUrl($commentUser['avatar'] ?? null));
      $pid = (int)$postId;
      $myReactionEsc = e((string)$cMyReaction);
      $authorHref = e('/profile.html?id=' . $authorId);

      return <<<HTML
<article id="comment-{$cId}" class="comment-item comment-depth-{$visualDepth}" data-comment-id="{$cId}" data-depth="{$visualDepth}" data-author-id="{$authorId}" data-author-username="{$authorUsernameAttr}" data-post-id="{$pid}" style="--comment-depth: {$visualDepth};">
  <a href="{$authorHref}"><img src="{$avatarSrc}" class="avatar avatar-sm comment-avatar" alt=""></a>
  <div class="comment-content-box">
    <div class="comment-bubble">
      <a href="{$authorHref}" class="comment-author-link"><strong>{$userName}</strong></a>
      {$replyToBadge}
      <p class="comment-text">{$contentHtml}</p>
    </div>
    <div class="comment-actions">
      <div class="reaction-control comment-reaction-control"
           onmouseenter="handleReactionControlEnter(event)"
           onmouseleave="handleReactionControlLeave(event)"
           onpointerdown="startReactionPress(event)"
           onpointerup="endReactionPress(event)"
           onpointercancel="endReactionPress(event)">
        <button type="button" class="comment-action reaction-trigger {$cReactedClass}" {$cReactionAttr} onclick="event.preventDefault(); event.stopPropagation(); likeComment({$cId}, {$pid}, '{$myReactionEsc}', this, event)">
          <span class="reaction-icon">{$activeEmoji}</span> {$activeLabel} · <span class="likes-count">{$cLikes}</span>
        </button>
        <div class="reaction-picker">{$choicesHtml}</div>
      </div>
      <button type="button" class="comment-action" onclick="replyToComment({$cId}, {$pid}, {$authorId}, '{$authorUsernameJs}')">Trả lời</button>
    </div>
  </div>
</article>
HTML;
  };

  // Lọc bỏ bình luận mồ côi: nếu có parent_id > 0 nhưng không tìm thấy cha hợp lệ thì loại bỏ
  $commentsList = array_values(array_filter($commentsList, static function ($c) use ($commentsById): bool {
      $pId = (int)($c['parent_id'] ?? 0);
      return $pId === 0 || isset($commentsById[$pId]);
  }));
  $commentCount = count($commentsList);

  $roots = [];
  foreach ($commentsList as $c) {
      $pId = (int)($c['parent_id'] ?? 0);
      if ($pId === 0) {
          $roots[] = $c;
      }
  }
  usort($roots, static fn($a, $b) => strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? '')) ?: ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0)));
@endphp

<section class="comment-section" id="comment-section-{{ (int)($postId ?? 0) }}">
  <h4 class="comment-section-title">Bình luận ({{ $commentCount }})</h4>
  <div class="comment-list" id="comment-list" data-post-id="{{ (int)($postId ?? 0) }}">
    @if(!empty($roots))
      @foreach($roots as $root)
        @php
          $tier1 = array_values(array_filter($commentsList, static fn($c) => (int)($c['parent_id'] ?? 0) === (int)$root['id']));
          usort($tier1, static fn($a, $b) => strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? '')) ?: ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0)));
        @endphp
        <div class="comment-branch-root" data-root-id="{{ (int)$root['id'] }}">
          {!! $renderCard($root, 0) !!}
          @if(!empty($tier1))
            <div class="comment-replies comment-replies-tier1">
              @foreach($tier1 as $c1)
                @php
                  $tier2Descendants = $collectDescendants((int)$c1['id']);
                  usort($tier2Descendants, static fn($a, $b) => strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? '')) ?: ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0)));
                @endphp
                <div class="comment-branch-tier1" data-comment-id="{{ (int)$c1['id'] }}">
                  {!! $renderCard($c1, 1) !!}
                  @if(!empty($tier2Descendants))
                    <div class="comment-replies comment-replies-tier2">
                      @foreach($tier2Descendants as $desc)
                        {!! $renderCard($desc, 2) !!}
                      @endforeach
                    </div>
                  @endif
                </div>
              @endforeach
            </div>
          @endif
        </div>
      @endforeach
    @else
      <p class="text-muted text-center py-2">Chưa có bình luận nào. Hãy là người đầu tiên!</p>
    @endif
  </div>

  <form class="comment-form" id="comment-form-{{ (int)($postId ?? 0) }}" onsubmit="submitComment(event, {{ (int)($postId ?? 0) }})">
    @csrf
    <input type="hidden" id="comment-parent-id-{{ (int)($postId ?? 0) }}" value="">
    <div class="comment-replying-indicator" id="comment-replying-indicator-{{ (int)($postId ?? 0) }}" style="display:none; font-size:12px; color:#0d9488; margin-bottom:6px; align-items:center; gap:6px;">
      <span>↳ Đang trả lời bình luận #<span class="reply-target-id"></span></span>
      <button type="button" onclick="cancelReplyComment({{ (int)($postId ?? 0) }})" style="background:none; border:none; color:#dc2626; cursor:pointer; font-size:11px; text-decoration:underline; padding:0;">(Hủy)</button>
    </div>
    <div style="display:flex; gap:10px; width:100%;">
      <input type="text" class="input-field" id="comment-input-{{ (int)($postId ?? 0) }}" placeholder="Viết bình luận của bạn..." required style="flex:1;">
      <button type="submit" class="action-btn action-btn-primary">Gửi</button>
    </div>
  </form>
</section>
