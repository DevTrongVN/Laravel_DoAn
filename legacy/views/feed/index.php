<?php
/** @var array $posts */
/** @var string $activeTab */
?>
<div class="feed-intro">
  <div>
    <span class="section-kicker">Từ trang viết</span>
    <h2>Mới trên blog</h2>
  </div>
  <div class="feed-tabs-cluster">
    <button class="feed-filter <?= ($activeTab ?? '') === 'home' ? 'active' : '' ?>" id="nav-home" onclick="filterNavFeed('home')">Tất cả</button>
    <button class="feed-filter <?= ($activeTab ?? '') === 'for_you' ? 'active' : '' ?>" id="nav-for_you" onclick="filterNavFeed('for_you')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:4px;" aria-hidden="true">
        <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"></path>
      </svg>Dành cho bạn
    </button>
    <button class="feed-filter <?= ($activeTab ?? '') === 'news' ? 'active' : '' ?>" id="nav-news" onclick="filterNavFeed('news')">Tin tức</button>
    <button class="feed-filter <?= ($activeTab ?? '') === 'following' ? 'active' : '' ?>" id="nav-following" onclick="filterNavFeed('following')">Đang theo dõi</button>

    <!-- Bộ lọc nhanh Trang chủ (Loại bài & Hình ảnh) -->
    <div class="feed-quick-filters" id="feed-quick-filters">
      <div class="feed-filter-select-wrapper" title="Lọc theo loại bài viết">
        <svg class="select-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 8h10"/><path d="M7 12h10"/><path d="M7 16h6"/></svg>
        <select id="feed-filter-type" class="feed-filter-select" aria-label="Loại bài viết" onchange="handleHomeFilterChange()">
          <option value="all">Tất cả loại</option>
          <option value="blog">Bài viết (Blog)</option>
          <option value="news">Tin tức (News)</option>
        </select>
        <svg class="select-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </div>

      <div class="feed-filter-select-wrapper" title="Lọc bài có hình ảnh">
        <svg class="select-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
        <select id="feed-filter-has-image" class="feed-filter-select" aria-label="Hình ảnh kèm theo" onchange="handleHomeFilterChange()">
          <option value="all">Tất cả</option>
          <option value="1">Có hình ảnh</option>
          <option value="0">Không có hình ảnh</option>
        </select>
        <svg class="select-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
    </div>
  </div>
  <button type="button" class="btn-feed-refresh" id="btn-feed-refresh" onclick="refreshFeedNew()" title="Xáo trộn & hiển thị các bài viết mới khác">
    <svg class="refresh-icon-svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
    <span>Làm mới bảng tin (Đổi bài mới)</span>
  </button>
</div>

<!-- Khung Tạo bài viết nhanh -->
<section class="post-card composer-card" id="create-post-card" aria-label="Tạo bài viết">
  <div class="composer-heading">
    <span class="composer-avatar" aria-hidden="true">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
    </span>
    <div>
      <strong>Ghi lại một điều bạn vừa nghĩ</strong>
      <span>Bài viết mới sẽ xuất hiện trên trang chủ.</span>
    </div>
  </div>
  <div class="flex-col gap-sm">
    <input type="text" id="post-title-input" class="input-field" placeholder="Tiêu đề bài viết...">
    <textarea id="post-editor-input" class="input-field" rows="3" placeholder="Hôm nay bạn muốn kể câu chuyện gì?"></textarea>
    <div class="upload-row">
      <label for="post-file-input" class="btn-upload-label"><i class="fa-regular fa-image"></i> Thêm ảnh</label>
      <input type="file" id="post-file-input" accept="image/*" class="file-input-hidden">
      <button type="button" class="btn-ai-assist" onclick="openAiWriterModal()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--green,#2d6347); vertical-align:-2px; margin-right:4px;" aria-hidden="true"><path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.2 1.2 0 0 0 1.72 0L21.64 5.36a1.2 1.2 0 0 0 0-1.72Z"/><path d="m14 7 3 3"/><path d="M5 6v4"/><path d="M19 14v4"/><path d="M10 2v2"/><path d="M7 8H3"/><path d="M21 16h-4"/></svg> AI Sửa văn phong
      </button>
    </div>
    <div id="image-preview-container" class="image-preview-container" style="display:none;">
      <img id="image-preview" src="" class="image-preview-img" alt="Ảnh xem trước">
      <button onclick="removeSelectedImage()" class="btn-remove-image" aria-label="Xóa ảnh">×</button>
    </div>
    <input type="text" id="post-tags-input" class="input-field" placeholder="Chủ đề (ví dụ: congnghe, ghi_chep)">
  </div>
  <div class="create-post-actions">
    <select id="post-type-select" class="input-field select-auto-width">
      <option value="blog">Bài viết (Blog)</option>
      <option value="news">Tin tức (News)</option>
    </select>
    <button class="action-btn action-btn-primary" onclick="createNewPost()">
      <i class="fa-solid fa-paper-plane"></i> Đăng bài
    </button>
  </div>
</section>

<!-- Danh sách bài viết Feed -->
<div id="feed-container" class="feed-posts">
  <?php if (!empty($posts)): ?>
    <?php foreach ($posts as $post): ?>
      <?php require __DIR__ . '/../components/post_card.php'; ?>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="text-center text-muted py-4">Chưa có bài viết nào trong mục này.</p>
  <?php endif; ?>
</div>

<!-- Khung phân trang / Tải thêm & Infinite Scroll -->
<div id="feed-load-more-wrapper" class="feed-load-more-wrapper">
  <button type="button" class="btn-load-more-feed" id="btn-load-more-feed" onclick="loadMoreFeedPosts()">
    <svg class="load-more-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
    <span class="btn-text">Tải thêm bài viết</span>
  </button>
  <div id="feed-loading-spinner" class="feed-loading-spinner" style="display: none;">
    <svg class="spinner-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
    <span>Đang tải thêm bài viết thú vị...</span>
  </div>
  <p id="feed-end-message" class="feed-end-message" style="display: none;">
    🎉 Bạn đã xem hết các bài viết trong mục này! Nhấn <a href="javascript:void(0)" onclick="refreshFeedNew()">Làm mới bảng tin</a> để cập nhật bài viết mới.
  </p>
</div>
