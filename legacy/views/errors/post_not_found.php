<?php
declare(strict_types=1);
/** @var int $postId */
/** @var bool $isModal */
$appBaseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
?>
<div class="post-not-found-wrapper" style="width: 100%; max-width: 680px; margin: <?= !empty($isModal) ? '20px auto' : '24px auto 40px auto' ?>;">
  <div class="post-not-found-card" style="
    background: #ffffff;
    border: 1px solid var(--border, #e4e8e2);
    border-radius: 16px;
    padding: <?= !empty($isModal) ? '36px 20px' : '52px 28px' ?>;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
  ">
    <!-- Icon minh họa SVG sắc nét -->
    <div style="
      width: 76px;
      height: 76px;
      border-radius: 50%;
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: #166534;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 22px;
      box-shadow: 0 4px 14px rgba(22, 101, 52, 0.08);
    ">
      <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="9" y1="13" x2="15" y2="13"/>
      </svg>
    </div>

    <!-- Tiêu đề chính -->
    <h2 style="
      font-size: 22px;
      font-weight: 700;
      color: var(--text, #1e2621);
      margin: 0 0 12px 0;
      letter-spacing: -0.01em;
    ">
      Bài viết này không còn tồn tại
    </h2>

    <!-- Mô tả chi tiết -->
    <p style="
      font-size: 14.5px;
      color: var(--muted, #64748b);
      line-height: 1.6;
      max-width: 480px;
      margin: 0 auto 28px auto;
    ">
      Nội dung bạn đang tìm kiếm có thể đã bị tác giả gỡ bỏ, bị quản trị viên xóa do vi phạm tiêu chuẩn cộng đồng, hoặc đường dẫn không chính xác.
    </p>

    <!-- Nút điều hướng -->
    <div style="display: flex; justify-content: center; align-items: center; gap: 12px; flex-wrap: wrap;">
      <a href="<?= $appBaseUrl ?>/" class="action-btn action-btn-primary" style="
        background: var(--green, #2d6347);
        color: #ffffff;
        border: 1px solid var(--green, #2d6347);
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 8px rgba(45, 99, 71, 0.2);
        transition: all 0.2s ease;
      ">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="19" y1="12" x2="5" y2="12"></line>
          <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        <span>Quay về Trang chủ</span>
      </a>

      <a href="<?= $appBaseUrl ?>/notifications" class="action-btn" style="
        background: #f8fafc;
        color: #334155;
        border: 1px solid #cbd5e1;
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
      ">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span>Trung tâm thông báo</span>
      </a>
    </div>
  </div>
</div>
