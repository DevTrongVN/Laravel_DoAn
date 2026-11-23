<?php
/** @var array $tickets */
?>
<div class="admin-tickets-view">
  <h2 class="mb-4">Duyệt Ticket Nâng Cấp Tin Tức</h2>
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Tác giả</th>
          <th>Điểm Uy Tín</th>
          <th>Bài viết</th>
          <th>Trạng thái</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($tickets)): ?>
          <?php foreach ($tickets as $t): ?>
            <tr>
              <td>#<?= $t['id'] ?></td>
              <td><?= htmlspecialchars((string)($t['author_name'] ?? $t['username'] ?? '')) ?></td>
              <td><span class="badge badge-info"><?= $t['author_reputation_snapshot'] ?></span></td>
              <td><strong><?= htmlspecialchars((string)($t['post_title'] ?? '')) ?></strong></td>
              <td>
                <span class="badge badge-<?= $t['status'] ?>"><?= $t['status'] ?></span>
              </td>
              <td>
                <?php if ($t['status'] === 'pending'): ?>
                  <button class="btn btn-sm btn-success" onclick="reviewTicket(<?= $t['id'] ?>, 'approved')">Duyệt</button>
                  <button class="btn btn-sm btn-danger" onclick="reviewTicket(<?= $t['id'] ?>, 'rejected')">Từ chối</button>
                <?php else: ?>
                  <span class="text-muted text-sm">Đã xử lý</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" class="text-center py-4 text-muted">Không có ticket nào chờ duyệt.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
