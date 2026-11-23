const deviceAccountsBase = new URL('.', window.location.href).pathname.replace(/\/$/, '');
const deviceList = document.getElementById('device-account-list');
const passwordModal = document.getElementById('password-modal');
const passwordInput = document.getElementById('device-password');
const loginForm = document.getElementById('device-login-form');
let selectedDeviceAccount = null;
let activeDeviceUser = null;
const DEVICE_AVATAR_VERSION = Date.now();

const deviceEscape = value => String(value ?? '').replace(/[&<>"']/g, char => ({
  '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'
}[char]));

function deviceAvatarUrl(value) {
  const raw = String(value || 'assets/images/avatars/default.svg').trim();
  if (/^(data:|blob:|https?:|\/\/)/i.test(raw)) return raw;
  const path = raw.startsWith('/') && /^\/(assets|uploads)\//i.test(raw) ? raw.slice(1) : raw;
  try {
    const url = new URL(path, `${window.location.origin}${deviceAccountsBase}/`);
    if (url.origin === window.location.origin && (url.pathname.includes('/uploads/') || url.pathname.includes('/assets/images/avatars/'))) {
      url.searchParams.set('avatar_v', String(DEVICE_AVATAR_VERSION));
    }
    return url.href;
  } catch (_) { return `${deviceAccountsBase}/assets/images/avatars/default.svg?v=${DEVICE_AVATAR_VERSION}`; }
}

document.addEventListener('error', event => {
  const image = event.target;
  if (!(image instanceof HTMLImageElement) || !image.matches('.account-avatar')) return;
  const fallback = `${deviceAccountsBase}/assets/images/avatars/default.svg?v=${DEVICE_AVATAR_VERSION}`;
  if (image.src !== fallback) { image.onerror = null; image.src = fallback; }
}, true);

function getDeviceCsrfCookie() {
  return window.Csrf ? window.Csrf.getCookie() : (document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)?.[1] || null);
}

async function getDeviceCsrfToken() {
  if (window.Csrf) {
    return window.Csrf.fetchToken(deviceAccountsBase);
  }
  let token = getDeviceCsrfCookie();
  if (!token) {
    try {
      const res = await fetch(`${deviceAccountsBase}/index.php?url=api/csrf-token`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });
      if (res.ok) {
        const data = await res.json();
        token = data.csrf_token || getDeviceCsrfCookie();
      }
    } catch (_) {}
  }
  return token || '';
}

async function deviceApi(route, body = null, isRetry = false) {
  const url = new URL(`${deviceAccountsBase}/index.php`, window.location.origin);
  url.searchParams.set('url', route);
  const method = body === null ? 'GET' : 'POST';
  const headers = body === null ? {'Accept':'application/json'} : {'Accept':'application/json','Content-Type':'application/json'};
  if (method === 'POST') {
    if (window.Csrf) {
      await window.Csrf.attach(headers, deviceAccountsBase);
    } else {
      const token = await getDeviceCsrfToken();
      if (token) headers['X-CSRF-TOKEN'] = token;
    }
  }
  const response = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers,
    body: body === null ? undefined : JSON.stringify(body),
    cache: 'no-store'
  });
  const text = await response.text();
  let result;
  try { result = JSON.parse(text); }
  catch (_) { throw new Error(`API returned HTTP ${response.status}, not JSON: ${text.slice(0, 120)}`); }
  if (!response.ok) {
    if (response.status === 403 && result && result.code === 'CSRF_TOKEN_INVALID' && !isRetry) {
      if (window.Csrf) {
        window.Csrf.clear();
        await window.Csrf.fetchToken(deviceAccountsBase);
      } else {
        document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/';
        await getDeviceCsrfToken();
      }
      return deviceApi(route, body, true);
    }
    throw new Error(result.error || `Request failed (HTTP ${response.status}).`);
  }
  if (result && result.csrf_token && window.Csrf) {
    window.Csrf.setToken(result.csrf_token);
  }
  return result;
}

function renderDeviceAccounts() {
  const accounts = getRememberedAccounts();
  const loginUrl = window.APP_URL ? `${window.APP_URL}/account` : 'account.html';
  if (!accounts.length) {
    deviceList.innerHTML = `<div class="empty-state"><strong>Chưa có tài khoản được ghi nhớ</strong>Đăng nhập một tài khoản trước, tài khoản đó sẽ xuất hiện ở đây.<br><br><a class="login-button" href="${loginUrl}">Đến trang đăng nhập</a></div>`;
    return;
  }
  deviceList.innerHTML = accounts.map(account => {
    const current = activeDeviceUser && activeDeviceUser.username === account.username;
    const avatar = deviceEscape(deviceAvatarUrl(account.avatar));
    const role = account.role === 'admin' ? 'Quản trị viên' : 'Thành viên';
    return `<article class="account-card" data-username="${deviceEscape(account.username)}" title="Bấm để đăng nhập bằng tài khoản ${deviceEscape(account.fullName || account.username)}">
      <img class="account-avatar" src="${avatar}" alt="">
      <div class="account-info"><span class="account-name">${deviceEscape(account.fullName || account.username)}</span><span class="account-handle">@${deviceEscape(account.username)} · ${role}</span></div>
      <div class="account-actions">
        ${current ? '<span class="current-badge">ĐANG ĐĂNG NHẬP</span>' : `<button class="login-button" type="button" data-login="${deviceEscape(account.username)}">Đăng nhập</button>`}
        <button class="remove-button" type="button" data-remove="${deviceEscape(account.username)}">Xóa khỏi máy</button>
      </div>
    </article>`;
  }).join('');
}

function openPasswordDialog(account) {
  selectedDeviceAccount = account;
  document.getElementById('dialog-title').textContent = `Xin chào, ${account.fullName || account.username}`;
  document.getElementById('dialog-username').textContent = `@${account.username} · Nhập mật khẩu để tiếp tục`;
  const avatar = document.getElementById('dialog-avatar');
  if (avatar) {
    avatar.src = deviceAvatarUrl(account.avatar);
    avatar.alt = account.fullName || account.username;
    avatar.style.display = 'block';
  }
  document.getElementById('login-message').textContent = '';
  passwordInput.value = '';
  passwordModal.hidden = false;
  setTimeout(() => passwordInput.focus(), 60);
}

function closePasswordDialog() {
  passwordModal.hidden = true;
  selectedDeviceAccount = null;
}

deviceList.addEventListener('click', async event => {
  const removeButton = event.target.closest('[data-remove]');
  if (removeButton) {
    event.stopPropagation();
    const username = removeButton.dataset.remove;
    const account = getRememberedAccounts().find(item => item.username === username);
    if (!account || !window.confirm(`Xóa @${account.username} khỏi thiết bị này?${activeDeviceUser?.username === username ? '\nPhiên đăng nhập hiện tại cũng sẽ kết thúc.' : ''}\nTài khoản trên máy chủ sẽ không bị xóa.`)) return;
    if (activeDeviceUser?.username === username) {
      try {
        await deviceApi('api/auth/logout', {});
      } catch (error) {
        window.alert(`Không thể đăng xuất tài khoản: ${error.message}`);
        return;
      }
      activeDeviceUser = null;
    }
    removeRememberedAccount(username);
    renderDeviceAccounts();
    return;
  }

  // Click vào bất kỳ vị trí nào trên thẻ tài khoản hoặc nút đăng nhập đều mở hộp thoại nhập mật khẩu ngay
  const card = event.target.closest('.account-card');
  if (card) {
    const username = card.dataset.username || card.querySelector('[data-login]')?.dataset.login;
    if (username) {
      const account = getRememberedAccounts().find(item => item.username === username);
      if (account) openPasswordDialog(account);
    }
  }
});

loginForm.addEventListener('submit', async event => {
  event.preventDefault();
  if (!selectedDeviceAccount) return;
  const message = document.getElementById('login-message');
  const submit = document.getElementById('login-submit');
  message.textContent = '';
  submit.disabled = true;
  submit.textContent = 'Đang đăng nhập…';
  try {
    const result = await deviceApi('api/auth/login', {
      login: selectedDeviceAccount.username,
      username: selectedDeviceAccount.username,
      password: passwordInput.value
    });
    rememberAccount(result.user);
    window.location.href = result.user.role === 'admin' ? 'admin' : 'index.html';
  } catch (error) {
    message.textContent = error.message;
    passwordInput.focus();
    passwordInput.select();
  } finally {
    submit.disabled = false;
    submit.textContent = 'Đăng nhập';
  }
});

document.getElementById('close-password-modal').addEventListener('click', closePasswordDialog);
passwordModal.addEventListener('click', event => { if (event.target === passwordModal) closePasswordDialog(); });
document.addEventListener('keydown', event => { if (event.key === 'Escape') closePasswordDialog(); });
document.getElementById('toggle-password').addEventListener('click', event => {
  const visible = passwordInput.type === 'password';
  passwordInput.type = visible ? 'text' : 'password';
  event.currentTarget.textContent = visible ? 'Ẩn' : 'Hiện';
});

async function initializeDeviceAccounts() {
  try {
    const session = await deviceApi('api/auth/me');
    activeDeviceUser = session.user || null;
    if (activeDeviceUser) rememberAccount(activeDeviceUser);
  } catch (_) {}
  const remembered = getRememberedAccounts();
  const refreshed = await Promise.all(remembered.map(async account => {
    if (!account.id) return account;
    try {
      const result = await deviceApi(`api/users/${Number(account.id)}`);
      const user = result.user;
      if (!user) return account;
      return { ...account, fullName: user.full_name || account.fullName, avatar: user.avatar || account.avatar, role: user.role || account.role };
    } catch (_) { return account; }
  }));
  try { localStorage.setItem(REMEMBERED_ACCOUNTS_KEY, JSON.stringify(refreshed)); } catch (_) {}
  renderDeviceAccounts();
  const requestedUsername = new URLSearchParams(window.location.search).get('account');
  if (requestedUsername) {
    const account = getRememberedAccounts().find(item => item.username === requestedUsername);
    if (account) openPasswordDialog(account);
  }
}

initializeDeviceAccounts();
