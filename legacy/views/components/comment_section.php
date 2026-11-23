<?php
/** @var array $comments */
/** @var int $postId */
require_once __DIR__ . '/../../app/Services/MentionService.php';

$commentsList = is_array($comments ?? null) ? $comments : [];
$activeCommentCount = 0;
$commentsById = [];
$childrenByParent = [];

foreach ($commentsList as $item) {
    if (!isset($item['id'])) continue;
    $cid = (int)$item['id'];
    $commentsById[$cid] = $item;
    if (empty($item['is_deleted'])) {
        $activeCommentCount++;
    }
}

foreach ($commentsList as $item) {
    if (!isset($item['id'])) continue;
    $pId = (int)($item['parent_id'] ?? 0);
    if ($pId > 0 && isset($commentsById[$pId])) {
        $childrenByParent[$pId][] = $item;
    } else {
        $childrenByParent[0][] = $item;
    }
}

$sortComments = static function (array &$arr): void {
    usort($arr, static fn($a, $b) => strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? '')) ?: ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0)));
};
foreach ($childrenByParent as &$group) {
    $sortComments($group);
}
unset($group);

$commentReactionConfig = [
    'like'  => ['emoji' => '👍', 'label' => 'Thích'],
    'love'  => ['emoji' => '❤️', 'label' => 'Yêu thích'],
    'haha'  => ['emoji' => '😄', 'label' => 'Haha'],
    'sad'   => ['emoji' => '😢', 'label' => 'Buồn'],
    'angry' => ['emoji' => '😠', 'label' => 'Phẫn nộ'],
];

if (!function_exists('appRenderCommentCardPhp')) {
    function appRenderCommentCardPhp(array $c, int $postId, int $depth, array $commentReactionConfig, array $commentsById): string {
        $cId = (int)($c['id'] ?? 0);
        $visualDepth = min($depth, 4);
        $isDeleted = !empty($c['is_deleted']);

        if ($isDeleted) {
            return <<<HTML
<article id="comment-{$cId}" class="comment-item comment-item-deleted comment-depth-{$visualDepth}" data-comment-id="{$cId}" data-depth="{$depth}" style="--comment-depth: {$visualDepth};">
  <div class="comment-deleted-avatar" aria-hidden="true">×</div>
  <div class="comment-content-box">
    <div class="comment-bubble comment-bubble-deleted">
      <p class="comment-text comment-deleted-text">[Bình luận này đã bị xóa]</p>
    </div>
  </div>
</article>
HTML;
        }

        $commentUser = $c['user'] ?? $c;
        $cMyReaction = $c['my_reaction'] ?? null;
        $cLikes = (int)($c['likes_count'] ?? 0);
        $cActiveCfg = $cMyReaction && isset($commentReactionConfig[$cMyReaction]) ? $commentReactionConfig[$cMyReaction] : null;
        $cReactedClass = $cActiveCfg ? 'active is-reacted' : '';
        $cReactionAttr = $cMyReaction ? 'data-reaction="' . htmlspecialchars((string)$cMyReaction, ENT_QUOTES, 'UTF-8') . '"' : '';

        $replyToBadge = '';
        $pId = (int)($c['parent_id'] ?? 0);
        if ($depth >= 2 && $pId > 0 && isset($commentsById[$pId])) {
            $parentC = $commentsById[$pId];
            if (!empty($parentC['is_deleted'])) {
                $replyToBadge = '<div class="comment-reply-to-badge"><span class="reply-arrow">↳ Trả lời</span> <span class="comment-deleted-ref">bình luận đã bị xóa</span></div>';
            } else {
                $parentUser = $parentC['user'] ?? $parentC;
                $parentUsername = $parentUser['username'] ?? $parentUser['full_name'] ?? 'user';
                $replyToBadge = '<div class="comment-reply-to-badge"><span class="reply-arrow">↳ Trả lời</span> @' . htmlspecialchars((string)$parentUsername, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        }

        $choicesHtml = '';
        foreach ($commentReactionConfig as $rKey => $rItem) {
            $choicesHtml .= '<button type="button" class="reaction-choice" title="' . $rItem['label'] . '" aria-label="' . $rItem['label'] . '" onclick="event.preventDefault(); event.stopPropagation(); setCommentReaction(' . $cId . ', ' . $postId . ', \'' . $rKey . '\', event, this)">' . $rItem['emoji'] . '</button>';
        }

        $activeEmoji = $cActiveCfg ? $cActiveCfg['emoji'] : '👍';
        $activeLabel = $cActiveCfg ? $cActiveCfg['label'] : 'Thích';
        $userName = htmlspecialchars((string)($commentUser['full_name'] ?? $commentUser['username'] ?? 'Người dùng'), ENT_QUOTES, 'UTF-8');
        $authorId = (int)($commentUser['id'] ?? $c['user_id'] ?? 0);
        $rawAuthorUsername = (string)($commentUser['username'] ?? '');
        $authorUsernameAttr = htmlspecialchars($rawAuthorUsername, ENT_QUOTES, 'UTF-8');
        $authorUsernameJs = htmlspecialchars(json_encode($rawAuthorUsername, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        $appUrl = defined('APP_URL') ? APP_URL : '/DoAnMaNguonMo/public';
        $authorHref = htmlspecialchars($appUrl . '/profile.html?id=' . $authorId, ENT_QUOTES, 'UTF-8');
        $mentionsMap = is_array($c['mentions_map'] ?? null) ? $c['mentions_map'] : [];
        $contentHtml = MentionService::renderHtmlPhp((string)($c['content'] ?? ''), $mentionsMap);
        $avatarSrc = appAvatarSrc($commentUser['avatar'] ?? null);
        $editedHtml = !empty($c['edited_at']) ? '<small class="comment-edited">đã chỉnh sửa</small>' : '';

        return <<<HTML
<article id="comment-{$cId}" class="comment-item comment-depth-{$visualDepth}" data-comment-id="{$cId}" data-depth="{$depth}" data-author-id="{$authorId}" data-author-username="{$authorUsernameAttr}" style="--comment-depth: {$visualDepth};">
  <a href="{$authorHref}"><img src="{$avatarSrc}" class="avatar avatar-sm comment-avatar" alt=""></a>
  <div class="comment-content-box">
    <div class="comment-bubble">
      <a href="{$authorHref}" class="comment-author-link"><strong>{$userName}</strong></a>
      {$replyToBadge}
      <p class="comment-text">{$contentHtml}</p>
      {$editedHtml}
    </div>
    <div class="comment-actions">
      <div class="reaction-control comment-reaction-control"
           onmouseenter="handleReactionControlEnter(event)"
           onmouseleave="handleReactionControlLeave(event)"
           onpointerdown="startReactionPress(event)"
           onpointerup="endReactionPress(event)"
           onpointercancel="endReactionPress(event)">
        <button type="button" class="comment-action reaction-trigger {$cReactedClass}" {$cReactionAttr} onclick="event.preventDefault(); event.stopPropagation(); likeComment({$cId}, {$postId}, '{$cMyReaction}', this, event)">
          <span class="reaction-icon">{$activeEmoji}</span> {$activeLabel} · <span class="likes-count">{$cLikes}</span>
        </button>
        <div class="reaction-picker">
          {$choicesHtml}
        </div>
      </div>
      <button type="button" class="comment-action" onclick="replyToComment({$cId}, {$postId}, {$authorId}, {$authorUsernameJs})">Trả lời</button>
    </div>
  </div>
</article>
HTML;
    }
}

if (!function_exists('appRenderCommentBranchPhp')) {
    function appRenderCommentBranchPhp(array $node, int $postId, int $depth, array $childrenByParent, array $commentReactionConfig, array $commentsById, array &$visited): string {
        $cId = (int)($node['id'] ?? 0);
        if ($cId <= 0 || isset($visited[$cId]) || $depth > 20) {
            return '';
        }
        $visited[$cId] = true;

        $cardHtml = appRenderCommentCardPhp($node, $postId, $depth, $commentReactionConfig, $commentsById);
        $children = $childrenByParent[$cId] ?? [];
        $childrenHtml = '';
        if (!empty($children)) {
            $nextDepth = $depth + 1;
            $tierClass = 'comment-replies-tier' . min($nextDepth, 4);
            $inner = '';
            foreach ($children as $child) {
                $inner .= appRenderCommentBranchPhp($child, $postId, $nextDepth, $childrenByParent, $commentReactionConfig, $commentsById, $visited);
            }
            if ($inner !== '') {
                $childrenHtml = '<div class="comment-replies ' . $tierClass . '">' . $inner . '</div>';
            }
        }

        $wrapperClass = $depth === 0 ? 'comment-branch-root' : ('comment-branch-node comment-branch-depth-' . min($depth, 4));
        return '<div class="' . $wrapperClass . '" data-comment-id="' . $cId . '" data-depth="' . $depth . '">' . $cardHtml . $childrenHtml . '</div>';
    }
}
?>
<section class="comment-section" id="comment-section-<?= $postId ?? 0 ?>">
  <h4 class="comment-section-title">Bình luận (<?= $activeCommentCount ?>)</h4>
  <div class="comment-list" id="comment-list" data-post-id="<?= (int)($postId ?? 0) ?>">
    <?php if (!empty($childrenByParent[0])): ?>
      <?php
        $visitedNodes = [];
        foreach ($childrenByParent[0] as $root) {
            echo appRenderCommentBranchPhp($root, (int)($postId ?? 0), 0, $childrenByParent, $commentReactionConfig, $commentsById, $visitedNodes);
        }
      ?>
    <?php else: ?>
      <p class="text-muted text-center py-2">Chưa có bình luận nào. Hãy là người đầu tiên!</p>
    <?php endif; ?>
  </div>

  <form class="comment-form" id="comment-form-<?= $postId ?? 0 ?>" onsubmit="submitComment(event, <?= $postId ?? 0 ?>)">
    <input type="hidden" id="comment-parent-id-<?= $postId ?? 0 ?>" value="">
    <div class="comment-replying-indicator" id="comment-replying-indicator-<?= $postId ?? 0 ?>" style="display:none; font-size:12px; color:#0d9488; margin-bottom:6px; align-items:center; gap:6px;">
      <span>↳ Đang trả lời <span class="reply-target-id"></span></span>
      <button type="button" onclick="cancelReplyComment(<?= $postId ?? 0 ?>)" style="background:none; border:none; color:#dc2626; cursor:pointer; font-size:11px; text-decoration:underline; padding:0;">(Hủy)</button>
    </div>
    <div style="display:flex; gap:10px; width:100%; position:relative;">
      <input type="text" class="input-field" id="comment-input-<?= $postId ?? 0 ?>" data-mention-input="1" placeholder="Viết bình luận của bạn... (gõ @ để nhắc tên)" autocomplete="off" required style="flex:1;">
      <button type="submit" class="action-btn action-btn-primary">Gửi</button>
    </div>
  </form>
</section>
