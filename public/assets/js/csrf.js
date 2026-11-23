/**
 * Quản lý CSRF Token tập trung cho frontend (Góc Nhỏ).
 * Hỗ trợ tự động đọc Cookie XSRF-TOKEN, lấy token từ API, gắn Header X-CSRF-TOKEN,
 * và tự động làm mới khi gặp HTTP 403 CSRF_TOKEN_INVALID.
 */
(function (root) {
  'use strict';

  // Tự động khởi tạo root.APP_URL nếu chưa được cung cấp từ PHP layout
  if (!root.APP_URL || typeof root.APP_URL !== 'string' || !root.APP_URL.trim()) {
    let detectedBase = '';
    try {
      const scriptEl = document.currentScript || document.querySelector('script[src*="assets/js/csrf.js"], script[src*="assets/js/"]');
      if (scriptEl && scriptEl.src) {
        detectedBase = new URL('../..', scriptEl.src).pathname.replace(/\/$/, '');
      }
    } catch (_) {}

    if (!detectedBase && typeof location !== 'undefined') {
      try {
        const match = location.pathname.match(/^(.*?\/public)(?:\/|$)/);
        detectedBase = match ? match[1].replace(/\/$/, '') : new URL('.', location.href).pathname.replace(/\/$/, '');
      } catch (_) {}
    }

    if (detectedBase) {
      root.APP_URL = detectedBase;
    }
  }

  const COOKIE_NAME = 'XSRF-TOKEN';
  let _cachedToken = null;

  const Csrf = {
    /**
     * Đọc giá trị token từ Cookie XSRF-TOKEN
     */
    getCookie: function () {
      try {
        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
        return match ? decodeURIComponent(match[1]) : null;
      } catch (_) {
        return null;
      }
    },

    /**
     * Lấy token hiện có (ưu tiên Cookie, sau đó đến Token đã cache trong bộ nhớ)
     */
    getToken: function () {
      const cookieToken = this.getCookie();
      if (cookieToken) {
        _cachedToken = cookieToken;
        return cookieToken;
      }
      return _cachedToken || '';
    },

    /**
     * Cập nhật token thủ công (khi nhận được từ response login, register, logout)
     */
    setToken: function (token) {
      if (typeof token === 'string' && token.trim()) {
        _cachedToken = token.trim();
      }
    },

    /**
     * Lấy token CSRF hợp lệ. Nếu chưa có trong cookie/cache thì fetch từ endpoint /api/csrf-token
     */
    fetchToken: async function (basePath = '') {
      let token = this.getCookie();
      if (token) {
        _cachedToken = token;
        return token;
      }

      try {
        const base = (basePath || window.APP_URL || new URL('.', window.location.href).pathname).replace(/\/$/, '');
        const url = `${base}/index.php?url=api/csrf-token`;
        const res = await fetch(url, {
          method: 'GET',
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' },
          cache: 'no-store'
        });

        if (res.ok) {
          const data = await res.json();
          if (data && data.csrf_token) {
            _cachedToken = data.csrf_token;
            return data.csrf_token;
          }
        }
      } catch (err) {
        console.warn('[Csrf] Lỗi khi tải CSRF Token từ server:', err);
      }

      return this.getCookie() || _cachedToken || '';
    },

    /**
     * Gắn Header X-CSRF-TOKEN vào đối tượng headers
     */
    attach: async function (headers = {}, basePath = '') {
      const token = await this.fetchToken(basePath);
      if (token) {
        headers['X-CSRF-TOKEN'] = token;
      }
      return headers;
    },

    /**
     * Xóa token cache và xóa cookie XSRF-TOKEN trên trình duyệt (dùng khi token bị hết hạn/sai)
     */
    clear: function () {
      _cachedToken = null;
      try {
        document.cookie = `${COOKIE_NAME}=; Max-Age=0; path=/`;
      } catch (_) {}
    }
  };

  root.Csrf = Csrf;

  // Cung cấp hàm tương thích ngược cho mã nguồn cũ
  root.getCsrfCookie = () => Csrf.getCookie();
  root.getValidCsrfToken = (basePath) => Csrf.fetchToken(basePath);

})(typeof window !== 'undefined' ? window : this);
