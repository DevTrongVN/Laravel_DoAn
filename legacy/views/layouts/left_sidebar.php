<aside class="left-sidebar editorial-following">
  <div class="sidebar-card">
    <div class="sidebar-title">
      <span class="section-kicker">Bạn đọc</span>
      <span class="following-title-text" id="following-title-text"><?= htmlspecialchars((string)($followingTitle ?? 'Những người mình theo dõi')) ?></span>
    </div>
    <div id="following-list" class="following-list-box">
      <?php if (!empty($followingUsers)): ?>
        <?php foreach ($followingUsers as $u): ?>
          <div class="sidebar-user-item" style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #f0f0eb;">
            <a href="<?= APP_URL ?>/profile.html?id=<?= (int)$u['id'] ?>">
              <img src="<?= appAvatarSrc($u['avatar'] ?? null) ?>" class="avatar avatar-sm" alt="<?= htmlspecialchars((string)($u['full_name'] ?? $u['username'])) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1.5px solid #ced0d4;">
            </a>
            <div style="flex:1;min-width:0;">
              <a href="<?= APP_URL ?>/profile.html?id=<?= (int)$u['id'] ?>" class="user-name user-name-sm" style="font-weight:700;font-size:12px;color:#252b28;text-decoration:none;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars((string)($u['full_name'] ?? $u['username'])) ?>">
                <?= htmlspecialchars((string)($u['full_name'] ?? $u['username'])) ?> <?= !empty($u['is_verified']) ? '<span class="verified-badge">✓</span>' : '' ?>
              </a>
              <div class="font-size-xs text-muted" style="font-size:11px;color:#7c827d;display:flex;align-items:center;gap:3px;margin-top:2px;">
                ⭐ <span><?= (int)($u['reputation_score'] ?? 0) ?> điểm uy tín</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php elseif (isset($followingUsers)): ?>
        <div class="following-empty-state" style="padding:14px 4px;font-size:12.5px;color:#7c827d;text-align:center;">
          <?= !empty($isGuestFollowing) ? 'Chưa có tác giả nổi bật.' : 'Bạn chưa theo dõi tác giả nào.' ?>
        </div>
      <?php endif; ?>
    </div>
    <button class="text-link" id="following-action-link" onclick="filterNavFeed('following')">
      <?= !empty($isGuestFollowing) ? 'Khám phá bài viết' : 'Xem bài viết của họ' ?> <svg class="btn-icon-svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1px;margin-left:4px;"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
    </button>
  </div>

  <div class="sidebar-card" style="margin-top: 12px; padding: 12px 16px;">
    <a href="<?= defined('APP_URL') ? APP_URL : '' ?>/bookmarks" class="text-link sidebar-bookmarks-link" id="sidebar-bookmarks-link" onclick="navigateToBookmarks(event)" style="display:flex;align-items:center;gap:8px;font-weight:600;text-decoration:none;color:#252b28;">
      <svg class="icon-bookmark-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
      </svg>
      <span>Bài viết đã lưu</span>
    </a>
  </div>
</aside>
