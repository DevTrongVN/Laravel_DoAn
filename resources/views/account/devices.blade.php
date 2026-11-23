<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f6f5f1">
  <title>Tài khoản trên thiết bị · Góc Nhỏ</title>
  <link rel="stylesheet" href="{{ asset('assets/css/account-devices.css') }}">
  <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
  <main class="device-page">
    <header class="page-header">
      <a class="brand" href="{{ url('/') }}"><span class="brand-mark">g.</span> góc nhỏ</a>
      <a class="back-link" href="{{ url('/account') }}?mode=login">Đăng nhập tài khoản khác</a>
    </header>

    <section class="intro">
      <span class="eyebrow">THIẾT BỊ NÀY</span>
      <h1>Tài khoản của bạn</h1>
      <p>Chọn tài khoản đã từng đăng nhập trên trình duyệt này để tiếp tục. Vì an toàn, bạn sẽ cần nhập lại mật khẩu.</p>
    </section>

    <div id="device-account-list" class="account-grid" aria-live="polite"></div>
    <p class="privacy-note">Danh sách này chỉ được lưu trên trình duyệt hiện tại. Xóa ở đây không xóa tài khoản trên máy chủ.</p>
  </main>

  <div class="modal-backdrop" id="password-modal" hidden>
    <section class="password-dialog" role="dialog" aria-modal="true" aria-labelledby="dialog-title">
      <button class="close-button" id="close-password-modal" type="button" aria-label="Đóng">×</button>
      <img class="dialog-avatar" id="dialog-avatar" src="" alt="">
      <span class="eyebrow">ĐĂNG NHẬP LẠI</span>
      <h2 id="dialog-title">Nhập mật khẩu</h2>
      <p class="dialog-username" id="dialog-username"></p>
      <form id="device-login-form">
        @csrf
        <label for="device-password">Mật khẩu</label>
        <div class="password-wrap">
          <input id="device-password" name="password" type="password" autocomplete="current-password" required>
          <button id="toggle-password" type="button" aria-label="Hiện mật khẩu">Hiện</button>
        </div>
        <p id="login-message" class="form-message" role="status"></p>
        <button id="login-submit" class="primary-button" type="submit">Đăng nhập</button>
      </form>
    </section>
  </div>

  <script>window.APP_URL = "{{ url('/') }}";</script>
  <script src="{{ asset('assets/js/account-memory.js') }}"></script>
  <script src="{{ asset('assets/js/csrf.js') }}?v=20261118-laravel1"></script>
  <script src="{{ asset('assets/js/account-devices.js') }}?v=20261118-laravel1"></script>
</body>
</html>
