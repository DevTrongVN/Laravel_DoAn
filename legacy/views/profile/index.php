<?php
/** @var array $user */
/** @var array $posts */
/** @var int $followers_count */
/** @var int $following_count */
/** @var bool $is_following */
$name = htmlspecialchars((string)($user['full_name'] ?? $user['username'] ?? ''), ENT_QUOTES, 'UTF-8');
$avatar = appAvatarSrc($user['avatar'] ?? null);
$isVerified = !empty($user['is_verified']);
$isOwner = Auth::check() && (int)Auth::id() === (int)$user['id'];
$isCustomAvatar = !empty($user['avatar']) && (str_contains($user['avatar'], '/uploads/avatars/') || str_contains($user['avatar'], 'uploads/avatars/'));
?>
<div class="profile-card mb-4" id="profile-header">
  <div class="profile-header-cover"></div>
  <div class="profile-header-content">
    <div class="profile-avatar-wrapper profile-avatar-container">
      <img src="<?= $avatar ?>" class="profile-avatar-lg" id="profile-avatar-img" alt="<?= $name ?>">
      <?php if ($isOwner): ?>
        <button type="button" class="btn-change-avatar" id="btn-trigger-avatar-upload" title="Đổi ảnh đại diện">
          <i class="fa-solid fa-camera"></i>
        </button>
      <?php endif; ?>
    </div>
    <div class="profile-info-row">
      <div>
        <h2 class="profile-name">
          <?= $name ?>
          <?php if ($isVerified): ?>
            <i class="fa-solid fa-circle-check text-primary" title="Tài khoản đã xác minh"></i>
          <?php endif; ?>
        </h2>
        <span class="profile-username">@<?= htmlspecialchars((string)$user['username']) ?></span>
      </div>
      <div>
        <?php if ($isOwner): ?>
          <div style="display:flex;gap:8px;align-items:center;">
            <button type="button" class="action-btn action-btn-sm" id="btn-open-avatar-modal" style="font-size:12px;padding:6px 14px;">
              <i class="fa-solid fa-camera"></i> Đổi avatar
            </button>
            <?php if ($isCustomAvatar): ?>
              <button type="button" class="action-btn action-btn-sm action-btn-outline" id="btn-reset-avatar-action" style="font-size:12px;padding:6px 14px;background:#f3f4f6;color:#374151;border:1px solid #d1d5db;">
                <i class="fa-solid fa-rotate-left"></i> Mặc định
              </button>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <button class="action-btn <?= $is_following ? 'action-btn-outline' : 'action-btn-primary' ?>" onclick="toggleProfileFollow(<?= $user['id'] ?>)">
            <?= $is_following ? 'Đang theo dõi' : '+ Theo dõi' ?>
          </button>
        <?php endif; ?>
      </div>
    </div>
    <p class="profile-bio mt-2"><?= htmlspecialchars((string)($user['bio'] ?? 'Chưa có tiểu sử.')) ?></p>
    <div class="profile-stats-bar mt-3">
      <span><strong><?= (int)($user['reputation_score'] ?? 0) ?></strong> Điểm uy tín</span>
      <span><strong><?= $followers_count ?></strong> Người theo dõi</span>
      <span><strong><?= $following_count ?></strong> Đang theo dõi</span>
      <span><strong><?= count($posts) ?></strong> Bài viết</span>
    </div>
  </div>
</div>

<h3 class="mb-3">Bài viết đã đăng</h3>
<div class="feed-posts" id="profile-feed-container">
  <?php if (!empty($posts)): ?>
    <?php foreach ($posts as $post): ?>
      <?php require __DIR__ . '/../components/post_card.php'; ?>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="text-center text-muted py-4">Người dùng này chưa đăng bài viết nào.</p>
  <?php endif; ?>
</div>

<!-- Modal Xem trước & Tải ảnh đại diện -->
<div class="modal-overlay" id="avatar-preview-modal" style="display:none;">
  <div class="modal-content" style="max-width: 440px; padding: 24px; text-align: center;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #f0efe9; padding-bottom:10px;">
      <h3 style="margin:0; font-size:18px; display:flex; align-items:center; gap:8px;">
        <i class="fa-solid fa-camera" style="color:#0d9488;"></i> Thay đổi ảnh đại diện
      </h3>
      <button type="button" class="close-modal" id="btn-close-avatar-modal" aria-label="Đóng">&times;</button>
    </div>

    <p class="text-muted" style="font-size:13px; margin-bottom:12px;">Xem trước ảnh đại diện mới trước khi lưu:</p>

    <div class="avatar-preview-box">
      <img id="avatar-preview-image" class="avatar-preview-img" src="" alt="Xem trước ảnh">
    </div>

    <div id="avatar-file-details" style="font-size:12px; color:#64748b; margin-bottom:12px;"></div>
    <div id="avatar-upload-feedback" style="font-size:13px; margin-bottom:12px;"></div>

    <div style="display:flex; justify-content:center; gap:12px;">
      <button type="button" class="action-btn" id="btn-cancel-avatar-modal" style="background:#e2e8f0; color:#334155;">Hủy</button>
      <button type="button" class="action-btn action-btn-primary" id="btn-confirm-avatar-upload">
        <i class="fa-solid fa-cloud-arrow-up"></i> Lưu ảnh đại diện
      </button>
    </div>
  </div>
</div>
<input type="file" id="custom-avatar-file-input" accept="image/jpeg,image/png,image/webp" style="display:none;">
