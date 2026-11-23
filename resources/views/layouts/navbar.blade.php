<header class="blog-header">
  <div class="blog-header-inner">
    <a class="blog-brand" href="{{ url('/') }}" aria-label="Góc Nhỏ - Trang chủ">
      <span class="brand-mark">g.</span><span>góc nhỏ</span>
    </a>
    <nav class="blog-nav" aria-label="Điều hướng chính">
      <a class="blog-nav-link {{ empty($activeTab) || ($activeTab ?? '') === 'home' ? 'is-active' : '' }}" href="{{ url('/#home') }}">Trang chủ</a>
      <a class="blog-nav-link {{ ($activeTab ?? '') === 'feed' ? 'is-active' : '' }}" href="{{ url('/#feed') }}" onclick="if(typeof filterNavFeed==='function') filterNavFeed('home')">Bài viết</a>
      <a class="blog-nav-link {{ ($activeTab ?? '') === 'news' ? 'is-active' : '' }}" href="{{ url('/news') }}">Tin tức</a>
      <a class="blog-nav-link" href="{{ url('/#about') }}">Về mình</a>
    </nav>
    <div class="blog-header-actions">
      <label class="blog-search" aria-label="Tìm bài viết">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color:var(--muted-light,#7c827d);flex-shrink:0;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input id="smart-search-input" type="search" placeholder="Tìm kiếm (#tag, @user, từ khóa)..." autocomplete="off">
      </label>

      <a href="{{ url('/admin') }}" class="blog-admin-link" title="Trang quản trị hệ thống" id="nav-admin-link">
        <i class="fa-solid fa-sliders"></i> <span>Quản trị</span>
      </a>

      <div class="notification-bell-wrapper" id="nav-notification-wrapper" style="display:none;">
        <button type="button" class="nav-bell-btn" id="nav-bell-btn" title="Thông báo" aria-label="Thông báo" aria-haspopup="true" aria-expanded="false">
          <svg class="nav-bell-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
          <span class="bell-badge" id="nav-bell-badge" style="display:none;">0</span>
        </button>
        <div class="nav-notification-dropdown" id="nav-notification-dropdown" style="display:none;">
          <div class="notif-dropdown-header">
            <h4>Thông báo</h4>
            <div class="notif-dropdown-actions">
              <button type="button" class="notif-read-all-btn" id="dropdown-mark-all-read" title="Đánh dấu tất cả đã đọc">
                <svg class="notif-check-double-svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:4px;"><polyline points="18 6 7 17 2 12"></polyline><polyline points="22 10 13 19 10 16"></polyline></svg>Đã đọc
              </button>
              <a href="{{ url('/notifications') }}" class="notif-view-all-link">Xem tất cả</a>
            </div>
          </div>
          <div class="notif-dropdown-list" id="dropdown-notif-list">
            <div class="notif-dropdown-loading">Đang tải thông báo...</div>
          </div>
          <div class="notif-dropdown-footer">
            <a href="{{ url('/notifications') }}" class="notif-see-full-link">
              <span>Đi tới Trung tâm thông báo</span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-left:4px;" aria-hidden="true">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </a>
          </div>
        </div>
      </div>

      <div class="fb-menu-wrapper" id="fb-user-menu-container">
        <button type="button" class="fb-avatar-btn" id="fb-avatar-trigger" title="Tài khoản cá nhân" aria-haspopup="true" aria-expanded="false">
          <img id="fb-nav-avatar" class="fb-avatar-img" src="{{ \App\Support\UnicodeHelper::avatarUrl(auth()->user()?->avatar ?? '/assets/images/avatars/user-admin.svg') }}" alt="Tài khoản">
          <span class="fb-nav-name" id="fb-nav-username">{{ auth()->user()?->full_name ?? '' }}</span>
          <span class="fb-avatar-arrow"><i class="fa-solid fa-chevron-down"></i></span>
        </button>
        <div class="fb-dropdown-panel" id="fb-dropdown-panel" style="display:none;"></div>
      </div>
    </div>
  </div>
</header>
