/**
 * Quản trị Hệ thống MXH Góc Nhỏ - Admin Controller JavaScript (v3)
 * Kiến trúc 6 Tab Navigation, Lọc & Phân trang Server-side, Custom Modal Xác nhận & Toast.
 */

const adminBase = (typeof window.APP_URL !== 'undefined' && window.APP_URL ? window.APP_URL : (typeof APP_URL !== 'undefined' && APP_URL ? APP_URL : new URL('.', window.location.href).pathname)).replace(/\/$/, '');
const adminEscape = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

// ============================================================================
// 1. CSRF & API UTILITIES
// ============================================================================

function getAdminCsrfCookie() {
  return window.Csrf ? window.Csrf.getCookie() : (document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)?.[1] || null);
}

async function getAdminCsrfToken() {
  if (window.Csrf) {
    return window.Csrf.fetchToken(adminBase);
  }
  let token = getAdminCsrfCookie();
  if (!token) {
    try {
      const res = await fetch(`${adminBase}/index.php?url=api/csrf-token`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });
      if (res.ok) {
        const data = await res.json();
        token = data.csrf_token || getAdminCsrfCookie();
      }
    } catch (_) {}
  }
  return token || '';
}

async function adminApi(route, body = null, isRetry = false) {
  const url = new URL(adminBase + '/index.php', window.location.origin);
  const [routePath, queryString] = route.split('?');
  url.searchParams.set('url', routePath);
  if (queryString) {
    const qParams = new URLSearchParams(queryString);
    for (const [key, val] of qParams.entries()) {
      url.searchParams.set(key, val);
    }
  }
  const method = body === null ? 'GET' : 'POST';
  const headers = body === null
    ? { 'Accept': 'application/json' }
    : { 'Accept': 'application/json', 'Content-Type': 'application/json' };

  if (method === 'POST') {
    if (window.Csrf) {
      await window.Csrf.attach(headers, adminBase);
    } else {
      const token = await getAdminCsrfToken();
      if (token) headers['X-CSRF-TOKEN'] = token;
    }
  }

  const response = await fetch(url, {
    cache: 'no-store',
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
    console.error('Phản hồi không phải JSON:', text);
    throw new Error(`API trả về HTTP ${response.status}, không phải JSON: ${text.slice(0, 160)}`);
  }

  if (!response.ok) {
    if (response.status === 403 && result && result.code === 'CSRF_TOKEN_INVALID' && !isRetry) {
      if (window.Csrf) {
        window.Csrf.clear();
        await window.Csrf.fetchToken(adminBase);
      } else {
        document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/';
        await getAdminCsrfToken();
      }
      return adminApi(route, body, true);
    }
    throw new Error(result.error || `Lỗi máy chủ (${response.status})`);
  }

  if (result && result.csrf_token && window.Csrf) {
    window.Csrf.setToken(result.csrf_token);
  }
  return result;
}

// ============================================================================
// 2. TOAST NOTIFICATIONS & CONFIRM MODAL (NO DEFAULT ALERT/CONFIRM)
// ============================================================================

function showToast(message, type = 'success') {
  const container = document.getElementById('admin-toast-container');
  if (!container) return;

  const iconMap = {
    success: 'fa-solid fa-circle-check',
    error: 'fa-solid fa-circle-xmark',
    warning: 'fa-solid fa-triangle-exclamation',
    info: 'fa-solid fa-circle-info'
  };

  const toast = document.createElement('div');
  toast.className = `admin-toast toast-${type}`;
  toast.innerHTML = `<i class="${iconMap[type] || iconMap.info}"></i> <span>${adminEscape(message)}</span>`;

  container.appendChild(toast);
  setTimeout(() => {
    if (toast.parentNode) toast.parentNode.removeChild(toast);
  }, 3600);
}

let activeConfirmResolver = null;

function confirmModal(options = {}) {
  return new Promise((resolve) => {
    activeConfirmResolver = resolve;
    const modal = document.getElementById('admin-confirm-modal');
    if (!modal) {
      resolve(window.confirm(options.message || 'Xác nhận thực hiện?') ? { confirmed: true } : null);
      return;
    }

    const titleEl = document.getElementById('admin-modal-title');
    const msgEl = document.getElementById('admin-modal-message');
    const actionGroup = document.getElementById('admin-modal-action-group');
    const actionSelect = document.getElementById('admin-modal-action-select');
    const inputGroup = document.getElementById('admin-modal-input-group');
    const noteInput = document.getElementById('admin-modal-note-input');
    const confirmBtn = document.getElementById('admin-modal-confirm-btn');

    if (titleEl) titleEl.innerHTML = `<i class="fa-solid fa-shield-halved" style="color:var(--admin-primary)"></i> ${adminEscape(options.title || 'Xác nhận thao tác')}`;
    if (msgEl) msgEl.textContent = options.message || 'Bạn có chắc chắn muốn thực hiện hành động này?';

    // Action Select
    if (options.showActionSelect) {
      actionGroup.style.display = 'block';
      if (options.actionOptions) {
        actionSelect.innerHTML = options.actionOptions.map(opt => `<option value="${adminEscape(opt.value)}">${adminEscape(opt.label)}</option>`).join('');
      }
    } else {
      actionGroup.style.display = 'none';
    }

    // Note Input
    if (options.showNoteInput) {
      inputGroup.style.display = 'block';
      noteInput.value = '';
      noteInput.placeholder = options.notePlaceholder || 'Nhập lý do gửi đến người dùng (nếu có)...';
    } else {
      inputGroup.style.display = 'none';
      noteInput.value = '';
    }

    if (confirmBtn) {
      confirmBtn.textContent = options.confirmText || 'Xác nhận thực hiện';
      confirmBtn.className = `action-btn ${options.confirmBtnClass || 'btn-primary'}`;
    }

    modal.style.display = 'flex';
  });
}

function closeConfirmModal(result = null) {
  const modal = document.getElementById('admin-confirm-modal');
  if (modal) modal.style.display = 'none';
  if (activeConfirmResolver) {
    activeConfirmResolver(result);
    activeConfirmResolver = null;
  }
}

// ============================================================================
// 3. TAB STATE MANAGEMENT
// ============================================================================

const state = {
  currentTab: 'users',
  currentAdminId: null,
  users: { page: 1, per_page: 15, q: '', is_verified: '', role: '', eligible: '', sort: 'reputation_desc' },
  posts: { page: 1, per_page: 15, q: '', category: '', moderation_status: '', sort: 'newest' },
  comments: { page: 1, per_page: 15, q: '', moderation_status: '', sort: 'newest' },
  post_reports: { page: 1, per_page: 15, q: '', status: 'pending', reason: '' },
  post_appeals: { page: 1, per_page: 15, q: '', status: 'pending' },
  comment_reports: { page: 1, per_page: 15, q: '', status: 'pending', reason: '' },
  news_suggestions: { page: 1, per_page: 15, q: '', source: 'all', status: 'pending' },
  ai_moderation: { page: 1, per_page: 15, q: '', ai_severity: '', ai_violation_type: '', ai_status: '', moderation_status: '' }
};

// ============================================================================
// 4. INITIALIZATION & AUTH CHECK
// ============================================================================

document.addEventListener('DOMContentLoaded', async () => {
  // Modal buttons
  const modalClose = document.getElementById('admin-modal-close-btn');
  const modalCancel = document.getElementById('admin-modal-cancel-btn');
  const modalConfirm = document.getElementById('admin-modal-confirm-btn');

  if (modalClose) modalClose.addEventListener('click', () => closeConfirmModal(null));
  if (modalCancel) modalCancel.addEventListener('click', () => closeConfirmModal(null));
  if (modalConfirm) {
    modalConfirm.addEventListener('click', () => {
      const noteInput = document.getElementById('admin-modal-note-input');
      const actionSelect = document.getElementById('admin-modal-action-select');
      closeConfirmModal({
        confirmed: true,
        note: noteInput ? noteInput.value.trim() : '',
        action: actionSelect ? actionSelect.value : 'none'
      });
    });
  }

  // Badge history modal close
  const badgeHistoryClose = document.getElementById('badge-history-close-btn');
  const badgeHistoryOk = document.getElementById('badge-history-ok-btn');
  if (badgeHistoryClose) badgeHistoryClose.addEventListener('click', () => document.getElementById('admin-badge-history-modal').style.display = 'none');
  if (badgeHistoryOk) badgeHistoryOk.addEventListener('click', () => document.getElementById('admin-badge-history-modal').style.display = 'none');

  // Logout button
  const logoutBtn = document.getElementById('admin-logout-btn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      const conf = await confirmModal({
        title: 'Đăng xuất',
        message: 'Bạn có chắc chắn muốn đăng xuất khỏi phiên làm việc Quản trị viên?',
        confirmText: 'Đăng xuất ngay',
        confirmBtnClass: 'btn-danger'
      });
      if (!conf) return;
      try {
        await adminApi('api/auth/logout', {});
      } catch (_) {}
      window.location.href = `${adminBase}/account.html`;
    });
  }

  // Setup Tab Navigation
  setupTabs();
  setupSubtabs();
  setupFilterEvents();

  // Check auth and initial load
  try {
    const me = await adminApi('api/auth/me');
    if (!me.user) {
      showToast('Vui lòng đăng nhập với tài khoản Quản trị viên.', 'error');
      setTimeout(() => { window.location.href = `${adminBase}/account.html`; }, 1200);
      return;
    }
    if (me.user.role !== 'admin') {
      showToast('Bạn không có quyền truy cập trang quản trị.', 'error');
      setTimeout(() => { window.location.href = `${adminBase}/`; }, 1200);
      return;
    }

    state.currentAdminId = me.user.id;
    const infoContainer = document.getElementById('admin-user-info');
    if (infoContainer) {
      infoContainer.innerHTML = `Xin chào, <strong>${adminEscape(me.user.full_name)}</strong> (@${adminEscape(me.user.username)}) — <span class="badge-role-admin">Quản trị viên</span>`;
    }

    // Refresh quick stats
    loadQuickStats();

    // Check hash for initial tab
    const hash = window.location.hash.replace(/^#tab-/, '');
    const validTabs = ['users', 'posts-comments', 'post-reports', 'comment-reports', 'news-suggestions', 'ai-moderation'];
    if (validTabs.includes(hash)) {
      switchTab(hash);
    } else {
      switchTab('users');
    }
  } catch (err) {
    showToast(err.message, 'error');
  }
});

// ============================================================================
// 5. TAB & SUBTAB SWITCHING
// ============================================================================

function setupTabs() {
  const tabBtns = document.querySelectorAll('.admin-tab-btn');
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.getAttribute('data-tab');
      if (tab) switchTab(tab);
    });
  });
}

function switchTab(tabName) {
  state.currentTab = tabName;
  window.location.hash = `tab-${tabName}`;

  // Update button active state
  document.querySelectorAll('.admin-tab-btn').forEach(btn => {
    const isActive = btn.getAttribute('data-tab') === tabName;
    btn.classList.toggle('active', isActive);
    btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
  });

  // Update tab pane active state
  document.querySelectorAll('.admin-tab-pane').forEach(pane => {
    pane.classList.remove('active');
  });
  const targetPane = document.getElementById(`tab-pane-${tabName}`);
  if (targetPane) targetPane.classList.add('active');

  // Load data for active tab
  loadTabData(tabName);
}

function setupSubtabs() {
  const subtabBars = document.querySelectorAll('.admin-subtab-bar');
  subtabBars.forEach(bar => {
    const parentPane = bar.closest('.admin-tab-pane');
    const btns = bar.querySelectorAll('.subtab-btn');
    btns.forEach(btn => {
      btn.addEventListener('click', () => {
        const subtab = btn.getAttribute('data-subtab');
        btns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        if (parentPane) {
          parentPane.querySelectorAll('.subtab-pane').forEach(p => p.classList.remove('active'));
          const targetSub = parentPane.querySelector(`#subtab-pane-${subtab}`);
          if (targetSub) targetSub.classList.add('active');
        }

        if (subtab === 'posts') loadPosts();
        if (subtab === 'comments') loadComments();
        if (subtab === 'post-reports-list') loadPostReports();
        if (subtab === 'post-appeals-list') loadPostAppeals();
      });
    });
  });
}

function loadTabData(tabName) {
  switch (tabName) {
    case 'users':
      loadUsers();
      break;
    case 'posts-comments': {
      const activeSubtab = document.querySelector('#tab-pane-posts-comments .subtab-btn.active')?.getAttribute('data-subtab') || 'posts';
      if (activeSubtab === 'posts') loadPosts(); else loadComments();
      break;
    }
    case 'post-reports': {
      const activeReportSub = document.querySelector('#tab-pane-post-reports .subtab-btn.active')?.getAttribute('data-subtab') || 'post-reports-list';
      if (activeReportSub === 'post-appeals-list') {
        loadPostAppeals();
      } else {
        loadPostReports();
      }
      break;
    }
    case 'comment-reports':
      loadCommentReports();
      break;
    case 'news-suggestions':
      loadNewsSuggestions();
      break;
    case 'ai-moderation':
      loadAiModeration();
      break;
  }
}

// ============================================================================
// 6. QUICK STATS & BADGE COUNTS
// ============================================================================

async function loadQuickStats() {
  try {
    const res = await adminApi('admin?api=1');
    const data = res.data || {};
    const totalUsersEl = document.getElementById('stat-total-users');
    const totalPostsEl = document.getElementById('stat-total-posts');
    const pendingTicketsEl = document.getElementById('stat-pending-tickets');
    const pendingReportsEl = document.getElementById('stat-pending-reports');
    const flaggedPostsEl = document.getElementById('stat-flagged-posts');

    if (totalUsersEl) totalUsersEl.textContent = data.total_users ?? 0;
    if (totalPostsEl) totalPostsEl.textContent = data.total_posts ?? 0;
    if (pendingTicketsEl) pendingTicketsEl.textContent = data.pending_tickets ?? 0;
    if (flaggedPostsEl) flaggedPostsEl.textContent = data.ai_moderation_pending ?? data.flagged_posts ?? 0;

    // Load pending counts for badges (chỉ đếm trạng thái 'pending' chờ duyệt)
    const [pRep, cRep, aRep] = await Promise.all([
      adminApi('api/admin/user-reports?status=pending&per_page=1').catch(() => ({ total: data.pending_post_reports ?? 0 })),
      adminApi('api/admin/comment-reports?status=pending&per_page=1').catch(() => ({ total: data.pending_comment_reports ?? 0 })),
      adminApi('api/admin/appeals?status=pending&per_page=1').catch(() => ({ total: data.pending_appeals ?? 0 }))
    ]);

    const pendingPostReports = Number(pRep?.total ?? data.pending_post_reports ?? 0);
    const pendingCommentReports = Number(cRep?.total ?? data.pending_comment_reports ?? 0);
    const pendingAppeals = Number(aRep?.total ?? data.pending_appeals ?? 0);
    const totalPendingReports = pendingPostReports + pendingCommentReports + pendingAppeals;

    if (pendingReportsEl) pendingReportsEl.textContent = totalPendingReports;

    updateTabBadge('subtab-badge-appeals', pendingAppeals);
    updateTabBadge('tab-badge-post-reports', pendingPostReports + pendingAppeals);
    updateTabBadge('tab-badge-comment-reports', pendingCommentReports);
    updateTabBadge('tab-badge-news', data.pending_tickets);
    updateTabBadge('tab-badge-ai', data.ai_moderation_pending ?? data.flagged_posts ?? 0);
  } catch (err) {
    console.error('Không thể tải thống kê nhanh:', err);
  }
}

function updateTabBadge(badgeId, count) {
  const badge = document.getElementById(badgeId);
  if (!badge) return;
  const num = Number(count) || 0;
  if (num > 0) {
    badge.textContent = num > 99 ? '99+' : num;
    badge.style.display = 'inline-block';
  } else {
    badge.style.display = 'none';
  }
}

// ============================================================================
// 7. TAB 1: USERS & VERIFIED BADGES
// ============================================================================

async function loadUsers() {
  const tbody = document.getElementById('admin-users-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải dữ liệu thành viên...</td></tr>';

  const params = new URLSearchParams();
  const st = state.users;
  params.set('page', st.page);
  params.set('per_page', st.per_page);
  if (st.q) params.set('q', st.q);
  if (st.is_verified !== '') params.set('is_verified', st.is_verified);
  if (st.role) params.set('role', st.role);
  if (st.eligible !== '') params.set('eligible', st.eligible);
  if (st.sort) params.set('sort', st.sort);

  try {
    const res = await adminApi(`api/admin/users?${params.toString()}`);
    const users = res.data || [];
    const pagination = res.pagination || { page: 1, per_page: 15, total: 0, last_page: 1 };
    renderUsersTable(users, tbody);
    renderPagination('users-pagination', pagination, (newPage) => {
      state.users.page = newPage;
      loadUsers();
    });
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">Lỗi: ${adminEscape(err.message)}</td></tr>`;
  }
}

function renderUsersTable(users, tbody) {
  if (!users.length) {
    tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">Không tìm thấy tài khoản người dùng phù hợp.</td></tr>';
    return;
  }

  tbody.innerHTML = users.map(user => {
    const isSelf = Number(user.id) === Number(state.currentAdminId);
    const roleBadge = user.role === 'admin'
      ? '<span class="badge-role-admin"><i class="fa-solid fa-shield"></i> Quản trị viên</span>'
      : '<span class="badge-role-user">Người dùng</span>';

    const isVerified = Boolean(user.is_verified);
    const verifiedBadge = isVerified
      ? '<span class="badge-verified-yes"><i class="fa-solid fa-circle-check"></i> Đã cấp</span>'
      : '<span class="badge-verified-no">Chưa có</span>';

    const eligibleBadge = user.is_eligible
      ? '<span class="badge-eligible"><i class="fa-solid fa-star"></i> Đạt chuẩn (≥100)</span>'
      : '<span class="text-muted" style="font-size:11.5px;">Chưa đủ</span>';

    const roleBtn = isSelf
      ? '<span class="text-muted" style="font-size:11px;">(Đang đăng nhập)</span>'
      : (user.role === 'admin'
        ? `<button class="action-btn btn-warning btn-table-action" onclick="handleDemoteRole(${user.id}, '${adminEscape(user.full_name)}')"><i class="fa-solid fa-arrow-down"></i> Hạ quyền</button>`
        : `<button class="action-btn btn-primary btn-table-action" onclick="handlePromoteRole(${user.id}, '${adminEscape(user.full_name)}')"><i class="fa-solid fa-arrow-up"></i> Thăng Admin</button>`);

    const verifyBtn = `<button class="action-btn ${isVerified ? 'btn-danger' : 'btn-success'} btn-table-action" onclick="handleToggleVerify(${user.id}, ${!isVerified}, '${adminEscape(user.full_name)}')">
      <i class="fa-solid ${isVerified ? 'fa-ban' : 'fa-check'}"></i> ${isVerified ? 'Thu hồi' : 'Cấp tích xanh'}
    </button>`;

    const historyBtn = `<button class="action-btn btn-outline btn-table-action" onclick="handleViewBadgeLogs(${user.id}, '${adminEscape(user.username)}')"><i class="fa-solid fa-clock-rotate-left"></i> Lịch sử</button>`;

    return `
      <tr>
        <td>#${user.id}</td>
        <td><strong>${adminEscape(user.full_name)}</strong><br><small class="text-muted">${adminEscape(user.email)}</small></td>
        <td>@${adminEscape(user.username)}</td>
        <td>${roleBadge}</td>
        <td><strong>${user.reputation_score}</strong> pts</td>
        <td>${eligibleBadge}</td>
        <td>${verifiedBadge}</td>
        <td>${verifyBtn}</td>
        <td>${roleBtn}</td>
        <td>${historyBtn}</td>
      </tr>
    `;
  }).join('');
}

async function handleToggleVerify(userId, willVerify, userName) {
  const conf = await confirmModal({
    title: willVerify ? 'Xác nhận cấp Tích xanh' : 'Xác nhận thu hồi Tích xanh',
    message: `Bạn có chắc chắn muốn ${willVerify ? 'CẤP' : 'THU HỒI'} Tích xanh xác minh cho người dùng "${userName}"?`,
    confirmText: willVerify ? 'Cấp tích xanh' : 'Thu hồi tích xanh',
    confirmBtnClass: willVerify ? 'btn-success' : 'btn-danger',
    showNoteInput: true,
    notePlaceholder: 'Nhập lý do thực hiện để lưu vào lịch sử kiểm toán...'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/users/${userId}/verify`, {
      is_verified: willVerify,
      reason: conf.note
    });
    showToast(res.message || 'Thao tác tích xanh thành công!', 'success');
    loadUsers();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

async function handleViewBadgeLogs(userId, username) {
  const modal = document.getElementById('admin-badge-history-modal');
  const userEl = document.getElementById('badge-history-username');
  const tbody = document.getElementById('badge-history-tbody');
  if (userEl) userEl.textContent = `@${username}`;
  if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải...</td></tr>';
  if (modal) modal.style.display = 'flex';

  try {
    const res = await adminApi(`api/admin/badge-logs/${userId}`);
    const logs = res.data || [];
    if (!logs.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Chưa có lịch sử thay đổi tích xanh cho tài khoản này.</td></tr>';
      return;
    }
    tbody.innerHTML = logs.map(l => {
      const actBadge = l.action === 'grant'
        ? '<span class="badge-status-safe"><i class="fa-solid fa-circle-check"></i> Cấp tích xanh</span>'
        : '<span class="badge-status-blocked"><i class="fa-solid fa-ban"></i> Thu hồi</span>';
      return `
        <tr>
          <td>${actBadge}</td>
          <td>${adminEscape(l.reason || '(Không có lý do)')}</td>
          <td><strong>${adminEscape(l.admin_name)}</strong> (@${adminEscape(l.admin_username)})</td>
          <td>${adminEscape(l.created_at)}</td>
        </tr>
      `;
    }).join('');
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-3">Lỗi: ${adminEscape(err.message)}</td></tr>`;
  }
}

async function handlePromoteRole(userId, userName) {
  const conf = await confirmModal({
    title: 'Thăng cấp Quản trị viên',
    message: `Bạn có chắc chắn muốn thăng cấp người dùng "${userName}" thành Quản trị viên (Admin)? Tài khoản này sẽ có toàn quyền can thiệp hệ thống.`,
    confirmText: 'Thăng cấp Admin',
    confirmBtnClass: 'btn-primary'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/users/${userId}/role`, { role: 'admin' });
    showToast(res.message || 'Thăng cấp Quản trị viên thành công!', 'success');
    loadUsers();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

async function handleDemoteRole(userId, userName) {
  const conf = await confirmModal({
    title: 'Hạ quyền người dùng',
    message: `Bạn có chắc chắn muốn hạ quyền Quản trị viên của "${userName}" xuống thành Người dùng thông thường (User)?`,
    confirmText: 'Hạ quyền User',
    confirmBtnClass: 'btn-warning'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/users/${userId}/role`, { role: 'user' });
    showToast(res.message || 'Hạ quyền thành công!', 'success');
    loadUsers();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

// ============================================================================
// 8. TAB 2: POSTS & COMMENTS MANAGEMENT
// ============================================================================

async function loadPosts() {
  const tbody = document.getElementById('admin-posts-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải bài viết...</td></tr>';

  const params = new URLSearchParams();
  const st = state.posts;
  params.set('page', st.page);
  params.set('per_page', st.per_page);
  if (st.q) params.set('q', st.q);
  if (st.category) params.set('category', st.category);
  if (st.moderation_status) params.set('moderation_status', st.moderation_status);
  if (st.sort) params.set('sort', st.sort);

  try {
    const res = await adminApi(`api/admin/posts?${params.toString()}`);
    const posts = res.data || [];
    const pagination = res.pagination || { page: 1, per_page: 15, total: 0, last_page: 1 };
    renderPostsTable(posts, tbody);
    renderPagination('posts-pagination', pagination, (newPage) => {
      state.posts.page = newPage;
      loadPosts();
    });
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">Lỗi: ${adminEscape(err.message)}</td></tr>`;
  }
}

function renderPostsTable(posts, tbody) {
  if (!posts.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Không tìm thấy bài viết nào phù hợp.</td></tr>';
    return;
  }

  tbody.innerHTML = posts.map(p => {
    const catBadge = p.post_type === 'news'
      ? '<span class="badge-role-admin" style="background:#e0f2fe;color:#0284c7;"><i class="fa-solid fa-newspaper"></i> Tin tức</span>'
      : '<span class="badge-role-user"><i class="fa-solid fa-pen-nib"></i> Blog</span>';

    const statusBadge = {
      safe: '<span class="badge-status-safe"><i class="fa-solid fa-circle-check"></i> An toàn</span>',
      flagged: '<span class="badge-status-flagged"><i class="fa-solid fa-triangle-exclamation"></i> Gắn cờ</span>',
      blocked: '<span class="badge-status-blocked"><i class="fa-solid fa-ban"></i> Đã khóa</span>'
    }[p.moderation_status] || `<span class="badge-status-pending">${adminEscape(p.moderation_status)}</span>`;

    const isBlocked = p.moderation_status === 'blocked';
    const lockBtn = `<button class="action-btn ${isBlocked ? 'btn-success' : 'btn-warning'} btn-table-action" onclick="handleToggleLockPost(${p.id}, '${isBlocked ? 'safe' : 'blocked'}', '${adminEscape(p.title)}')">
      <i class="fa-solid ${isBlocked ? 'fa-unlock' : 'fa-lock'}"></i> ${isBlocked ? 'Mở khóa' : 'Khóa'}
    </button>`;

    const isNews = p.post_type === 'news';
    const typeBtn = `<button class="action-btn btn-outline btn-table-action" onclick="handleTogglePostType(${p.id}, '${isNews ? 'blog' : 'news'}', '${adminEscape(p.title)}')">
      <i class="fa-solid ${isNews ? 'fa-arrow-down' : 'fa-newspaper'}"></i> ${isNews ? 'Về Blog' : 'Lên Tin tức'}
    </button>`;

    const deleteBtn = `<button class="action-btn btn-danger btn-table-action" onclick="handleDeletePost(${p.id}, '${adminEscape(p.title)}')"><i class="fa-solid fa-trash"></i> Xóa</button>`;
    const viewBtn = `<a href="${adminBase}/post/${p.id}" target="_blank" class="action-btn btn-secondary btn-table-action"><i class="fa-solid fa-arrow-up-right-from-square"></i> Xem</a>`;

    return `
      <tr>
        <td>#${p.id}</td>
        <td><strong>${adminEscape(p.title)}</strong></td>
        <td>${catBadge}</td>
        <td>${adminEscape(p.author_name)}<br><small class="text-muted">@${adminEscape(p.author_username)}</small></td>
        <td><small><i class="fa-solid fa-eye"></i> ${p.views_count} · <i class="fa-solid fa-thumbs-up"></i> ${p.likes_count} · <i class="fa-solid fa-comment"></i> ${p.comments_count}</small></td>
        <td>${statusBadge}</td>
        <td><small class="text-muted">${adminEscape(p.created_at)}</small></td>
        <td>${viewBtn} ${lockBtn} ${typeBtn} ${deleteBtn}</td>
      </tr>
    `;
  }).join('');
}

async function handleToggleLockPost(postId, targetStatus, title) {
  const isLocking = targetStatus === 'blocked';
  const conf = await confirmModal({
    title: isLocking ? 'Khóa bài viết' : 'Mở khóa bài viết',
    message: `Bạn có chắc chắn muốn ${isLocking ? 'KHÓA' : 'MỞ KHÓA'} bài viết "${title}"?`,
    confirmText: isLocking ? 'Khóa bài viết' : 'Mở khóa',
    confirmBtnClass: isLocking ? 'btn-danger' : 'btn-success',
    showNoteInput: isLocking,
    notePlaceholder: 'Nhập lý do gửi thông báo kiểm duyệt đến tác giả...'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/posts/${postId}/moderation`, {
      status: targetStatus,
      reason: conf.note
    });
    showToast(res.message || 'Cập nhật trạng thái bài viết thành công!', 'success');
    loadPosts();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

async function handleTogglePostType(postId, targetType, title) {
  const conf = await confirmModal({
    title: 'Chuyển đổi thể loại bài viết',
    message: `Bạn có muốn chuyển bài viết "${title}" sang thể loại "${targetType === 'news' ? 'Tin tức' : 'Blog cá nhân'}"?`,
    confirmText: 'Chuyển thể loại',
    confirmBtnClass: 'btn-primary'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/posts/${postId}/type`, { post_type: targetType });
    showToast(res.message || 'Chuyển thể loại thành công!', 'success');
    loadPosts();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

async function handleDeletePost(postId, title) {
  const conf = await confirmModal({
    title: 'Xóa bài viết vĩnh viễn',
    message: `Hành động này KHÔNG THỂ HOÀN TÁC! Toàn bộ bình luận và tương tác liên quan đến bài viết "${title}" sẽ bị xóa.`,
    confirmText: 'Xóa vĩnh viễn bài viết',
    confirmBtnClass: 'btn-danger'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/posts/${postId}/delete`, {});
    showToast(res.message || 'Đã xóa bài viết thành công!', 'success');
    loadPosts();
    loadQuickStats();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

// Comments
async function loadComments() {
  const tbody = document.getElementById('admin-comments-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải bình luận...</td></tr>';

  const params = new URLSearchParams();
  const st = state.comments;
  params.set('page', st.page);
  params.set('per_page', st.per_page);
  if (st.q) params.set('q', st.q);
  if (st.moderation_status) params.set('moderation_status', st.moderation_status);
  if (st.sort) params.set('sort', st.sort);

  try {
    const res = await adminApi(`api/admin/comments?${params.toString()}`);
    const comments = res.data || [];
    const pagination = res.pagination || { page: 1, per_page: 15, total: 0, last_page: 1 };
    renderCommentsTable(comments, tbody);
    renderPagination('comments-pagination', pagination, (newPage) => {
      state.comments.page = newPage;
      loadComments();
    });
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">Lỗi: ${adminEscape(err.message)}</td></tr>`;
  }
}

function renderCommentsTable(comments, tbody) {
  if (!comments.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Không tìm thấy bình luận nào phù hợp.</td></tr>';
    return;
  }

  tbody.innerHTML = comments.map(c => {
    const isBlocked = c.moderation_status === 'blocked';
    const statusBadge = isBlocked
      ? '<span class="badge-status-blocked"><i class="fa-solid fa-eye-slash"></i> Đã ẩn</span>'
      : '<span class="badge-status-safe"><i class="fa-solid fa-eye"></i> Hiển thị</span>';

    const lockBtn = `<button class="action-btn ${isBlocked ? 'btn-success' : 'btn-warning'} btn-table-action" onclick="handleToggleLockComment(${c.id}, '${isBlocked ? 'safe' : 'blocked'}')">
      <i class="fa-solid ${isBlocked ? 'fa-eye' : 'fa-eye-slash'}"></i> ${isBlocked ? 'Hiện lại' : 'Ẩn'}
    </button>`;

    const deleteBtn = `<button class="action-btn btn-danger btn-table-action" onclick="handleDeleteComment(${c.id})"><i class="fa-solid fa-trash"></i> Xóa</button>`;

    return `
      <tr>
        <td>#${c.id}</td>
        <td><strong>${adminEscape(c.content)}</strong></td>
        <td>${adminEscape(c.author_name)}<br><small class="text-muted">@${adminEscape(c.author_username)}</small></td>
        <td><a href="${adminBase}/post/${c.post_id}" target="_blank" style="color:var(--admin-primary);text-decoration:none;">${adminEscape(c.post_title || 'Xem bài viết')}</a></td>
        <td>${statusBadge}</td>
        <td><small class="text-muted">${adminEscape(c.created_at)}</small></td>
        <td>${lockBtn} ${deleteBtn}</td>
      </tr>
    `;
  }).join('');
}

async function handleToggleLockComment(commentId, targetStatus) {
  const isHiding = targetStatus === 'blocked';
  const conf = await confirmModal({
    title: isHiding ? 'Ẩn bình luận' : 'Mở lại bình luận',
    message: `Bạn có chắc chắn muốn ${isHiding ? 'ẨN' : 'MỞ LẠI'} bình luận này?`,
    confirmText: isHiding ? 'Ẩn bình luận' : 'Hiện lại',
    confirmBtnClass: isHiding ? 'btn-warning' : 'btn-success',
    showNoteInput: isHiding,
    notePlaceholder: 'Nhập lý do gửi thông báo đến tác giả bình luận...'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/comments/${commentId}/moderation`, {
      status: targetStatus,
      reason: conf.note
    });
    showToast(res.message || 'Cập nhật trạng thái bình luận thành công!', 'success');
    loadComments();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

async function handleDeleteComment(commentId) {
  const conf = await confirmModal({
    title: 'Xóa bình luận vĩnh viễn',
    message: 'Bạn có chắc chắn muốn xóa vĩnh viễn bình luận này khỏi bài viết?',
    confirmText: 'Xóa bình luận',
    confirmBtnClass: 'btn-danger'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/comments/${commentId}/delete`, {});
    showToast(res.message || 'Đã xóa bình luận thành công!', 'success');
    loadComments();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

// ============================================================================
// 9. TAB 3: POST REPORTS
// ============================================================================

const reportReasonLabels = {
  offensive: 'Nội dung xúc phạm / thù ghét',
  spam: 'Spam / Quảng cáo rác',
  inappropriate: 'Nội dung không phù hợp',
  misinformation: 'Thông tin sai lệch',
  harassment: 'Công kích / Quấy rối',
  impersonation: 'Giả mạo người khác',
  other: 'Lý do khác'
};

async function loadPostReports() {
  const tbody = document.getElementById('admin-post-reports-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải báo cáo bài viết...</td></tr>';

  const params = new URLSearchParams();
  const st = state.post_reports;
  params.set('page', st.page);
  params.set('per_page', st.per_page);
  if (st.q) params.set('q', st.q);
  if (st.status) params.set('status', st.status);
  if (st.reason) params.set('reason', st.reason);

  try {
    const res = await adminApi(`api/admin/user-reports?${params.toString()}`);
    const reports = res.data || [];
    const pagination = res.pagination || { page: 1, per_page: 15, total: 0, last_page: 1 };
    renderPostReportsTable(reports, tbody);
    renderPagination('post-reports-pagination', pagination, (newPage) => {
      state.post_reports.page = newPage;
      loadPostReports();
    });
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">Lỗi: ${adminEscape(err.message)}</td></tr>`;
  }
}

function renderPostReportsTable(reports, tbody) {
  if (!reports.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Không có báo cáo bài viết nào phù hợp.</td></tr>';
    return;
  }

  tbody.innerHTML = reports.map(r => {
    const statusBadge = {
      pending: '<span class="badge-status-pending"><i class="fa-solid fa-clock"></i> Chờ xử lý</span>',
      reviewed: '<span class="badge-status-safe"><i class="fa-solid fa-check"></i> Đã xử lý</span>',
      dismissed: '<span class="badge-role-user"><i class="fa-solid fa-ban"></i> Đã bỏ qua</span>'
    }[r.status] || adminEscape(r.status);

    const isPending = r.status === 'pending';
    const actions = isPending
      ? `<button class="action-btn btn-primary btn-table-action" onclick="handleResolvePostReport(${r.id}, '${adminEscape(r.post_title)}')"><i class="fa-solid fa-check"></i> Xử lý</button>
         <button class="action-btn btn-outline btn-table-action" onclick="handleDismissPostReport(${r.id})"><i class="fa-solid fa-xmark"></i> Bỏ qua</button>`
      : '<span class="text-muted" style="font-size:11.5px;">Hoàn tất</span>';

    const reviewerInfo = r.reviewer_name
      ? `<strong>${adminEscape(r.reviewer_name)}</strong><br><small class="text-muted">${adminEscape(r.admin_note || '')}</small>`
      : '<span class="text-muted">-</span>';

    return `
      <tr>
        <td>#${r.id}</td>
        <td><a href="${adminBase}/post/${r.post_id}" target="_blank" style="color:var(--admin-primary);font-weight:600;">${adminEscape(r.post_title)}</a></td>
        <td>${adminEscape(r.author_name)}<br><small class="text-muted">@${adminEscape(r.author_username)}</small></td>
        <td>${adminEscape(r.reporter_name)}<br><small class="text-muted">@${adminEscape(r.username)}</small></td>
        <td><strong>${adminEscape(reportReasonLabels[r.reason] || r.reason)}</strong>${r.details ? `<br><small class="text-muted">${adminEscape(r.details)}</small>` : ''}</td>
        <td>${statusBadge}</td>
        <td>${reviewerInfo}</td>
        <td><small class="text-muted">${adminEscape(r.created_at)}</small></td>
        <td>${actions}</td>
      </tr>
    `;
  }).join('');
}

async function handleResolvePostReport(reportId, postTitle) {
  const conf = await confirmModal({
    title: 'Xử lý báo cáo bài viết vi phạm',
    message: `Xét duyệt báo cáo cho bài viết "${postTitle}". Bạn có thể chọn giữ nguyên, khóa bài hoặc xóa bài viết. Hệ thống sẽ tự động gửi thông báo kiểm duyệt ẩn danh cho tác giả.`,
    confirmText: 'Chấp nhận & Xử lý',
    confirmBtnClass: 'btn-primary',
    showActionSelect: true,
    actionOptions: [
      { value: 'none', label: '1. Chỉ duyệt báo cáo (giữ nguyên bài viết)' },
      { value: 'hide', label: '2. Khóa bài viết vi phạm (Blocked)' },
      { value: 'delete', label: '3. Xóa vĩnh viễn bài viết khỏi hệ thống' }
    ],
    showNoteInput: true,
    notePlaceholder: 'Ghi chú kiểm duyệt gửi ẩn danh cho tác giả...'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/user-reports/${reportId}/status`, {
      status: 'reviewed',
      post_action: conf.action,
      admin_note: conf.note
    });
    showToast(res.message || 'Đã xử lý báo cáo thành công!', 'success');
    loadPostReports();
    loadQuickStats();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

async function handleDismissPostReport(reportId) {
  const conf = await confirmModal({
    title: 'Bỏ qua báo cáo bài viết',
    message: 'Bạn có chắc chắn muốn bỏ qua báo cáo này? Bài viết sẽ không bị ảnh hưởng.',
    confirmText: 'Bỏ qua báo cáo',
    confirmBtnClass: 'btn-secondary',
    showNoteInput: true,
    notePlaceholder: 'Ghi chú lý do bỏ qua (lưu nội bộ)...'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/user-reports/${reportId}/status`, {
      status: 'dismissed',
      admin_note: conf.note
    });
    showToast(res.message || 'Đã bỏ qua báo cáo bài viết.', 'info');
    loadPostReports();
    loadQuickStats();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

// ============================================================================
// 9b. TAB 3 SUB-TAB: POST APPEALS (KHIẾU NẠI MỞ KHÓA BÀI VIẾT)
// ============================================================================

async function loadPostAppeals() {
  const tbody = document.getElementById('admin-post-appeals-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải danh sách khiếu nại...</td></tr>';

  const params = new URLSearchParams();
  const st = state.post_appeals;
  params.set('page', st.page);
  params.set('per_page', st.per_page);
  if (st.q) params.set('q', st.q);
  if (st.status) params.set('status', st.status);

  try {
    const res = await adminApi(`api/admin/appeals?${params.toString()}`);
    const appeals = res.data || [];
    const pagination = {
      page: res.page || 1,
      per_page: res.per_page || 15,
      total: res.total || 0,
      last_page: res.last_page || 1
    };

    renderPostAppealsTable(appeals, tbody);
    renderPagination('post-appeals-pagination', pagination, (newPage) => {
      state.post_appeals.page = newPage;
      loadPostAppeals();
    });
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">Lỗi: ${adminEscape(err.message)}</td></tr>`;
  }
}

function renderPostAppealsTable(appeals, tbody) {
  if (!appeals.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Không có khiếu nại bài viết nào.</td></tr>';
    return;
  }

  tbody.innerHTML = appeals.map(a => {
    const statusBadge = {
      pending: '<span class="badge-status-pending"><i class="fa-solid fa-clock"></i> Chờ xem xét</span>',
      approved: '<span class="badge-status-safe"><i class="fa-solid fa-check"></i> Đã chấp nhận</span>',
      rejected: '<span class="badge-status-blocked"><i class="fa-solid fa-xmark"></i> Đã từ chối</span>'
    }[a.status] || adminEscape(a.status);

    const isPending = a.status === 'pending';
    const actions = isPending
      ? `<button class="action-btn btn-primary btn-table-action" onclick="handleResolveAppeal(${a.id}, 'approved', '${adminEscape(a.post_title)}')"><i class="fa-solid fa-check"></i> Chấp nhận</button>
         <button class="action-btn btn-outline btn-table-action text-danger" onclick="handleResolveAppeal(${a.id}, 'rejected', '${adminEscape(a.post_title)}')"><i class="fa-solid fa-xmark"></i> Từ chối</button>`
      : '<span class="text-muted" style="font-size:11.5px;">Hoàn tất</span>';

    const reviewerInfo = a.reviewer_name
      ? `<strong>${adminEscape(a.reviewer_name)}</strong><br><small class="text-muted">${adminEscape(a.admin_response || '')}</small>`
      : '<span class="text-muted">-</span>';

    return `
      <tr>
        <td>#${a.id}</td>
        <td>
          <a href="${adminBase}/post/${a.post_id}" target="_blank" style="color:var(--admin-primary);font-weight:600;">
            ${adminEscape(a.post_title)}
          </a>
        </td>
        <td>
          <strong>${adminEscape(a.author_name)}</strong><br>
          <small class="text-muted">@${adminEscape(a.author_username)}</small>
        </td>
        <td>
          <span class="text-danger small fw-medium">${adminEscape(a.ai_reason || 'Vi phạm tiêu chuẩn cộng đồng')}</span>
        </td>
        <td>
          <div class="p-2 bg-light rounded small" style="max-width:280px;white-space:pre-wrap;border:1px solid #e2e8f0;">
            ${adminEscape(a.reason)}
          </div>
        </td>
        <td>${statusBadge}</td>
        <td>${reviewerInfo}</td>
        <td><small class="text-muted">${adminEscape(a.created_at)}</small></td>
        <td>${actions}</td>
      </tr>
    `;
  }).join('');
}

async function handleResolveAppeal(appealId, action, postTitle) {
  const isApprove = action === 'approved';
  const conf = await confirmModal({
    title: isApprove ? 'Chấp nhận khiếu nại mở khóa bài viết' : 'Từ chối khiếu nại bài viết',
    message: isApprove
      ? `Bạn có chắc chắn muốn CHẤP NHẬN khiếu nại cho bài viết "${postTitle}"? Bài viết sẽ được khôi phục về trạng thái an toàn (Safe) và hiển thị lại bình thường trên hệ thống.`
      : `Bạn có chắc chắn muốn TỪ CHỐI khiếu nại cho bài viết "${postTitle}"? Bài viết sẽ tiếp tục bị khóa.`,
    confirmText: isApprove ? 'Chấp nhận & Mở khóa' : 'Từ chối khiếu nại',
    confirmBtnClass: isApprove ? 'btn-primary' : 'btn-danger',
    showNoteInput: true,
    notePlaceholder: isApprove ? 'Lời nhắn gửi tác giả (tùy chọn)...' : 'Nhập lý do từ chối gửi tới tác giả bài viết...'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/appeals/${appealId}/resolve`, {
      action: action,
      admin_response: conf.note
    });
    showToast(res.message || 'Đã xử lý khiếu nại thành công!', 'success');
    loadPostAppeals();
    loadQuickStats();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

// ============================================================================
// 10. TAB 4: COMMENT REPORTS
// ============================================================================

async function loadCommentReports() {
  const tbody = document.getElementById('admin-comment-reports-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải báo cáo bình luận...</td></tr>';

  const params = new URLSearchParams();
  const st = state.comment_reports;
  params.set('page', st.page);
  params.set('per_page', st.per_page);
  if (st.q) params.set('q', st.q);
  if (st.status) params.set('status', st.status);
  if (st.reason) params.set('reason', st.reason);

  try {
    const res = await adminApi(`api/admin/comment-reports?${params.toString()}`);
    const reports = res.data || [];
    const pagination = res.pagination || { page: 1, per_page: 15, total: 0, last_page: 1 };
    renderCommentReportsTable(reports, tbody);
    renderPagination('comment-reports-pagination', pagination, (newPage) => {
      state.comment_reports.page = newPage;
      loadCommentReports();
    });
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">Lỗi: ${adminEscape(err.message)}</td></tr>`;
  }
}

function renderCommentReportsTable(reports, tbody) {
  if (!reports.length) {
    tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">Không có báo cáo bình luận nào phù hợp.</td></tr>';
    return;
  }

  tbody.innerHTML = reports.map(r => {
    const statusBadge = {
      pending: '<span class="badge-status-pending"><i class="fa-solid fa-clock"></i> Chờ xử lý</span>',
      reviewed: '<span class="badge-status-safe"><i class="fa-solid fa-check"></i> Đã xử lý</span>',
      rejected: '<span class="badge-role-user"><i class="fa-solid fa-ban"></i> Đã từ chối</span>'
    }[r.status] || adminEscape(r.status);

    const isPending = r.status === 'pending';
    const actions = isPending
      ? `<button class="action-btn btn-primary btn-table-action" onclick="handleResolveCommentReport(${r.id})"><i class="fa-solid fa-check"></i> Xử lý</button>
         <button class="action-btn btn-outline btn-table-action" onclick="handleRejectCommentReport(${r.id})"><i class="fa-solid fa-xmark"></i> Từ chối</button>`
      : '<span class="text-muted" style="font-size:11.5px;">Hoàn tất</span>';

    const reviewerInfo = r.reviewer_name
      ? `<strong>${adminEscape(r.reviewer_name)}</strong><br><small class="text-muted">${adminEscape(r.admin_note || '')}</small>`
      : '<span class="text-muted">-</span>';

    return `
      <tr>
        <td>#${r.id}</td>
        <td><strong>${adminEscape(r.comment_content)}</strong></td>
        <td><a href="${adminBase}/post/${r.post_id}" target="_blank" style="color:var(--admin-primary);text-decoration:none;">${adminEscape(r.post_title || 'Xem bài viết')}</a></td>
        <td>${adminEscape(r.comment_author_name)}<br><small class="text-muted">@${adminEscape(r.comment_author_username)}</small></td>
        <td>${adminEscape(r.reporter_name)}<br><small class="text-muted">@${adminEscape(r.reporter_username)}</small></td>
        <td><strong>${adminEscape(reportReasonLabels[r.reason] || r.reason)}</strong>${r.details ? `<br><small class="text-muted">${adminEscape(r.details)}</small>` : ''}</td>
        <td>${statusBadge}</td>
        <td>${reviewerInfo}</td>
        <td><small class="text-muted">${adminEscape(r.created_at)}</small></td>
        <td>${actions}</td>
      </tr>
    `;
  }).join('');
}

async function handleResolveCommentReport(reportId) {
  const conf = await confirmModal({
    title: 'Xử lý báo cáo bình luận',
    message: 'Xác nhận xử lý vi phạm cho bình luận này. Hệ thống sẽ gửi thông báo ẩn danh cho tác giả bình luận.',
    confirmText: 'Chấp nhận & Xử lý',
    confirmBtnClass: 'btn-primary',
    showActionSelect: true,
    actionOptions: [
      { value: 'none', label: '1. Chỉ duyệt báo cáo (giữ nguyên bình luận)' },
      { value: 'hide', label: '2. Ẩn bình luận vi phạm (Blocked)' },
      { value: 'delete', label: '3. Xóa vĩnh viễn bình luận' }
    ],
    showNoteInput: true,
    notePlaceholder: 'Ghi chú kiểm duyệt gửi ẩn danh cho tác giả bình luận...'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/comment-reports/${reportId}/status`, {
      status: 'reviewed',
      comment_action: conf.action,
      admin_note: conf.note
    });
    showToast(res.message || 'Đã xử lý báo cáo bình luận thành công!', 'success');
    loadCommentReports();
    loadQuickStats();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

async function handleRejectCommentReport(reportId) {
  const conf = await confirmModal({
    title: 'Từ chối báo cáo bình luận',
    message: 'Bạn có chắc chắn muốn từ chối báo cáo này? Bình luận vẫn sẽ hiển thị bình thường.',
    confirmText: 'Từ chối báo cáo',
    confirmBtnClass: 'btn-secondary',
    showNoteInput: true,
    notePlaceholder: 'Ghi chú lý do từ chối (lưu nội bộ)...'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/comment-reports/${reportId}/status`, {
      status: 'rejected',
      admin_note: conf.note
    });
    showToast(res.message || 'Đã từ chối báo cáo bình luận.', 'info');
    loadCommentReports();
    loadQuickStats();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

// ============================================================================
// 11. TAB 5: NEWS SUGGESTIONS (TICKET & AI)
// ============================================================================

async function loadNewsSuggestions() {
  const tbody = document.getElementById('admin-news-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải đề xuất tin tức...</td></tr>';

  const params = new URLSearchParams();
  const st = state.news_suggestions;
  params.set('page', st.page);
  params.set('per_page', st.per_page);
  if (st.q) params.set('q', st.q);
  if (st.source) params.set('source', st.source);
  if (st.status) params.set('status', st.status);

  try {
    const res = await adminApi(`api/admin/news-suggestions?${params.toString()}`);
    const items = res.data || [];
    const pagination = res.pagination || { page: 1, per_page: 15, total: 0, last_page: 1 };
    renderNewsSuggestionsTable(items, tbody);
    renderPagination('news-pagination', pagination, (newPage) => {
      state.news_suggestions.page = newPage;
      loadNewsSuggestions();
    });
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">Lỗi: ${adminEscape(err.message)}</td></tr>`;
  }
}

function renderNewsSuggestionsTable(items, tbody) {
  if (!items.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Không có đề xuất tin tức nào phù hợp.</td></tr>';
    return;
  }

  tbody.innerHTML = items.map(item => {
    const sourceBadge = item.source === 'ai'
      ? '<span class="badge-role-admin" style="background:#f3e8ff;color:#9333ea;"><i class="fa-solid fa-robot"></i> AI gợi ý</span>'
      : '<span class="badge-role-admin" style="background:#fef3c7;color:#d97706;"><i class="fa-solid fa-ticket"></i> Ticket tác giả</span>';

    const statusBadge = {
      pending: '<span class="badge-status-pending"><i class="fa-solid fa-clock"></i> Chờ duyệt</span>',
      approved: '<span class="badge-status-safe"><i class="fa-solid fa-check"></i> Đã duyệt Tin</span>',
      rejected: '<span class="badge-role-user"><i class="fa-solid fa-ban"></i> Đã từ chối</span>'
    }[item.status] || adminEscape(item.status);

    const isPending = item.status === 'pending';
    const actions = isPending
      ? `<button class="action-btn btn-success btn-table-action" onclick="handleReviewNews('${item.unified_id}', 'approved', '${adminEscape(item.post_title)}')"><i class="fa-solid fa-check"></i> Duyệt Tin</button>
         <button class="action-btn btn-danger btn-table-action" onclick="handleReviewNews('${item.unified_id}', 'rejected', '${adminEscape(item.post_title)}')"><i class="fa-solid fa-xmark"></i> Từ chối</button>`
      : '<span class="text-muted" style="font-size:11.5px;">Đã xử lý</span>';

    return `
      <tr>
        <td><code>${item.unified_id}</code></td>
        <td>${sourceBadge}</td>
        <td><a href="${adminBase}/post/${item.post_id}" target="_blank" style="color:var(--admin-primary);font-weight:600;">${adminEscape(item.post_title)}</a></td>
        <td>${adminEscape(item.author_name)}<br><small class="text-muted">@${adminEscape(item.author_username)}</small></td>
        <td><strong>${item.author_reputation}</strong> pts</td>
        <td><small>${adminEscape(item.suggestion_reason)}</small></td>
        <td>${statusBadge}</td>
        <td><small class="text-muted">${adminEscape(item.created_at)}</small></td>
        <td>${actions}</td>
      </tr>
    `;
  }).join('');
}

async function handleReviewNews(unifiedId, action, postTitle) {
  const isApprove = action === 'approved';
  const conf = await confirmModal({
    title: isApprove ? 'Duyệt bài viết lên Tin tức' : 'Từ chối đề xuất Tin tức',
    message: isApprove
      ? `Bạn có chắc chắn muốn duyệt bài viết "${postTitle}" thành TIN TỨC chính thức của hệ thống?`
      : `Bạn có muốn từ chối đề xuất đưa bài viết "${postTitle}" lên Tin tức?`,
    confirmText: isApprove ? 'Duyệt thành Tin tức' : 'Từ chối đề xuất',
    confirmBtnClass: isApprove ? 'btn-success' : 'btn-danger',
    showNoteInput: true,
    notePlaceholder: isApprove ? 'Ghi chú gửi thông báo chúc mừng tác giả...' : 'Lý do từ chối gửi đến tác giả...'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/news-suggestions/${unifiedId}/review`, {
      action,
      admin_note: conf.note
    });
    showToast(res.message || 'Xử lý đề xuất thành công!', 'success');
    loadNewsSuggestions();
    loadQuickStats();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

// ============================================================================
// 12. TAB 6: AI MODERATION ITEMS
// ============================================================================

async function loadAiModeration() {
  const tbody = document.getElementById('admin-ai-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải dữ liệu kiểm duyệt AI...</td></tr>';

  const params = new URLSearchParams();
  const st = state.ai_moderation;
  params.set('page', st.page);
  params.set('per_page', st.per_page);
  if (st.q) params.set('q', st.q);
  if (st.ai_severity) params.set('ai_severity', st.ai_severity);
  if (st.ai_violation_type) params.set('ai_violation_type', st.ai_violation_type);
  if (st.ai_status) params.set('ai_status', st.ai_status);
  if (st.moderation_status) params.set('moderation_status', st.moderation_status);

  try {
    const res = await adminApi(`api/admin/ai-moderation?${params.toString()}`);
    const items = res.data || [];
    const pagination = res.pagination || { page: 1, per_page: 15, total: 0, last_page: 1 };
    renderAiModerationTable(items, tbody);
    renderPagination('ai-pagination', pagination, (newPage) => {
      state.ai_moderation.page = newPage;
      loadAiModeration();
    });
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">Lỗi: ${adminEscape(err.message)}</td></tr>`;
  }
}

function renderAiModerationTable(items, tbody) {
  if (!items.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Không có bài viết nào cần kiểm duyệt AI.</td></tr>';
    return;
  }

  const violationLabels = {
    inappropriate: 'Ngôn từ không phù hợp',
    spam: 'Spam / Quảng cáo',
    scam: 'Lừa đảo',
    hate_speech: 'Công kích / Thù ghét',
    harassment: 'Quấy rối / Xúc phạm',
    illegal: 'Vi phạm pháp luật / Độc hại',
    gambling: 'Cờ bạc / Cá độ',
    violence: 'Bạo lực / Đe dọa',
    sensitive: 'Nội dung nhạy cảm'
  };

  const suggestionConfig = {
    high: { text: 'Khóa bài viết ngay', cls: 'suggest-high', icon: 'fa-ban' },
    medium: { text: 'Tạm ẩn chờ kiểm tra', cls: 'suggest-medium', icon: 'fa-triangle-exclamation' },
    low: { text: 'Cảnh báo nhẹ / Xem xét gỡ cờ', cls: 'suggest-low', icon: 'fa-circle-check' }
  };

  tbody.innerHTML = items.map(item => {
    const severityBadge = {
      high: '<span class="badge-severity-high"><i class="fa-solid fa-triangle-exclamation"></i> Cao (High)</span>',
      medium: '<span class="badge-severity-medium"><i class="fa-solid fa-triangle-exclamation"></i> Trung bình</span>',
      low: '<span class="badge-severity-low"><i class="fa-solid fa-circle-info"></i> Nhẹ (Low)</span>'
    }[item.ai_severity] || '<span class="text-muted">-</span>';

    const statusBadge = {
      safe: '<span class="badge-status-safe"><i class="fa-solid fa-circle-check"></i> An toàn</span>',
      flagged: '<span class="badge-status-flagged"><i class="fa-solid fa-triangle-exclamation"></i> Gắn cờ</span>',
      blocked: '<span class="badge-status-blocked"><i class="fa-solid fa-ban"></i> Đã khóa</span>'
    }[item.moderation_status] || `<span class="badge-status-pending">${adminEscape(item.moderation_status)}</span>`;

    const rawType = String(item.ai_violation_type || 'inappropriate').toLowerCase();
    const vLabel = violationLabels[rawType] || item.ai_violation_type || 'Ngôn từ không phù hợp';
    const violationBadge = `<span class="badge-violation-type badge-violation-${adminEscape(rawType)}"><i class="fa-solid fa-tag"></i> ${adminEscape(vLabel)}</span>`;

    const sug = suggestionConfig[item.ai_severity] || {
      text: item.suggested_action_text || 'Xem xét xử lý',
      cls: 'suggest-medium',
      icon: 'fa-robot'
    };
    const suggestedText = item.suggested_action_text || sug.text;
    const suggestionHtml = `
      <div class="ai-suggestion-tag ${sug.cls}">
        <i class="fa-solid ${sug.icon}"></i> <strong>Đề xuất AI:</strong> ${adminEscape(suggestedText)}
      </div>
    `;

    const reviewerInfo = item.reviewer_name
      ? `<strong>${adminEscape(item.reviewer_name)}</strong><br><small class="text-muted">${adminEscape(item.moderated_at || '')}</small>`
      : (item.ai_status === 'approved' 
          ? '<span class="badge-status-safe"><i class="fa-solid fa-check"></i> Đã duyệt</span>' 
          : '<span class="badge-status-pending"><i class="fa-solid fa-clock"></i> Chờ duyệt</span>');

    const actions = `
      <div style="display:flex;gap:4px;flex-wrap:wrap;">
        <button class="action-btn btn-success btn-table-action" title="Duyệt an toàn (Safe)" onclick="handleAiDecision(${item.id}, 'safe', '${adminEscape(item.title)}')"><i class="fa-solid fa-check"></i> Safe</button>
        <button class="action-btn btn-warning btn-table-action" title="Gắn cờ cảnh báo (Flag)" onclick="handleAiDecision(${item.id}, 'flagged', '${adminEscape(item.title)}')"><i class="fa-solid fa-flag"></i> Flag</button>
        <button class="action-btn btn-danger btn-table-action" title="Khóa bài viết (Block)" onclick="handleAiDecision(${item.id}, 'blocked', '${adminEscape(item.title)}')"><i class="fa-solid fa-lock"></i> Block</button>
        <button class="action-btn btn-outline btn-table-action" style="color:#b91c1c;" title="Xóa vĩnh viễn" onclick="handleAiDecision(${item.id}, 'delete', '${adminEscape(item.title)}')"><i class="fa-solid fa-trash"></i> Xóa</button>
      </div>
    `;

    return `
      <tr>
        <td>#${item.id}</td>
        <td><a href="${adminBase}/post/${item.id}" target="_blank" style="color:var(--admin-primary);font-weight:600;">${adminEscape(item.title)}</a></td>
        <td>${adminEscape(item.author_name)}<br><small class="text-muted">@${adminEscape(item.author_username)}</small></td>
        <td>${severityBadge}</td>
        <td>${violationBadge}</td>
        <td>
          <div class="ai-reason-text">${adminEscape(item.ai_reason || '(Không có phân tích)')}</div>
          ${suggestionHtml}
        </td>
        <td>${statusBadge}</td>
        <td>${reviewerInfo}</td>
        <td>${actions}</td>
      </tr>
    `;
  }).join('');
}

async function handleAiDecision(postId, decision, title) {
  const decisionLabels = {
    safe: 'Duyệt AN TOÀN (Safe - hiển thị bình thường)',
    flagged: 'GẮN CỜ cảnh báo nhạy cảm (Flagged)',
    blocked: 'KHÓA bài viết vi phạm (Blocked)',
    delete: 'XÓA VĨNH VIỄN bài viết khỏi hệ thống'
  };

  const isDanger = decision === 'delete' || decision === 'blocked';
  const conf = await confirmModal({
    title: `Quyết định kiểm duyệt AI: ${decision.toUpperCase()}`,
    message: `Bạn đang chọn: ${decisionLabels[decision]} cho bài viết "${title}". Hệ thống sẽ tự động cập nhật và thông báo cho tác giả.`,
    confirmText: `Xác nhận: ${decision}`,
    confirmBtnClass: isDanger ? 'btn-danger' : (decision === 'flagged' ? 'btn-warning' : 'btn-success'),
    showNoteInput: true,
    notePlaceholder: 'Ghi chú lý do quyết định kiểm duyệt gửi đến tác giả...'
  });
  if (!conf) return;

  try {
    const res = await adminApi(`api/admin/ai-moderation/${postId}/decision`, {
      decision,
      admin_note: conf.note
    });
    showToast(res.message || 'Đã áp dụng quyết định kiểm duyệt thành công!', 'success');
    loadAiModeration();
    loadQuickStats();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

// ============================================================================
// 13. PAGINATION RENDERER
// ============================================================================

function renderPagination(containerId, pagination, onPageChange) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const { page, per_page, total, last_page } = pagination;
  if (total === 0) {
    container.innerHTML = '<span class="text-muted">Không có dữ liệu</span>';
    return;
  }

  const start = Math.min((page - 1) * per_page + 1, total);
  const end = Math.min(page * per_page, total);
  const infoText = `Hiển thị <strong>${start} - ${end}</strong> trên tổng số <strong>${total}</strong> mục`;

  let buttonsHtml = '';
  // Prev
  buttonsHtml += `<button type="button" class="page-btn" ${page <= 1 ? 'disabled' : ''} data-page="${page - 1}"><i class="fa-solid fa-chevron-left"></i></button>`;

  // Page Numbers
  const maxButtons = 5;
  let startPage = Math.max(1, page - 2);
  let endPage = Math.min(last_page, startPage + maxButtons - 1);
  if (endPage - startPage < maxButtons - 1) {
    startPage = Math.max(1, endPage - maxButtons + 1);
  }

  if (startPage > 1) {
    buttonsHtml += `<button type="button" class="page-btn" data-page="1">1</button>`;
    if (startPage > 2) buttonsHtml += `<span class="page-dots">...</span>`;
  }

  for (let i = startPage; i <= endPage; i++) {
    buttonsHtml += `<button type="button" class="page-btn ${i === page ? 'active' : ''}" data-page="${i}">${i}</button>`;
  }

  if (endPage < last_page) {
    if (endPage < last_page - 1) buttonsHtml += `<span class="page-dots">...</span>`;
    buttonsHtml += `<button type="button" class="page-btn" data-page="${last_page}">${last_page}</button>`;
  }

  // Next
  buttonsHtml += `<button type="button" class="page-btn" ${page >= last_page ? 'disabled' : ''} data-page="${page + 1}"><i class="fa-solid fa-chevron-right"></i></button>`;

  container.innerHTML = `
    <div>${infoText}</div>
    <div class="pagination-controls">${buttonsHtml}</div>
  `;

  // Attach click listeners to pagination buttons
  container.querySelectorAll('.page-btn[data-page]').forEach(btn => {
    btn.addEventListener('click', () => {
      const p = parseInt(btn.getAttribute('data-page'), 10);
      if (p && p !== page && p >= 1 && p <= last_page) {
        onPageChange(p);
      }
    });
  });
}

// ============================================================================
// 14. FILTER EVENT LISTENERS
// ============================================================================

function debounce(func, wait) {
  let timeout;
  return function (...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(this, args), wait);
  };
}

function setupFilterEvents() {
  // 1. Users Filters
  const uSearch = document.getElementById('users-search');
  const uVer = document.getElementById('users-filter-verified');
  const uRole = document.getElementById('users-filter-role');
  const uElig = document.getElementById('users-filter-eligible');
  const uSort = document.getElementById('users-filter-sort');
  const uReset = document.getElementById('users-btn-reset');

  if (uSearch) uSearch.addEventListener('input', debounce((e) => { state.users.q = e.target.value.trim(); state.users.page = 1; loadUsers(); }, 350));
  if (uVer) uVer.addEventListener('change', (e) => { state.users.is_verified = e.target.value; state.users.page = 1; loadUsers(); });
  if (uRole) uRole.addEventListener('change', (e) => { state.users.role = e.target.value; state.users.page = 1; loadUsers(); });
  if (uElig) uElig.addEventListener('change', (e) => { state.users.eligible = e.target.value; state.users.page = 1; loadUsers(); });
  if (uSort) uSort.addEventListener('change', (e) => { state.users.sort = e.target.value; state.users.page = 1; loadUsers(); });
  if (uReset) uReset.addEventListener('click', () => {
    state.users = { page: 1, per_page: 15, q: '', is_verified: '', role: '', eligible: '', sort: 'reputation_desc' };
    if (uSearch) uSearch.value = '';
    if (uVer) uVer.value = '';
    if (uRole) uRole.value = '';
    if (uElig) uElig.value = '';
    if (uSort) uSort.value = 'reputation_desc';
    loadUsers();
    showToast('Đã đặt lại bộ lọc người dùng.', 'info');
  });

  // 2. Posts Filters
  const pSearch = document.getElementById('posts-search');
  const pCat = document.getElementById('posts-filter-category');
  const pStat = document.getElementById('posts-filter-status');
  const pSort = document.getElementById('posts-filter-sort');
  const pReset = document.getElementById('posts-btn-reset');

  if (pSearch) pSearch.addEventListener('input', debounce((e) => { state.posts.q = e.target.value.trim(); state.posts.page = 1; loadPosts(); }, 350));
  if (pCat) pCat.addEventListener('change', (e) => { state.posts.category = e.target.value; state.posts.page = 1; loadPosts(); });
  if (pStat) pStat.addEventListener('change', (e) => { state.posts.moderation_status = e.target.value; state.posts.page = 1; loadPosts(); });
  if (pSort) pSort.addEventListener('change', (e) => { state.posts.sort = e.target.value; state.posts.page = 1; loadPosts(); });
  if (pReset) pReset.addEventListener('click', () => {
    state.posts = { page: 1, per_page: 15, q: '', category: '', moderation_status: '', sort: 'newest' };
    if (pSearch) pSearch.value = '';
    if (pCat) pCat.value = '';
    if (pStat) pStat.value = '';
    if (pSort) pSort.value = 'newest';
    loadPosts();
    showToast('Đã đặt lại bộ lọc bài viết.', 'info');
  });

  // 3. Comments Filters
  const cSearch = document.getElementById('comments-search');
  const cStat = document.getElementById('comments-filter-status');
  const cSort = document.getElementById('comments-filter-sort');
  const cReset = document.getElementById('comments-btn-reset');

  if (cSearch) cSearch.addEventListener('input', debounce((e) => { state.comments.q = e.target.value.trim(); state.comments.page = 1; loadComments(); }, 350));
  if (cStat) cStat.addEventListener('change', (e) => { state.comments.moderation_status = e.target.value; state.comments.page = 1; loadComments(); });
  if (cSort) cSort.addEventListener('change', (e) => { state.comments.sort = e.target.value; state.comments.page = 1; loadComments(); });
  if (cReset) cReset.addEventListener('click', () => {
    state.comments = { page: 1, per_page: 15, q: '', moderation_status: '', sort: 'newest' };
    if (cSearch) cSearch.value = '';
    if (cStat) cStat.value = '';
    if (cSort) cSort.value = 'newest';
    loadComments();
    showToast('Đã đặt lại bộ lọc bình luận.', 'info');
  });

  // 4. Post Reports Filters
  const prSearch = document.getElementById('post-reports-search');
  const prStat = document.getElementById('post-reports-filter-status');
  const prReason = document.getElementById('post-reports-filter-reason');
  const prReset = document.getElementById('post-reports-btn-reset');

  if (prSearch) prSearch.addEventListener('input', debounce((e) => { state.post_reports.q = e.target.value.trim(); state.post_reports.page = 1; loadPostReports(); }, 350));
  if (prStat) prStat.addEventListener('change', (e) => { state.post_reports.status = e.target.value; state.post_reports.page = 1; loadPostReports(); });
  if (prReason) prReason.addEventListener('change', (e) => { state.post_reports.reason = e.target.value; state.post_reports.page = 1; loadPostReports(); });
  if (prReset) prReset.addEventListener('click', () => {
    state.post_reports = { page: 1, per_page: 15, q: '', status: '', reason: '' };
    if (prSearch) prSearch.value = '';
    if (prStat) prStat.value = '';
    if (prReason) prReason.value = '';
    loadPostReports();
    showToast('Đã đặt lại bộ lọc báo cáo bài viết.', 'info');
  });

  // 4b. Post Appeals Filters
  const paSearch = document.getElementById('post-appeals-search');
  const paStat = document.getElementById('post-appeals-filter-status');
  const paReset = document.getElementById('post-appeals-btn-reset');

  if (paSearch) paSearch.addEventListener('input', debounce((e) => { state.post_appeals.q = e.target.value.trim(); state.post_appeals.page = 1; loadPostAppeals(); }, 350));
  if (paStat) paStat.addEventListener('change', (e) => { state.post_appeals.status = e.target.value; state.post_appeals.page = 1; loadPostAppeals(); });
  if (paReset) paReset.addEventListener('click', () => {
    state.post_appeals = { page: 1, per_page: 15, q: '', status: '' };
    if (paSearch) paSearch.value = '';
    if (paStat) paStat.value = '';
    loadPostAppeals();
    showToast('Đã đặt lại bộ lọc khiếu nại bài viết.', 'info');
  });

  // 5. Comment Reports Filters
  const crSearch = document.getElementById('comment-reports-search');
  const crStat = document.getElementById('comment-reports-filter-status');
  const crReason = document.getElementById('comment-reports-filter-reason');
  const crReset = document.getElementById('comment-reports-btn-reset');

  if (crSearch) crSearch.addEventListener('input', debounce((e) => { state.comment_reports.q = e.target.value.trim(); state.comment_reports.page = 1; loadCommentReports(); }, 350));
  if (crStat) crStat.addEventListener('change', (e) => { state.comment_reports.status = e.target.value; state.comment_reports.page = 1; loadCommentReports(); });
  if (crReason) crReason.addEventListener('change', (e) => { state.comment_reports.reason = e.target.value; state.comment_reports.page = 1; loadCommentReports(); });
  if (crReset) crReset.addEventListener('click', () => {
    state.comment_reports = { page: 1, per_page: 15, q: '', status: '', reason: '' };
    if (crSearch) crSearch.value = '';
    if (crStat) crStat.value = '';
    if (crReason) crReason.value = '';
    loadCommentReports();
    showToast('Đã đặt lại bộ lọc báo cáo bình luận.', 'info');
  });

  // 6. News Suggestions Filters
  const nsSearch = document.getElementById('news-search');
  const nsSource = document.getElementById('news-filter-source');
  const nsStat = document.getElementById('news-filter-status');
  const nsReset = document.getElementById('news-btn-reset');

  if (nsSearch) nsSearch.addEventListener('input', debounce((e) => { state.news_suggestions.q = e.target.value.trim(); state.news_suggestions.page = 1; loadNewsSuggestions(); }, 350));
  if (nsSource) nsSource.addEventListener('change', (e) => { state.news_suggestions.source = e.target.value; state.news_suggestions.page = 1; loadNewsSuggestions(); });
  if (nsStat) nsStat.addEventListener('change', (e) => { state.news_suggestions.status = e.target.value; state.news_suggestions.page = 1; loadNewsSuggestions(); });
  if (nsReset) nsReset.addEventListener('click', () => {
    state.news_suggestions = { page: 1, per_page: 15, q: '', source: 'all', status: '' };
    if (nsSearch) nsSearch.value = '';
    if (nsSource) nsSource.value = 'all';
    if (nsStat) nsStat.value = '';
    loadNewsSuggestions();
    showToast('Đã đặt lại bộ lọc đề xuất tin tức.', 'info');
  });

  // 7. AI Moderation Filters
  const aiSearch = document.getElementById('ai-search');
  const aiSev = document.getElementById('ai-filter-severity');
  const aiVType = document.getElementById('ai-filter-violation-type');
  const aiModStat = document.getElementById('ai-filter-mod-status');
  const aiStat = document.getElementById('ai-filter-ai-status');
  const aiReset = document.getElementById('ai-btn-reset');

  if (aiSearch) aiSearch.addEventListener('input', debounce((e) => { state.ai_moderation.q = e.target.value.trim(); state.ai_moderation.page = 1; loadAiModeration(); }, 350));
  if (aiSev) aiSev.addEventListener('change', (e) => { state.ai_moderation.ai_severity = e.target.value; state.ai_moderation.page = 1; loadAiModeration(); });
  if (aiVType) aiVType.addEventListener('change', (e) => { state.ai_moderation.ai_violation_type = e.target.value; state.ai_moderation.page = 1; loadAiModeration(); });
  if (aiModStat) aiModStat.addEventListener('change', (e) => { state.ai_moderation.moderation_status = e.target.value; state.ai_moderation.page = 1; loadAiModeration(); });
  if (aiStat) aiStat.addEventListener('change', (e) => { state.ai_moderation.ai_status = e.target.value; state.ai_moderation.page = 1; loadAiModeration(); });
  if (aiReset) aiReset.addEventListener('click', () => {
    state.ai_moderation = { page: 1, per_page: 15, q: '', ai_severity: '', ai_violation_type: '', ai_status: '', moderation_status: '' };
    if (aiSearch) aiSearch.value = '';
    if (aiSev) aiSev.value = '';
    if (aiVType) aiVType.value = '';
    if (aiModStat) aiModStat.value = '';
    if (aiStat) aiStat.value = '';
    loadAiModeration();
    showToast('Đã đặt lại bộ lọc kiểm duyệt AI.', 'info');
  });
}
