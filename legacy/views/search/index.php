<?php
/** @var string $query */
/** @var string $tab */
/** @var array $posts */
/** @var array $users */
/** @var array $pagination */
$type = (string)($_GET['type'] ?? $_GET['category'] ?? 'all');
$hasImage = (string)($_GET['has_image'] ?? 'all');
$sort = (string)($_GET['sort'] ?? 'latest');
$page = (int)($pagination['page'] ?? 1);
$lastPage = (int)($pagination['last_page'] ?? 1);
$total = (int)($pagination['total'] ?? count($posts));

function searchUrl(array $params = []): string {
    global $query, $tab, $type, $hasImage, $sort, $page;
    $merged = array_merge([
        'q'         => $query,
        'tab'       => $tab,
        'type'      => ($type !== '' && $type !== 'all') ? $type : null,
        'has_image' => ($hasImage !== '' && $hasImage !== 'all') ? $hasImage : null,
        'sort'      => $sort,
        'page'      => $page,
    ], $params);
    $filtered = array_filter($merged, static function($v, $k) {
        if ($v === null || $v === '') return false;
        if ($k === 'tab' && $v === 'all') return false;
        if ($k === 'sort' && $v === 'latest') return false;
        if ($k === 'type' && $v === 'all') return false;
        if ($k === 'has_image' && $v === 'all') return false;
        if ($k === 'page' && (int)$v <= 1) return false;
        return true;
    }, ARRAY_FILTER_USE_BOTH);
    return '?' . http_build_query($filtered);
}
?>
<div class="search-results-page page-shell" style="max-width: 900px; margin: 28px auto; padding: 0 16px;">
  <div class="search-header mb-4" style="border-bottom:1px solid var(--border,#e4e8e2); padding-bottom:16px;">
    <!-- Khung tìm kiếm trực tiếp trên trang kết quả -->
    <form action="" method="GET" id="search-keyword-form" style="display:flex; gap:10px; margin-bottom:16px;">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <input type="hidden" name="type" id="search-filter-type" value="<?= htmlspecialchars($type) ?>">
      <input type="hidden" name="has_image" id="search-filter-has-image" value="<?= htmlspecialchars($hasImage) ?>">
      <input type="hidden" name="sort" id="search-filter-sort" value="<?= htmlspecialchars($sort) ?>">
      <div style="position:relative; flex:1;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--muted,#7c827d); pointer-events:none;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="search" name="q" id="search-page-input" value="<?= htmlspecialchars($query) ?>" class="input-field" placeholder="Tìm kiếm theo từ khóa, #hashtag hoặc @tác_giả..." style="padding-left:38px; width:100%; border-radius:30px; font-size:15px;" autocomplete="off">
      </div>
      <button type="submit" class="action-btn action-btn-primary" style="padding:10px 22px; border-radius:30px; font-weight:600;">
        Tìm kiếm
      </button>
    </form>

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
      <div>
        <h2 style="margin:0; font-size:20px; font-weight:700;">
          <?php if ($query !== ''): ?>
            Kết quả tìm kiếm cho: <span class="text-primary" style="color:var(--green,#3e6652);">"<?= htmlspecialchars($query) ?>"</span>
          <?php else: ?>
            Danh sách bài viết lọc theo tiêu chí
          <?php endif; ?>
        </h2>
        <span class="text-muted" style="font-size:13px;" id="search-header-stats">Tìm thấy <?= $total ?> bài viết và <?= count($users) ?> tài khoản</span>
      </div>

      <!-- Tabs: Tất cả, Bài viết, Tài khoản -->
      <div class="search-tabs" style="display:flex; gap:6px;">
        <a href="<?= searchUrl(['tab' => 'all', 'page' => 1]) ?>" class="search-tab-btn <?= $tab === 'all' ? 'active' : '' ?>">Tất cả</a>
        <a href="<?= searchUrl(['tab' => 'posts', 'page' => 1]) ?>" class="search-tab-btn <?= $tab === 'posts' ? 'active' : '' ?>" id="search-tab-posts-btn">Bài viết (<?= $total ?>)</a>
        <a href="<?= searchUrl(['tab' => 'users', 'page' => 1]) ?>" class="search-tab-btn <?= $tab === 'users' ? 'active' : '' ?>">Tài khoản (<?= count($users) ?>)</a>
      </div>
    </div>

    <!-- Thanh bộ lọc nâng cao: Loại bài, Hình ảnh, Sắp xếp & Đặt lại -->
    <?php if ($tab === 'all' || $tab === 'posts'): ?>
      <div class="search-filters-bar" id="search-filters-bar">
        <div style="display:flex; align-items:center; flex-wrap:wrap; gap:16px;">
          <!-- 1. Bộ lọc Loại bài -->
          <div class="filter-group">
            <span class="filter-label">Loại bài:</span>
            <div class="filter-chips-group" data-filter-name="type">
              <a href="<?= searchUrl(['type' => 'all', 'page' => 1]) ?>" data-value="all" class="filter-chip <?= $type === 'all' ? 'active' : '' ?>">Tất cả loại</a>
              <a href="<?= searchUrl(['type' => 'blog', 'page' => 1]) ?>" data-value="blog" class="filter-chip <?= $type === 'blog' ? 'active' : '' ?>">Bài viết (Blog)</a>
              <a href="<?= searchUrl(['type' => 'news', 'page' => 1]) ?>" data-value="news" class="filter-chip <?= $type === 'news' ? 'active' : '' ?>">Tin tức (News)</a>
            </div>
          </div>

          <!-- 2. Bộ lọc Hình ảnh -->
          <div class="filter-group">
            <span class="filter-label">Hình ảnh:</span>
            <div class="filter-chips-group" data-filter-name="has_image">
              <a href="<?= searchUrl(['has_image' => 'all', 'page' => 1]) ?>" data-value="all" class="filter-chip <?= $hasImage === 'all' ? 'active' : '' ?>">Tất cả</a>
              <a href="<?= searchUrl(['has_image' => '1', 'page' => 1]) ?>" data-value="1" class="filter-chip <?= $hasImage === '1' ? 'active' : '' ?>">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1px;margin-right:3px;"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>Có hình ảnh
              </a>
              <a href="<?= searchUrl(['has_image' => '0', 'page' => 1]) ?>" data-value="0" class="filter-chip <?= $hasImage === '0' ? 'active' : '' ?>">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1px;margin-right:3px;"><line x1="2" y1="2" x2="22" y2="22"/><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/></svg>Không có hình ảnh
              </a>
            </div>
          </div>
        </div>

        <div style="display:flex; align-items:center; flex-wrap:wrap; gap:12px;">
          <!-- 3. Sắp xếp -->
          <div class="filter-group">
            <span class="filter-label">Sắp xếp:</span>
            <div class="filter-chips-group" data-filter-name="sort">
              <a href="<?= searchUrl(['sort' => 'latest', 'page' => 1]) ?>" data-value="latest" class="filter-chip <?= $sort === 'latest' ? 'active' : '' ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:3px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>Mới nhất
              </a>
              <a href="<?= searchUrl(['sort' => 'popular', 'page' => 1]) ?>" data-value="popular" class="filter-chip <?= $sort === 'popular' ? 'active' : '' ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:3px;"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path></svg>Phổ biến nhất
              </a>
            </div>
          </div>

          <!-- 4. Nút Đặt lại bộ lọc -->
          <a href="<?= searchUrl(['type' => 'all', 'has_image' => 'all', 'sort' => 'latest', 'page' => 1]) ?>" id="search-filter-reset-btn" class="search-filter-reset-btn" title="Đặt lại toàn bộ bộ lọc về mặc định">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
            <span>Đặt lại</span>
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if (str_starts_with($query, '@')): ?>
    <div style="margin-bottom:18px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
      <span style="font-size:13px; color:#555e58;">Đang lọc bài viết từ tác giả:</span>
      <span style="display:inline-flex; align-items:center; gap:6px; background:#e8f4ed; color:#23573c; padding:4px 12px; border-radius:16px; font-weight:600; font-size:13px; border:1px solid #cbe5d5;">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;"><circle cx="12" cy="12" r="4"></circle><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"></path></svg><?= htmlspecialchars(ltrim($query, '@')) ?>
        <a href="<?= searchUrl(['q' => '', 'page' => 1]) ?>" style="color:#23573c; margin-left:4px; text-decoration:none; font-weight:700;" title="Xóa bộ lọc tác giả">&times;</a>
      </span>
    </div>
  <?php endif; ?>

  <!-- Danh sách tác giả khớp từ khóa (Hiển thị nổi bật phía trên bài viết) -->
  <?php if (!empty($users) && ($tab === 'all' || $tab === 'users')): ?>
    <section class="search-authors-section mb-4" style="margin-bottom:28px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
        <h3 style="font-size:16px; font-weight:700; color:var(--text,#1e2621); margin:0; display:flex; align-items:center; gap:8px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--green,#2d6347)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          <span>Tác giả khớp từ khóa (<?= count($users) ?>)</span>
        </h3>
        <?php if ($tab === 'all' && count($users) > 3): ?>
          <a href="<?= searchUrl(['tab' => 'users', 'page' => 1]) ?>" style="font-size:13px; color:var(--green,#2d6347); text-decoration:none; font-weight:600;">
            Xem tất cả tài khoản &rarr;
          </a>
        <?php endif; ?>
      </div>

      <div class="search-authors-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:14px;">
        <?php 
        $displayUsers = ($tab === 'all') ? array_slice($users, 0, 6) : $users;
        foreach ($displayUsers as $u): 
        ?>
          <div class="search-author-card" style="background:#ffffff; border:1px solid #e1e8e2; border-radius:14px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,0.03); display:flex; flex-direction:column; justify-content:space-between; transition:all 0.2s ease;">
            <div style="display:flex; align-items:center; gap:14px; margin-bottom:14px;">
              <a href="<?= APP_URL ?>/profile.html?id=<?= $u['id'] ?>" style="flex-shrink:0;">
                <img src="<?= appAvatarSrc($u['avatar'] ?? null) ?>" alt="<?= htmlspecialchars((string)$u['full_name']) ?>" style="width:52px; height:52px; border-radius:50%; object-fit:cover; border:2px solid #e2ede5;">
              </a>
              <div style="min-width:0; flex:1;">
                <a href="<?= APP_URL ?>/profile.html?id=<?= $u['id'] ?>" style="text-decoration:none; color:inherit;">
                  <strong style="display:block; font-size:15px; font-weight:700; color:#1e2621; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?= htmlspecialchars((string)$u['full_name']) ?>
                    <?php if (!empty($u['is_verified'])): ?>
                      <span style="display:inline-block; vertical-align:-2px; margin-left:4px;" title="Tài khoản đã xác minh">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="#1d9bf0"></circle><polyline points="8 12 11 15 16 9" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"></polyline></svg>
                      </span>
                    <?php endif; ?>
                  </strong>
                </a>
                <div style="font-size:12.5px; color:var(--muted,#7c827d); margin-top:2px;">
                  @<?= htmlspecialchars((string)$u['username']) ?>
                </div>
                <div style="display:flex; align-items:center; gap:10px; margin-top:6px; font-size:12px; color:#555e58;">
                  <span title="Điểm uy tín" style="display:inline-flex; align-items:center; gap:3px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    <span><?= number_format((int)($u['reputation_score'] ?? 0)) ?></span>
                  </span>
                  <span title="Số bài viết an toàn" style="display:inline-flex; align-items:center; gap:3px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--green,#2d6347)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                    <span><?= number_format((int)($u['total_posts'] ?? 0)) ?> bài viết</span>
                  </span>
                </div>
              </div>
            </div>

            <!-- Nút hành động: Xem hồ sơ hoặc Lọc bài viết của tác giả -->
            <div style="display:flex; gap:8px; border-top:1px solid #f0f3ef; padding-top:12px;">
              <a href="<?= APP_URL ?>/profile.html?id=<?= $u['id'] ?>" class="action-btn" style="flex:1; display:inline-flex; align-items:center; justify-content:center; gap:5px; padding:7px 10px; font-size:12.5px; border-radius:8px; text-decoration:none; background:#f4f6f4; color:#2e3630; border:1px solid #dce2dc; font-weight:600;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span>Hồ sơ</span>
              </a>
              <a href="<?= searchUrl(['q' => '@' . $u['username'], 'tab' => 'posts', 'page' => 1]) ?>" class="action-btn action-btn-primary" style="flex:1; display:inline-flex; align-items:center; justify-content:center; gap:5px; padding:7px 10px; font-size:12.5px; border-radius:8px; text-decoration:none; font-weight:600;" title="Lọc bài viết của @<?= htmlspecialchars((string)$u['username']) ?>">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                <span>Lọc bài</span>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- Danh sách bài viết tìm thấy -->
  <section class="search-posts-section" id="search-posts-section" <?= (empty($posts) && ($tab === 'all' || $tab === 'posts')) ? 'style="display:none;"' : '' ?>>
    <h3 class="mb-3" style="font-size:16px; font-weight:700; color:var(--text,#1e2621);" id="search-posts-headline">
      Bài viết (<span id="search-posts-count"><?= $total ?></span>)
    </h3>
    <div class="feed-posts" id="search-posts-container">
      <?php if (!empty($posts)): ?>
        <?php foreach ($posts as $post): ?>
          <?php require __DIR__ . '/../components/post_card.php'; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Phân trang bài viết tìm kiếm -->
    <div id="search-pagination-container">
      <?php if ($lastPage > 1): ?>
        <nav class="search-pagination" style="display:flex; justify-content:center; align-items:center; gap:8px; margin:36px 0;">
          <?php if ($page > 1): ?>
            <a href="<?= searchUrl(['page' => $page - 1]) ?>" data-page="<?= $page - 1 ?>" class="action-btn search-page-link" style="display:inline-flex; align-items:center; gap:4px; padding:8px 14px; font-size:13px;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
              <span>Trang trước</span>
            </a>
          <?php endif; ?>

          <span style="font-size:13px; color:var(--muted,#7c827d);" id="search-page-info">
            Trang <?= $page ?> / <?= $lastPage ?> (Tổng <?= $total ?> bài viết)
          </span>

          <?php if ($page < $lastPage): ?>
            <a href="<?= searchUrl(['page' => $page + 1]) ?>" data-page="<?= $page + 1 ?>" class="action-btn search-page-link" style="display:inline-flex; align-items:center; gap:4px; padding:8px 14px; font-size:13px;">
              <span>Trang sau</span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
  </section>

  <!-- Trạng thái trống -->
  <div id="search-empty-state" class="text-center py-5 text-muted" style="text-align:center; padding:60px 0; color:var(--muted,#7c827d); <?= (!empty($users) || !empty($posts)) ? 'display:none;' : '' ?>">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="opacity:0.35;margin-bottom:12px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    <h3 style="margin:12px 0 6px; font-size:18px; font-weight:600;">Không tìm thấy kết quả nào</h3>
    <p style="margin:0; font-size:14px;">Thử tìm kiếm với từ khóa khác, bớt các bộ lọc hoặc kiểm tra lại chính tả.</p>
  </div>
</div>
