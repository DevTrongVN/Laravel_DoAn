<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#f6f5f1">
  <title><?= htmlspecialchars((string)($pageTitle ?? 'Góc Nhỏ — Blog cá nhân')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@400;500;600;700&family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap&subset=vietnamese" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css?v=20261117-ui-fix3">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/components.css?v=20261118-mention2">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/blog-theme.css?v=20261117-ui-fix3">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/animations.css?v=20261117-ui-fix3">
</head>
<body class="blog-home">
  <!-- Navbar chung -->
  <?php require __DIR__ . '/navbar.php'; ?>

  <!-- Layout 3 Cột chính -->
  <main class="page-shell" id="main-content">
    <div class="editorial-layout">
      <!-- Cột trái: Kênh đang theo dõi -->
      <?php require __DIR__ . '/left_sidebar.php'; ?>

      <!-- Cột giữa: Nội dung View chính -->
      <section class="main-feed" aria-label="Bảng tin">
        <?= $content ?? '' ?>
      </section>

      <!-- Cột phải: Top Tin tức HOT & Top Hashtags -->
      <?php require __DIR__ . '/right_sidebar.php'; ?>
    </div>
  </main>

  <!-- Popup Modals Container -->
  <?php require __DIR__ . '/../components/ai_writer_modal.php'; ?>
  <?php require __DIR__ . '/../components/ticket_modal.php'; ?>

  <!-- Scripts -->
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
  <script src="<?= APP_URL ?>/assets/js/csrf.js?v=20261114-csrf1"></script>
  <script src="<?= APP_URL ?>/assets/js/widget_pagination.js?v=20261117-ui-fix3"></script>
  <script src="<?= APP_URL ?>/assets/js/avatar-picker.js?v=20261109-avatar-picker1"></script>
  <script src="<?= APP_URL ?>/assets/js/mentions.js?v=20261118-mention2"></script>
  <script src="<?= APP_URL ?>/assets/js/app.js?v=20261118-mention2"></script>
  <script src="<?= APP_URL ?>/assets/js/ai_assistant.js?v=20261118-full1"></script>
  <script src="<?= APP_URL ?>/assets/js/feed_pagination.js"></script>
  <script src="<?= APP_URL ?>/assets/js/animations.js?v=20261114-btn-fix7"></script>
</body>
</html>
