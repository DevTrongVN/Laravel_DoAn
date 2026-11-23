const accountBase = new URL('.', window.location.href).pathname.replace(/\/$/, '');
const accountForm = document.getElementById('account-form');
let registerMode = false;
let currentAuthenticatedUser = null;

const deviceEscape = value => String(value ?? '').replace(/[&<>"']/g, char => ({
  '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'
}[char]));

function deviceAvatarUrl(value) {
  const raw = String(value || 'assets/images/avatars/default.svg').trim();
  if (/^(data:|blob:|https?:|\/\/)/i.test(raw)) return raw;
  const path = raw.startsWith('/') && /^\/(assets|uploads)\//i.test(raw) ? raw.slice(1) : raw;
  try {
    const url = new URL(path, `${window.location.origin}${accountBase}/`);
    return url.href;
  } catch (_) {
    return `${accountBase}/assets/images/avatars/default.svg`;
  }
}

// Khởi tạo AvatarPicker cho form đăng ký nếu có thư viện
const avatarContainer = document.getElementById('register-avatar-container');
if (avatarContainer && window.AvatarPicker) {
  avatarContainer.innerHTML = AvatarPicker.markup({ id: 'register-avatar-options' });
}

function getAccountCsrfCookie() {
  return window.Csrf ? window.Csrf.getCookie() : (document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)?.[1] || null);
}

async function getAccountCsrfToken() {
  if (window.Csrf) {
    return window.Csrf.fetchToken(accountBase);
  }
  let token = getAccountCsrfCookie();
  if (!token) {
    try {
      const res = await fetch(`${accountBase}/index.php?url=api/csrf-token`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });
      if (res.ok) {
        const data = await res.json();
        token = data.csrf_token || getAccountCsrfCookie();
      }
    } catch (_) {}
  }
  return token || '';
}

async function accountApi(route, body = null, isRetry = false) {
  const url = new URL(`${accountBase}/index.php`, window.location.origin);
  url.searchParams.set('url', route);
  const method = body === null ? 'GET' : 'POST';
  const headers = body === null ? { 'Accept': 'application/json' } : { 'Accept': 'application/json', 'Content-Type': 'application/json' };
  if (method === 'POST') {
    if (window.Csrf) {
      await window.Csrf.attach(headers, accountBase);
    } else {
      const token = await getAccountCsrfToken();
      if (token) headers['X-CSRF-TOKEN'] = token;
    }
  }
  const response = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers,
    body: body === null ? undefined : JSON.stringify(body)
  });

  const text = await response.text();
  let result;
  try {
    result = JSON.parse(text);
  } catch (e) {
    console.error('Lỗi định dạng JSON từ máy chủ:', text);
    throw new Error('Máy chủ phản hồi không đúng định dạng JSON: ' + text.slice(0, 100));
  }

  if (!response.ok) {
    if (response.status === 403 && result && result.code === 'CSRF_TOKEN_INVALID' && !isRetry) {
      if (window.Csrf) {
        window.Csrf.clear();
        await window.Csrf.fetchToken(accountBase);
      } else {
        document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/';
        await getAccountCsrfToken();
      }
      return accountApi(route, body, true);
    }
    throw new Error(result.error || 'Có lỗi xảy ra.');
  }

  if (result && result.csrf_token && window.Csrf) {
    window.Csrf.setToken(result.csrf_token);
  }

  return result;
}

/**
 * Chuyển đổi trạng thái giữa Đăng nhập và Đăng ký trên Auth Card
 */
function setAuthMode(mode) {
  registerMode = (mode === 'register');

  const tabLogin = document.getElementById('tab-login');
  const tabRegister = document.getElementById('tab-register');
  const regFields = document.getElementById('register-fields');
  const loginFields = document.getElementById('login-fields');
  const avatarBox = document.getElementById('register-avatar-container');
  const titleEl = document.getElementById('auth-title');
  const subtitleEl = document.getElementById('auth-subtitle');
  const submitBtn = document.getElementById('submit-button');
  const footerText = document.getElementById('auth-footer-text');
  const switchLink = document.getElementById('auth-switch-link');
  const messageEl = document.getElementById('account-message');

  if (messageEl) messageEl.textContent = '';

  if (registerMode) {
    if (tabLogin) tabLogin.classList.remove('active');
    if (tabRegister) tabRegister.classList.add('active');
    if (regFields) regFields.style.display = 'block';
    if (loginFields) loginFields.style.display = 'none';
    if (avatarBox) avatarBox.style.display = 'block';

    if (titleEl) titleEl.textContent = 'Tạo tài khoản mới';
    if (subtitleEl) subtitleEl.textContent = 'Tham gia cùng Góc Nhỏ để sẻ chia câu chuyện và góc nhìn của bạn.';
    if (submitBtn) submitBtn.textContent = 'Tạo tài khoản';
    if (footerText) footerText.textContent = 'Đã có tài khoản?';
    if (switchLink) switchLink.textContent = 'Đăng nhập ngay';

    // Ràng buộc các trường bắt buộc
    const regFullname = document.getElementById('reg-fullname');
    const regUsername = document.getElementById('reg-username');
    const regEmail = document.getElementById('reg-email');
    const loginInput = document.getElementById('login-input');
    if (regFullname) regFullname.required = true;
    if (regUsername) regUsername.required = true;
    if (regEmail) regEmail.required = true;
    if (loginInput) loginInput.required = false;

    // Tự động chọn 1 avatar ngẫu nhiên nếu chưa có
    const avatarVal = avatarBox ? avatarBox.querySelector('[data-avatar-value]') : null;
    if (avatarBox && avatarVal && !avatarVal.value) {
      avatarBox.querySelector('[data-avatar-random]')?.click();
    }
  } else {
    if (tabLogin) tabLogin.classList.add('active');
    if (tabRegister) tabRegister.classList.remove('active');
    if (regFields) regFields.style.display = 'none';
    if (loginFields) loginFields.style.display = 'block';
    if (avatarBox) avatarBox.style.display = 'none';

    if (titleEl) titleEl.textContent = 'Đăng nhập';
    if (subtitleEl) subtitleEl.textContent = 'Chào mừng bạn quay trở lại với Góc Nhỏ.';
    if (submitBtn) submitBtn.textContent = 'Đăng nhập';
    if (footerText) footerText.textContent = 'Chưa có tài khoản?';
    if (switchLink) switchLink.textContent = 'Đăng ký ngay';

    const regFullname = document.getElementById('reg-fullname');
    const regUsername = document.getElementById('reg-username');
    const regEmail = document.getElementById('reg-email');
    const loginInput = document.getElementById('login-input');
    if (regFullname) regFullname.required = false;
    if (regUsername) regUsername.required = false;
    if (regEmail) regEmail.required = false;
    if (loginInput) loginInput.required = true;
  }

  // Cập nhật URL một cách gọn gàng mà không tải lại trang
  try {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('mode', registerMode ? 'register' : 'login');
    window.history.replaceState(null, '', currentUrl.toString());
  } catch (_) {}
}

function toggleAuthMode() {
  setAuthMode(registerMode ? 'login' : 'register');
}

window.setAuthMode = setAuthMode;
window.toggleAuthMode = toggleAuthMode;

// Nút ẩn/hiện mật khẩu form đăng nhập/đăng ký
const btnToggleAuthPwd = document.getElementById('btn-toggle-auth-pwd');
const authPwdInput = document.getElementById('auth-password');
if (btnToggleAuthPwd && authPwdInput) {
  btnToggleAuthPwd.addEventListener('click', () => {
    const isPass = authPwdInput.type === 'password';
    authPwdInput.type = isPass ? 'text' : 'password';
    btnToggleAuthPwd.textContent = isPass ? 'Ẩn' : 'Hiện';
  });
}

// Xử lý gửi Form Đăng nhập / Đăng ký
if (accountForm) {
  accountForm.addEventListener('submit', async event => {
    event.preventDefault();
    const values = Object.fromEntries(new FormData(accountForm));
    const messageEl = document.getElementById('account-message');
    const submitBtn = document.getElementById('submit-button');

    if (messageEl) messageEl.textContent = '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = registerMode ? 'Đang tạo tài khoản...' : 'Đang đăng nhập...';
    }

    try {
      const payload = registerMode
        ? {
            username: (values.username || '').trim(),
            email: (values.email || '').trim(),
            full_name: (values.full_name || '').trim(),
            password: values.password || '',
            avatar: values.avatar || null
          }
        : {
            login: (values.login || '').trim(),
            password: values.password || ''
          };

      const res = await accountApi(registerMode ? 'api/auth/register' : 'api/auth/login', payload);
      rememberAccount(res.user);

      if (res.user && res.user.role === 'admin') {
        window.location.href = 'admin';
      } else {
        window.location.href = 'index.html';
      }
    } catch (error) {
      if (messageEl) messageEl.textContent = error.message;
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = registerMode ? 'Tạo tài khoản' : 'Đăng nhập';
      }
    }
  });
}

// Xử lý Đăng xuất
const logoutBtn = document.getElementById('logout-button');
if (logoutBtn) {
  logoutBtn.addEventListener('click', async () => {
    try {
      logoutBtn.disabled = true;
      await accountApi('api/auth/logout', {});
      window.location.href = 'account.html?mode=login';
    } catch (error) {
      alert('Không thể đăng xuất: ' + error.message);
      logoutBtn.disabled = false;
    }
  });
}

// Render dữ liệu thẻ hồ sơ cá nhân
function renderUserProfileCard(user) {
  const avatarImg = document.getElementById('profile-display-avatar');
  const nameEl = document.getElementById('profile-display-name');
  const usernameEl = document.getElementById('profile-display-username');
  const emailEl = document.getElementById('profile-display-email');
  const roleEl = document.getElementById('profile-display-role');
  const adminContainer = document.getElementById('admin-entry-container');

  if (avatarImg) avatarImg.src = deviceAvatarUrl(user.avatar);
  if (nameEl) nameEl.textContent = user.full_name || user.username;
  if (usernameEl) usernameEl.textContent = `@${user.username}`;
  if (emailEl) emailEl.textContent = user.email || 'N/A';

  if (roleEl) {
    if (user.role === 'admin') {
      roleEl.textContent = 'Quản trị viên';
      roleEl.style.background = '#fee2e2';
      roleEl.style.color = '#b91c1c';
    } else {
      roleEl.textContent = 'Thành viên';
      roleEl.style.background = '#f3f4f6';
      roleEl.style.color = '#4b5563';
    }
  }

  if (adminContainer) {
    adminContainer.style.display = (user.role === 'admin') ? 'block' : 'none';
  }

  const editUsername = document.getElementById('profile-edit-username');
  const editFullname = document.getElementById('profile-edit-fullname');
  if (editUsername) editUsername.value = user.username;
  if (editFullname) editFullname.value = user.full_name || '';

  // Gắn sự kiện sửa thông tin & đổi mật khẩu
  const editForm = document.getElementById('profile-edit-form');
  if (editForm && !editForm.dataset.bound) {
    editForm.dataset.bound = 'true';
    editForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = document.getElementById('profile-edit-username').value.trim();
      const fullName = document.getElementById('profile-edit-fullname').value.trim();
      const currentPassword = document.getElementById('profile-edit-current-pass').value;
      const newPassword = document.getElementById('profile-edit-new-pass').value;
      const msg = document.getElementById('profile-edit-msg');

      if (newPassword && !currentPassword) {
        if (msg) {
          msg.textContent = 'Vui lòng nhập mật khẩu hiện tại để đổi mật khẩu.';
          msg.style.color = '#dc2626';
        }
        return;
      }

      const payload = { username, full_name: fullName };
      if (newPassword) {
        payload.current_password = currentPassword;
        payload.new_password = newPassword;
      }

      try {
        const res = await accountApi('api/auth/profile', payload);
        if (res.user) rememberAccount(res.user);
        if (msg) {
          msg.textContent = (res.message || 'Cập nhật thành công!');
          msg.style.color = '#15803d';
        }
        setTimeout(() => window.location.reload(), 1000);
      } catch (err) {
        if (msg) {
          msg.textContent = err.message;
          msg.style.color = '#dc2626';
        }
      }
    });
  }
}

// Khởi chạy và xác định chế độ hiển thị trang
async function initializeAccountPage() {
  const urlParams = new URLSearchParams(window.location.search);
  const requestedMode = urlParams.get('mode'); // 'login' | 'register'

  const authSection = document.getElementById('auth-card-section');
  const profileSection = document.getElementById('profile-card-section');
  const switchAlert = document.getElementById('auth-switch-alert');

  try {
    const result = await accountApi('api/auth/me');
    currentAuthenticatedUser = result && result.user ? result.user : null;
    if (currentAuthenticatedUser) {
      rememberAccount(currentAuthenticatedUser);
    }
  } catch (_) {
    currentAuthenticatedUser = null;
  }

  // TRƯỜNG HỢP 1: Có query ?mode=login hoặc ?mode=register (ví dụ bấm "Đăng nhập tài khoản khác") HOẶC chưa đăng nhập
  if (requestedMode === 'login' || requestedMode === 'register' || !currentAuthenticatedUser) {
    if (authSection) authSection.style.display = 'block';
    if (profileSection) profileSection.style.display = 'none';

    // Nếu đang có tài khoản đăng nhập mà cố ý bấm "Đăng nhập tài khoản khác" -> hiển thị banner nhắc nhở
    if (switchAlert) {
      if (currentAuthenticatedUser) {
        switchAlert.innerHTML = `
          <span>Bạn đang đăng nhập bằng <strong>@${deviceEscape(currentAuthenticatedUser.username)}</strong>.</span>
          <a href="account.html" style="color:#166534; font-weight:700; text-decoration:underline;">Quay lại tài khoản</a>
        `;
        switchAlert.style.display = 'flex';
      } else {
        switchAlert.style.display = 'none';
      }
    }

    setAuthMode(requestedMode === 'register' ? 'register' : 'login');
  } else {
    // TRƯỜNG HỢP 2: Đã đăng nhập và truy cập account.html bình thường từ menu / cài đặt
    if (authSection) authSection.style.display = 'none';
    if (profileSection) profileSection.style.display = 'block';

    renderUserProfileCard(currentAuthenticatedUser);
  }
}

initializeAccountPage();
