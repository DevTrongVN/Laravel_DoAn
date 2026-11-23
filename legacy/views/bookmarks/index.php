<?php
/** @var array $posts */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var bool $hasMore */
?>
<div class="feed-intro">
  <div>
    <span class="section-kicker">Bộ sưu tập cá nhân</span>
    <h2>Bài viết đã lưu <span class="badge badge-news" id="bookmark-count-badge"><?= (int)($total ?? count($posts)) ?></span></h2>
  </div>
</div>

<!-- Container hiển thị danh sách bài viết đã lưu -->
<div id="bookmark-feed-container" class="feed-posts">
  <?php if (!empty($posts)): ?>
    <?php foreach ($posts as $post): ?>
      <?php require __DIR__ . '/../components/post_card.php'; ?>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="post-card empty-state-card" id="empty-bookmarks-msg">
      <div style="text-align: center; padding: 48px 20px;">
        <div style="margin-bottom: 16px; display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: #e6fffa; color: #0d9488;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        </div>
        <h3 style="margin-bottom: 8px; font-size: 20px; font-weight: 700; color: #1b4d3e;">Chưa có bài viết nào được lưu</h3>
        <p class="text-muted" style="max-width: 440px; margin: 0 auto 24px; line-height: 1.6; font-size: 14.5px;">
          Bạn có thể lưu bất kỳ bài viết hoặc tin tức nào để đọc lại sau này bằng cách nhấn vào nút <strong>Lưu</strong> trên mỗi thẻ bài viết.
        </p>
        <a href="<?= defined('APP_URL') ? APP_URL . '/' : 'index.html' ?>" class="action-btn action-btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; font-weight: 600;">
          <svg class="btn-icon-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
          <span>Khám phá bài viết ngay</span>
        </a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($hasMore)): ?>
  <div class="text-center my-4" id="bookmark-load-more-container">
    <button class="action-btn" id="btn-load-more-bookmarks" onclick="loadMoreBookmarks()" style="display: inline-flex; align-items: center; gap: 8px;">
      <svg class="btn-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
      <span>Tải thêm bài viết đã lưu</span>
    </button>
  </div>
<?php endif; ?>

<script>
  window.isBookmarkPage = true;
  let currentBookmarkPage = <?= (int)($page ?? 1) ?>;
  const bookmarkPerPage = <?= (int)($perPage ?? 10) ?>;

  function checkEmptyBookmarks() {
    const container = document.getElementById('bookmark-feed-container');
    const remainingCards = container ? container.querySelectorAll('.post-card:not(.empty-state-card)') : [];
    const badge = document.getElementById('bookmark-count-badge');
    if (badge) badge.textContent = String(remainingCards.length);

    if (container && remainingCards.length === 0) {
      const appBase = String(window.APP_URL || '').replace(/\/$/, '');
      const exploreUrl = appBase ? `${appBase}/` : 'index.html';
      container.innerHTML = `
        <div class="post-card empty-state-card" id="empty-bookmarks-msg">
          <div style="text-align: center; padding: 48px 20px;">
            <div style="margin-bottom: 16px; display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: #e6fffa; color: #0d9488;">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
            </div>
            <h3 style="margin-bottom: 8px; font-size: 20px; font-weight: 700; color: #1b4d3e;">Chưa có bài viết nào được lưu</h3>
            <p class="text-muted" style="max-width: 440px; margin: 0 auto 24px; line-height: 1.6; font-size: 14.5px;">
              Bạn có thể lưu bất kỳ bài viết hoặc tin tức nào để đọc lại sau này bằng cách nhấn vào nút <strong>Lưu</strong> trên mỗi thẻ bài viết.
            </p>
            <a href="${exploreUrl}" class="action-btn action-btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; font-weight: 600;">
              <svg class="btn-icon-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
              <span>Khám phá bài viết ngay</span>
            </a>
          </div>
        </div>
      `;
      const loadMore = document.getElementById('bookmark-load-more-container');
      if (loadMore) loadMore.style.display = 'none';
    }
  }

  async function loadMoreBookmarks() {
    const btn = document.getElementById('btn-load-more-bookmarks');
    if (btn) btn.disabled = true;
    try {
      currentBookmarkPage++;
      const res = await apiRequest(`api/bookmarks?page=${currentBookmarkPage}&per_page=${bookmarkPerPage}`);
      if (res && res.data && res.data.length > 0) {
        const container = document.getElementById('bookmark-feed-container');
        if (container && typeof renderPostsHtml === 'function') {
          const newHtml = renderPostsHtml(res.data);
          container.insertAdjacentHTML('beforeend', newHtml);
        }
      }
      if (!res.pagination || !res.pagination.has_more) {
        const loadMore = document.getElementById('bookmark-load-more-container');
        if (loadMore) loadMore.style.display = 'none';
      }
    } catch (e) {
      console.error('Lỗi khi tải thêm bookmark:', e);
    } finally {
      if (btn) btn.disabled = false;
    }
  }
</script>
