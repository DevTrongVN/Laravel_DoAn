<?php
/** @var array $items */
?>
<div class="admin-reports-view">
  <h2 class="mb-4">Kiểm Duyệt Nội Dung & AI Moderation</h2>
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Bài viết</th>
          <th>Tác giả</th>
          <th>Trạng thái AI</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($items)): ?>
          <?php foreach ($items as $item): ?>
            <tr>
              <td>#<?= $item['id'] ?></td>
              <td>
                <strong><?= htmlspecialchars((string)$item['title']) ?></strong>
                <p class="text-sm text-muted"><?= htmlspecialchars((string)$item['summary']) ?></p>
              </td>
              <td><?= htmlspecialchars((string)($item['full_name'] ?? $item['username'] ?? '')) ?></td>
              <td>
                <span class="badge badge-<?= $item['moderation_status'] ?>">
                  <?= $item['moderation_status'] ?>
                </span>
              </td>
              <td>
                <button class="btn btn-sm btn-success" onclick="updatePostModStatus(<?= $item['id'] ?>, 'safe')">Bỏ cờ (Safe)</button>
                <button class="btn btn-sm btn-danger" onclick="updatePostModStatus(<?= $item['id'] ?>, 'blocked')">Khóa bài</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5" class="text-center py-4 text-muted">Không có bài viết nào vi phạm hoặc bị AI gắn cờ.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="admin-reports-view mt-4">
  <h2 class="mb-4">Báo cáo bài viết từ người dùng</h2>
  <div class="table-responsive">
    <table class="admin-table">
      <thead><tr><th>Bài viết</th><th>Tác giả</th><th>Người báo cáo</th><th>Lý do / mô tả</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
      <tbody>
        <?php if (!empty($userReports)): foreach ($userReports as $report): ?>
          <tr>
            <td>#<?= (int)$report['post_id'] ?> <?= htmlspecialchars((string)$report['post_title'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)$report['author_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)$report['reporter_name'], ENT_QUOTES, 'UTF-8') ?> (@<?= htmlspecialchars((string)$report['username'], ENT_QUOTES, 'UTF-8') ?>)</td>
            <td><?= htmlspecialchars((string)$report['reason'], ENT_QUOTES, 'UTF-8') ?><br><?= htmlspecialchars((string)($report['details'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)$report['status'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?php if ($report['status'] === 'pending'): ?>
                <button class="btn btn-sm btn-success" onclick="changeUserReportStatus(<?= (int)$report['id'] ?>, 'reviewed')">Đã xử lý</button>
                <button class="btn btn-sm" onclick="changeUserReportStatus(<?= (int)$report['id'] ?>, 'dismissed')">Bỏ qua</button>
              <?php endif; ?>
              <button class="btn btn-sm btn-danger" onclick="updatePostModStatus(<?= (int)$report['post_id'] ?>, '<?= $report['moderation_status'] === 'blocked' ? 'safe' : 'blocked' ?>')"><?= $report['moderation_status'] === 'blocked' ? 'Mở khóa' : 'Khóa bài' ?></button>
              <button class="btn btn-sm btn-danger" onclick="deleteAdminPost(<?= (int)$report['post_id'] ?>)">Xóa bài</button>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="6" class="text-center py-4 text-muted">Chưa có báo cáo từ người dùng.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
