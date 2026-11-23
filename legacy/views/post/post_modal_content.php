<?php
/** @var array $post */
$postId = (int)($post['id'] ?? 0);
$isRestricted = !empty($post['is_restricted']);
$moderationStatus = (string)($post['moderation_status'] ?? 'safe');
$isModerated = ($moderationStatus !== 'safe');
$isOwner = !empty($post['is_owner']);
$isAdmin = !empty($post['is_admin']);
$blockReason = htmlspecialchars((string)($post['block_reason'] ?? 'Bài viết vi phạm tiêu chuẩn cộng đồng.'), ENT_QUOTES, 'UTF-8');

$title = htmlspecialchars((string)($post['title'] ?? ''), ENT_QUOTES, 'UTF-8');
$author = htmlspecialchars((string)($post['full_name'] ?? $post['username'] ?? ''), ENT_QUOTES, 'UTF-8');
$avatar = appAvatarSrc($post['avatar'] ?? null);
$comments = $post['comments'] ?? [];
?>
<?php if ($isRestricted): ?>
  <div class="p-5 text-center" style="padding:48px 24px; text-align:center;">
    <div style="width:56px; height:56px; border-radius:50%; background:#fff1f2; color:#e11d48; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path>
        <line x1="12" y1="9" x2="12" y2="13"></line>
        <line x1="12" y1="17" x2="12.01" y2="17"></line>
      </svg>
    </div>
    <h4 style="font-weight:700; color:#1e293b; margin:0 0 8px 0; font-size:18px;">Bài viết không khả dụng</h4>
    <p style="color:#64748b; font-size:14px; margin:0; line-height:1.5;">Bài viết này đã bị tạm khóa do vi phạm Tiêu chuẩn cộng đồng hoặc đang trong quá trình kiểm duyệt.</p>
  </div>
<?php else: ?>
  <div class="post-modal-inner">
    <div class="post-modal-left">
      <?php if ($isModerated): ?>
        <div style="background:#fff1f2; border:1px solid #fecdd3; border-left:4px solid #e11d48; border-radius:10px; padding:12px 14px; margin-bottom:18px; font-size:13.5px;">
          <div style="font-weight:700; color:#9f1239; margin-bottom:4px; display:flex; align-items:center; gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path>
              <line x1="12" y1="9" x2="12" y2="13"></line>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <span>Bài viết bị tạm khóa: <?= $blockReason ?></span>
          </div>
          <div style="margin-top:6px;">
            <a href="<?= APP_URL ?>/post/<?= $postId ?>" style="color:#be123c; font-weight:600; text-decoration:underline;">Xem chi tiết trạng thái & khiếu nại →</a>
          </div>
        </div>
      <?php endif; ?>

      <div class="post-header mb-3">
        <img src="<?= $avatar ?>" class="post-avatar" alt="">
        <div>
          <strong><?= $author ?></strong>
          <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:#64748b; margin-top:2px;">
            <span class="post-time"><?= htmlspecialchars((string)($post['created_at'] ?? '')) ?></span>
            <span aria-hidden="true">•</span>
            <span style="display:inline-flex; align-items:center; gap:4px;" title="<?= number_format((int)($post['views_count'] ?? $post['views'] ?? 0)) ?> lượt xem">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              <span><?= number_format((int)($post['views_count'] ?? $post['views'] ?? 0)) ?> lượt xem</span>
            </span>
          </div>
        </div>
      </div>
      <h2><?= $title ?></h2>
      <?php if (!empty($post['thumbnail'])): ?>
        <img src="<?= htmlspecialchars((string)$post['thumbnail']) ?>" class="post-thumbnail mb-3" alt="">
      <?php endif; ?>
      <div class="post-body-text">
        <?= nl2br(htmlspecialchars((string)($post['content'] ?? ''))) ?>
      </div>
    </div>

    <div class="post-modal-right">
      <?php if (!$isModerated): ?>
        <?php require __DIR__ . '/../components/comment_section.php'; ?>
      <?php else: ?>
        <div style="padding:36px 20px; text-align:center; color:#64748b;">
          <div style="width:48px; height:48px; border-radius:50%; background:#f1f5f9; display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px; color:#94a3b8;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
          </div>
          <p style="margin:0; font-size:13.5px; font-weight:500;">Bình luận bị tạm dừng do bài viết đang trong trạng thái vi phạm.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
