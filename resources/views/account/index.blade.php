<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tài khoản · Góc Nhỏ</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@400;500;600;700&family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap&subset=vietnamese" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}?v=20261109-avatar">
  <link rel="stylesheet" href="{{ asset('assets/css/components.css') }}?v=20261114-post-exclamation2">
  <link rel="stylesheet" href="{{ asset('assets/css/blog-theme.css') }}?v=20261119-typefix1">
  <link rel="stylesheet" href="{{ asset('assets/css/auth-redesign.css') }}?v=20261119-whitebg1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <style>
    body.auth-page { background: #0f2a1c !important; margin: 0; padding: 0; min-height: 100vh; }
    .account-page-wrapper { display: none !important; }
    .account-card-box { display: none !important; }
  </style>
</head>
<body class="auth-page">

<div class="auth-split-page" id="auth-split-page">

  <!-- ==============================================
       BÊN TAY TRÁI: DÒNG CHỮ NGHIÊNG PHÔNG ĐẸP & BRANDING
       ============================================== -->
  <aside class="auth-left-panel">
    <a class="auth-brand" href="{{ url('/') }}" title="Trở về trang chủ Góc Nhỏ">
      <div class="auth-brand-logo">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" fill="none" width="24" height="24">
          <circle cx="48" cy="48" r="46" fill="rgba(255,255,255,0.15)"/>
          <path d="M23 37.5c9-3.8 17.2-2.5 25 3.8 7.8-6.3 16-7.6 25-3.8v29c-9-3.8-17.2-2.5-25 3.8-7.8-6.3-16-7.6-25-3.8v-29Z" fill="#fff" fill-opacity=".95"/>
          <path d="M48 41.5v28.8M29.5 43.5c5.1-1.3 9.8-.4 14.1 2.5M29.5 51c5.1-1.3 9.8-.4 14.1 2.5m22.9-10c-5.1-1.3-9.8-.4-14.1 2.5m14.1 5c-5.1-1.3-9.8-.4-14.1 2.5" stroke="#5cc4a1" stroke-width="2.2" stroke-linecap="round"/>
        </svg>
      </div>
      <div class="auth-brand-name">góc nhỏ <span>— blog</span></div>
    </a>

    <!-- Center: Dòng chữ nghiêng phông đẹp nghệ thuật -->
    <div class="auth-left-center">
      <div class="auth-left-quote">
        <blockquote>
          “Mỗi câu chuyện bạn chia sẻ đều có thể thắp sáng một ý nghĩ trong lòng người khác.”
        </blockquote>
        <cite>— Góc Nhỏ, nơi lưu giữ những câu chuyện thật</cite>
        <p class="auth-left-desc">
          Không gian sẻ chia những bài viết, cảm xúc và góc nhìn đời thường chân thật từ cộng đồng người viết.
        </p>
      </div>

      <div class="auth-left-stats">
        <div class="auth-stat-item">
          <div class="auth-stat-number">2,000+</div>
          <div class="auth-stat-label">Thành viên</div>
        </div>
        <div class="auth-stat-item">
          <div class="auth-stat-number">13,000+</div>
          <div class="auth-stat-label">Bài viết</div>
        </div>
        <div class="auth-stat-item">
          <div class="auth-stat-number">50K+</div>
          <div class="auth-stat-label">Lượt đọc</div>
        </div>
      </div>
    </div>

    <!-- Bottom: Footer note -->
    <div class="auth-left-footer">
      © 2026 Góc Nhỏ &nbsp;·&nbsp; Nơi mọi câu chuyện đều xứng đáng được kể
    </div>
  </aside>


  <!-- ==============================================
       BÊN TAY PHẢI: QUY TRÌNH DOODLE 2 BƯỚC NHƯ THAO TÁC CŨ
       ============================================== -->
  <main class="auth-right-panel" id="auth-right-panel">

    <div class="doodle-stage-container">

      <!-- ==============================================
           BƯỚC 1: BẢN CHÀO MỪNG / CHỌN CHẾ ĐỘ (Thẻ Trắng)
           Hiển thị ban đầu khi vào trang
           ============================================== -->
      <section class="doodle-card doodle-card-white animate-in" id="doodle-welcome-card">
        <h1 class="doodle-title-serif">Góc Nhỏ</h1>
        <p class="doodle-subtitle-left">Khởi hành cùng không gian sẻ chia câu chuyện</p>

        <!-- Doodle Rocket / Space Shuttle SVG -->
        <div class="doodle-illustration-wrap" aria-hidden="true">
          <svg width="170" height="170" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Star Sparkles & Dots -->
            <path d="M40 50l3 3-3 3-3-3 3-3z" fill="#4d6888"/>
            <path d="M165 45l4 4-4 4-4-4 4-4z" fill="#eb7154"/>
            <circle cx="35" cy="115" r="2.5" fill="#222b38"/>
            <circle cx="170" cy="120" r="2.5" fill="#222b38"/>
            <path d="M60 25h4v4h-4z" fill="#5cc4a1"/>
            <circle cx="135" cy="30" r="1.5" fill="#eb7154"/>

            <!-- Left Ringed Planet (Mint Green) -->
            <ellipse cx="45" cy="42" rx="14" ry="14" fill="#5cc4a1" stroke="#222b38" stroke-width="2.5"/>
            <path d="M22 42c8-7 38-7 46 0" stroke="#222b38" stroke-width="2.5" stroke-linecap="round"/>
            <path d="M25 45c7 6 32 6 40 0" stroke="#222b38" stroke-width="2.5" stroke-linecap="round"/>

            <!-- Right Ringed Planet (Coral Orange) -->
            <ellipse cx="165" cy="70" rx="13" ry="13" fill="#eb7154" stroke="#222b38" stroke-width="2.5"/>
            <path d="M145 70c6-6 34-6 40 0" stroke="#222b38" stroke-width="2.5" stroke-linecap="round"/>
            <path d="M147 73c6 5 29 5 36 0" stroke="#222b38" stroke-width="2.5" stroke-linecap="round"/>

            <!-- Billowing smoke clouds beneath rocket -->
            <path d="M65 168c-8 0-14-6-14-14 0-6 4-11 9-13 1-9 9-16 18-16 4 0 7 1 10 3 4-8 12-14 22-14s18 6 22 14c3-2 6-3 10-3 9 0 17 7 18 16 5 2 9 7 9 13 0 8-6 14-14 14H65z" fill="#ffffff" stroke="#222b38" stroke-width="2.5" stroke-linejoin="round"/>
            <path d="M82 145c2-4 6-6 11-6 6 0 10 3 12 7" stroke="#222b38" stroke-width="2.5" stroke-linecap="round"/>
            <path d="M115 147c2-3 5-5 9-5 4 0 7 2 9 6" stroke="#222b38" stroke-width="2.5" stroke-linecap="round"/>

            <!-- Left Booster Rocket -->
            <path d="M72 135V95c0-10 6-18 10-22 4 4 10 12 10 22v40H72z" fill="#f4f6f8" stroke="#222b38" stroke-width="2.5" stroke-linejoin="round"/>
            <line x1="72" y1="108" x2="92" y2="108" stroke="#222b38" stroke-width="2.5"/>
            <line x1="72" y1="122" x2="92" y2="122" stroke="#222b38" stroke-width="2.5"/>

            <!-- Right Booster Rocket -->
            <path d="M108 135V95c0-10 6-18 10-22 4 4 10 12 10 22v40h-20z" fill="#f4f6f8" stroke="#222b38" stroke-width="2.5" stroke-linejoin="round"/>
            <line x1="108" y1="108" x2="128" y2="108" stroke="#222b38" stroke-width="2.5"/>
            <line x1="108" y1="122" x2="128" y2="122" stroke="#222b38" stroke-width="2.5"/>

            <!-- Center Main Fuselage (Space Shuttle) -->
            <path d="M85 140V70c0-18 10-32 15-38 5 6 15 20 15 38v70H85z" fill="#ffffff" stroke="#222b38" stroke-width="2.5" stroke-linejoin="round"/>
            
            <!-- Cockpit Window -->
            <path d="M93 68c0-5 3-9 7-9s7 4 7 9v6H93v-6z" fill="#222b38"/>

            <!-- Shuttle Grid Details -->
            <line x1="85" y1="92" x2="115" y2="92" stroke="#222b38" stroke-width="2.5"/>
            <line x1="100" y1="92" x2="100" y2="122" stroke="#222b38" stroke-width="2.5"/>
            <rect x="88" y="97" width="8" height="8" rx="2" fill="#5cc4a1"/>
            <rect x="104" y="97" width="8" height="8" rx="2" fill="#eb7154"/>
            <line x1="88" y1="112" x2="96" y2="112" stroke="#222b38" stroke-width="2"/>
            <line x1="88" y1="117" x2="96" y2="117" stroke="#222b38" stroke-width="2"/>

            <!-- Mint Green Bottom Thruster Hood -->
            <path d="M88 128h24c3 0 5 2 5 5v5H83v-5c0-3 2-5 5-5z" fill="#5cc4a1" stroke="#222b38" stroke-width="2.5"/>
            <line x1="96" y1="131" x2="96" y2="135" stroke="#222b38" stroke-width="2" stroke-linecap="round"/>
            <line x1="100" y1="131" x2="100" y2="135" stroke="#222b38" stroke-width="2" stroke-linecap="round"/>
            <line x1="104" y1="131" x2="104" y2="135" stroke="#222b38" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>

        <!-- Pill Buttons on Welcome Card: Chuyển sang Bước 2 -->
        <div class="doodle-actions-left">
          <button type="button" class="doodle-btn-slate" onclick="showAuthScreen('register')">
            Tạo tài khoản mới
          </button>
          <button type="button" class="doodle-btn-coral" onclick="showAuthScreen('login')">
            Đăng nhập
          </button>
        </div>

        <div class="doodle-footer-note">
          <a href="{{ url('/') }}">← Trở về trang chủ Góc Nhỏ</a> &nbsp;·&nbsp; Điều khoản
        </div>
      </section>


      <!-- ==============================================
           BƯỚC 2: BẢN FORM NHẬP LIỆU (Thẻ Slate Blue)
           Xuất hiện sau khi chọn Đăng nhập hoặc Tạo tài khoản
           ============================================== -->
      <section class="doodle-card doodle-card-blue animate-in" id="doodle-form-card" style="display:none;">

        <!-- Top Navigation: Nút Quay lại Bước 1 + Link tài khoản thiết bị -->
        <div class="doodle-top-nav">
          <button type="button" class="doodle-nav-back-btn" onclick="showWelcomeScreen()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Quay lại
          </button>
          <a class="doodle-nav-device-link" href="{{ url('/accounts') }}">
            Tài khoản đã lưu
          </a>
        </div>

        <!-- Heading -->
        <div class="auth-form-eyebrow" id="auth-eyebrow">Chào mừng trở lại</div>
        <h2 class="doodle-title-serif" id="auth-title">Đăng nhập</h2>
        <p class="auth-form-subtitle" id="auth-subtitle">Điền thông tin để vào Góc Nhỏ của bạn.</p>

        <!-- Hidden tab buttons for account.js compatibility -->
        <div style="display:none;" role="tablist">
          <button type="button" class="auth-tab active" id="tab-login" onclick="setAuthMode('login')">Đăng nhập</button>
          <button type="button" class="auth-tab" id="tab-register" onclick="setAuthMode('register')">Đăng ký</button>
        </div>

        <!-- Doodle Satellite SVG -->
        <div class="doodle-illustration-wrap" aria-hidden="true" style="min-height:120px; margin:2px 0 16px;">
          <svg width="145" height="120" viewBox="0 0 180 160" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="35" cy="45" r="2.5" fill="rgba(255,255,255,0.75)"/>
            <circle cx="150" cy="125" r="2" fill="rgba(255,255,255,0.75)"/>
            <path d="M145 35l3 3-3 3-3-3 3-3z" fill="#5cc4a1"/>

            <!-- Left Ringed Planet (Coral) -->
            <ellipse cx="32" cy="85" rx="10" ry="10" fill="#eb7154" stroke="#222b38" stroke-width="2.2"/>
            <path d="M15 85c5-4 28-4 34 0" stroke="#222b38" stroke-width="2.2" stroke-linecap="round"/>

            <!-- Right Crescent Moon / Planet (Mint) -->
            <path d="M145 68a10 10 0 0 0 14-8 10 10 0 1 1-14 8z" fill="#5cc4a1" stroke="#222b38" stroke-width="2"/>

            <!-- The Satellite tilted at -40 deg angle -->
            <g transform="rotate(-40 90 80)">
              <!-- Left Solar Wing -->
              <rect x="24" y="68" width="42" height="24" rx="2" fill="#ffffff" stroke="#222b38" stroke-width="2.5"/>
              <line x1="38" y1="68" x2="38" y2="92" stroke="#222b38" stroke-width="2"/>
              <line x1="52" y1="68" x2="52" y2="92" stroke="#222b38" stroke-width="2"/>
              <line x1="24" y1="80" x2="66" y2="80" stroke="#222b38" stroke-width="2"/>
              <line x1="66" y1="80" x2="74" y2="80" stroke="#222b38" stroke-width="3"/>

              <!-- Right Solar Wing -->
              <rect x="106" y="68" width="42" height="24" rx="2" fill="#ffffff" stroke="#222b38" stroke-width="2.5"/>
              <line x1="120" y1="68" x2="120" y2="92" stroke="#222b38" stroke-width="2"/>
              <line x1="134" y1="68" x2="134" y2="92" stroke="#222b38" stroke-width="2"/>
              <line x1="106" y1="80" x2="148" y2="80" stroke="#222b38" stroke-width="2"/>
              <line x1="98" y1="80" x2="106" y2="80" stroke="#222b38" stroke-width="3"/>

              <!-- Central Satellite Body -->
              <path d="M84 48c0-7 6-12 12-12s12 5 12 12H84z" fill="#ffffff" stroke="#222b38" stroke-width="2.5"/>
              <circle cx="96" cy="36" r="3" fill="#222b38"/>
              <line x1="96" y1="36" x2="96" y2="48" stroke="#222b38" stroke-width="2.5"/>

              <path d="M78 48h36v28c0 4-3 7-7 7H85c-4 0-7-3-7-7V48z" fill="#ffffff" stroke="#222b38" stroke-width="2.5"/>
              <rect x="78" y="83" width="36" height="8" fill="#5cc4a1" stroke="#222b38" stroke-width="2.5"/>
              <rect x="78" y="91" width="36" height="8" fill="#eb7154" stroke="#222b38" stroke-width="2.5"/>
              <path d="M84 99h24l-3 10H87l-3-10z" fill="#ffffff" stroke="#222b38" stroke-width="2.5"/>
            </g>
          </svg>
        </div>

        <!-- Alert when switching accounts -->
        <div id="auth-switch-alert" class="auth-switch-alert-new" style="display:none;"></div>

        <!-- Form Container -->
        <div class="doodle-form-wrap" id="auth-card-section">
          <form id="account-form" novalidate>
            @csrf

            <!-- Register fields (hiển thị khi đăng ký) -->
            <div id="register-fields" style="display:none;">
              <div class="doodle-field-group">
                <div class="doodle-input-pill-wrap">
                  <span class="doodle-input-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  </span>
                  <input class="doodle-input-pill auth-input" name="full_name" id="reg-fullname" maxlength="120" autocomplete="name" placeholder="Họ và tên">
                </div>

                <div class="doodle-input-pill-wrap">
                  <span class="doodle-input-icon" style="font-weight:700;">@</span>
                  <input class="doodle-input-pill auth-input" name="username" id="reg-username" minlength="3" maxlength="40" autocomplete="username" placeholder="Tên đăng nhập (@username)">
                </div>

                <div class="doodle-input-pill-wrap">
                  <span class="doodle-input-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                  </span>
                  <input class="doodle-input-pill auth-input" name="email" id="reg-email" type="email" autocomplete="email" placeholder="E-Mail">
                </div>
              </div>
            </div>

            <!-- Login fields (hiển thị khi đăng nhập) -->
            <div id="login-fields">
              <div class="doodle-field-group">
                <div class="doodle-input-pill-wrap">
                  <span class="doodle-input-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                  </span>
                  <input class="doodle-input-pill auth-input" name="login" id="login-input" autocomplete="username" placeholder="E-Mail hoặc Username">
                </div>
              </div>
            </div>

            <!-- Password field (dùng chung) -->
            <div class="doodle-field-group">
              <div class="doodle-input-pill-wrap">
                <span class="doodle-input-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </span>
                <input class="doodle-input-pill auth-input" name="password" id="auth-password" type="password" minlength="6" autocomplete="current-password" required placeholder="Password">
                <button type="button" class="doodle-pwd-toggle" id="btn-toggle-auth-pwd" aria-label="Hiện/ẩn mật khẩu">Hiện</button>
              </div>
            </div>

            <!-- Avatar picker for register -->
            <div id="register-avatar-container" style="display:none;">
              <div class="doodle-avatar-box">
                <div class="doodle-avatar-label">Chọn ảnh đại diện</div>
                <!-- AvatarPicker renders here -->
              </div>
            </div>

            <!-- Remember me checkbox -->
            <label class="doodle-remember-row">
              <input type="checkbox" id="doodle-remember-check" checked>
              <span>Ghi nhớ đăng nhập</span>
            </label>

            <!-- Submit Pill Button -->
            <button class="doodle-submit-btn auth-submit-btn-new" id="submit-button" type="submit">
              GO! &nbsp;→
            </button>
          </form>

          <!-- Error/status message -->
          <p class="auth-message-new" id="account-message" role="status"></p>

          <!-- Footer switch prompt -->
          <div class="doodle-bottom-link-row" id="auth-footer-prompt">
            <span id="auth-footer-text">Chưa có tài khoản?</span>
            <button type="button" id="auth-switch-link" onclick="toggleAuthMode()">Đăng ký ngay</button>
          </div>
        </div>

      </section>


      <!-- ==============================================
           BƯỚC 3: PROFILE CARD (Khi đã đăng nhập)
           ============================================== -->
      <section class="doodle-card doodle-card-blue animate-in" id="profile-card-section" style="display:none;">

        <!-- Hero Header -->
        <div class="doodle-profile-hero">
          <img class="doodle-profile-avatar" id="profile-display-avatar" src="{{ asset('assets/images/avatars/default.svg') }}" alt="">
          <div style="flex:1; min-width:0;">
            <h3 class="doodle-profile-name" id="profile-display-name">Người dùng</h3>
            <p class="doodle-profile-user" id="profile-display-username">@username</p>
            <span class="doodle-profile-role" id="profile-display-role">Thành viên</span>
            <div style="font-size:11px; opacity:0.8; margin-top:2px;" id="profile-display-email">email@example.com</div>
          </div>
        </div>

        <!-- Edit Form -->
        <form id="profile-edit-form" class="doodle-profile-edit">
          @csrf
          <label>
            <span>Tên đăng nhập (@username)</span>
            <input class="doodle-input-pill auth-input" id="profile-edit-username" required minlength="3" maxlength="40">
          </label>
          <label>
            <span>Họ và tên</span>
            <input class="doodle-input-pill auth-input" id="profile-edit-fullname" required maxlength="120">
          </label>
          <label>
            <span>Mật khẩu hiện tại (nếu muốn đổi)</span>
            <input type="password" class="doodle-input-pill auth-input" id="profile-edit-current-pass" placeholder="Nhập mật khẩu hiện tại">
          </label>
          <label>
            <span>Mật khẩu mới (tối thiểu 8 ký tự)</span>
            <input type="password" class="doodle-input-pill auth-input" id="profile-edit-new-pass" minlength="8" placeholder="Nhập mật khẩu mới">
          </label>

          <div id="profile-edit-msg" class="auth-message-new"></div>

          <button type="submit" class="doodle-submit-btn" style="margin-top:6px;">
            Lưu thay đổi
          </button>
        </form>

        <!-- Actions -->
        <div class="doodle-profile-actions">
          <div id="admin-entry-container" style="display:none;">
            <a href="{{ url('/admin') }}" class="doodle-btn-sub" style="width:100%; box-sizing:border-box;">
              Trang Quản Trị Hệ Thống
            </a>
          </div>
          <div class="doodle-profile-btn-row">
            <a href="{{ url('/accounts') }}" class="doodle-btn-sub">
              Tài khoản khác
            </a>
            <button type="button" class="doodle-btn-logout" id="logout-button">
              Đăng xuất
            </button>
          </div>
        </div>

      </section>

    </div>

  </main>

</div>

<script>window.APP_URL = "{{ url('/') }}";</script>
<script>
  // Chuyển đổi giữa Bước 1 (Bản Chào mừng) và Bước 2 (Bản Form Đăng nhập / Đăng ký)
  function showWelcomeScreen() {
    const welcomeCard = document.getElementById('doodle-welcome-card');
    const formCard = document.getElementById('doodle-form-card');
    const profileCard = document.getElementById('profile-card-section');
    if (profileCard) profileCard.style.display = 'none';
    if (formCard) formCard.style.display = 'none';
    if (welcomeCard) {
      welcomeCard.style.display = 'flex';
      welcomeCard.classList.remove('animate-in');
      void welcomeCard.offsetWidth; // trigger reflow
      welcomeCard.classList.add('animate-in');
    }
  }

  function showAuthScreen(mode) {
    const welcomeCard = document.getElementById('doodle-welcome-card');
    const formCard = document.getElementById('doodle-form-card');
    const profileCard = document.getElementById('profile-card-section');
    if (profileCard) profileCard.style.display = 'none';
    if (welcomeCard) welcomeCard.style.display = 'none';
    if (formCard) {
      formCard.style.display = 'flex';
      formCard.classList.remove('animate-in');
      void formCard.offsetWidth;
      formCard.classList.add('animate-in');
    }
    if (typeof window.setAuthMode === 'function') {
      window.setAuthMode(mode);
    }
  }

  // Khả năng tương thích với account.js + Cập nhật tiêu đề và nút bấm
  const _origSetAuthMode = window.setAuthMode;
  function patchAuthMode() {
    const eyebrowEl = document.getElementById('auth-eyebrow');
    const titleEl = document.getElementById('auth-title');
    const subtitleEl = document.getElementById('auth-subtitle');
    const submitBtn = document.getElementById('submit-button');
    const origFn = window.setAuthMode;
    if (!origFn) { setTimeout(patchAuthMode, 50); return; }
    window.setAuthMode = function(mode) {
      origFn(mode);
      if (eyebrowEl) {
        eyebrowEl.textContent = mode === 'register' ? 'KHỞI HÀNH CÙNG GÓC NHỎ' : 'CHÀO MỪNG TRỞ LẠI';
      }
      if (titleEl) {
        titleEl.textContent = mode === 'register' ? 'Tạo tài khoản' : 'Đăng nhập';
      }
      if (subtitleEl) {
        subtitleEl.textContent = mode === 'register'
          ? 'Tạo tài khoản để bắt đầu chia sẻ câu chuyện của bạn.'
          : 'Điền thông tin để vào Góc Nhỏ của bạn.';
      }
      if (submitBtn) {
        submitBtn.innerHTML = mode === 'register' ? 'CREATE ACCOUNT &nbsp;→' : 'GO! &nbsp;→';
      }
    };
  }

  document.addEventListener('DOMContentLoaded', function() {
    patchAuthMode();

    // Eye toggle password
    const toggleBtn = document.getElementById('btn-toggle-auth-pwd');
    const pwdInput = document.getElementById('auth-password');
    if (toggleBtn && pwdInput) {
      toggleBtn.addEventListener('click', function() {
        const isHidden = pwdInput.type === 'password';
        pwdInput.type = isHidden ? 'text' : 'password';
        toggleBtn.textContent = isHidden ? 'Ẩn' : 'Hiện';
      });
    }

    // Xác định màn hình ban đầu:
    // Nếu có URL param ?mode=login hoặc ?mode=register thì vào thẳng Bước 2
    // Ngược lại nếu chưa đăng nhập thì hiện Bước 1 (Bản chào mừng với 2 lựa chọn)
    const urlParams = new URLSearchParams(window.location.search);
    const requestedMode = urlParams.get('mode');

    setTimeout(() => {
      const profileCard = document.getElementById('profile-card-section');
      const isProfileVisible = profileCard && profileCard.style.display !== 'none';
      if (isProfileVisible) {
        const welcomeCard = document.getElementById('doodle-welcome-card');
        const formCard = document.getElementById('doodle-form-card');
        if (welcomeCard) welcomeCard.style.display = 'none';
        if (formCard) formCard.style.display = 'none';
        return;
      }

      if (requestedMode === 'login' || requestedMode === 'register') {
        showAuthScreen(requestedMode);
      } else {
        showWelcomeScreen();
      }
    }, 60);
  });
</script>
<script src="{{ asset('assets/js/account-memory.js') }}"></script>
<script src="{{ asset('assets/js/csrf.js') }}?v=20261114-csrf1"></script>
<script src="{{ asset('assets/js/avatar-picker.js') }}?v=20261109-avatar-picker1"></script>
<script src="{{ asset('assets/js/account.js') }}?v=20261119-auth1"></script>
</body>
</html>