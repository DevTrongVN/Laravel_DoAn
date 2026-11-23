<?php
declare(strict_types=1);
/** @var array $data */
/** @var array $notifications */
/** @var int $unread_count */
/** @var array $pagination */
$items = $notifications ?? (isset($data['data']) && is_array($data['data']) ? $data['data'] : (is_array($data) ? $data : []));
$unreadCount = (int)($unread_count ?? 0);
$page = (int)($pagination['page'] ?? 1);
$lastPage = (int)($pagination['last_page'] ?? 1);
$total = (int)($pagination['total'] ?? count($items));
$appBaseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
?>
<div class="notifications-page" style="margin-bottom: 32px;">
  <!-- Header Trung tâm thông báo -->
  <div class="notifications-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom:24px; padding-bottom:18px; border-bottom:1px solid var(--border,#e4e8e2);">
    <div>
      <h2 style="margin:0 0 6px 0; font-size:24px; font-weight:700; color:var(--text,#1e2621); display:flex; align-items:center; gap:10px;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--green,#2d6347);vertical-align:-3px;" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg> Trung tâm thông báo
      </h2>
      <span class="text-muted" id="notifications-subtitle" style="font-size:13.5px;">
        <?= $unreadCount > 0 ? "Bạn có <strong style='color:var(--green,#2d6347);'>{$unreadCount}</strong> thông báo chưa đọc" : "Tất cả thông báo đã được đọc" ?>
      </span>
    </div>
    
    <div style="display:flex; gap:10px; align-items:center;">
      <?php if ($unreadCount > 0): ?>
        <button type="button" class="action-btn" id="btn-read-all" onclick="markAllNotificationsAsRead()" style="background:#eef6f0; color:#23613b; border:1px solid #c2e0cd; font-size:13px; font-weight:600; padding:8px 14px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:4px;"><polyline points="18 6 7 17 2 12"></polyline><polyline points="22 10 13 19 10 16"></polyline></svg>Đánh dấu tất cả đã đọc
        </button>
      <?php endif; ?>
      <a href="<?= $appBaseUrl ?>/" class="action-btn" style="background:#fff; border:1px solid #d2dbd0; font-size:13px; padding:8px 14px; border-radius:8px; color:var(--ink,#1c2621); text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        <span>Về trang chủ</span>
      </a>
    </div>
  </div>

  <!-- Danh sách thông báo -->
  <div class="notifications-list" id="notifications-list-container" style="display:flex; flex-direction:column; gap:12px;">
    <?php if (!empty($items)): ?>
      <?php foreach ($items as $notif): ?>
        <?php
          $isUnread = empty($notif['is_read']);
          $notifId = (int)$notif['id'];
          $rawTarget = (string)($notif['target_url'] ?? '');
          if (!empty($rawTarget) && $rawTarget !== '#') {
              if (str_starts_with($rawTarget, 'http://') || str_starts_with($rawTarget, 'https://')) {
                  $targetUrl = htmlspecialchars($rawTarget, ENT_QUOTES, 'UTF-8');
              } else {
                  $targetUrl = htmlspecialchars($appBaseUrl . '/' . ltrim($rawTarget, '/'), ENT_QUOTES, 'UTF-8');
              }
          } else {
              $targetUrl = '#';
          }
          $notifType = (string)($notif['type'] ?? 'system');

          // Icon loại tương tác (SVG sắc nét)
          $typeBadge = match ($notifType) {
              'reaction', 'post_reaction' => '<span style="background:#fee2e2; color:#ef4444; border-radius:50%; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; position:absolute; bottom:-3px; right:-3px; border:2px solid #fff;"><svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg></span>',
              'comment'  => '<span style="background:#e0e7ff; color:#4f46e5; border-radius:50%; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; position:absolute; bottom:-3px; right:-3px; border:2px solid #fff;"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>',
              'mention'  => '<span style="background:#dbeafe; color:#1877f2; border-radius:50%; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; position:absolute; bottom:-3px; right:-3px; border:2px solid #fff; font-size:12px; font-weight:800; line-height:1;" aria-hidden="true">@</span>',
              'repost'   => '<span style="background:#ecfdf5; color:#10b981; border-radius:50%; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; position:absolute; bottom:-3px; right:-3px; border:2px solid #fff;"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg></span>',
              'follow'   => '<span style="background:#fef3c7; color:#d97706; border-radius:50%; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; position:absolute; bottom:-3px; right:-3px; border:2px solid #fff;"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span>',
              'ticket_approved' => '<span style="background:#dcfce7; color:#16a34a; border-radius:50%; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; position:absolute; bottom:-3px; right:-3px; border:2px solid #fff;"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg></span>',
              'ticket_rejected' => '<span style="background:#fee2e2; color:#dc2626; border-radius:50%; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; position:absolute; bottom:-3px; right:-3px; border:2px solid #fff;"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></span>',
              default    => '<span style="background:#e0f2fe; color:#0284c7; border-radius:50%; width:22px; height:22px; display:inline-flex; align-items:center; justify-content:center; position:absolute; bottom:-3px; right:-3px; border:2px solid #fff;"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>',
          };

          // Avatar hiển thị: Nếu là thông báo hệ thống ẩn danh (actor_id NULL) -> Icon Shield hệ thống
          $isSystem = empty($notif['actor_username']) || in_array($notifType, ['system', 'system_moderation', 'ticket_approved', 'ticket_rejected'], true);
        ?>
        <div class="notification-item <?= $isUnread ? 'unread' : 'read' ?>"
             id="notif-item-<?= $notifId ?>"
             data-id="<?= $notifId ?>"
             onclick="handleNotificationClick(event, <?= $notifId ?>, '<?= $targetUrl ?>', <?= $isUnread ? 'true' : 'false' ?>)"
             style="display:flex; align-items:center; gap:16px; padding:14px 18px; border-radius:12px; cursor:pointer; background:<?= $isUnread ? '#f2f9f4' : '#ffffff' ?>; border:1px solid <?= $isUnread ? '#cbe6d4' : '#edf0eb' ?>; border-left:4px solid <?= $isUnread ? 'var(--green,#2d6347)' : 'transparent' ?>; box-shadow:0 1px 3px rgba(0,0,0,0.03); transition:all 0.18s ease; position:relative;">
          
          <div style="position:relative; flex-shrink:0;">
            <?php if (!$isSystem && !empty($notif['actor_avatar'])): ?>
              <img src="<?= appAvatarSrc($notif['actor_avatar']) ?>" style="width:48px; height:48px; border-radius:50%; object-fit:cover; border:1px solid #e0e5df;" alt="">
            <?php else: ?>
              <div style="width:48px; height:48px; border-radius:50%; background:linear-gradient(135deg, #0284c7, #0369a1); color:#fff; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(2,132,199,0.3);">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
              </div>
            <?php endif; ?>
            <?= $typeBadge ?>
          </div>

          <div class="notification-content" style="flex:1; min-width:0;">
            <p style="margin:0 0 5px 0; font-size:14px; line-height:1.45; color:var(--text,#1e2621); font-weight:<?= $isUnread ? '600' : '400' ?>;">
              <?= htmlspecialchars((string)$notif['content']) ?>
            </p>
            <span class="notification-time" style="font-size:12px; color:var(--muted,#7c827d); display:inline-flex; align-items:center; gap:5px;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1.5px;margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> <?= htmlspecialchars((string)$notif['created_at']) ?>
            </span>
          </div>

          <!-- Nút đánh dấu đã đọc hoặc chấm xanh -->
          <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
            <?php if ($isUnread): ?>
              <button type="button" class="btn-single-read" onclick="markSingleNotificationAsRead(event, <?= $notifId ?>)" title="Đánh dấu đã đọc" style="background:#fff; border:1px solid #c2e0cd; color:var(--green,#2d6347); width:32px; height:32px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; font-size:12px; transition:all 0.15s ease;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
              </button>
              <span class="unread-dot" title="Chưa đọc" style="width:10px; height:10px; border-radius:50%; background:var(--green,#2d6347); flex-shrink:0;"></span>
            <?php endif; ?>
          </div>

        </div>
      <?php endforeach; ?>

      <!-- Phân trang -->
      <?php if ($lastPage > 1): ?>
        <nav class="notifications-pagination" style="display:flex; justify-content:center; align-items:center; gap:10px; margin-top:28px;">
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>" class="action-btn" style="padding:8px 16px; font-size:13px; background:#fff; border:1px solid #d2dbd0; border-radius:8px; color:inherit; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
              <span>Trang trước</span>
            </a>
          <?php endif; ?>

          <span style="font-size:13px; color:var(--muted,#7c827d);">Trang <strong><?= $page ?></strong> / <?= $lastPage ?> (Tổng <?= $total ?> thông báo)</span>

          <?php if ($page < $lastPage): ?>
            <a href="?page=<?= $page + 1 ?>" class="action-btn" style="padding:8px 16px; font-size:13px; background:#fff; border:1px solid #d2dbd0; border-radius:8px; color:inherit; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
              <span>Trang sau</span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>

    <?php else: ?>
      <div class="text-center py-5 text-muted" style="padding:60px 0; text-align:center; color:var(--muted,#7c827d);">
        <div style="width:70px; height:70px; border-radius:50%; background:#f1f4f0; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#9ba69f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path><line x1="2" y1="2" x2="22" y2="22"></line></svg>
        </div>
        <h4 style="margin:0 0 8px; font-weight:700; color:var(--ink,#1c2621); font-size:18px;">Bạn chưa có thông báo nào</h4>
        <p style="margin:0 auto; font-size:13.5px; max-width:420px; line-height:1.5;">Khi có người tương tác, bình luận, chia sẻ bài viết hoặc thông báo kiểm duyệt từ quản trị viên, thông tin sẽ được cập nhật ở đây.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
// Đánh dấu tất cả thông báo đã đọc
async function markAllNotificationsAsRead() {
  const btn = document.getElementById('btn-read-all');
  if (btn) { btn.disabled = true; btn.innerHTML = '<svg class="icon-spin-slow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:6px;" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10"></path></svg> Đang xử lý...'; }
  try {
    if (typeof apiRequest === 'function') {
      await apiRequest('api/notifications/read-all', { method: 'POST', body: {} });
    } else {
      await fetch('api/notifications/read-all', { method: 'POST', headers: { 'Accept': 'application/json' } });
    }

    // Đổi toàn bộ giao diện thành đã đọc
    document.querySelectorAll('.notification-item.unread').forEach(item => {
      item.classList.remove('unread');
      item.classList.add('read');
      item.style.background = '#ffffff';
      item.style.borderColor = '#edf0eb';
      item.style.borderLeftColor = 'transparent';
      const dot = item.querySelector('.unread-dot');
      if (dot) dot.remove();
      const readBtn = item.querySelector('.btn-single-read');
      if (readBtn) readBtn.remove();
      const p = item.querySelector('p');
      if (p) p.style.fontWeight = '400';
    });

    if (btn) btn.remove();
    const subText = document.getElementById('notifications-subtitle');
    if (subText) subText.textContent = 'Tất cả thông báo đã được đọc';

    // Cập nhật badge trên header
    const bellBadge = document.getElementById('nav-bell-badge');
    if (bellBadge) { bellBadge.textContent = '0'; bellBadge.style.display = 'none'; }
  } catch (err) {
    alert(err.message || 'Không thể đánh dấu đã đọc.');
    if (btn) { btn.disabled = false; btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:4px;"><polyline points="18 6 7 17 2 12"></polyline><polyline points="22 10 13 19 10 16"></polyline></svg>Đánh dấu tất cả đã đọc'; }
  }
}

// Đánh dấu 1 thông báo là đã đọc
async function markSingleNotificationAsRead(event, id) {
  if (event) { event.stopPropagation(); event.preventDefault(); }
  const item = document.getElementById(`notif-item-${id}`);
  if (!item) return;

  try {
    if (typeof apiRequest === 'function') {
      await apiRequest(`api/notifications/${id}/read`, { method: 'POST', body: {} });
    } else {
      await fetch(`api/notifications/${id}/read`, { method: 'POST' });
    }

    // Cập nhật giao diện item
    item.classList.remove('unread');
    item.classList.add('read');
    item.style.background = '#ffffff';
    item.style.borderColor = '#edf0eb';
    item.style.borderLeftColor = 'transparent';
    const dot = item.querySelector('.unread-dot');
    if (dot) dot.remove();
    const btn = item.querySelector('.btn-single-read');
    if (btn) btn.remove();
    const p = item.querySelector('p');
    if (p) p.style.fontWeight = '400';

    // Giảm badge trên header
    const bellBadge = document.getElementById('nav-bell-badge');
    if (bellBadge) {
      const cur = parseInt(bellBadge.textContent, 10) || 0;
      const next = Math.max(0, cur - 1);
      bellBadge.textContent = next > 99 ? '99+' : String(next);
      if (next === 0) bellBadge.style.display = 'none';
    }
  } catch (_) {}
}

// Xử lý khi click vào thẻ thông báo
async function handleNotificationClick(event, id, targetUrl, isUnread) {
  if (isUnread) {
    try {
      if (typeof apiRequest === 'function') {
        apiRequest(`api/notifications/${id}/read`, { method: 'POST', body: {} });
      } else {
        fetch(`api/notifications/${id}/read`, { method: 'POST' });
      }
    } catch (_) {}
  }
  if (targetUrl && targetUrl !== '#' && targetUrl !== '') {
    if (/^https?:\/\//i.test(targetUrl) || targetUrl.startsWith('/')) {
      window.location.href = targetUrl;
    } else {
      const base = typeof APP_URL !== 'undefined' ? APP_URL : '';
      window.location.href = base + '/' + targetUrl.replace(/^\/+/, '');
    }
  }
}
</script>
