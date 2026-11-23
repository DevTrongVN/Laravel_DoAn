<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars((string)($pageTitle ?? 'Bảng Quản Trị Hệ Thống - Góc Nhỏ')) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/components.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">
  <header class="navbar admin-navbar">
    <div class="navbar-left">
      <h2 class="navbar-brand"><i class="fa-solid fa-gear"></i> Admin Control Panel</h2>
    </div>
    <div class="navbar-right">
      <a href="<?= APP_URL ?>/" class="nav-btn">← Về trang chủ</a>
    </div>
  </header>

  <div class="admin-container">
    <aside class="admin-sidebar">
      <nav class="admin-nav">
        <a href="<?= APP_URL ?>/admin#tab-users" class="admin-nav-item is-active"><i class="fa-solid fa-user-shield"></i> Người dùng & Tích xanh</a>
        <a href="<?= APP_URL ?>/admin#tab-posts-comments" class="admin-nav-item"><i class="fa-solid fa-newspaper"></i> Bài viết & Bình luận</a>
        <a href="<?= APP_URL ?>/admin#tab-post-reports" class="admin-nav-item"><i class="fa-solid fa-flag"></i> Báo cáo Bài viết</a>
        <a href="<?= APP_URL ?>/admin#tab-comment-reports" class="admin-nav-item"><i class="fa-solid fa-comment-slash"></i> Báo cáo Bình luận</a>
        <a href="<?= APP_URL ?>/admin#tab-news-suggestions" class="admin-nav-item"><i class="fa-solid fa-wand-magic-sparkles"></i> Đề xuất Tin tức</a>
        <a href="<?= APP_URL ?>/admin#tab-ai-moderation" class="admin-nav-item"><i class="fa-solid fa-robot"></i> Kiểm duyệt nội dung AI</a>
      </nav>
    </aside>

    <main class="admin-content">
      <?= $content ?? '' ?>
    </main>
  </div>

  <script src="<?= APP_URL ?>/assets/js/csrf.js?v=20261114-csrf1"></script>
  <script src="<?= APP_URL ?>/assets/js/admin.js?v=20261114-admin-v3"></script>
</body>
</html>
