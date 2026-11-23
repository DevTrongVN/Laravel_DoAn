<?php
/** @var array $post */
$postId = (int)($post['id'] ?? 0);
$isRestricted = !empty($post['is_restricted']);
$moderationStatus = (string)($post['moderation_status'] ?? 'safe');
$isModerated = ($moderationStatus !== 'safe');
$isOwner = !empty($post['is_owner']);
$isAdmin = !empty($post['is_admin']);
$blockReason = htmlspecialchars((string)($post['block_reason'] ?? 'Bài viết vi phạm tiêu chuẩn cộng đồng hoặc đang được tạm khóa để kiểm duyệt.'), ENT_QUOTES, 'UTF-8');
$latestAppeal = $post['latest_appeal'] ?? null;

$title = htmlspecialchars((string)($post['title'] ?? ''), ENT_QUOTES, 'UTF-8');
$author = htmlspecialchars((string)($post['full_name'] ?? $post['username'] ?? ''), ENT_QUOTES, 'UTF-8');
$avatar = appAvatarSrc($post['avatar'] ?? null);
$comments = $post['comments'] ?? [];
?>

<?php if ($isRestricted): ?>
  <!-- Giao diện bài viết bị khóa đối với người ngoài / khách vãng lai -->
  <div class="post-restricted-card">
    <div class="post-restricted-icon">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path>
        <line x1="12" y1="9" x2="12" y2="13"></line>
        <line x1="12" y1="17" x2="12.01" y2="17"></line>
      </svg>
    </div>
    <h3 class="post-restricted-title">Bài viết không khả dụng</h3>
    <p class="post-restricted-desc">
      Bài viết này đã bị tạm khóa do vi phạm Tiêu chuẩn cộng đồng hoặc đang trong quá trình kiểm duyệt nội dung.
    </p>
    <div class="post-restricted-actions">
      <a href="<?= APP_URL ?>/" class="btn-appeal-submit" style="text-decoration:none;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
          <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        <span>Về trang chủ</span>
      </a>
      <button type="button" class="btn-appeal-submit" style="background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; box-shadow:none;" onclick="history.back()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="19" y1="12" x2="5" y2="12"></line>
          <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        <span>Quay lại</span>
      </button>
    </div>
  </div>
<?php else: ?>
  <article class="post-card post-detail-card" id="post-detail" data-post-id="<?= $postId ?>">
    <?php if ($isModerated): ?>
      <!-- Khung cảnh báo vi phạm dành cho Tác giả & Quản trị viên (Editorial Theme) -->
      <section class="blocked-alert-box" aria-label="Cảnh báo kiểm duyệt">
        <div class="blocked-alert-header">
          <div class="blocked-alert-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path>
              <line x1="12" y1="9" x2="12" y2="13"></line>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
          </div>
          <div class="blocked-alert-body">
            <h4 class="blocked-alert-title">
              Bài viết này đã bị tạm khóa do vi phạm tiêu chuẩn cộng đồng
            </h4>

            <div class="blocked-reason-card">
              <div class="blocked-reason-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                <span>Lý do từ Ban Quản Trị</span>
              </div>
              <p class="blocked-reason-text"><?= $blockReason ?></p>
            </div>

            <p class="blocked-alert-note">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
              </svg>
              <span>Chỉ tác giả bài viết và Quản trị viên mới thấy thông tin này. Người dùng khác không thể thấy nội dung bài viết.</span>
            </p>

            <?php if ($isOwner): ?>
              <!-- Thẻ Form Khiếu nại cho Tác giả -->
              <div class="appeal-card">
                <?php if (!empty($latestAppeal) && ($latestAppeal['status'] ?? '') === 'pending'): ?>
                  <div class="appeal-pending-box">
                    <div class="appeal-pending-header">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-spin-slow" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                      </svg>
                      <span>Yêu cầu khiếu nại đang chờ Ban Quản Trị xem xét (gửi lúc <?= htmlspecialchars((string)$latestAppeal['created_at']) ?>)</span>
                    </div>
                    <div class="appeal-submitted-content">
                      <strong style="color:#1f2937; display:block; margin-bottom:4px;">Nội dung giải trình của bạn:</strong>
                      <?= nl2br(htmlspecialchars((string)$latestAppeal['reason'])) ?>
                    </div>
                  </div>
                <?php elseif (!empty($latestAppeal) && ($latestAppeal['status'] ?? '') === 'rejected'): ?>
                  <div class="appeal-rejected-box">
                    <div class="appeal-rejected-header">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                      </svg>
                      <span>Khiếu nại trước đó đã bị Ban Quản Trị từ chối <?= !empty($latestAppeal['reviewed_at']) ? '(lúc ' . htmlspecialchars((string)$latestAppeal['reviewed_at']) . ')' : '' ?></span>
                    </div>
                    <p class="appeal-rejected-reason">
                      <strong>Lý do từ chối:</strong> <?= htmlspecialchars((string)($latestAppeal['admin_response'] ?? 'Không có lý do cụ thể.')) ?>
                    </p>
                  </div>

                  <form id="post-appeal-form" onsubmit="handlePostAppealSubmit(event, <?= $postId ?>)">
                    <div class="appeal-card-header">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                      </svg>
                      <span>Gửi lại khiếu nại / lời giải trình mới</span>
                    </div>
                    <p class="appeal-card-desc">Bạn có thể bổ sung bằng chứng hoặc lời giải trình chi tiết hơn để Quản trị viên xem xét lại.</p>
                    <textarea id="appeal-reason" class="appeal-textarea" placeholder="Nhập lời giải trình hoặc thông tin bổ sung để Admin xem xét lại..." required></textarea>
                    <button type="submit" class="btn-appeal-submit">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                      </svg>
                      <span>Gửi khiếu nại tới Admin</span>
                    </button>
                  </form>
                <?php else: ?>
                  <form id="post-appeal-form" onsubmit="handlePostAppealSubmit(event, <?= $postId ?>)">
                    <div class="appeal-card-header">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                      </svg>
                      <span>Yêu cầu Ban Quản Trị xem xét lại</span>
                    </div>
                    <p class="appeal-card-desc">Nếu bạn cho rằng bài viết bị khóa do nhầm lẫn, hãy gửi lời giải trình rõ ràng tại đây.</p>
                    <textarea id="appeal-reason" class="appeal-textarea" placeholder="Nhập lời giải trình hoặc lý do bài viết không vi phạm tiêu chuẩn cộng đồng..." required></textarea>
                    <button type="submit" class="btn-appeal-submit">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                      </svg>
                      <span>Gửi khiếu nại tới Admin</span>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            <?php elseif ($isAdmin): ?>
              <div style="margin-top: 14px;">
                <a href="<?= APP_URL ?>/admin#post-reports" class="btn-appeal-submit" style="background:#dc2626; text-decoration:none;">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                  </svg>
                  <span>Mở Trang Quản Trị</span>
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <!-- Thanh điều hướng đầu bài viết (Quay lại / Trang chủ) -->
    <div class="post-detail-top-nav mb-3">
      <button type="button" class="btn-detail-back" onclick="if(window.history.length > 1){window.history.back();}else{window.location.href='<?= APP_URL ?>/';}">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        <span>Quay lại</span>
      </button>
      <a href="<?= APP_URL ?>/" class="btn-detail-home-link">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
        <span>Trang chủ</span>
      </a>
    </div>

    <header class="post-header mb-4">
      <a href="<?= APP_URL ?>/profile.html?id=<?= $post['user_id'] ?? 1 ?>" class="post-user-info">
        <img src="<?= $avatar ?>" class="post-avatar" alt="<?= $author ?>">
        <div>
          <strong class="post-author-name"><?= $author ?></strong>
          <div class="post-meta-sub" style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--text-muted, #64748b); margin-top:2px;">
            <span class="post-time"><?= htmlspecialchars((string)($post['created_at'] ?? '')) ?></span>
            <span aria-hidden="true">•</span>
            <span class="post-views-count" title="<?= number_format((int)($post['views_count'] ?? $post['views'] ?? 0)) ?> lượt xem" style="display:inline-flex; align-items:center; gap:4px;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
              <span><?= number_format((int)($post['views_count'] ?? $post['views'] ?? 0)) ?> lượt xem</span>
            </span>
          </div>
        </div>
      </a>
      <span class="badge badge-<?= $post['post_type'] ?? 'blog' ?>">
        <?= ($post['post_type'] ?? '') === 'news' ? 'Tin tức' : 'Blog' ?>
      </span>
    </header>

    <h1 class="post-detail-title"><?= $title ?></h1>

    <?php if (!empty($post['thumbnail'])): ?>
      <img src="<?= htmlspecialchars((string)$post['thumbnail']) ?>" class="post-detail-hero-img" alt="<?= $title ?>">
    <?php endif; ?>

    <div class="post-detail-content">
      <?= nl2br(htmlspecialchars((string)($post['content'] ?? ''))) ?>
    </div>

    <?php if (!$isModerated): ?>
      <?php
        $dId = (int)$post['id'];
        $dLikes = (int)($post['likes_count'] ?? 0);
        $dShares = (int)($post['shares_count'] ?? 0);
        $dIsBookmarked = !empty($post['is_bookmarked']);
        $dMyReaction = $post['my_reaction'] ?? null;
        $dReactionConfig = [
            'like'  => ['emoji' => '👍', 'label' => 'Đã thích'],
            'love'  => ['emoji' => '❤️', 'label' => 'Yêu thích'],
            'haha'  => ['emoji' => '😄', 'label' => 'Haha'],
            'sad'   => ['emoji' => '😢', 'label' => 'Buồn'],
            'angry' => ['emoji' => '😠', 'label' => 'Phẫn nộ'],
        ];
        $dActiveReaction = $dMyReaction && isset($dReactionConfig[$dMyReaction]) ? $dReactionConfig[$dMyReaction] : null;
        $dReactedClass = $dActiveReaction ? 'active is-reacted' : '';
        $dReactionAttr = $dMyReaction ? 'data-reaction="' . htmlspecialchars((string)$dMyReaction, ENT_QUOTES, 'UTF-8') . '"' : '';
      ?>
      <div class="post-interactive-stats post-detail-actions" id="post-detail-actions" aria-label="Tương tác bài viết">
        <div class="reaction-control" data-post-id="<?= $dId ?>"
             onmouseenter="handleReactionControlEnter(event)"
             onmouseleave="handleReactionControlLeave(event)"
             onpointerdown="startReactionPress(event)"
             onpointerup="endReactionPress(event)"
             onpointercancel="endReactionPress(event)">
          <button type="button" class="action-btn stat-btn reaction-trigger <?= $dReactedClass ?>" <?= $dReactionAttr ?> onclick="toggleLike(<?= $dId ?>, this)" aria-label="Cảm xúc bài viết">
            <span class="reaction-icon"><?= $dActiveReaction ? $dActiveReaction['emoji'] : '👍' ?></span>
            <span class="likes-count"><?= $dLikes ?></span>
            <span class="reaction-label"><?= $dActiveReaction ? $dActiveReaction['label'] : 'Thích' ?></span>
          </button>
          <div class="reaction-picker" role="group" aria-label="Chọn cảm xúc">
            <?php foreach ($dReactionConfig as $rKey => $rItem): ?>
              <button type="button" class="reaction-choice" title="<?= $rItem['label'] ?>" aria-label="<?= $rItem['label'] ?>" onclick="setPostReaction(<?= $dId ?>, '<?= $rKey ?>', event)"><?= $rItem['emoji'] ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <button type="button" class="action-btn stat-btn stat-btn-share" data-post-id="<?= $dId ?>" onclick="sharePost(<?= $dId ?>)" aria-label="Chia sẻ">
          <svg class="btn-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
          <span class="shares-count"><?= $dShares ?></span> Chia sẻ
        </button>
        <button type="button" class="action-btn action-btn-bookmark stat-btn stat-btn-bookmark <?= $dIsBookmarked ? 'bookmarked active' : '' ?>" data-post-id="<?= $dId ?>" title="<?= $dIsBookmarked ? 'Bỏ lưu bài viết' : 'Lưu bài viết' ?>" onclick="toggleBookmark(<?= $dId ?>, this)">
          <svg class="btn-icon-svg icon-bookmark-svg" width="15" height="15" viewBox="0 0 24 24" fill="<?= $dIsBookmarked ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
          <span class="bookmark-label"><?= $dIsBookmarked ? 'Đã lưu' : 'Lưu' ?></span>
        </button>
        <div class="stat-btn stat-btn-views" title="Lượt xem bài viết" style="cursor:default; pointer-events:none; opacity:0.85; margin-left:auto;">
          <svg class="btn-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
          <span><?= number_format((int)($post['views_count'] ?? $post['views'] ?? 0)) ?> lượt xem</span>
        </div>
      </div>
    <?php else: ?>
      <div class="post-paused-bar">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
        <span>Tính năng bình luận và tương tác cảm xúc đang tạm dừng do bài viết bị khóa.</span>
      </div>
    <?php endif; ?>

    <div class="post-detail-hashtags mt-4">
      <?php if (!empty($post['hashtags'])): ?>
        <?php foreach ($post['hashtags'] as $tag): ?>
          <a href="<?= APP_URL ?>/search?q=%23<?= urlencode((string)$tag) ?>" class="hashtag-pill">#<?= htmlspecialchars((string)$tag) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="post-sep-line my-4"></div>

    <!-- Phần bình luận -->
    <?php if (!$isModerated): ?>
      <?php require __DIR__ . '/../components/comment_section.php'; ?>
    <?php endif; ?>
  </article>

  <script>
  async function handlePostAppealSubmit(event, postId) {
    if (event) event.preventDefault();
    const textarea = document.getElementById('appeal-reason');
    if (!textarea) return;
    const reason = textarea.value.trim();
    if (!reason) {
      if (typeof showToast === 'function') showToast('Vui lòng nhập lý do / lời giải trình khiếu nại.', 'warning');
      else alert('Vui lòng nhập lý do / lời giải trình khiếu nại.');
      return;
    }
    const btn = event.target ? event.target.querySelector('button[type="submit"]') : null;
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<svg class="icon-spin-slow" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:6px;" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Đang gửi khiếu nại...';
    }
    try {
      const res = await apiRequest(`/api/posts/${postId}/appeal`, {
        method: 'POST',
        body: JSON.stringify({ reason })
      });
      if (res && res.error) throw new Error(res.error);
      if (typeof showToast === 'function') {
        showToast((res && res.message) ? res.message : 'Đã gửi khiếu nại thành công!', 'success');
      } else {
        alert((res && res.message) ? res.message : 'Đã gửi khiếu nại thành công!');
      }
      setTimeout(() => window.location.reload(), 1200);
    } catch (err) {
      if (typeof showToast === 'function') {
        showToast(err.message || 'Không thể gửi khiếu nại.', 'error');
      } else {
        alert(err.message || 'Không thể gửi khiếu nại.');
      }
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:6px;" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Gửi khiếu nại tới Admin';
      }
    }
  }
  </script>
<?php endif; ?>
