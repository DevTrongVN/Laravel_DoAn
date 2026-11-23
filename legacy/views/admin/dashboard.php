<?php
/** @var array $stats */
?>
<div class="admin-dashboard-view">
  <h2 class="mb-4">Bảng Điều Khiển Quản Trị</h2>
  <div class="admin-stats-grid">
    <div class="stat-card">
      <div class="stat-icon users"><i class="fa-solid fa-users"></i></div>
      <div class="stat-info">
        <span class="stat-number"><?= (int)($stats['total_users'] ?? 0) ?></span>
        <span class="stat-label">Tổng thành viên</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon posts"><i class="fa-solid fa-newspaper"></i></div>
      <div class="stat-info">
        <span class="stat-number"><?= (int)($stats['total_posts'] ?? 0) ?></span>
        <span class="stat-label">Tổng bài viết</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon tickets"><i class="fa-solid fa-ticket"></i></div>
      <div class="stat-info">
        <span class="stat-number"><?= (int)($stats['pending_tickets'] ?? 0) ?></span>
        <span class="stat-label">Ticket chờ duyệt</span>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon moderation"><i class="fa-solid fa-shield-halved"></i></div>
      <div class="stat-info">
        <span class="stat-number"><?= (int)($stats['flagged_posts'] ?? 0) ?></span>
        <span class="stat-label">Nghi vấn AI Flagged</span>
      </div>
    </div>
  </div>
</div>
