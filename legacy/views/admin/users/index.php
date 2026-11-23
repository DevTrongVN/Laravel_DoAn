<?php
/** @var array $users */
?>
<div class="admin-users-view">
  <h2 class="mb-4">Quản Lý Thành Viên & Cấp Tích Xanh</h2>
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Thành viên</th>
          <th>Email</th>
          <th>Vai trò</th>
          <th>Điểm Uy Tín</th>
          <th>Tích xanh</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($users)): ?>
          <?php foreach ($users as $u): ?>
            <tr>
              <td>#<?= $u['id'] ?></td>
              <td>
                <div class="flex items-center gap-2">
                  <img src="<?= appAvatarSrc($u['avatar'] ?? null) ?>" class="user-avatar-sm" alt="">
                  <div>
                    <strong><?= htmlspecialchars((string)$u['full_name']) ?></strong>
                    <div class="text-xs text-muted">@<?= htmlspecialchars((string)$u['username']) ?></div>
                  </div>
                </div>
              </td>
              <td><?= htmlspecialchars((string)$u['email']) ?></td>
              <td><span class="badge badge-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
              <td><strong><?= (int)$u['reputation_score'] ?></strong></td>
              <td>
                <?= !empty($u['is_verified']) 
                  ? '<span class="badge badge-success"><i class="fa-solid fa-check"></i> Đã cấp</span>' 
                  : '<span class="badge badge-secondary">Chưa cấp</span>' 
                ?>
              </td>
              <td>
                <button class="btn btn-sm btn-outline-primary" onclick="toggleUserVerified(<?= $u['id'] ?>)">
                  <?= !empty($u['is_verified']) ? 'Thu hồi tích xanh' : 'Cấp tích xanh' ?>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" class="text-center py-4 text-muted">Chưa có người dùng nào.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
