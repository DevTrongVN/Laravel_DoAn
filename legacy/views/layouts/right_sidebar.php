<aside class="right-sidebar editorial-aside">
  <!-- Widget Top Tin tức HOT -->
  <?php require __DIR__ . '/../components/widget_top_news.php'; ?>

  <!-- Widget Top Hashtags HOT -->
  <section class="sidebar-card tags-card">
    <div class="sidebar-title">
      <span class="section-kicker">Theo chủ đề</span>
      Từ khóa nổi bật
    </div>
    <div id="widget-top-hashtags" class="hashtags-cloud">
      <?php if (!empty($topHashtags)): ?>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
          <?php foreach ($topHashtags as $tag): ?>
            <a href="<?= APP_URL ?>/?hashtag=<?= urlencode((string)$tag['name']) ?>" class="post-hashtag-pill-widget" onclick="if(typeof filterHashtag === 'function'){event.preventDefault(); filterHashtag('<?= htmlspecialchars((string)$tag['name'], ENT_QUOTES) ?>');}">
              #<?= htmlspecialchars((string)$tag['name']) ?> <small style="color:#7c827d;font-weight:normal;">(<?= (int)$tag['usage_count'] ?>)</small>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <span class="text-muted font-size-sm">Đang cập nhật chủ đề...</span>
      <?php endif; ?>
    </div>
  </section>

  <!-- About Widget -->
  <?php
    $aboutAuthor = null;
    try {
        if (Auth::check()) {
            $aboutAuthor = Auth::user();
        } else {
            $aboutAuthor = (new User())->findPrimaryAdmin();
        }
    } catch (Throwable $e) {}
    $aboutFullName = !empty($aboutAuthor['full_name']) ? (string)$aboutAuthor['full_name'] : 'RobloxAdmin';
    $aboutBio = !empty($aboutAuthor['bio']) 
        ? (string)$aboutAuthor['bio'] 
        : (($aboutAuthor['role'] ?? '') === 'admin' 
            ? 'Quản trị viên hệ thống Góc Nhỏ · Đam mê kiến trúc phần mềm, AI & công nghệ mã nguồn mở.' 
            : 'Thành viên Góc Nhỏ · Yêu thích chia sẻ kiến thức, viết lách và kết nối cộng đồng.');
    $aboutAvatar = appAvatarSrc((string)($aboutAuthor['avatar'] ?? '/assets/images/avatars/user-demo.svg'));
    $aboutAuthorId = (int)($aboutAuthor['id'] ?? 1);
  ?>
  <section class="about-card" id="about">
    <span class="about-overline">Người viết blog</span>
    <div class="about-avatar">
      <img class="about-author-avatar" src="<?= $aboutAvatar ?>" alt="Chân dung <?= htmlspecialchars($aboutFullName) ?>">
    </div>
    <h2 class="about-title">Xin chào, mình là <?= htmlspecialchars($aboutFullName) ?>.</h2>
    <p class="about-bio"><?= htmlspecialchars($aboutBio) ?></p>
    <a href="<?= APP_URL ?>/profile.html?id=<?= $aboutAuthorId ?>" class="text-link about-link">Đọc thêm về mình <i class="fa-solid fa-arrow-right"></i></a>
    <div class="about-signature"><span class="about-author-name"><?= htmlspecialchars($aboutFullName) ?></span> <span class="about-sig-star"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block;vertical-align:baseline;margin-left:3px;" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span></div>
  </section>

  <!-- Newsletter note / Editorial CTA note -->
  <div class="newsletter-note">
    <svg class="newsletter-icon-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
    <strong>Thích những câu chuyện này?</strong>
    <span>Ghé lại đây mỗi ngày để đọc thêm những chia sẻ mới hoặc tự tay viết nên câu chuyện của riêng bạn.</span>
    <button type="button" id="btn-sidebar-new-post" class="newsletter-cta-btn" data-action="write-post" onclick="scrollToPostComposer(event)" style="position:relative; z-index:5; cursor:pointer; pointer-events:auto;">
      Viết bài mới <span class="cta-arrow" aria-hidden="true">&rarr;</span>
    </button>
  </div>
</aside>
