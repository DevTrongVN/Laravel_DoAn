const REMEMBERED_ACCOUNTS_KEY = 'goc-nho-remembered-accounts';
function getRememberedAccounts() {
  try {
    const accounts = JSON.parse(localStorage.getItem(REMEMBERED_ACCOUNTS_KEY) || '[]');
    return Array.isArray(accounts) ? accounts.filter(account => account && account.username) : [];
  } catch (_) { return []; }
}
function rememberAccount(user) {
  if (!user || !user.username) return;
  try {
    const accounts = getRememberedAccounts().filter(account => account.username !== user.username && Number(account.id) !== Number(user.id));
    accounts.unshift({ id: Number(user.id), username: user.username, fullName: user.full_name || user.username,
      avatar: user.avatar || '', role: user.role || 'user' });
    localStorage.setItem(REMEMBERED_ACCOUNTS_KEY, JSON.stringify(accounts.slice(0, 10)));
  } catch (_) {}
}
function switchToRememberedAccount(username) {
  const account = getRememberedAccounts().find(item => item.username === username);
  if (!account) {
    window.location.href = 'accounts.html';
    return;
  }
  window.location.href = `accounts.html?account=${encodeURIComponent(account.username)}`;
}

let CURRENT_USER_ID = null;
let CURRENT_USER_REPUTATION = 0;
let CURRENT_USER_ROLE = 'guest';
window.mockPosts = Array.isArray(window.mockPosts) ? window.mockPosts : [];
const APP_URL = (function () {
  let base = '';
  if (typeof window !== 'undefined' && window.APP_URL) {
    const raw = String(window.APP_URL).trim();
    if (raw.startsWith('http://') || raw.startsWith('https://')) {
      try {
        base = new URL(raw).pathname.replace(/\/$/, '');
      } catch (_) {
        base = raw.replace(/\/$/, '');
      }
    } else {
      base = raw.replace(/\/$/, '');
    }
  }
  if (!base) {
    try {
      const scriptEl = document.currentScript || document.querySelector('script[src*="assets/js/"]');
      if (scriptEl && scriptEl.src) {
        base = new URL('../..', scriptEl.src).pathname.replace(/\/$/, '');
      }
    } catch (_) {}
  }
  if (!base && typeof location !== 'undefined') {
    const match = location.pathname.match(/^(.*?\/public)(?:\/|$)/);
    base = match ? match[1].replace(/\/$/, '') : '';
  }
  if (!base || base === '/') base = '';
  if (typeof window !== 'undefined') window.APP_URL = base;
  return base;
})();
const AVATAR_CACHE_VERSION = Date.now();
function avatarUrl(value) {
  const raw = String(value || '').trim();
  const fallback = `${APP_URL}/assets/images/avatars/default.svg`;
  if (!raw) return `${fallback}?v=${AVATAR_CACHE_VERSION}`;
  if (/^(data:|blob:|https?:|\/\/)/i.test(raw)) return raw;
  const path = raw.startsWith('/') && /^\/(assets|uploads)\//i.test(raw) ? raw.slice(1) : raw;
  try {
    const url = new URL(path, `${window.location.origin}${APP_URL}/`);
    if (url.origin === window.location.origin && (url.pathname.includes('/uploads/') || url.pathname.includes('/assets/images/avatars/'))) {
      url.searchParams.set('avatar_v', String(AVATAR_CACHE_VERSION));
    }
    return url.href;
  } catch (_) { return `${fallback}?v=${AVATAR_CACHE_VERSION}`; }
}
document.addEventListener('error', event => {
  const image = event.target;
  if (!(image instanceof HTMLImageElement) || !image.matches('.fb-avatar-img,.fb-switch-user-avatar,.fb-avatar-52,.profile-avatar-large,.profile-avatar-lg,.avatar,.avatar-sm,.post-avatar,.comment-avatar,.user-avatar-sm,.notification-avatar,.activity-avatar,.account-avatar')) return;
  const fallback = avatarUrl('assets/images/avatars/default.svg');
  if (image.src !== fallback) { image.onerror = null; image.src = fallback; }
}, true);
const POST_REACTIONS = {
  like: { emoji: '👍', label: 'Đã thích' },
  love: { emoji: '❤️', label: 'Yêu thích' },
  haha: { emoji: '😄', label: 'Haha' },
  sad: { emoji: '😢', label: 'Buồn' },
  angry: { emoji: '😠', label: 'Phẫn nộ' }
};

const SVG_ICONS = {
  comment: '<svg class="btn-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
  share: '<svg class="btn-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>',
  bookmark: (active = false) => `<svg class="btn-icon-svg icon-bookmark-svg" width="14" height="14" viewBox="0 0 24 24" fill="${active ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>`,
  ticket: '<svg class="btn-icon-svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 11v2"/><path d="M13 17v2"/></svg>',
  link: '<svg class="btn-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
  repost: '<svg class="btn-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>'
};

function getCsrfCookie() {
  return window.Csrf ? window.Csrf.getCookie() : (document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)?.[1] || null);
}

async function getValidCsrfToken(appBase = APP_URL) {
  if (window.Csrf) {
    return window.Csrf.fetchToken(appBase);
  }
  let token = getCsrfCookie();
  if (!token) {
    try {
      const res = await fetch(`${appBase}/index.php?url=api/csrf-token`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });
      if (res.ok) {
        const data = await res.json();
        token = data.csrf_token || getCsrfCookie();
      }
    } catch (_) {}
  }
  return token || '';
}

async function apiRequest(route, { method = 'GET', body = null, params = {} } = {}) {
  const endpoint = new URL(`${APP_URL}/index.php`, window.location.origin);
  endpoint.searchParams.set('url', route);
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '' && key !== '__csrf_retry') endpoint.searchParams.set(key, value);
  });
  const headers = body === null ? {} : { 'Content-Type': 'application/json' };
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
    if (window.Csrf) {
      await window.Csrf.attach(headers, APP_URL);
    } else {
      const token = await getValidCsrfToken(APP_URL);
      if (token) headers['X-CSRF-TOKEN'] = token;
    }
  }
  const response = await fetch(endpoint, {
    method,
    headers,
    credentials: 'same-origin',
    body: body === null ? undefined : JSON.stringify(body)
  });
  const text = await response.text();
  let result;
  try {
    result = JSON.parse(text);
  } catch (e) {
    throw new Error('Lỗi định dạng phản hồi từ máy chủ: ' + text.slice(0, 100));
  }
  if (!response.ok) {
    if (response.status === 403 && result && result.code === 'CSRF_TOKEN_INVALID' && !params.__csrf_retry) {
      if (window.Csrf) {
        window.Csrf.clear();
        await window.Csrf.fetchToken(APP_URL);
      } else {
        document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/';
        await getValidCsrfToken(APP_URL);
      }
      return apiRequest(route, { method, body, params: { ...params, __csrf_retry: true } });
    }
    throw new Error(result.error || 'Không thể kết nối máy chủ.');
  }
  if (result && result.csrf_token && window.Csrf) {
    window.Csrf.setToken(result.csrf_token);
  }
  return result;
}

function escapeHtml(value) {
  const str = String(value ?? '');
  const normalized = typeof str.normalize === 'function' ? str.normalize('NFC') : str;
  return normalized.replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
}

function reactionMarkup(post) {
  const reactionKey = post.my_reaction || null;
  const reaction = reactionKey && POST_REACTIONS[reactionKey] ? POST_REACTIONS[reactionKey] : POST_REACTIONS.like;
  const active = Boolean(reactionKey);
  const reactedClass = active ? 'active is-reacted' : '';
  const reactionAttr = active ? `data-reaction="${escapeHtml(reactionKey)}"` : '';
  const choices = Object.entries(POST_REACTIONS).map(([key, item]) =>
    `<button type="button" class="reaction-choice" title="${item.label}" aria-label="${item.label}" onclick="setPostReaction(${Number(post.id)}, '${key}', event)">${item.emoji}</button>`
  ).join('');

  // Breakdown tooltip hiển thị từng loại cảm xúc
  const counts = post.reaction_counts || {};
  const breakdownParts = [];
  for (const [key, item] of Object.entries(POST_REACTIONS)) {
    const c = Number(counts[key] || 0);
    if (c > 0) breakdownParts.push(`${item.emoji} ${c}`);
  }
  const breakdownText = breakdownParts.length > 0 ? breakdownParts.join(' · ') : 'Chưa có tương tác';

  return `<div class="reaction-control" data-post-id="${Number(post.id)}"
      onmouseenter="handleReactionControlEnter(event)"
      onmouseleave="handleReactionControlLeave(event)"
      onpointerdown="startReactionPress(event)"
      onpointerup="endReactionPress(event)"
      onpointercancel="endReactionPress(event)">
    <button type="button" class="stat-btn reaction-trigger ${reactedClass}" ${reactionAttr} onclick="event.preventDefault(); event.stopPropagation(); toggleLike(${Number(post.id)}, this, event)" title="${escapeHtml(breakdownText)}" aria-label="Cảm xúc bài viết">
      <span class="reaction-icon">${active ? reaction.emoji : '👍'}</span>
      <span class="likes-count">${Number(post.likes_count ?? post.like_count ?? post.total_reactions ?? post.reaction_count ?? 0)}</span>
      <span class="reaction-label">${active ? reaction.label : 'Thích'}</span>
    </button>
    <div class="reaction-picker" role="group" aria-label="Chọn cảm xúc">${choices}</div>
    <div class="reaction-tooltip">${escapeHtml(breakdownText)}</div>
  </div>`;
}

/**
 * Render nút thả cảm xúc chuẩn 5 loại cho bình luận (Thích, Yêu thích, Haha, Buồn, Phẫn nộ).
 * Hỗ trợ icon, số lượng, tooltip phân bổ cảm xúc, sự kiện tương tác và trạng thái .is-reacted.
 */
function commentReactionMarkup(comment, postId = null) {
  if (!comment) return '';

  const commentId = Number(comment.id || 0);
  const targetPostId = Number(postId ?? comment.post_id ?? 0);
  const currentReaction = comment.my_reaction || comment.reaction || null;
  const isReacted = Boolean(currentReaction && POST_REACTIONS[currentReaction]);
  const reactionConfig = isReacted ? POST_REACTIONS[currentReaction] : POST_REACTIONS.like;
  const emoji = isReacted ? reactionConfig.emoji : '👍';
  const label = isReacted ? reactionConfig.label : 'Thích';
  const reactedClass = isReacted ? 'active is-reacted' : '';
  const reactionAttr = isReacted ? `data-reaction="${escapeHtml(currentReaction)}"` : '';
  const likesCount = Number(comment.likes_count ?? comment.likes ?? comment.total_reactions ?? comment.like_count ?? 0);

  // Danh sách các lựa chọn cảm xúc
  const choices = Object.entries(POST_REACTIONS).map(([key, item]) =>
    `<button type="button" class="reaction-choice" title="${escapeHtml(item.label)}" aria-label="${escapeHtml(item.label)}" onclick="event.preventDefault(); event.stopPropagation(); setCommentReaction(${commentId}, ${targetPostId}, '${key}', event, this)">${item.emoji}</button>`
  ).join('');

  // Breakdown tooltip hiển thị từng loại cảm xúc
  const counts = comment.reaction_counts || {};
  const breakdownParts = [];
  for (const [key, item] of Object.entries(POST_REACTIONS)) {
    const c = Number(counts[key] || 0);
    if (c > 0) breakdownParts.push(`${item.emoji} ${c}`);
  }
  const breakdownText = breakdownParts.length > 0 ? breakdownParts.join(' · ') : (likesCount > 0 ? `👍 ${likesCount}` : 'Chưa có tương tác');

  return `<div class="reaction-control comment-reaction-control" data-comment-id="${commentId}"
      title="${escapeHtml(breakdownText)}"
      onmouseenter="handleReactionControlEnter(event)"
      onmouseleave="handleReactionControlLeave(event)"
      onpointerdown="startReactionPress(event)"
      onpointerup="endReactionPress(event)"
      onpointercancel="endReactionPress(event)">
    <button type="button" class="comment-action reaction-trigger ${reactedClass}" ${reactionAttr}
        onclick="event.preventDefault(); event.stopPropagation(); likeComment(${commentId}, ${targetPostId}, '${escapeHtml(currentReaction || '')}', this, event)">
      <span class="reaction-icon">${emoji}</span>
      <span class="reaction-label">${label}</span>
      <span class="likes-count">· ${likesCount}</span>
    </button>
    <div class="reaction-picker" role="group" aria-label="Chọn cảm xúc">${choices}</div>
  </div>`;
}
window.commentReactionMarkup = commentReactionMarkup;
window.profileCommentReactionMarkup = commentReactionMarkup;

// Xử lý giữ hover & đóng mở picker mượt mà với độ trễ (grace delay ~280ms)
const reactionHideTimeouts = new WeakMap();

function handleReactionControlEnter(event) {
  const control = event?.currentTarget?.closest?.('.reaction-control') || event?.currentTarget;
  if (!control) return;
  const timer = reactionHideTimeouts.get(control);
  if (timer) {
    clearTimeout(timer);
    reactionHideTimeouts.delete(control);
  }
  control.classList.add('show-reactions');
}

function handleReactionControlLeave(event) {
  const control = event?.currentTarget?.closest?.('.reaction-control') || event?.currentTarget;
  if (!control) return;
  const timer = setTimeout(() => {
    control.classList.remove('show-reactions');
    reactionHideTimeouts.delete(control);
  }, 280);
  reactionHideTimeouts.set(control, timer);
}

function closeReactionPicker(control) {
  if (!control) return;
  const timer = reactionHideTimeouts.get(control);
  if (timer) {
    clearTimeout(timer);
    reactionHideTimeouts.delete(control);
  }
  control.classList.remove('show-reactions');
  if (document.activeElement && control.contains(document.activeElement)) {
    document.activeElement.blur();
  }
}
window.handleReactionControlEnter = handleReactionControlEnter;
window.handleReactionControlLeave = handleReactionControlLeave;
window.closeReactionPicker = closeReactionPicker;

let reactionPressTimer = null;
function startReactionPress(event) {
  const wrapper = event.currentTarget;
  if (!wrapper) return;
  clearTimeout(reactionPressTimer);
  wrapper.dataset.longPressTriggered = '0';
  reactionPressTimer = setTimeout(() => {
    wrapper.classList.add('show-reactions');
    wrapper.dataset.longPressTriggered = '1';
  }, 450);
}
function endReactionPress(event) {
  clearTimeout(reactionPressTimer);
  const wrapper = event?.currentTarget;
  if (!wrapper || event.target.closest('.reaction-choice')) return;
  if (wrapper.dataset.longPressTriggered === '1') {
    wrapper.dataset.suppressLikeClick = '1';
    setTimeout(() => delete wrapper.dataset.suppressLikeClick, 900);
  }
}
window.startReactionPress = startReactionPress;
window.endReactionPress = endReactionPress;

/**
 * Cập nhật đồng bộ DOM hiển thị cảm xúc bài viết ở mọi vị trí trên trang
 * (Feed trang chủ, Trang chi tiết bài viết #post-detail-actions, Pop-up Modal)
 */
function updatePostReactionDom(postId, data) {
  const numericId = Number(postId);
  const myReaction = data.my_reaction || null;
  const likesCount = Number(data.likes_count ?? data.likes ?? 0);
  const reactionCounts = data.reaction_counts || {};

  const reactionCfg = myReaction && POST_REACTIONS[myReaction] ? POST_REACTIONS[myReaction] : null;
  const isReacted = Boolean(myReaction && reactionCfg);

  // Tính lại chuỗi phân bổ breakdown tooltip
  const breakdownParts = [];
  for (const [key, item] of Object.entries(POST_REACTIONS)) {
    const c = Number(reactionCounts[key] || 0);
    if (c > 0) breakdownParts.push(`${item.emoji} ${c}`);
  }
  const breakdownText = breakdownParts.length > 0 ? breakdownParts.join(' · ') : 'Chưa có tương tác';

  // Gom toàn bộ cụm .reaction-control tương ứng bài viết này
  const controls = new Set();
  document.querySelectorAll(`.reaction-control[data-post-id="${numericId}"]`).forEach(el => controls.add(el));

  const feedCard = document.getElementById(`post-card-${numericId}`);
  if (feedCard) {
    const ctrl = feedCard.querySelector('.reaction-control');
    if (ctrl) controls.add(ctrl);
  }

  const detailView = document.getElementById('post-detail');
  if (detailView && Number(detailView.dataset.postId) === numericId) {
    const detailActions = document.getElementById('post-detail-actions');
    if (detailActions) {
      const ctrl = detailActions.querySelector('.reaction-control');
      if (ctrl) controls.add(ctrl);
    }
  }

  const modal = document.getElementById('post-modal');
  if (modal && Number(modal.dataset.postId) === numericId) {
    const ctrl = modal.querySelector('.reaction-control');
    if (ctrl) controls.add(ctrl);
  }

  controls.forEach(ctrl => {
    ctrl.dataset.postId = String(numericId);
    const trigger = ctrl.querySelector('.reaction-trigger');
    if (!trigger) return;

    if (isReacted) {
      trigger.classList.add('is-reacted', 'active');
      trigger.setAttribute('data-reaction', myReaction);
    } else {
      trigger.classList.remove('is-reacted', 'active');
      trigger.removeAttribute('data-reaction');
    }

    const iconEl = trigger.querySelector('.reaction-icon');
    if (iconEl) {
      iconEl.textContent = isReacted ? reactionCfg.emoji : '👍';
    }

    const labelEl = trigger.querySelector('.reaction-label');
    if (labelEl) {
      labelEl.textContent = isReacted ? reactionCfg.label : 'Thích';
    }

    const countEl = trigger.querySelector('.likes-count');
    if (countEl) {
      countEl.textContent = Number(likesCount).toLocaleString('vi-VN');
    }

    trigger.setAttribute('title', breakdownText);
    const tooltipEl = ctrl.querySelector('.reaction-tooltip');
    if (tooltipEl) {
      tooltipEl.textContent = breakdownText;
    }
  });

  // Đồng bộ trong bộ nhớ cache mockPosts (nếu có)
  if (typeof mockPosts !== 'undefined' && Array.isArray(mockPosts)) {
    const postItem = mockPosts.find(p => Number(p.id) === numericId);
    if (postItem) {
      postItem.my_reaction = myReaction;
      postItem.is_liked = isReacted;
      postItem.likes_count = likesCount;
      postItem.reaction_counts = reactionCounts;
    }
  }
}
window.updatePostReactionDom = updatePostReactionDom;

async function setPostReaction(postId, reaction, event) {
  if (event) {
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
    if (typeof event.preventDefault === 'function') event.preventDefault();
  }

  const numericId = Number(postId);
  const targetControl = event?.target?.closest?.('.reaction-control') ||
    document.querySelector(`.reaction-control[data-post-id="${numericId}"]`) ||
    document.querySelector(`#post-card-${numericId} .reaction-control`);

  // Đóng bảng chọn ngay lập tức
  if (targetControl) {
    closeReactionPicker(targetControl);
    targetControl.classList.add('reaction-closing');
    setTimeout(() => targetControl.classList.remove('reaction-closing'), 300);
  }

  const trigger = targetControl?.querySelector('.reaction-trigger');
  const currentReaction = trigger?.getAttribute('data-reaction') || null;

  // Nếu bấm lại chính cảm xúc đang chọn -> gỡ bỏ cảm xúc (null)
  let nextReaction = reaction;
  if (reaction && reaction === currentReaction) {
    nextReaction = null;
  }

  try {
    const res = await apiRequest(`api/posts/${numericId}/reaction`, {
      method: 'POST',
      body: { reaction: nextReaction }
    });

    if (res && res.success) {
      updatePostReactionDom(numericId, res);
    }
  } catch (error) {
    console.error('Lỗi khi gửi cảm xúc:', error);
    if (error.message && (error.message.includes('Đăng nhập') || error.message.includes('401'))) {
      if (confirm('Bạn cần đăng nhập để thả cảm xúc. Đến trang đăng nhập ngay?')) {
        window.location.href = 'account.html?mode=login';
      }
    } else {
      alert(error.message || 'Không thể cập nhật cảm xúc. Vui lòng thử lại.');
    }
  }
}
window.setPostReaction = setPostReaction;

// ========================================================
// ĐIỀU HƯỚNG BÀI VIẾT & QUẢN LÝ TRẠNG THÁI (BFCACHE / LOCK)
// ========================================================
let isNavigating = false;
let currentOpeningPostId = null;

function getPostDetailUrl(postId) {
  const numericId = Number(postId);
  const base = (typeof APP_URL !== 'undefined' && APP_URL) ? String(APP_URL).replace(/\/$/, '') : '';
  if (typeof window !== 'undefined' && window.location.pathname.includes('index.php')) {
    return `${base}/index.php?url=post/${numericId}`;
  }
  return `${base}/post/${numericId}`;
}
window.getPostDetailUrl = getPostDetailUrl;

function navigateToPost(postId) {
  const numericId = Number(postId);
  if (!numericId || isNaN(numericId)) return;

  // Bỏ qua click đúp liên tục vào chính bài viết đó trong tích tắc
  if (isNavigating && currentOpeningPostId === numericId) return;

  isNavigating = true;
  currentOpeningPostId = numericId;

  // Tự động giải phóng cờ sau 1200ms để không bao giờ bị kẹt
  setTimeout(() => {
    isNavigating = false;
    currentOpeningPostId = null;
  }, 1200);

  const targetUrl = getPostDetailUrl(numericId);
  window.location.href = targetUrl;
}
window.navigateToPost = navigateToPost;

function getBookmarksUrl() {
  const base = (typeof APP_URL !== 'undefined' && APP_URL) ? String(APP_URL).replace(/\/$/, '') : '';
  if (typeof window !== 'undefined' && window.location.pathname.includes('index.php')) {
    return `${base}/index.php?url=bookmarks`;
  }
  return `${base}/bookmarks`;
}
window.getBookmarksUrl = getBookmarksUrl;

function navigateToBookmarks(event) {
  if (event && event.preventDefault) event.preventDefault();
  if (!CURRENT_USER_ID) {
    alert('Vui lòng đăng nhập để xem danh sách bài viết đã lưu.');
    window.location.href = 'account.html?mode=login';
    return;
  }
  window.location.href = getBookmarksUrl();
}
window.navigateToBookmarks = navigateToBookmarks;

/**
 * Đóng sạch mọi modal và giải phóng hoàn toàn các cờ khóa trạng thái
 * (Dùng khi người dùng bấm Back từ bfcache hoặc popstate)
 */
function resetNavigationAndModals() {
  isNavigating = false;
  currentOpeningPostId = null;

  document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.classList.remove('active');
    modal.style.display = 'none';
    modal.style.pointerEvents = 'none';
    modal.style.visibility = 'hidden';
    delete modal.dataset.postId;
  });
  document.body.style.overflow = '';
}
window.resetNavigationAndModals = resetNavigationAndModals;

// Lắng nghe pageshow (đặc biệt khi browser phục hồi từ bfcache) và popstate
window.addEventListener('pageshow', (event) => {
  resetNavigationAndModals();
});
window.addEventListener('popstate', (event) => {
  resetNavigationAndModals();
});

// Event Delegation toàn cục bắt click mở bài viết ổn định 100%
document.addEventListener('click', (event) => {
  // Cho phép mở tab mới nếu người dùng nhấn chuột giữa hoặc giữ Ctrl/Cmd/Shift
  if (event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) return;

  // TUYỆT ĐỐI KHÔNG kích hoạt mở bài viết nếu người dùng click trúng bất kỳ nút bấm, link hoặc cụm tương tác nào
  if (event.target.closest('button, input, textarea, select, details, summary, .post-actions, .post-interactive-stats, .reaction-control, .reaction-picker, .reaction-choice, .reaction-trigger, .stat-btn, .action-btn, .btn-bookmark, .stat-btn-bookmark, .action-btn-bookmark, .comment-menu, .post-hashtags, [onclick]:not(.cursor-pointer):not(.post-title):not(.post-body)')) {
    return;
  }

  // 1. Click vào liên kết .post-card-link
  const cardLink = event.target.closest('.post-card-link');
  if (cardLink) {
    const postId = cardLink.dataset.postId || cardLink.closest('.post-card')?.dataset.postId;
    if (postId) {
      event.preventDefault();
      navigateToPost(postId);
      return;
    }
  }

  // 2. Click vào tiêu đề, ảnh thumbnail hoặc thân bài viết mà không phải nút tương tác
  const postTitle = event.target.closest('.post-title');
  const postBody = event.target.closest('.post-body');
  const postImg = event.target.closest('.post-thumbnail, .post-image');

  if (postTitle || postBody || postImg) {
    const card = event.target.closest('.post-card');
    const postId = card?.dataset?.postId || card?.id?.replace(/^post-card-/, '');
    if (postId) {
      event.preventDefault();
      navigateToPost(postId);
    }
  }
});

let feedState = {
  tab: 'home',
  filters: { mode: 'all' },
  page: 1,
  perPage: 10,
  seed: Math.floor(Math.random() * 1000000),
  hasMore: true,
  isLoading: false,
  total: 0
};

async function loadBackendFeed(params = {}, isAppend = false) {
  const container = document.getElementById('feed-container');
  if (!container) return [];

  if (feedState.isLoading) return [];
  feedState.isLoading = true;

  const loadMoreBtn = document.getElementById('btn-load-more-feed');
  const spinner = document.getElementById('feed-loading-spinner');
  const endMessage = document.getElementById('feed-end-message');

  if (isAppend) {
    if (loadMoreBtn) loadMoreBtn.style.display = 'none';
    if (spinner) spinner.style.display = 'inline-flex';
  }

  // Merge filters nếu là tải mới
  if (!isAppend) {
    feedState.page = params.page || 1;
    feedState.filters = { ...params };
    delete feedState.filters.page;
    delete feedState.filters.per_page;
    delete feedState.filters.seed;
    if (params.seed !== undefined) {
      feedState.seed = params.seed;
    }

    // Tự động nhận diện bộ lọc nhanh trên trang chủ nếu có
    const typeSelect = document.getElementById('feed-filter-type');
    const imageSelect = document.getElementById('feed-filter-has-image');
    if (typeSelect && typeSelect.value !== 'all' && !feedState.filters.type) {
      feedState.filters.type = typeSelect.value;
      typeSelect.classList.add('is-filtered');
    }
    if (imageSelect && imageSelect.value !== 'all' && !feedState.filters.has_image) {
      feedState.filters.has_image = imageSelect.value;
      imageSelect.classList.add('is-filtered');
    }
  }

  const queryParams = {
    ...feedState.filters,
    page: feedState.page,
    per_page: feedState.perPage,
    seed: feedState.seed
  };

  try {
    const result = await apiRequest('api/feed', { params: queryParams });
    const posts = result.data || [];
    const pagination = result.pagination || {};

    feedState.hasMore = Boolean(pagination.has_more);
    feedState.total = Number(pagination.total || 0);
    if (pagination.seed !== undefined && pagination.seed !== null) {
      feedState.seed = pagination.seed;
    }

    window.mockPosts = Array.isArray(window.mockPosts) ? window.mockPosts : [];
    if (!isAppend) {
      window.mockPosts.splice(0, window.mockPosts.length, ...posts);
      renderFeed(window.mockPosts.length > 0 ? window.mockPosts : posts);
    } else {
      window.mockPosts.push(...posts);
      appendFeed(posts);
    }

    if (loadMoreBtn) loadMoreBtn.style.display = feedState.hasMore ? 'inline-flex' : 'none';
    if (spinner) spinner.style.display = 'none';
    if (endMessage) endMessage.style.display = (!feedState.hasMore && (posts?.length || window.mockPosts?.length || 0) > 0) ? 'block' : 'none';

    renderTopNewsWidget();
    renderTopHashtagsWidget();
    return posts;
  } catch (err) {
    console.error('Lỗi khi tải bảng tin:', err);
    if (!isAppend && container) {
      container.innerHTML = `<div class="post-card text-center py-4 text-danger"><p>Không thể tải bài viết: ${escapeHtml(err.message)}</p></div>`;
    }
    return [];
  } finally {
    feedState.isLoading = false;
    if (spinner) spinner.style.display = 'none';
    if (loadMoreBtn) loadMoreBtn.style.display = feedState.hasMore ? 'inline-flex' : 'none';
  }
}

async function loadMoreFeedPosts() {
  if (feedState.isLoading || !feedState.hasMore) return;
  feedState.page += 1;
  await loadBackendFeed({ ...feedState.filters, page: feedState.page }, true);
}
window.loadMoreFeedPosts = loadMoreFeedPosts;

async function refreshFeedNew() {
  const refreshBtn = document.getElementById('btn-feed-refresh');
  if (refreshBtn) refreshBtn.classList.add('is-refreshing');

  // Khởi tạo seed ngẫu nhiên mới hoàn toàn để đổi danh sách bài viết
  feedState.seed = Math.floor(Math.random() * 1000000);
  feedState.page = 1;
  feedState.hasMore = true;

  try {
    await loadBackendFeed({ ...feedState.filters, page: 1, seed: feedState.seed }, false);
    if (typeof showToast === 'function') {
      showToast('Đã làm mới bảng tin và xáo trộn bài viết mới!', 'success');
    }
  } catch (err) {
    if (typeof showToast === 'function') {
      showToast(err.message, 'error');
    }
  } finally {
    if (refreshBtn) {
      setTimeout(() => refreshBtn.classList.remove('is-refreshing'), 450);
    }
  }
}
window.refreshFeedNew = refreshFeedNew;

function renderAboutAuthorCard(authorUser) {
  if (!authorUser) return;
  const fullName = authorUser.full_name || authorUser.username || 'RobloxAdmin';
  const bio = authorUser.bio && String(authorUser.bio).trim()
    ? String(authorUser.bio).trim()
    : ((authorUser.role === 'admin' || Number(authorUser.id) === 1)
        ? 'Quản trị viên hệ thống Góc Nhỏ · Đam mê kiến trúc phần mềm, AI & công nghệ mã nguồn mở.'
        : 'Thành viên Góc Nhỏ · Yêu thích chia sẻ kiến thức, viết lách và kết nối cộng đồng.');
  const avatar = avatarUrl(authorUser.avatar || '/assets/images/avatars/user-demo.svg');
  const profileId = authorUser.id || 1;

  document.querySelectorAll('.about-author-avatar').forEach(img => {
    img.src = avatar;
    img.alt = `Chân dung ${fullName}`;
  });
  document.querySelectorAll('.about-card h2, .about-card .about-title').forEach(el => {
    el.textContent = `Xin chào, mình là ${fullName}.`;
  });
  document.querySelectorAll('.about-card p, .about-card .about-bio').forEach(el => {
    el.textContent = bio;
  });
  document.querySelectorAll('.about-card a.about-link, .about-card a.text-link').forEach(link => {
    link.href = `profile.html?id=${profileId}`;
  });
  const starSvg = `<span class="about-sig-star"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block;vertical-align:baseline;margin-left:3px;" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>`;
  document.querySelectorAll('.about-signature').forEach(sig => {
    sig.innerHTML = `<span class="about-author-name">${escapeHtml(fullName)}</span> ${starSvg}`;
  });

  const isSelf = activeUser && Number(activeUser.id) === Number(profileId);
  document.querySelectorAll('.about-card').forEach(card => {
    let editBioWrap = card.querySelector('.about-edit-bio-wrap');
    if (isSelf) {
      if (!editBioWrap) {
        editBioWrap = document.createElement('div');
        editBioWrap.className = 'about-edit-bio-wrap';
        editBioWrap.style.cssText = 'margin: 8px auto 12px auto; text-align: center;';
        editBioWrap.innerHTML = `
          <button type="button" class="about-edit-bio-btn" onclick="openBioSettingsDirect(event)" title="Thay đổi cài đặt tài khoản của bạn" style="border:1.5px solid #3e6652;background:#f0fdf4;color:#1e4d35;border-radius:18px;padding:5px 16px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 1px 2px rgba(0,0,0,0.05);transition:all 0.2s;">
            <i class="fa-solid fa-gear"></i> Thay đổi cài đặt
          </button>
        `;
        const bioP = card.querySelector('.about-bio') || card.querySelector('p');
        if (bioP) {
          bioP.after(editBioWrap);
        } else {
          card.appendChild(editBioWrap);
        }
      }
    } else if (editBioWrap) {
      editBioWrap.remove();
    }
  });
}
window.renderAboutAuthorCard = renderAboutAuthorCard;

async function initializeBackend() {
  let user = null;
  try {
    const session = await apiRequest('api/auth/me');
    user = session?.user || null;
  } catch (err) {
    console.warn('Khách chưa đăng nhập hoặc không lấy được session:', err);
  }

  try {
    if (user) {
      rememberAccount(user);
      initNotificationBell(user);
    } else {
      const notifWrapper = document.getElementById('nav-notification-wrapper');
      if (notifWrapper) notifWrapper.style.display = 'none';
    }
    CURRENT_USER_ID = user ? Number(user.id) : null;
    CURRENT_USER_REPUTATION = user ? Number(user.reputation_score || 0) : 0;
    CURRENT_USER_ROLE = user?.role || 'guest';
    setupNewsPublishingAccess();
    
    setupUserDropdown(user);
    setupSettingsModal(user);
    const authorCardElements = document.querySelectorAll('.about-card, .about-author-avatar');
    if (authorCardElements.length) {
      if (user) {
        // Đồng bộ thẻ người viết blog với tài khoản đang đăng nhập (ví dụ: Kanz)
        renderAboutAuthorCard(user);
      } else {
        // Khách chưa đăng nhập: lấy thông tin Quản trị viên từ cơ sở dữ liệu
        try {
          const authorRes = await apiRequest('api/author');
          if (authorRes && authorRes.user) {
            renderAboutAuthorCard(authorRes.user);
          } else {
            const fallbackRes = await apiRequest('api/users/1');
            if (fallbackRes && fallbackRes.user) {
              renderAboutAuthorCard(fallbackRes.user);
            }
          }
        } catch (_) {
          try {
            const fallbackRes = await apiRequest('api/users/1');
            if (fallbackRes && fallbackRes.user) {
              renderAboutAuthorCard(fallbackRes.user);
            }
          } catch (_) { /* Keep the local default portrait and text if the author profile is unavailable. */ }
        }
      }
    }

    const adminLink = document.querySelector('.blog-admin-link');
    if (adminLink) {
      adminLink.style.display = 'inline-flex';
      adminLink.style.alignItems = 'center';
      adminLink.style.gap = '6px';
      if (user && user.role === 'admin') {
        adminLink.innerHTML = '<i class="fa-solid fa-sliders"></i> <span>Quản trị (Admin)</span>';
        adminLink.title = 'Trang quản trị (Admin Dashboard)';
      } else {
        adminLink.innerHTML = '<i class="fa-solid fa-sliders"></i> <span>Quản trị</span>';
        adminLink.title = 'Trang quản trị';
      }
    }
  } catch (err) {
    console.warn('Lỗi khi thiết lập giao diện người dùng:', err);
  }

  // Tải bảng tin độc lập, luôn luôn chạy bất kể trạng thái đăng nhập
  try {
    const urlParams = new URLSearchParams(window.location.search);
    const initialHashtag = urlParams.get('hashtag');
    const initialTab = urlParams.get('tab');
    if (initialHashtag) {
      const searchInput = document.getElementById("smart-search-input");
      if (searchInput) searchInput.value = `#${initialHashtag}`;
      await loadBackendFeed({ hashtag: initialHashtag });
    } else if (initialTab) {
      filterNavFeed(initialTab);
    } else {
      await loadBackendFeed();
    }
  } catch (feedError) {
    console.error('Không tải được bài viết bảng tin:', feedError);
  }

  // Tải hashtag & sidebar độc lập
  try {
    const hashtags = await apiRequest('api/hashtags');
    if (typeof mockHashtags !== 'undefined' && Array.isArray(mockHashtags)) {
      mockHashtags.splice(0, mockHashtags.length, ...(hashtags?.data || []));
    }
    renderTopHashtagsWidget();
    renderLeftSidebar();
  } catch (widgetError) {
    console.error('Không tải được widget hashtags:', widgetError);
  }
}

async function initializePostDetail() {
  const detail = document.getElementById('post-detail');
  if (!detail) return;
  const postId = Number(detail.dataset.postId);
  if (!postId || isNaN(postId)) return;
  try {
    const response = await apiRequest(`api/posts/${postId}`);
    const post = response?.data;
    if (!post) return;
    const actions = document.getElementById('post-detail-actions');
    const ownerActions = Number(post.user_id) === Number(CURRENT_USER_ID)
      ? `<details class="comment-menu post-menu"><summary aria-label="Tùy chọn bài viết">•••</summary><div class="comment-menu-items"><button type="button" onclick="this.closest('details').open=false; editPost(${postId})">Sửa bài viết</button><button type="button" class="danger" onclick="this.closest('details').open=false; deletePost(${postId})">Xóa bài viết</button></div></details>`
      : (CURRENT_USER_ID !== null ? `<button type="button" class="stat-btn" onclick="reportPost(${postId})"><span class="report-exclamation-icon" aria-hidden="true">!</span> Báo cáo</button>` : '');
    if (actions) actions.innerHTML = `${reactionMarkup(post)}<button type="button" class="stat-btn stat-btn-share" data-post-id="${postId}" onclick="sharePost(${postId})">${SVG_ICONS.share} <span class="shares-count">${Number(post.shares_count || 0)}</span> Chia sẻ</button><button type="button" class="stat-btn stat-btn-bookmark ${post.is_bookmarked ? 'active' : ''}" data-post-id="${postId}" onclick="toggleBookmark(${postId}, this)">${SVG_ICONS.bookmark(post.is_bookmarked)} <span>${post.is_bookmarked ? 'Đã lưu' : 'Lưu'}</span></button>${ownerActions}`;
    const list = document.getElementById('comment-list');
    if (list) list.innerHTML = renderCommentThreads(post.comments || [], post);
    const heading = document.querySelector('.comment-section-title');
    if (heading) heading.textContent = `Bình luận (${(post.comments || []).filter(c => !c.is_deleted).length})`;
  } catch (error) {
    console.error('Không tải được nội dung tương tác bài viết:', error);
  }
}
window.initializePostDetail = initializePostDetail;
window.loadPostDetailActions = initializePostDetail;

async function submitComment(event, postId) {
  event.preventDefault();
  const input = document.getElementById(`comment-input-${postId}`);
  const parentInput = document.getElementById(`comment-parent-id-${postId}`);
  const rawValue = input?.value.trim() || '';
  if (!rawValue) return;
  const content = typeof window.serializeMentionsFromInput === 'function'
    ? window.serializeMentionsFromInput(input, rawValue).trim()
    : rawValue;
  if (!content) return;
  const parentId = parentInput?.value ? Number(parentInput.value) : null;
  try {
    await apiRequest(`api/posts/${postId}/comments`, { method: 'POST', body: { content, parent_id: parentId } });
    if (input) {
      input.value = '';
      if (typeof window.clearInputMentions === 'function') window.clearInputMentions(input);
    }
    if (typeof cancelReplyComment === 'function') cancelReplyComment(postId);
    await initializePostDetail();
  } catch (error) { alert(error.message); }
}

function setupNewsPublishingAccess() {
  const select = document.getElementById('post-type-select');
  if (!select) return;
  const option = select.querySelector('option[value="news"]');
  if (!option) return;
  const canPublishNews = CURRENT_USER_REPUTATION >= 500 || CURRENT_USER_ROLE === 'admin';
  option.disabled = !canPublishNews;
  option.hidden = !canPublishNews;
  let note = document.getElementById('news-access-note');
  if (!note) {
    note = document.createElement('small');
    note.id = 'news-access-note';
    note.className = 'news-access-note';
    select.insertAdjacentElement('afterend', note);
  }
  note.textContent = canPublishNews
    ? 'Tài khoản VIP: có thể đăng Tin tức trực tiếp.'
    : CURRENT_USER_REPUTATION >= 100
      ? `Bạn có ${CURRENT_USER_REPUTATION} điểm; có thể gửi Ticket duyệt Blog thành Tin tức.`
      : `Cần 100 điểm để gửi Ticket (hiện có ${CURRENT_USER_REPUTATION}).`;
}

let activeUser = null;

function slideFbMenu(target) {
  const track = document.getElementById('fb-slider-track');
  if (!track) return;
  if (target === 'settings') {
    track.style.transform = 'translateX(-33.333333%)';
    const settingsPanel = document.getElementById('fb-panel-settings');
    if (settingsPanel) settingsPanel.scrollTop = 0;
  } else if (target === 'switch') {
    track.style.transform = 'translateX(-66.666666%)';
    const switchPanel = document.getElementById('fb-panel-switch');
    if (switchPanel) switchPanel.scrollTop = 0;
  } else {
    track.style.transform = 'translateX(0%)';
    const mainPanel = document.getElementById('fb-panel-main');
    if (mainPanel) mainPanel.scrollTop = 0;
  }
}
window.slideFbMenu = slideFbMenu;

function closeFacebookDropdown() {
  const dropdownPanel = document.getElementById('fb-dropdown-panel') || document.getElementById('user-dropdown-menu');
  const avatarBtn = document.getElementById('fb-avatar-trigger') || document.getElementById('user-menu-btn');
  if (dropdownPanel) dropdownPanel.style.display = 'none';
  if (avatarBtn) avatarBtn.setAttribute('aria-expanded', 'false');
  slideFbMenu('main');
}
window.closeFacebookDropdown = closeFacebookDropdown;

async function handleFbChangeName(event) {
  if (event) event.preventDefault();
  const nameInput = document.getElementById('fb-set-fullname');
  const userInput = document.getElementById('fb-set-username');
  const bioInput = document.getElementById('fb-set-bio');
  const feedback = document.getElementById('fb-feedback-name');
  const saveBtn = document.getElementById('fb-btn-save-name');
  if (!nameInput || !userInput || !feedback) return;

  const fullName = nameInput.value.trim();
  const username = userInput.value.trim();
  const bio = bioInput ? bioInput.value.trim() : undefined;

  if (!fullName) {
    feedback.className = 'fb-feedback-msg error';
    feedback.textContent = 'Vui lòng nhập họ và tên.';
    return;
  }
  if (!username || !/^[A-Za-z0-9_]{3,40}$/.test(username)) {
    feedback.className = 'fb-feedback-msg error';
    feedback.textContent = 'Tên đăng nhập cần 3-40 ký tự (chữ, số và dấu gạch dưới).';
    return;
  }

  if (saveBtn) {
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';
  }

  try {
    const payload = { full_name: fullName, username };
    if (typeof bio !== 'undefined') payload.bio = bio;
    const avatar = document.querySelector('#fb-avatar-picker [data-avatar-value]')?.value;
    if (avatar) payload.avatar = avatar;
    const res = await apiRequest('api/auth/profile', { method: 'POST', body: payload });
    feedback.className = 'fb-feedback-msg success';
    feedback.textContent = 'Đã cập nhật thông tin thành công!';
    if (res.user) {
      activeUser = res.user;
      rememberAccount(res.user);
      const avatarSrc = avatarUrl(res.user.avatar);
      document.querySelectorAll('#fb-nav-avatar, .fb-avatar-52').forEach(image => { image.src = avatarSrc; });
      const nameDisplays = document.querySelectorAll('.fb-full-name');
      nameDisplays.forEach(el => { el.textContent = res.user.full_name; });
      const handleDisplays = document.querySelectorAll('.fb-user-handle');
      handleDisplays.forEach(el => { el.textContent = '@' + res.user.username; });
      const navName = document.getElementById('fb-nav-username');
      if (navName) navName.textContent = res.user.full_name ? res.user.full_name.split(' ').slice(-1)[0] : res.user.username;
      renderAboutAuthorCard(res.user);
    }
  } catch (err) {
    feedback.className = 'fb-feedback-msg error';
    feedback.textContent = err.message || 'Lỗi cập nhật thông tin.';
  } finally {
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi';
    }
  }
}
window.handleFbChangeName = handleFbChangeName;

async function handleFbChangePassword(event) {
  if (event) event.preventDefault();
  const curPassInput = document.getElementById('fb-set-current-pass');
  const newPassInput = document.getElementById('fb-set-new-pass');
  const confirmPassInput = document.getElementById('fb-set-confirm-pass');
  const feedback = document.getElementById('fb-feedback-pass');
  const saveBtn = document.getElementById('fb-btn-save-pass');
  if (!curPassInput || !newPassInput || !confirmPassInput || !feedback) return;

  const current_password = curPassInput.value;
  const new_password = newPassInput.value;
  const confirm_password = confirmPassInput.value;

  if (!current_password) {
    feedback.className = 'fb-feedback-msg error';
    feedback.textContent = 'Vui lòng nhập mật khẩu hiện tại.';
    return;
  }
  if (new_password.length < 8) {
    feedback.className = 'fb-feedback-msg error';
    feedback.textContent = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    return;
  }
  if (new_password !== confirm_password) {
    feedback.className = 'fb-feedback-msg error';
    feedback.textContent = 'Xác nhận mật khẩu mới không khớp.';
    return;
  }

  if (saveBtn) {
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang đổi...';
  }

  try {
    await apiRequest('api/auth/profile', {
      method: 'POST',
      body: { current_password, new_password }
    });
    feedback.className = 'fb-feedback-msg success';
    feedback.textContent = 'Đã đổi mật khẩu thành công!';
    curPassInput.value = '';
    newPassInput.value = '';
    confirmPassInput.value = '';
  } catch (err) {
    feedback.className = 'fb-feedback-msg error';
    feedback.textContent = err.message || 'Lỗi đổi mật khẩu.';
  } finally {
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<i class="fa-solid fa-lock"></i> Cập nhật mật khẩu';
    }
  }
}
window.handleFbChangePassword = handleFbChangePassword;

async function fbLogout() {
  try {
    await apiRequest('api/auth/logout', { method: 'POST' });
  } catch (_) {}
  window.location.reload();
}
window.fbLogout = fbLogout;

function setupFacebookDropdown(user) {
  activeUser = user;
  const avatarImg = document.getElementById('fb-nav-avatar') || document.getElementById('nav-user-avatar');
  const avatarBtn = document.getElementById('fb-avatar-trigger') || document.getElementById('user-menu-btn');
  const dropdownPanel = document.getElementById('fb-dropdown-panel') || document.getElementById('user-dropdown-menu');
  const navUsername = document.getElementById('fb-nav-username');

  if (!avatarBtn || !dropdownPanel) return;

  if (avatarImg) {
    avatarImg.src = avatarUrl(user && user.avatar);
  }

  if (navUsername) {
    if (user && user.full_name) {
      const parts = user.full_name.trim().split(/\s+/);
      navUsername.textContent = parts[parts.length - 1];
      navUsername.title = user.full_name;
    } else if (user && user.username) {
      navUsername.textContent = user.username;
    } else {
      navUsername.textContent = 'Khách';
    }
  }

  const switchAccounts = getRememberedAccounts().map(account => ({
    username: account.username, fullName: account.fullName, roleLabel: account.role === 'admin' ? 'Admin' : 'User',
    avatar: account.avatar
  }));

  if (user) {
    const roleBadge = user.role === 'admin'
      ? '<span class="fb-tag-role admin">Admin</span>'
      : '<span class="fb-tag-role user">Thành viên</span>';

    const switchCardsHtml = switchAccounts.map(acc => {
      const isCurrent = user.username === acc.username;
      return `
        <div class="fb-switch-user-card ${isCurrent ? 'active' : ''}">
          <div class="fb-switch-user-left">
            <img src="${avatarUrl(acc.avatar)}" class="fb-switch-user-avatar" alt="${escapeHtml(acc.fullName)}">
            <div class="fb-switch-user-info">
              <span class="fb-switch-user-name">${escapeHtml(acc.fullName)}</span>
              <span class="fb-switch-user-role">@${escapeHtml(acc.username)} · ${escapeHtml(acc.roleLabel)}</span>
            </div>
          </div>
          <div>
            ${isCurrent
              ? '<span class="fb-badge-current"><i class="fa-solid fa-check"></i> Đang dùng</span>'
              : `<button type="button" class="fb-btn-switch-now" onclick="quickSwitchAccount('${acc.username}')">Chuyển ngay</button>`
            }
          </div>
        </div>
      `;
    }).join('');

    dropdownPanel.innerHTML = `
      <div class="fb-slider-viewport">
        <div class="fb-slider-track" id="fb-slider-track">

          <!-- PANEL 1: MENU CHÍNH -->
          <div class="fb-panel-view" id="fb-panel-main">
            <!-- Header menu: Avatar tròn, Tên người dùng và @username -->
            <div class="fb-card-profile">
              <a href="profile.html?id=${user.id}" class="fb-card-profile-inner" title="Xem trang cá nhân của bạn">
                <div class="fb-avatar-box">
                  <img src="${avatarUrl(user.avatar)}" alt="${escapeHtml(user.full_name)}" class="fb-avatar-52">
                  <span class="fb-active-dot" title="Đang trực tuyến"></span>
                </div>
                <div class="fb-profile-titles">
                  <div class="fb-name-row">
                    <span class="fb-full-name">${escapeHtml(user.full_name)}</span>
                    ${user.is_verified ? '<i class="fa-solid fa-circle-check fb-verified-badge" title="Tài khoản đã xác minh"></i>' : ''}
                  </div>
                  <div class="fb-handle-row">
                    <span class="fb-user-handle">@${escapeHtml(user.username)}</span>
                    ${roleBadge}
                  </div>
                </div>
              </a>
              <div class="fb-profile-view-link-wrap">
                <a href="profile.html?id=${user.id}" class="fb-profile-view-link">
                  <i class="fa-regular fa-id-badge"></i>
                  <span>Xem trang cá nhân của bạn</span>
                </a>
              </div>
            </div>

            <div class="fb-sep"></div>

            <!-- Các mục menu chính -->
            <div class="fb-menu-group">
              <!-- Trang chủ: Icon ngôi nhà -->
              <a href="profile.html?id=${user.id}" class="fb-nav-item"><div class="fb-circle-icon profile"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg></div><div class="fb-nav-text"><div class="fb-nav-title">Trang cá nhân</div><div class="fb-nav-desc">Bài viết và tương tác với bài viết của bạn</div></div></a>
              <a href="index.html" class="fb-nav-item" title="Về trang chủ / bảng tin">
                <div class="fb-circle-icon home">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
                </div>
                <div class="fb-nav-text">
                  <div class="fb-nav-title">Trang chủ</div>
                  <div class="fb-nav-desc">Bảng tin bài viết & tin tức mới</div>
                </div>
              </a>

              <!-- Trung tâm thông báo: Icon Chuông -->
              <a href="notifications" class="fb-nav-item" title="Xem trung tâm thông báo">
                <div class="fb-circle-icon notif" style="background:#eaf3ed; color:#2d6347;">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                </div>
                <div class="fb-nav-text">
                  <div class="fb-nav-title">Thông báo</div>
                  <div class="fb-nav-desc">Tương tác và tin nhắn từ hệ thống</div>
                </div>
                <span id="fb-menu-notif-count" class="bell-badge" style="display:none; position:static; margin-left:auto;">0</span>
              </a>

              <!-- Bài viết đã lưu: Icon Bookmark -->
              <a href="${getBookmarksUrl()}" class="fb-nav-item" title="Xem các bài viết bạn đã lưu">
                <div class="fb-circle-icon bookmark">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                </div>
                <div class="fb-nav-text">
                  <div class="fb-nav-title">Đã lưu</div>
                  <div class="fb-nav-desc">Danh sách bài viết bạn đã lưu</div>
                </div>
              </a>

              <!-- Cài đặt & Tài khoản: Icon bánh răng, mũi tên > sang sub-menu -->
              <button type="button" class="fb-nav-item" id="fb-trigger-settings" onclick="slideFbMenu('settings')">
                <div class="fb-circle-icon settings">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.7a8 8 0 0 1-1.8 1l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.8-1l-1.7.7-1.4-2.4 1.4-1.1a7 7 0 0 1 0-2l-1.4-1.1 1.4-2.4 1.7.7a8 8 0 0 1 1.8-1l.3-1.8h2.8l.3 1.8a8 8 0 0 1 1.8 1l1.7-.7 1.4 2.4-1.4 1.1a7 7 0 0 1 0 2Z" transform="translate(-1 -1)"/></svg>
                </div>
                <div class="fb-nav-text">
                  <div class="fb-nav-title">Cài đặt & Tài khoản</div>
                  <div class="fb-nav-desc">Đổi họ tên, @username & mật khẩu</div>
                </div>
                <i class="fa-solid fa-chevron-right fb-nav-arrow"></i>
              </button>

              <!-- Đổi tài khoản: Icon chuyển đổi người dùng -->
              <button type="button" class="fb-nav-item" id="fb-trigger-switch" onclick="slideFbMenu('switch')">
                <div class="fb-circle-icon switch">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h15m0 0-4-4m4 4-4 4M20 17H5m0 0 4-4m-4 4 4 4"/></svg>
                </div>
                <div class="fb-nav-text">
                  <div class="fb-nav-title">Đổi tài khoản</div>
                  <div class="fb-nav-desc">Chuyển sang tài khoản khác trên máy</div>
                </div>
                <i class="fa-solid fa-chevron-right fb-nav-arrow"></i>
              </button>

              <!-- Trang quản trị (Nếu là Admin) -->
              ${user.role === 'admin' ? `
              <a href="admin" class="fb-nav-item" title="Trang quản trị hệ thống">
                <div class="fb-circle-icon admin">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <div class="fb-nav-text">
                  <div class="fb-nav-title">Trang quản trị (Admin)</div>
                  <div class="fb-nav-desc">Kiểm duyệt bài viết, tin tức & thành viên</div>
                </div>
                <i class="fa-solid fa-arrow-up-right-from-square fb-nav-arrow"></i>
              </a>` : ''}

              <div class="fb-sep"></div>

              <!-- Đăng xuất: Icon đăng xuất, màu sắc hover nổi bật -->
              <div class="fb-logout-box">
                <button type="button" class="fb-logout-btn" id="fb-trigger-logout" onclick="fbLogout()">
                  <div class="fb-circle-icon danger">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></svg>
                  </div>
                  <div class="fb-nav-text">
                    <div class="fb-nav-title text-danger">Đăng xuất</div>
                    <div class="fb-nav-desc">Thoát tài khoản khỏi thiết bị này</div>
                  </div>
                </button>
              </div>
            </div>

            <div class="fb-footer-copyright">
              Quyền riêng tư · Điều khoản · Góc Nhỏ © 2026
            </div>
          </div>

          <!-- PANEL 2: SUB-MENU CÀI ĐẶT -->
          <div class="fb-panel-view" id="fb-panel-settings">
            <div class="fb-sub-header">
              <button type="button" class="fb-back-circle-btn" onclick="slideFbMenu('main')" title="Quay lại menu chính">
                <i class="fa-solid fa-arrow-left"></i>
              </button>
              <div>
                <div class="fb-sub-title">Cài đặt & Tài khoản</div>
                <div class="fb-sub-desc">Cập nhật họ tên, username hoặc mật khẩu</div>
              </div>
            </div>

            <!-- Form Đổi tên -->
            <form id="fb-form-change-name" onsubmit="handleFbChangeName(event)">
              <div class="fb-form-section-title">
                <i class="fa-solid fa-user-pen"></i> Đổi tên hiển thị
              </div>
              <div class="fb-field-group">
                <label class="fb-field-label" for="fb-set-fullname">Họ và tên mới</label>
                <input type="text" id="fb-set-fullname" class="fb-field-input" value="${escapeHtml(user.full_name)}" placeholder="Nhập họ và tên mới" required maxlength="120">
              </div>
              <div class="fb-field-group">
                <label class="fb-field-label" for="fb-set-username">Tên đăng nhập (@username)</label>
                <input type="text" id="fb-set-username" class="fb-field-input" value="${escapeHtml(user.username)}" placeholder="3-40 ký tự chữ, số, _" required pattern="[A-Za-z0-9_]{3,40}">
              </div>
              <div class="fb-field-group">
                <label class="fb-field-label" for="fb-set-bio">Tiểu sử</label>
                <textarea id="fb-set-bio" class="fb-field-input" rows="3" maxlength="300" style="resize:vertical;font-family:inherit;font-size:13px;line-height:1.45;" placeholder="Viết vài dòng giới thiệu bản thân...">${escapeHtml(user.bio || '')}</textarea>
              </div>
              ${AvatarPicker.markup({ id: 'fb-avatar-picker', value: user.avatar })}
              <button type="submit" class="fb-btn-submit" id="fb-btn-save-name">
                <i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi
              </button>
              <div id="fb-feedback-name" class="fb-feedback-msg"></div>
            </form>

            <div class="fb-sep-dashed"></div>

            <!-- Form Đổi mật khẩu -->
            <form id="fb-form-change-password" onsubmit="handleFbChangePassword(event)">
              <div class="fb-form-section-title">
                <i class="fa-solid fa-key"></i> Đổi mật khẩu
              </div>
              <div class="fb-field-group">
                <label class="fb-field-label" for="fb-set-current-pass">Mật khẩu hiện tại</label>
                <input type="password" id="fb-set-current-pass" class="fb-field-input" placeholder="Nhập mật khẩu hiện tại" required>
              </div>
              <div class="fb-field-group">
                <label class="fb-field-label" for="fb-set-new-pass">Mật khẩu mới</label>
                <input type="password" id="fb-set-new-pass" class="fb-field-input" placeholder="Tối thiểu 8 ký tự" required minlength="8">
              </div>
              <div class="fb-field-group">
                <label class="fb-field-label" for="fb-set-confirm-pass">Xác nhận mật khẩu mới</label>
                <input type="password" id="fb-set-confirm-pass" class="fb-field-input" placeholder="Nhập lại mật khẩu mới" required minlength="8">
              </div>
              <button type="submit" class="fb-btn-submit" id="fb-btn-save-pass">
                <i class="fa-solid fa-lock"></i> Cập nhật mật khẩu
              </button>
              <div id="fb-feedback-pass" class="fb-feedback-msg"></div>
            </form>
          </div>

          <!-- PANEL 3: SUB-MENU ĐỔI TÀI KHOẢN -->
          <div class="fb-panel-view" id="fb-panel-switch">
            <div class="fb-sub-header">
              <button type="button" class="fb-back-circle-btn" onclick="slideFbMenu('main')" title="Quay lại menu chính">
                <i class="fa-solid fa-arrow-left"></i>
              </button>
              <div>
                <div class="fb-sub-title">Đổi tài khoản</div>
                <div class="fb-sub-desc">Chuyển sang tài khoản khác trên máy này</div>
              </div>
            </div>

            <div class="fb-switch-accounts-list">
              ${switchCardsHtml}
            </div>

            <div class="fb-other-login-box">
              <a href="accounts.html" class="fb-btn-other-login">
                <i class="fa-solid fa-user-plus"></i> Đăng nhập bằng tài khoản khác
              </a>
            </div>
          </div>

        </div>
      </div>
    `;
  } else {
    // Trạng thái Khách Ghé Thăm (Chưa Đăng Nhập)
    dropdownPanel.innerHTML = `
      <div class="fb-slider-viewport">
        <div class="fb-panel-view" style="width:100%;">
          <div class="fb-sub-header" style="justify-content:space-between;margin-bottom:12px;">
            <div class="fb-sub-title">Chào mừng bạn!</div>
            <button type="button" class="fb-back-circle-btn" onclick="closeFacebookDropdown()" title="Đóng">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>
          <div style="text-align:center;padding:12px 6px;">
            <div class="fb-circle-icon home" style="width:48px;height:48px;font-size:20px;margin:0 auto 10px;">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg>
            </div>
            <div style="font-weight:700;font-size:15px;color:#050505;">Bạn chưa đăng nhập</div>
            <div style="font-size:12px;color:#65676b;margin:6px 0 14px;line-height:1.4;">
              Đăng nhập để viết bài, bình luận, tương tác và quản trị hệ thống.
            </div>
            <a href="account.html" class="fb-btn-submit" style="display:block;text-align:center;text-decoration:none;margin-bottom:14px;">
              <i class="fa-solid fa-arrow-right-to-bracket"></i> Đăng nhập / Đăng ký
            </a>
          </div>
          </div>
        </div>
      </div>
    `;
  }

  // Toggle Dropdown when clicking avatar button
  avatarBtn.onclick = (e) => {
    e.stopPropagation();
    const isShown = dropdownPanel.style.display === 'block';
    if (!isShown) {
      slideFbMenu('main');
      dropdownPanel.style.display = 'block';
      avatarBtn.setAttribute('aria-expanded', 'true');
    } else {
      dropdownPanel.style.display = 'none';
      avatarBtn.setAttribute('aria-expanded', 'false');
    }
  };

  // Close dropdown on outside click
  document.addEventListener('click', (e) => {
    if (!dropdownPanel.contains(e.target) && !avatarBtn.contains(e.target)) {
      dropdownPanel.style.display = 'none';
      avatarBtn.setAttribute('aria-expanded', 'false');
      slideFbMenu('main');
    }
  });
}

function setupUserDropdown(user) {
  setupFacebookDropdown(user);
}

function openBioSettingsDirect(event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }
  const targetUser = (typeof activeUser !== 'undefined' && activeUser)
                  || (typeof getRememberedAccounts === 'function' && getRememberedAccounts()[0])
                  || null;
  openSettingsModal(targetUser);
  setTimeout(() => {
    const bioEl = document.getElementById('settings-bio');
    if (bioEl) {
      bioEl.focus();
      bioEl.select();
      bioEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }, 120);
}
window.openBioSettingsDirect = openBioSettingsDirect;

function openSettingsModal(user) {
  if (!user) {
    user = (typeof activeUser !== 'undefined' && activeUser)
        || (typeof getRememberedAccounts === 'function' && getRememberedAccounts()[0])
        || null;
  }
  if (!user) {
    window.location.href = 'account.html';
    return;
  }
  const modal = document.getElementById('settings-modal');
  if (!modal) return;

  setupSettingsModal(user);

  const uName = document.getElementById('settings-username');
  if (uName) uName.value = user.username || '';
  const fName = document.getElementById('settings-fullname');
  if (fName) fName.value = user.full_name || '';
  const email = document.getElementById('settings-email');
  if (email) email.value = user.email || '';
  const bioInput = document.getElementById('settings-bio');
  if (bioInput) {
    bioInput.value = user.bio || '';
    const bioCount = document.getElementById('settings-bio-count');
    if (bioCount) bioCount.textContent = (user.bio || '').length;
  }
  const curPass = document.getElementById('settings-current-password');
  if (curPass) curPass.value = '';
  const newPass = document.getElementById('settings-new-password');
  if (newPass) newPass.value = '';

  try {
    if (window.AvatarPicker && typeof window.AvatarPicker.setValue === 'function') {
      const avatarEl = document.getElementById('settings-avatar-options');
      if (avatarEl) {
        window.AvatarPicker.setValue(avatarEl, user.avatar || '');
      }
    }
  } catch (_) {}

  const msgEl = document.getElementById('settings-message');
  if (msgEl) { msgEl.textContent = ''; msgEl.style.color = ''; }

  modal.classList.add('active');
  modal.style.display = 'flex';
  modal.style.pointerEvents = 'auto';
  modal.style.visibility = 'visible';
  modal.style.opacity = '1';
}
window.openSettingsModal = openSettingsModal;

function closeSettingsModal() {
  const modal = document.getElementById('settings-modal');
  if (modal) {
    modal.classList.remove('active');
    modal.style.display = 'none';
    modal.style.pointerEvents = 'none';
    modal.style.visibility = 'hidden';
    modal.style.opacity = '0';
  }
}
window.closeSettingsModal = closeSettingsModal;

function openSwitchModal(currentUser) {
  const modal = document.getElementById('switch-account-modal');
  const listEl = document.getElementById('switch-account-list');
  if (!modal || !listEl) return;

  const accounts = getRememberedAccounts();

  if (!accounts.length) {
    listEl.innerHTML = '<p>Bạn chưa đăng nhập tài khoản nào trên trình duyệt này.</p>';
    modal.classList.add('active');
    modal.style.display = 'flex';
    modal.style.pointerEvents = 'auto';
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';
    return;
  }
  listEl.innerHTML = accounts.map(acc => {
    const isCurrent = currentUser && currentUser.username === acc.username;
    return `
      <div class="fb-switch-user-card ${isCurrent ? 'active' : ''}" style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:10px;border:1px solid ${isCurrent ? '#1877f2' : '#e4e6eb'};margin-bottom:10px;background:${isCurrent ? '#e7f3ff' : '#fff'};">
        <div style="display:flex;align-items:center;gap:12px;">
          <img src="${avatarUrl(acc.avatar)}" alt="${escapeHtml(acc.fullName)}" class="fb-switch-user-avatar" style="width:46px;height:46px;border-radius:50%;object-fit:cover;border:1.5px solid #ced0d4;">
          <div>
            <div style="font-weight:700;font-size:14px;color:#050505;">${escapeHtml(acc.fullName)}</div>
            <div style="font-size:12px;color:#65676b;">@${escapeHtml(acc.username)} · ${escapeHtml(acc.role === 'admin' ? 'Quản trị viên' : 'Người dùng')}</div>
          </div>
        </div>
        <div>
          ${isCurrent 
            ? '<span style="font-size:11px;font-weight:600;background:#1877f2;color:#fff;padding:4px 9px;border-radius:12px;"><i class="fa-solid fa-check"></i> Đang dùng</span>' 
            : `<button type="button" class="action-btn" onclick="quickSwitchAccount('${acc.username}')" style="font-size:12px;padding:6px 14px;background:#1877f2;color:#fff;border-radius:6px;cursor:pointer;border:none;font-weight:600;">Chuyển ngay</button>`
          }
        </div>
      </div>
    `;
  }).join('');

  modal.classList.add('active');
  modal.style.display = 'flex';
  modal.style.pointerEvents = 'auto';
  modal.style.visibility = 'visible';
  modal.style.opacity = '1';
}
window.openSwitchModal = openSwitchModal;

function closeSwitchModal() {
  const modal = document.getElementById('switch-account-modal');
  if (modal) {
    modal.classList.remove('active');
    modal.style.display = 'none';
    modal.style.pointerEvents = 'none';
    modal.style.visibility = 'hidden';
    modal.style.opacity = '0';
  }
}
window.closeSwitchModal = closeSwitchModal;

async function quickSwitchAccount(username) {
  try {
    switchToRememberedAccount(username);
  } catch (err) {
    alert('Could not switch account: ' + err.message);
  }
}
window.quickSwitchAccount = quickSwitchAccount;

function setupSettingsModal(user) {
  const modal = document.getElementById('settings-modal');
  const form = document.getElementById('settings-form');
  const logoutBtn = document.getElementById('settings-modal-logout-btn');
  if (!modal || !form) return;
  if (window.AvatarPicker && !form.querySelector('#settings-avatar-options')) {
    const host = document.createElement('div');
    host.innerHTML = AvatarPicker.markup({ id: 'settings-avatar-options', value: user?.avatar || '' });
    form.querySelector('hr')?.before(host.firstElementChild);
  }

  const bioInput = document.getElementById('settings-bio');
  const bioCount = document.getElementById('settings-bio-count');
  if (bioInput && bioCount && !bioInput.dataset.boundCount) {
    bioInput.dataset.boundCount = '1';
    bioInput.addEventListener('input', () => {
      bioCount.textContent = bioInput.value.length;
    });
  }

  if (logoutBtn && !logoutBtn.dataset.bound) {
    logoutBtn.dataset.bound = '1';
    logoutBtn.addEventListener('click', async () => {
      try {
        await apiRequest('api/auth/logout', { method: 'POST' });
      } catch (_) {}
      window.location.reload();
    });
  }

  if (!modal.dataset.bound) {
    modal.dataset.bound = '1';
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeSettingsModal();
    });
  }

  const switchModal = document.getElementById('switch-account-modal');
  if (switchModal && !switchModal.dataset.bound) {
    switchModal.dataset.bound = '1';
    switchModal.addEventListener('click', (e) => {
      if (e.target === switchModal) closeSwitchModal();
    });
  }

  if (form.dataset.bound) return;
  form.dataset.bound = '1';

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('settings-username').value.trim();
    const fullName = document.getElementById('settings-fullname').value.trim();
    const bioField = document.getElementById('settings-bio');
    const bio = bioField ? bioField.value.trim() : (user?.bio || '');
    const currentPassword = document.getElementById('settings-current-password').value;
    const newPassword = document.getElementById('settings-new-password').value;
    const msgEl = document.getElementById('settings-message');

    if (newPassword && !currentPassword) {
      msgEl.textContent = 'Vui lòng nhập mật khẩu hiện tại để đổi sang mật khẩu mới.';
      msgEl.style.color = '#dc2626';
      return;
    }

    const payload = {
      username,
      full_name: fullName,
      bio: bio
    };
    const avatar = form.querySelector('[data-avatar-value]')?.value;
    if (avatar) payload.avatar = avatar;
    if (newPassword) {
      payload.current_password = currentPassword;
      payload.new_password = newPassword;
    }

    const submitBtn = document.getElementById('settings-submit-btn');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Đang lưu...'; }

    try {
      const res = await apiRequest('api/auth/profile', { method: 'POST', body: payload });
      msgEl.textContent = (res.message || 'Cập nhật thành công!');
      msgEl.style.color = '#15803d';
      activeUser = res.user;
      setupFacebookDropdown(res.user);
      renderAboutAuthorCard(res.user);
      document.getElementById('settings-current-password').value = '';
      document.getElementById('settings-new-password').value = '';
      setTimeout(() => {
        closeSettingsModal();
      }, 1200);
    } catch (err) {
      msgEl.textContent = err.message;
      msgEl.style.color = '#dc2626';
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Lưu thay đổi'; }
    }
  });
}

document.addEventListener("DOMContentLoaded", () => {
  try {
    const remembered = getRememberedAccounts();
    if (remembered.length > 0 && remembered[0]) {
      renderAboutAuthorCard({
        id: remembered[0].id,
        username: remembered[0].username,
        full_name: remembered[0].fullName || remembered[0].username,
        avatar: remembered[0].avatar,
        role: remembered[0].role
      });
    }
  } catch (_) {}
  initializeBackend().then(initializePostDetail);
  renderLeftSidebar();
  if (typeof mockPosts !== 'undefined' && Array.isArray(mockPosts) && mockPosts.length > 0) {
    renderFeed(mockPosts);
  }
  renderTopNewsWidget();
  renderTopHashtagsWidget();
  setupSearch();
  setupSearchPageFilters();
  setupImageUploadAndPaste();

  // Chuẩn hóa liên kết "Bài viết đã lưu" trên toàn bộ giao diện
  document.querySelectorAll('a[href="bookmarks"], a.sidebar-bookmarks-link').forEach(link => {
    link.href = getBookmarksUrl();
    link.addEventListener('click', (e) => {
      if (!CURRENT_USER_ID) {
        e.preventDefault();
        alert('Vui lòng đăng nhập để xem danh sách bài viết đã lưu.');
        window.location.href = 'account.html?mode=login';
      }
    });
  });

  // Đóng modal khi bấm ra ngoài vùng nội dung modal
  window.addEventListener('click', (e) => {
    const modal = document.getElementById("post-modal");
    if (e.target === modal) {
      closeModal("post-modal");
    }
  });
});

// 0. Bộ Lọc Nhanh Trang Chủ (Home Feed Filter Change)
function handleHomeFilterChange() {
  const typeSelect = document.getElementById('feed-filter-type');
  const imageSelect = document.getElementById('feed-filter-has-image');
  if (!typeSelect && !imageSelect) return;

  const typeVal = typeSelect ? typeSelect.value : 'all';
  const hasImageVal = imageSelect ? imageSelect.value : 'all';

  if (typeSelect) typeSelect.classList.toggle('is-filtered', typeVal !== 'all');
  if (imageSelect) imageSelect.classList.toggle('is-filtered', hasImageVal !== 'all');

  const filters = {};

  // Giữ lại từ khóa tìm kiếm nếu đang có trên header
  const searchInput = document.getElementById('smart-search-input');
  const qVal = searchInput ? searchInput.value.trim() : '';
  if (qVal) {
    if (qVal.startsWith('#')) {
      filters.hashtag = qVal.slice(1);
    } else if (qVal.startsWith('@')) {
      filters.author_username = qVal.slice(1);
    } else {
      filters.q = qVal;
    }
  }

  // Đồng bộ tab với loại bài
  if (typeVal === 'news') {
    feedState.tab = 'news';
    filters.type = 'news';
    document.querySelectorAll('.feed-intro .feed-filter').forEach(btn => btn.classList.remove('active'));
    const navNews = document.getElementById('nav-news');
    if (navNews) navNews.classList.add('active');
  } else if (typeVal === 'blog') {
    filters.type = 'blog';
    if (feedState.tab === 'news') {
      feedState.tab = 'home';
      document.querySelectorAll('.feed-intro .feed-filter').forEach(btn => btn.classList.remove('active'));
      const navHome = document.getElementById('nav-home');
      if (navHome) navHome.classList.add('active');
    }
  } else if (feedState.tab === 'news' && typeVal === 'all') {
    feedState.tab = 'home';
    document.querySelectorAll('.feed-intro .feed-filter').forEach(btn => btn.classList.remove('active'));
    const navHome = document.getElementById('nav-home');
    if (navHome) navHome.classList.add('active');
  }

  // Áp dụng chế độ tab nếu không phải lọc cứng news
  if (feedState.tab === 'following') {
    filters.following = 1;
  } else if (feedState.tab === 'for_you') {
    filters.mode = 'for_you';
  } else if (!filters.type) {
    filters.mode = 'all';
  }

  // Bổ sung lọc hình ảnh
  if (hasImageVal !== 'all') {
    filters.has_image = hasImageVal;
  }

  feedState.page = 1;
  feedState.hasMore = true;
  loadBackendFeed(filters, false).catch(err => console.error('Lỗi khi lọc bài viết trang chủ:', err));
}
window.handleHomeFilterChange = handleHomeFilterChange;

// 1. Navbar Feed Lọc Thực Tế
function filterNavFeed(type) {
  // Nếu không có feed-container trên trang (ví dụ ở trang chi tiết bài viết), chuyển hướng về index.html
  const feedContainer = document.getElementById('feed-container');
  if (!feedContainer) {
    const base = (typeof window.APP_URL !== 'undefined' && window.APP_URL)
      ? String(window.APP_URL).replace(/\/$/, '')
      : new URL('.', window.location.href).pathname.replace(/\/$/, '');
    window.location.href = `${base}/index.html?tab=${encodeURIComponent(type)}#feed`;
    return;
  }

  // Nếu đang ở trang Tìm kiếm và có thanh bộ lọc tìm kiếm: đồng bộ bộ lọc Loại bài
  const searchFilterBar = document.getElementById('search-filters-bar');
  if (searchFilterBar && typeof window.applySearchFilterParam === 'function') {
    const targetType = (type === 'news') ? 'news' : ((type === 'home' || type === 'blog') ? 'blog' : 'all');
    window.applySearchFilterParam('type', targetType);
    return;
  }

  feedState.tab = type;
  document.querySelectorAll('.feed-intro .feed-filter').forEach(btn => btn.classList.remove('active'));
  const targetBtn = document.getElementById(`nav-${type}`);
  if (targetBtn) targetBtn.classList.add('active');

  const typeSelect = document.getElementById('feed-filter-type');
  const imageSelect = document.getElementById('feed-filter-has-image');

  // Đồng bộ dropdown Loại bài với tab được click
  if (typeSelect) {
    if (type === 'news') {
      typeSelect.value = 'news';
      typeSelect.classList.add('is-filtered');
    } else if (type === 'home' && typeSelect.value === 'news') {
      typeSelect.value = 'all';
      typeSelect.classList.remove('is-filtered');
    }
  }

  const filters = type === 'news'
    ? { type: 'news' }
    : (type === 'following'
      ? { following: 1 }
      : (type === 'for_you' ? { mode: 'for_you' } : { mode: 'all' }));

  // Giữ lại bộ lọc Loại bài nếu đang chọn blog và không phải tab news
  if (typeSelect && typeSelect.value === 'blog' && type !== 'news') {
    filters.type = 'blog';
    typeSelect.classList.add('is-filtered');
  }

  // Giữ lại bộ lọc Hình ảnh nếu đang chọn
  if (imageSelect && imageSelect.value !== 'all') {
    filters.has_image = imageSelect.value;
    imageSelect.classList.add('is-filtered');
  }

  // Giữ lại từ khóa tìm kiếm nếu có
  const searchInput = document.getElementById('smart-search-input');
  const qVal = searchInput ? searchInput.value.trim() : '';
  if (qVal) {
    if (qVal.startsWith('#')) filters.hashtag = qVal.slice(1);
    else if (qVal.startsWith('@')) filters.author_username = qVal.slice(1);
    else filters.q = qVal;
  }

  // Khi chuyển tab, cấp seed ngẫu nhiên mới để luôn thấy bài mới
  feedState.seed = Math.floor(Math.random() * 1000000);
  feedState.page = 1;
  feedState.hasMore = true;
  loadBackendFeed(filters, false).catch(error => alert(error.message));
}

// 2. Render Feed Bài Viết trên Trang Chính
function renderSinglePostCard(post) {
  const isMyPost = (Number(post.user.id) === Number(CURRENT_USER_ID));
  const commentCount = Number(
    post.comments_count ??
    post.comment_count ??
    post.total_comments ??
    (Array.isArray(post.comments) ? post.comments.length : 0)
  );
  const shareCount = Number(post.shares_count ?? post.total_shares ?? post.share_count ?? 0);
  let sharedMarkup = '';
  if (post.original_id) {
    sharedMarkup = `
      ${post.share_comment ? `<p class="repost-comment">${escapeHtml(post.share_comment)}</p>` : ''}
      <article class="repost-preview" onclick="event.stopPropagation(); openPostModal(${Number(post.original_id)})">
        <strong>${escapeHtml(post.original_title || 'Bài viết gốc')}</strong>
        <p>${escapeHtml(post.original_summary || String(post.original_content || '').slice(0, 180))}</p>
        <small>Đăng bởi ${escapeHtml(post.original_author || post.original_username || '')}</small>
      </article>`;
  } else if (post.share_comment || (post.title && post.title.startsWith('Đăng lại:') && !post.original_id)) {
    sharedMarkup = `
      ${post.share_comment ? `<p class="repost-comment">${escapeHtml(post.share_comment)}</p>` : ''}
      <article class="repost-preview repost-deleted" onclick="event.stopPropagation()">
        <em><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Bài viết gốc đã bị xóa hoặc không còn khả dụng.</em>
      </article>`;
  } else {
    sharedMarkup = `
      <div class="post-body cursor-pointer" data-post-id="${Number(post.id)}">
        <a href="${getPostDetailUrl(post.id)}" class="post-card-link" data-post-id="${Number(post.id)}">
          <div class="post-title cursor-pointer">${escapeHtml(post.title)}</div>
        </a>
        <p class="post-summary">${escapeHtml(post.summary || post.content.substring(0, 120) + '...')}</p>
      </div>`;
  }

  return `
    <div class="post-card" id="post-card-${Number(post.id)}" data-post-id="${Number(post.id)}">
      <div class="post-header">
        <a href="profile.html?id=${post.user.id}">
          <img src="${avatarUrl(post.user.avatar)}" class="avatar" alt="Avatar">
        </a>
        <div class="user-info">
          <a href="profile.html?id=${post.user.id}" class="user-name">
            ${escapeHtml(post.user.full_name)} ${post.user.is_verified ? '<span class="verified-badge">✓</span>' : ''}
          </a>
          <span class="font-size-sm text-muted">${post.created_at} · <span title="${Number(post.views_count || post.views || 0).toLocaleString()} lượt xem"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:2px;display:inline-block;" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>${Number(post.views_count || post.views || 0).toLocaleString()}</span></span>
        </div>
        <span class="badge ${post.post_type === 'news' ? 'badge-news' : 'badge-blog'} ml-auto">
          ${post.post_type === 'news' ? 'Tin tức' : 'Blog'}
        </span>
      </div>
      
      <!-- Bấm Tiêu đề để mở Chi Tiết Bài Viết -->
      ${sharedMarkup}

      <!-- Hashtags dưới bài viết -->
      <div class="post-hashtags">
        ${post.hashtags ? post.hashtags.map(tag => `
          <span class="post-hashtag-pill" onclick="filterHashtag('${escapeHtml(tag)}')">#${escapeHtml(tag)}</span>
        `).join('') : ''}
      </div>

      ${post.thumbnail ? `<a href="${getPostDetailUrl(post.id)}" class="post-card-link post-thumbnail-link" data-post-id="${Number(post.id)}"><img src="${post.thumbnail}" class="post-image cursor-pointer" alt="Thumbnail"></a>` : ''}

      <!-- Nút Tương Tác Ngay Số Đếm -->
      <div class="post-interactive-stats">
        ${reactionMarkup(post)}
        
        <button type="button" class="stat-btn" onclick="event.preventDefault(); event.stopPropagation(); openPostModal(${post.id}, event)">
          ${SVG_ICONS.comment} <span>${commentCount}</span> Bình luận
        </button>
        
        <button type="button" class="stat-btn stat-btn-share" data-post-id="${post.id}" onclick="event.preventDefault(); event.stopPropagation(); sharePost(${post.id}, event)">
          ${SVG_ICONS.share} <span class="shares-count">${shareCount}</span> Chia sẻ
        </button>

        <button type="button" class="stat-btn stat-btn-bookmark ${post.is_bookmarked ? 'active' : ''}" data-post-id="${post.id}" onclick="event.preventDefault(); event.stopPropagation(); toggleBookmark(${post.id}, this, event)">
          ${SVG_ICONS.bookmark(post.is_bookmarked)} <span>${post.is_bookmarked ? 'Đã lưu' : 'Lưu'}</span>
        </button>

        ${isMyPost
          ? `<details class="comment-menu post-menu" onclick="event.stopPropagation()"><summary aria-label="Tùy chọn bài viết" onclick="event.stopPropagation()">•••</summary><div class="comment-menu-items"><button type="button" onclick="event.stopPropagation(); this.closest('details').open=false; editPost(${Number(post.id)})">Sửa bài viết</button><button type="button" class="danger" onclick="event.stopPropagation(); this.closest('details').open=false; deletePost(${Number(post.id)})">Xóa bài viết</button></div></details>`
          : (CURRENT_USER_ID !== null ? `<button type="button" class="stat-btn" onclick="event.preventDefault(); event.stopPropagation(); reportPost(${Number(post.id)}, event)"><span class="report-exclamation-icon" aria-hidden="true">!</span> Báo cáo</button>` : '')}

        ${isMyPost && post.post_type === 'blog' && Number(post.user.reputation_score || 0) >= 100 ? `
          <button type="button" class="stat-btn stat-btn-ticket" onclick="event.preventDefault(); event.stopPropagation(); sendTicket(${post.id}, event)">
            ${SVG_ICONS.ticket} Gửi Ticket
          </button>
        ` : ''}
      </div>
    </div>
  `;
}

function renderFeed(postsToRender) {
  const container = document.getElementById("feed-container");
  if (!container) return;

  if (postsToRender.length === 0) {
    container.innerHTML = `<div class="post-card text-center py-5"><p class="text-muted">Không có bài viết nào phù hợp.</p></div>`;
    return;
  }

  container.innerHTML = postsToRender.map(renderSinglePostCard).join('');
}

function appendFeed(newPosts) {
  const container = document.getElementById("feed-container");
  if (!container || !newPosts || !newPosts.length) return;
  const html = newPosts.map(renderSinglePostCard).join('');
  container.insertAdjacentHTML('beforeend', html);
}

// Setup Infinite Scroll tự động cuộn tải thêm bài viết
let isScrollThrottled = false;
window.addEventListener('scroll', () => {
  if (isScrollThrottled) return;
  isScrollThrottled = true;
  setTimeout(() => { isScrollThrottled = false; }, 250);

  const container = document.getElementById('feed-container');
  if (!container || feedState.isLoading || !feedState.hasMore) return;

  const rect = container.getBoundingClientRect();
  if (rect.bottom <= window.innerHeight + 500) {
    loadMoreFeedPosts();
  }
}, { passive: true });



function openPostActionModal(title, formMarkup, onSubmit) {
  let modal = document.getElementById('post-action-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'post-action-modal';
    modal.className = 'modal-overlay';
    document.body.appendChild(modal);
  }
  modal.innerHTML = `<div class="modal-content post-action-modal-content" role="dialog" aria-modal="true" aria-labelledby="post-action-modal-title">
    <button type="button" class="close-modal" aria-label="Đóng cửa sổ">×</button>
    <h2 id="post-action-modal-title"></h2>
    ${formMarkup}
  </div>`;
  modal.querySelector('#post-action-modal-title').textContent = title;
  const close = () => modal.classList.remove('active');
  modal.querySelector('.close-modal').addEventListener('click', close);
  modal.onclick = event => { if (event.target === modal) close(); };
  modal.querySelector('form').addEventListener('submit', async event => {
    event.preventDefault();
    const submit = modal.querySelector('[type="submit"]');
    submit.disabled = true;
    try { await onSubmit(new FormData(event.currentTarget)); }
    catch (error) { alert(error.message || 'Không thể hoàn tất thao tác.'); submit.disabled = false; }
  });
  modal.classList.add('active');
  return modal;
}

async function editPost(postId) {
  try {
    const response = await apiRequest(`api/posts/${postId}`);
    const post = response.data;
    const modal = openPostActionModal('Chỉnh sửa bài viết đã đăng', `<form class="post-action-form">
      <label for="edit-post-title">Tiêu đề bài viết</label>
      <input class="input-field" id="edit-post-title" name="title" maxlength="200" required>
      <label for="edit-post-content">Nội dung bài viết</label>
      <textarea class="input-field" id="edit-post-content" name="content" rows="9" maxlength="50000" required></textarea>
      <div class="post-action-form-buttons"><button type="button" class="action-btn post-action-cancel">Hủy</button><button type="submit" class="action-btn action-btn-primary">Lưu thay đổi</button></div>
    </form>`, async formData => {
      await apiRequest(`api/posts/${postId}/edit`, { method: 'POST', body: { title: formData.get('title'), content: formData.get('content') } });
      modal.classList.remove('active');
      location.reload();
    });
    modal.querySelector('[name="title"]').value = post.title || '';
    modal.querySelector('[name="content"]').value = post.content || '';
    modal.querySelector('.post-action-cancel').addEventListener('click', () => modal.classList.remove('active'));
    modal.querySelector('[name="title"]').focus();
  } catch (error) { alert(error.message); }
}

async function deletePost(postId) {
  if (!confirm('Bạn có chắc muốn xóa bài viết này?')) return;
  try {
    await apiRequest(`api/posts/${postId}`, { method: 'DELETE' });
    const card = document.getElementById(`post-card-${postId}`);
    if (card) card.remove();
    if (document.getElementById('post-detail')) location.href = `${APP_URL}/`;
    else if (!document.querySelector('.post-card[id^="post-card-"]')) location.reload();
  } catch (error) { alert(error.message); }
}

async function reportPost(postId, event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }
  if (CURRENT_USER_ID === null) {
    alert('Vui lòng đăng nhập để báo cáo bài viết.');
    return;
  }
  const modal = openPostActionModal('Báo cáo bài viết', `<form class="post-action-form">
    <p class="post-report-intro">Cho quản trị viên biết vấn đề bạn gặp trong bài viết này.</p>
    <label for="post-report-reason">Lý do báo cáo</label>
    <select class="input-field" id="post-report-reason" name="reason" required>
      <option value="">-- Chọn lý do báo cáo --</option>
      <option value="offensive">Nội dung xúc phạm, quấy rối hoặc thô tục</option>
      <option value="spam">Spam, quảng cáo trái phép hoặc nội dung rác</option>
      <option value="inappropriate">Nội dung không phù hợp chuẩn mực</option>
      <option value="misinformation">Thông tin sai lệch hoặc lừa đảo</option>
      <option value="other">Lý do khác</option>
    </select>
    <label for="post-report-details">Mô tả nội dung cần báo cáo (không bắt buộc)</label>
    <textarea class="input-field" id="post-report-details" name="details" rows="4" maxlength="1000" placeholder="Mô tả cụ thể nội dung hoặc lý do bạn báo cáo..."></textarea>
    <div class="post-action-form-buttons">
      <button type="button" class="action-btn post-action-cancel">Hủy</button>
      <button type="submit" class="action-btn action-btn-primary">Gửi báo cáo</button>
    </div>
  </form>`, async formData => {
    const result = await apiRequest(`api/posts/${postId}/report`, { method: 'POST', body: { reason: formData.get('reason'), details: formData.get('details') } });
    modal.classList.remove('active');
    alert(result.message || 'Đã gửi báo cáo đến quản trị viên.');
  });
  modal.querySelector('.post-action-cancel').addEventListener('click', () => modal.classList.remove('active'));
}

async function reportComment(commentId) {
  if (CURRENT_USER_ID === null) {
    alert('Vui lòng đăng nhập để báo cáo bình luận.');
    return;
  }
  const modal = openPostActionModal('Báo cáo bình luận', `<form class="post-action-form">
    <p class="post-report-intro">Hãy cho quản trị viên biết vấn đề bạn gặp phải với bình luận này.</p>
    <label for="comment-report-reason">Lý do báo cáo</label>
    <select class="input-field" id="comment-report-reason" name="reason" required>
      <option value="">-- Chọn lý do báo cáo --</option>
      <option value="offensive">Nội dung xúc phạm, đe dọa hoặc thô tục</option>
      <option value="spam">Spam, quảng cáo trái phép hoặc lặp lại</option>
      <option value="inappropriate">Nội dung không phù hợp chuẩn mực</option>
      <option value="misinformation">Thông tin sai lệch hoặc lừa đảo</option>
      <option value="other">Lý do khác</option>
    </select>
    <label for="comment-report-details">Mô tả chi tiết (không bắt buộc)</label>
    <textarea class="input-field" id="comment-report-details" name="details" rows="4" maxlength="1000" placeholder="Cung cấp thêm chi tiết để quản trị viên xử lý..."></textarea>
    <div class="post-action-form-buttons">
      <button type="button" class="action-btn post-action-cancel">Hủy</button>
      <button type="submit" class="action-btn action-btn-primary">Gửi báo cáo</button>
    </div>
  </form>`, async formData => {
    const result = await apiRequest(`api/comments/${commentId}/report`, {
      method: 'POST',
      body: {
        reason: formData.get('reason'),
        details: formData.get('details')
      }
    });
    modal.classList.remove('active');
    alert(result.message || 'Đã gửi báo cáo bình luận đến quản trị viên.');
  });
  modal.querySelector('.post-action-cancel').addEventListener('click', () => modal.classList.remove('active'));
}

window.editPost = editPost;
window.deletePost = deletePost;
window.reportPost = reportPost;
window.reportComment = reportComment;

function getBranchDescendants(parentId, allComments) {
  const pId = Number(parentId);
  const directChildren = allComments.filter(c => Number(c.parent_id || 0) === pId);
  let descendants = [];
  for (const child of directChildren) {
    descendants.push(child);
    const sub = getBranchDescendants(child.id, allComments);
    if (sub.length > 0) {
      descendants = descendants.concat(sub);
    }
  }
  return descendants;
}

function renderCommentCard(comment, post, comments, depth) {
  const commentId = Number(comment.id);
  const visualDepth = Math.min(Number(depth || 0), 4);
  const isDeleted = Boolean(comment.is_deleted);

  if (isDeleted) {
    return `<article id="comment-${commentId}" class="comment-item comment-item-deleted comment-depth-${visualDepth}" data-comment-id="${commentId}" data-depth="${depth}" style="--comment-depth:${visualDepth}">
      <div class="comment-deleted-avatar" aria-hidden="true">×</div>
      <div class="comment-content-box">
        <div class="comment-bubble comment-bubble-deleted">
          <p class="comment-text comment-deleted-text">[Bình luận này đã bị xóa]</p>
        </div>
      </div>
    </article>`;
  }

  const authorId = Number(comment.user_id ?? comment.user?.id);
  const isOwnComment = CURRENT_USER_ID !== null && authorId === Number(CURRENT_USER_ID);
  const safePost = post || {};
  const targetPostId = Number(safePost.id ?? comment.post_id ?? 0);
  const postAuthorId = Number(safePost.user_id ?? safePost.user?.id ?? 0);
  const isPostOwner = CURRENT_USER_ID !== null && postAuthorId === Number(CURRENT_USER_ID);
  const canEdit = isOwnComment;
  const canDelete = isOwnComment || isPostOwner || CURRENT_USER_ROLE === 'admin';
  const canReport = CURRENT_USER_ID !== null && !isOwnComment;
  const user = comment.user || comment;
  const showMenu = canEdit || canDelete || canReport;

  let replyToBadge = '';
  const pId = Number(comment.parent_id || 0);
  if (depth >= 2 && pId > 0 && Array.isArray(comments)) {
    const parentComment = comments.find(c => Number(c.id) === pId);
    if (parentComment) {
      if (parentComment.is_deleted) {
        replyToBadge = `<div class="comment-reply-to-badge"><span class="reply-arrow">↳ Trả lời</span> <span class="comment-deleted-ref">bình luận đã bị xóa</span></div>`;
      } else {
        const pUser = parentComment.user || parentComment;
        const pUsername = pUser.username || pUser.full_name || 'user';
        replyToBadge = `<div class="comment-reply-to-badge"><span class="reply-arrow">↳ Trả lời</span> @${escapeHtml(pUsername)}</div>`;
      }
    }
  }

  const authorUsername = String(user.username || comment.username || '');
  const authorHref = `${(typeof APP_URL !== 'undefined' && APP_URL ? APP_URL : '/DoAnMaNguonMo/public').replace(/\/+$/, '')}/profile.html?id=${authorId}`;
  const formattedContent = typeof window.formatCommentContentWithMentions === 'function'
    ? window.formatCommentContentWithMentions(comment.content, comment.mentions_map)
    : escapeHtml(comment.content);

  return `<article id="comment-${commentId}" class="comment-item comment-depth-${visualDepth}" data-comment-id="${commentId}" data-depth="${depth}" data-author-id="${authorId}" data-author-username="${escapeHtml(authorUsername)}" style="--comment-depth:${visualDepth}">
    <a href="${escapeHtml(authorHref)}"><img src="${avatarUrl(user.avatar)}" class="avatar avatar-sm comment-avatar" alt=""></a>
    <div class="comment-content-box">
      <div class="comment-bubble">
        <a href="${escapeHtml(authorHref)}" class="comment-author-link"><strong>${escapeHtml(user.full_name || user.username || 'Người dùng')}</strong></a>
        ${replyToBadge}
        <p class="comment-text">${formattedContent}</p>
        ${comment.edited_at ? '<small class="comment-edited">đã chỉnh sửa</small>' : ''}
      </div>
      <div class="comment-actions">
        ${commentReactionMarkup(comment, targetPostId)}
        <button type="button" class="comment-action" onclick="replyToComment(${commentId}, ${targetPostId}, ${authorId}, '${escapeHtml(authorUsername).replace(/'/g, "\\'")}')">Trả lời</button>
        ${showMenu ? `
          <details class="comment-menu">
            <summary aria-label="Tùy chọn bình luận">•••</summary>
            <div class="comment-menu-items">
              ${canEdit ? `<button type="button" onclick="this.closest('details').open=false; editComment(${commentId}, ${targetPostId})">Sửa bình luận</button>` : ''}
              ${canDelete ? `<button type="button" class="danger" onclick="this.closest('details').open=false; deleteComment(${commentId}, ${targetPostId})">${isPostOwner && !isOwnComment ? 'Xóa (chủ bài viết)' : 'Xóa bình luận'}</button>` : ''}
              ${canReport ? `<button type="button" onclick="this.closest('details').open=false; reportComment(${commentId})"><span class="report-exclamation-icon" aria-hidden="true">!</span> Báo cáo bình luận</button>` : ''}
            </div>
          </details>` : ''}
      </div>
    </div>
  </article>`;
}

function renderCommentNode(comment, post, comments, depth = 0) {
  return renderCommentCard(comment, post, comments, depth);
}

function renderCommentThreads(comments, post) {
  if (!comments || comments.length === 0) {
    return '<p class="text-muted text-center py-2">Chưa có bình luận nào. Hãy là người đầu tiên!</p>';
  }

  const commentMap = new Map();
  comments.forEach(c => commentMap.set(Number(c.id), c));

  // 1. Lọc bỏ bình luận mồ côi: nếu có parent_id nhưng parent_id không tồn tại trong danh sách comment thì loại bỏ
  const validComments = comments.filter(c => {
    const pId = Number(c.parent_id || 0);
    return pId === 0 || commentMap.has(pId);
  });

  const childrenByParent = new Map();
  const sortFn = (a, b) => (new Date(a.created_at || 0).getTime() - new Date(b.created_at || 0).getTime()) || (Number(a.id) - Number(b.id));

  validComments.forEach(c => {
    const pId = Number(c.parent_id || 0);
    if (!childrenByParent.has(pId)) childrenByParent.set(pId, []);
    childrenByParent.get(pId).push(c);
  });

  childrenByParent.forEach(list => list.sort(sortFn));

  // Hàm thu thập toàn bộ hậu duệ của một comment (tất cả các cấp sâu hơn)
  function collectDescendants(parentId) {
    const pId = Number(parentId);
    const direct = childrenByParent.get(pId) || [];
    let descendants = [];
    for (const child of direct) {
      descendants.push(child);
      const sub = collectDescendants(child.id);
      if (sub.length > 0) {
        descendants = descendants.concat(sub);
      }
    }
    return descendants;
  }

  const roots = childrenByParent.get(0) || [];
  return roots.map(root => {
    const rootId = Number(root.id);
    const rootHtml = renderCommentCard(root, post, validComments, 0);

    // Cấp 1: Các bình luận con trực tiếp của bình luận gốc
    const tier1Children = childrenByParent.get(rootId) || [];
    let tier1Html = '';

    if (tier1Children.length > 0) {
      const tier1CardsHtml = tier1Children.map(c1 => {
        const c1Id = Number(c1.id);
        const c1Html = renderCommentCard(c1, post, validComments, 1);

        // Cấp 2 và tất cả các cấp sâu hơn: gom phẳng thành một danh sách duy nhất, không lồng thêm DOM
        const tier2Descendants = collectDescendants(c1Id);
        tier2Descendants.sort(sortFn);

        let tier2Html = '';
        if (tier2Descendants.length > 0) {
          const tier2CardsHtml = tier2Descendants.map(desc => renderCommentCard(desc, post, validComments, 2)).join('');
          tier2Html = `<div class="comment-replies comment-replies-tier2">${tier2CardsHtml}</div>`;
        }

        return `<div class="comment-branch-tier1" data-comment-id="${c1Id}">${c1Html}${tier2Html}</div>`;
      }).join('');

      tier1Html = `<div class="comment-replies comment-replies-tier1">${tier1CardsHtml}</div>`;
    }

    return `<div class="comment-branch-root" data-root-id="${rootId}" data-comment-id="${rootId}">${rootHtml}${tier1Html}</div>`;
  }).join('');
}

// 3. XỬ LÝ POP-UP MODAL XEM CHI TIẾT
let isModalOpening = false;
async function openPostModal(postId, event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }
  const numericId = Number(postId);
  if (!numericId || isNaN(numericId)) return;

  const modalEl = document.getElementById("post-modal");
  // Nếu trang không có modal, điều hướng trực tiếp sang trang chi tiết bài viết
  if (!modalEl) {
    navigateToPost(numericId);
    return;
  }

  if (isModalOpening) return;
  isModalOpening = true;
  setTimeout(() => { isModalOpening = false; }, 1500);

  let post = (typeof mockPosts !== 'undefined' && Array.isArray(mockPosts)) ? mockPosts.find(p => Number(p.id) === numericId) : null;
  try {
    const response = await apiRequest(`api/posts/${numericId}`);
    post = response.data;
    if (typeof mockPosts !== 'undefined' && Array.isArray(mockPosts)) {
      const index = mockPosts.findIndex(item => Number(item.id) === Number(post.id));
      if (index >= 0) mockPosts[index] = post;
    }
  } catch (error) {
    console.warn('Lỗi tải modal bài viết, chuyển hướng trang chi tiết:', error);
    if (!post) {
      isModalOpening = false;
      navigateToPost(numericId);
      return;
    }
  }
  if (!post) {
    isModalOpening = false;
    return;
  }

  const postComments = Array.isArray(post.comments) ? post.comments : [];
  const commentCount = Number(
    post.comments_count ??
    post.comment_count ??
    post.total_comments ??
    postComments.length
  );
  const shareCount = Number(post.shares_count ?? post.total_shares ?? post.share_count ?? 0);
  const modalBody = document.getElementById("modal-body-content");
  const postAuthorId = post.user?.id || post.user_id || 1;
  const postAuthorName = post.user?.full_name || post.full_name || post.username || 'Người dùng';
  const postAuthorAvatar = post.user?.avatar || post.avatar;

  modalBody.innerHTML = `
    <div class="post-header">
      <a href="profile.html?id=${postAuthorId}"><img src="${avatarUrl(postAuthorAvatar)}" class="avatar" alt="Avatar"></a>
      <div class="user-info">
        <a href="profile.html?id=${postAuthorId}" class="user-name">${escapeHtml(postAuthorName)}</a>
        <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--text-muted, #64748b); margin-top:2px;">
          <span class="font-size-sm text-muted">${post.created_at || ''}</span>
          <span aria-hidden="true">•</span>
          <span style="display:inline-flex; align-items:center; gap:4px;" title="${Number(post.views_count || post.views || 0).toLocaleString()} lượt xem">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            <span>${Number(post.views_count || post.views || 0).toLocaleString()} lượt xem</span>
          </span>
        </div>
      </div>
      <span class="badge ${post.post_type === 'news' ? 'badge-news' : 'badge-blog'} ml-auto">${post.post_type === 'news' ? 'Tin tức' : 'Blog'}</span>
    </div>

    ${post.original_id ? `<p class="repost-comment">${escapeHtml(post.share_comment || '')}</p><article class="repost-preview" onclick="openPostModal(${Number(post.original_id)})"><strong>${escapeHtml(post.original_title || 'Bài viết gốc')}</strong><p>${escapeHtml(post.original_summary || String(post.original_content || '').slice(0, 240))}</p><small>Đăng bởi ${escapeHtml(post.original_author || '')}</small></article>` : (post.share_comment || (post.title && post.title.startsWith('Đăng lại:') && !post.original_id) ? `<p class="repost-comment">${escapeHtml(post.share_comment || '')}</p><article class="repost-preview repost-deleted"><em><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Bài viết gốc đã bị xóa hoặc không còn khả dụng.</em></article>` : `<h2 class="font-size-xl mb-3 mt-3">${escapeHtml(post.title)}</h2><div class="post-content-full">${escapeHtml(post.content).replace(/\n/g, '<br>')}</div>`)}

    <!-- Hashtags trong Pop-up -->
    <div class="post-hashtags mt-3">
      ${post.hashtags ? post.hashtags.map(tag => `
        <span class="post-hashtag-pill" onclick="closeModal('post-modal'); filterHashtag('${tag}');">#${tag}</span>
      `).join('') : ''}
    </div>

    ${post.thumbnail ? `<img src="${post.thumbnail}" class="post-image mt-3 post-image-zoomable" alt="Ảnh bài viết" role="button" tabindex="0" title="Nhấn để xem ảnh lớn" onclick="openImageViewer(this.src, this.alt)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openImageViewer(this.src,this.alt)}">` : ''}

    <div class="post-interactive-stats mt-4">
      ${reactionMarkup(post)}
      <button class="stat-btn">${SVG_ICONS.comment} <span>${commentCount}</span> Bình luận</button>
      <button type="button" class="stat-btn stat-btn-share" data-post-id="${post.id}" onclick="sharePost(${post.id})">${SVG_ICONS.share} <span class="shares-count">${shareCount}</span> Chia sẻ</button>
      <button class="stat-btn stat-btn-bookmark ${post.is_bookmarked ? 'active' : ''}" data-post-id="${post.id}" onclick="toggleBookmark(${post.id}, this)">${SVG_ICONS.bookmark(post.is_bookmarked)} <span>${post.is_bookmarked ? 'Đã lưu' : 'Lưu'}</span></button>
    </div>

    <!-- Danh Sách Bình Luận Trong Pop-up -->
    <div class="comments-section">
      <h4 class="mb-3">Bình luận (${commentCount})</h4>
      <div id="modal-comments-list">
        ${renderCommentThreads(postComments, post)}
      </div>

      <div class="comment-input-box">
        <input type="hidden" id="modal-comment-parent-id" value="">
        <input type="text" id="modal-comment-input" class="input-field" placeholder="Viết bình luận...">
        <button class="action-btn action-btn-primary" onclick="addCommentFromModal(${post.id})">Gửi</button>
      </div>
    </div>
  `;

  modalEl.dataset.postId = String(post.id);
  modalEl.style.display = "flex";
  modalEl.style.pointerEvents = "auto";
  modalEl.style.visibility = "visible";
  modalEl.classList.add("active");
  document.body.style.overflow = "hidden";
  isModalOpening = false;
}
window.openPostModal = openPostModal;

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove("active");
    modal.style.display = "none";
    modal.style.pointerEvents = "none";
    modal.style.visibility = "hidden";
    delete modal.dataset.postId;
  }
  document.body.style.overflow = "";
  isNavigating = false;
  isModalOpening = false;
  currentOpeningPostId = null;
}
window.closeModal = closeModal;

function openImageViewer(src, alt = 'Ảnh bài viết') {
  let viewer = document.getElementById('image-viewer');
  if (!viewer) {
    viewer = document.createElement('div');
    viewer.id = 'image-viewer';
    viewer.className = 'image-viewer';
    viewer.setAttribute('role', 'dialog');
    viewer.setAttribute('aria-modal', 'true');
    viewer.innerHTML = '<button type="button" class="image-viewer-close" aria-label="Đóng xem ảnh">&times;</button><img class="image-viewer-image" alt="">';
    viewer.addEventListener('click', event => { if (event.target === viewer || event.target.closest('.image-viewer-close')) viewer.classList.remove('active'); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') viewer.classList.remove('active'); });
    document.body.appendChild(viewer);
  }
  const image = viewer.querySelector('img');
  image.src = src;
  image.alt = alt;
  viewer.classList.add('active');
}

async function addCommentFromModal(postId) {
  const input = document.getElementById("modal-comment-input");
  const rawText = input?.value.trim() || '';
  if (!rawText) return;
  const text = typeof window.serializeMentionsFromInput === 'function'
    ? window.serializeMentionsFromInput(input, rawText).trim()
    : rawText;
  if (!text) return;
  try {
    const parentId = document.getElementById('modal-comment-parent-id')?.value || null;
    await apiRequest(`api/posts/${postId}/comments`, { method: 'POST', body: { content: text, parent_id: parentId } });
    const modalParent = document.getElementById('modal-comment-parent-id');
    if (modalParent) modalParent.value = '';
    const modalInd = document.getElementById('modal-replying-indicator');
    if (modalInd) modalInd.style.display = 'none';
    if (input) {
      input.value = '';
      input.placeholder = 'Viết bình luận... (gõ @ để nhắc tên)';
      if (typeof window.clearInputMentions === 'function') window.clearInputMentions(input);
    }
    await loadBackendFeed();
    await openPostModal(postId);
  } catch (error) { alert(error.message); }
}

function resolveCommentAuthorMeta(commentId, authorId, authorUsername) {
  let uid = Number(authorId || 0);
  let uname = String(authorUsername || '').trim();
  const article = document.querySelector(`article[data-comment-id="${Number(commentId)}"]`);
  if (article) {
    if (!uid) uid = Number(article.dataset.authorId || 0);
    if (!uname) uname = String(article.dataset.authorUsername || '').trim();
    if (!uname) {
      const strong = article.querySelector('.comment-bubble strong');
      if (strong) uname = strong.textContent.trim();
    }
  }
  return { uid, uname };
}

function replyToComment(commentId, arg2, arg3, arg4) {
  let postId = null;
  let authorId = null;
  let authorUsername = '';

  if (typeof arg4 !== 'undefined') {
    postId = arg2;
    authorId = arg3;
    authorUsername = arg4;
  } else if (typeof arg3 === 'string') {
    authorId = arg2;
    authorUsername = arg3;
  } else {
    postId = arg2;
    if (typeof arg3 !== 'undefined' && typeof arg3 !== 'object') {
      authorId = arg3;
    }
  }

  const { uid, uname } = resolveCommentAuthorMeta(commentId, authorId, authorUsername);
  const displayTarget = uname ? `@${uname}` : `bình luận #${commentId}`;

  // 1. Nếu đang mở Modal chi tiết
  const modalParent = document.getElementById('modal-comment-parent-id');
  const modalInput = document.getElementById('modal-comment-input');
  const modalOverlay = document.getElementById('post-modal');
  const isModalVisible = modalOverlay && (modalOverlay.classList.contains('active') || modalOverlay.style.display === 'flex' || modalOverlay.style.display === 'block');
  if (modalParent && modalInput && (isModalVisible || modalInput.offsetParent !== null)) {
    modalParent.value = String(commentId);
    modalInput.placeholder = `Đang trả lời ${displayTarget}...`;
    let modalInd = document.getElementById('modal-replying-indicator');
    if (!modalInd && modalInput.parentElement) {
      modalInd = document.createElement('div');
      modalInd.id = 'modal-replying-indicator';
      modalInd.className = 'comment-replying-indicator';
      modalInd.style.cssText = 'display:flex; font-size:12px; color:#0d9488; margin-bottom:6px; align-items:center; gap:6px;';
      modalInd.innerHTML = '<span>↳ Đang trả lời <span class="reply-target-id"></span></span><button type="button" onclick="cancelReplyComment()" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:11px;text-decoration:underline;padding:0;">(Hủy)</button>';
      modalInput.parentElement.parentElement.insertBefore(modalInd, modalInput.parentElement);
    }
    if (modalInd) {
      modalInd.style.display = 'flex';
      const sp = modalInd.querySelector('.reply-target-id');
      if (sp) sp.textContent = displayTarget;
    }
    if (uid > 0 && uname && typeof window.applyAutoReplyMention === 'function') {
      window.applyAutoReplyMention(modalInput, uid, uname);
    } else {
      modalInput.focus();
    }
    return;
  }

  // 2. Nếu đang ở trang chi tiết bài viết /post/{id}
  const targetPostId = postId || document.getElementById('post-detail')?.dataset?.postId || document.getElementById('comment-list')?.dataset?.postId;
  if (targetPostId) {
    const parentInput = document.getElementById(`comment-parent-id-${targetPostId}`);
    const commentInput = document.getElementById(`comment-input-${targetPostId}`);
    const indicator = document.getElementById(`comment-replying-indicator-${targetPostId}`);
    if (parentInput && commentInput) {
      parentInput.value = String(commentId);
      commentInput.placeholder = `Đang trả lời ${displayTarget}...`;
      if (indicator) {
        indicator.style.display = 'flex';
        const span = indicator.querySelector('.reply-target-id');
        if (span) span.textContent = displayTarget;
      }
      if (uid > 0 && uname && typeof window.applyAutoReplyMention === 'function') {
        window.applyAutoReplyMention(commentInput, uid, uname);
      } else {
        commentInput.focus();
      }
    }
  }
}
window.replyToComment = replyToComment;

function cancelReplyComment(postId) {
  const modalParent = document.getElementById('modal-comment-parent-id');
  const modalInput = document.getElementById('modal-comment-input');
  const modalInd = document.getElementById('modal-replying-indicator');
  if (modalParent) modalParent.value = '';
  if (modalInput) {
    if (typeof window.clearAutoReplyMention === 'function') window.clearAutoReplyMention(modalInput);
    modalInput.placeholder = 'Viết bình luận... (gõ @ để nhắc tên)';
  }
  if (modalInd) modalInd.style.display = 'none';

  const targetPostId = postId || document.getElementById('post-detail')?.dataset?.postId || document.getElementById('comment-list')?.dataset?.postId;
  if (targetPostId) {
    const parentInput = document.getElementById(`comment-parent-id-${targetPostId}`);
    const commentInput = document.getElementById(`comment-input-${targetPostId}`);
    const indicator = document.getElementById(`comment-replying-indicator-${targetPostId}`);
    if (parentInput) parentInput.value = '';
    if (commentInput) {
      if (typeof window.clearAutoReplyMention === 'function') window.clearAutoReplyMention(commentInput);
      commentInput.placeholder = 'Viết bình luận của bạn... (gõ @ để nhắc tên)';
    }
    if (indicator) indicator.style.display = 'none';
  }
}
window.cancelReplyComment = cancelReplyComment;

async function editComment(commentId, postId) {
  const comment = document.querySelector(`article[data-comment-id="${commentId}"] .comment-text`);
  const content = prompt('Chỉnh sửa bình luận:', comment?.textContent || '');
  if (content === null || !content.trim()) return;
  try {
    await apiRequest(`api/comments/${commentId}/edit`, { method: 'POST', body: { content: content.trim() } });
    if (document.getElementById('post-detail')) {
      await initializePostDetail();
    } else {
      await openPostModal(postId);
    }
  } catch (error) { alert(error.message); }
}

async function deleteComment(commentId, postId) {
  if (!confirm('Bạn chắc chắn muốn xóa bình luận này?')) return;
  try {
    const res = await apiRequest(`api/comments/${commentId}/delete`, { method: 'POST', body: {} });
    const deletedIds = Array.isArray(res?.deleted_ids) ? res.deleted_ids : [Number(commentId)];

    // 1. Xóa ngay lập tức khỏi DOM toàn bộ nhánh bình luận (cả bình luận cha lẫn con/cháu/chắt)
    deletedIds.forEach(id => {
      const numId = Number(id);
      document.querySelectorAll(`[data-comment-id="${numId}"]`).forEach(el => {
        const branchWrapper = el.closest('.comment-branch-root, .comment-branch-tier1');
        if (branchWrapper && (Number(branchWrapper.dataset.commentId) === numId || Number(branchWrapper.dataset.rootId) === numId)) {
          branchWrapper.remove();
        } else {
          el.remove();
        }
      });
      document.querySelectorAll(`[data-root-id="${numId}"]`).forEach(el => el.remove());
    });

    // 2. Cập nhật lại số lượng bình luận trên giao diện
    const targetPostId = Number(postId || res?.post_id);
    const newCount = (typeof res?.comment_count === 'number') ? res.comment_count : null;

    if (newCount !== null) {
      const heading = document.querySelector('.comment-section-title');
      if (heading) heading.textContent = `Bình luận (${newCount})`;

      const modalHeading = document.querySelector('.comments-section h4');
      if (modalHeading) modalHeading.textContent = `Bình luận (${newCount})`;

      if (targetPostId) {
        document.querySelectorAll(`.post-card[data-post-id="${targetPostId}"] .comments-count, button[onclick*="openPostModal(${targetPostId})"] .comments-count`).forEach(span => {
          span.textContent = newCount;
        });
      }
    }

    // 3. Nếu danh sách bình luận đã trống, hiển thị thông báo
    const commentList = document.getElementById('comment-list') || document.getElementById('modal-comments-list');
    if (commentList && !commentList.querySelector('.comment-item, article')) {
      commentList.innerHTML = '<p class="text-muted text-center py-2">Chưa có bình luận nào. Hãy là người đầu tiên!</p>';
    }

    // 4. Nếu ở trang chi tiết bài viết, đồng bộ lại dữ liệu nền
    if (document.getElementById('post-detail')) {
      await initializePostDetail();
    } else if (document.getElementById('post-modal') && document.getElementById('post-modal').style.display !== 'none') {
      if (targetPostId) {
        const freshRes = await apiRequest(`api/posts/${targetPostId}`);
        if (freshRes?.data) {
          const mList = document.getElementById('modal-comments-list');
          if (mList) mList.innerHTML = renderCommentThreads(freshRes.data.comments || [], freshRes.data);
        }
      }
    }

    if (typeof showToast === 'function') {
      showToast('Đã xóa bình luận và các phản hồi liên quan.', 'success');
    }
  } catch (error) { alert(error.message); }
}

/**
 * Cập nhật đồng bộ DOM hiển thị cảm xúc của một bình luận cụ thể ([data-comment-id="..."])
 * Tuyệt đối không ghi đè innerHTML của container bài viết / danh sách bình luận.
 */
function updateCommentReactionDom(commentId, data) {
  const numericCommentId = Number(commentId);
  if (!numericCommentId || isNaN(numericCommentId)) return;

  const newReaction = data.reaction ?? data.my_reaction ?? null;
  const totalCount = Number(data.total ?? data.likes_count ?? data.likes ?? 0);
  const reactionCounts = data.counts ?? data.reaction_counts ?? {};

  const reactionCfg = newReaction && POST_REACTIONS[newReaction] ? POST_REACTIONS[newReaction] : null;
  const isReacted = Boolean(newReaction && reactionCfg);

  // Tìm tất cả các phần tử comment trên trang hoặc trong modal
  const commentItems = document.querySelectorAll(`[data-comment-id="${numericCommentId}"]`);
  if (!commentItems.length) return;

  commentItems.forEach(item => {
    const trigger = item.querySelector('.comment-reaction-control .reaction-trigger') || item.querySelector('.reaction-trigger');
    if (!trigger) return;

    if (isReacted) {
      trigger.classList.add('is-reacted', 'active');
      trigger.setAttribute('data-reaction', newReaction);
    } else {
      trigger.classList.remove('is-reacted', 'active');
      trigger.removeAttribute('data-reaction');
    }

    const labelText = isReacted ? reactionCfg.label : 'Thích';
    const emoji = isReacted ? reactionCfg.emoji : '👍';

    trigger.innerHTML = `<span class="reaction-icon">${emoji}</span> ${labelText} · <span class="likes-count">${totalCount}</span>`;

    // Cập nhật tooltip phân bổ cảm xúc
    const ctrl = item.querySelector('.reaction-control');
    if (ctrl) {
      const breakdownParts = [];
      for (const [key, pItem] of Object.entries(POST_REACTIONS)) {
        const c = Number(reactionCounts[key] || 0);
        if (c > 0) breakdownParts.push(`${pItem.emoji} ${c}`);
      }
      const breakdownText = breakdownParts.length > 0 ? breakdownParts.join(' · ') : 'Chưa có tương tác';
      ctrl.setAttribute('title', breakdownText);
      ctrl.classList.remove('reaction-open');
    }
  });
}
window.updateCommentReactionDom = updateCommentReactionDom;

async function setCommentReaction(commentId, postId, reaction, event, triggerEl) {
  if (event) {
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
    if (typeof event.preventDefault === 'function') event.preventDefault();
  }
  const targetControl = triggerEl?.closest?.('.reaction-control') || event?.target?.closest?.('.reaction-control');
  if (targetControl) {
    closeReactionPicker(targetControl);
    targetControl.classList.add('reaction-closing');
    setTimeout(() => targetControl.classList.remove('reaction-closing'), 300);
  }
  try {
    const res = await apiRequest(`api/comments/${commentId}/like`, { method: 'POST', body: { reaction } });
    // Cập nhật đúng số lượng và trạng thái trên nút cảm xúc của comment vừa bấm
    // Tuyệt đối KHÔNG ghi đè innerHTML của post-detail hoặc comment-list!
    updateCommentReactionDom(commentId, res);
  } catch (error) {
    console.error('Lỗi khi gửi cảm xúc bình luận:', error);
    if (error.message && (error.message.includes('Đăng nhập') || error.message.includes('401'))) {
      if (confirm('Bạn cần đăng nhập để thả cảm xúc. Đến trang đăng nhập ngay?')) {
        window.location.href = 'account.html?mode=login';
      }
    } else {
      alert(error.message || 'Không thể cập nhật cảm xúc bình luận.');
    }
  }
}
window.setCommentReaction = setCommentReaction;

async function likeComment(commentId, postId, currentReaction, trigger, event) {
  if (event) {
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
    if (typeof event.preventDefault === 'function') event.preventDefault();
  }
  const wrapper = trigger?.closest('.reaction-control');
  if (wrapper?.dataset.suppressLikeClick === '1') { delete wrapper.dataset.suppressLikeClick; return; }
  const isReacted = trigger?.classList?.contains('is-reacted') || Boolean(trigger?.getAttribute('data-reaction')) || Boolean(currentReaction);
  const nextReaction = isReacted ? null : 'like';
  await setCommentReaction(commentId, postId, nextReaction, event, trigger);
}
window.likeComment = likeComment;
window.setCommentReaction = setCommentReaction;
window.reactComment = setCommentReaction;
window.editComment = editComment;
window.deleteComment = deleteComment;
window.replyToComment = replyToComment;
window.replyComment = replyToComment;
window.cancelReplyComment = cancelReplyComment;
window.addCommentFromModal = addCommentFromModal;
window.reportComment = reportComment;
window.renderCommentCard = renderCommentCard;
window.renderCommentItem = renderCommentCard;
window.renderCommentNode = renderCommentNode;
window.renderCommentThreads = renderCommentThreads;
window.renderCommentTree = renderCommentThreads;

// 4. HASHTAGS FILTERING
function filterHashtag(tagName) {
  const cleanTag = String(tagName || '').replace(/^#/, '').trim();
  if (!cleanTag) return;

  const feedContainer = document.getElementById('feed-container');
  if (!feedContainer) {
    const base = (typeof window.APP_URL !== 'undefined' && window.APP_URL)
      ? String(window.APP_URL).replace(/\/$/, '')
      : new URL('.', window.location.href).pathname.replace(/\/$/, '');
    window.location.href = `${base}/index.html?hashtag=${encodeURIComponent(cleanTag)}#feed`;
    return;
  }

  const typeSelect = document.getElementById('feed-filter-type');
  const imageSelect = document.getElementById('feed-filter-has-image');
  const extra = {};
  if (typeSelect && typeSelect.value !== 'all') extra.type = typeSelect.value;
  if (imageSelect && imageSelect.value !== 'all') extra.has_image = imageSelect.value;
  loadBackendFeed({ hashtag: cleanTag, ...extra }).catch(error => alert(error.message));
  const searchInput = document.getElementById("smart-search-input");
  if (searchInput) searchInput.value = `#${cleanTag}`;
}
window.filterHashtag = filterHashtag;

async function renderTopHashtagsWidget() {
  const container = document.getElementById("widget-top-hashtags");
  if (!container) return;

  try {
    let tags = [];
    if (typeof apiRequest === 'function') {
      const res = await apiRequest('api/hashtags', { params: { limit: 12, _t: Date.now() } });
      tags = res && Array.isArray(res.data) ? res.data : [];
    } else {
      const base = (typeof window.APP_URL !== 'undefined' && window.APP_URL)
        ? String(window.APP_URL).replace(/\/$/, '')
        : new URL('.', window.location.href).pathname.replace(/\/$/, '');
      const resp = await fetch(`${base}/index.php?url=api/hashtags&limit=12&_t=${Date.now()}`);
      const json = await resp.json();
      tags = json && Array.isArray(json.data) ? json.data : [];
    }

    if ((!tags || tags.length === 0) && typeof mockHashtags !== 'undefined' && Array.isArray(mockHashtags) && mockHashtags.length > 0) {
      tags = mockHashtags;
    }

    if (!tags || tags.length === 0) {
      container.innerHTML = '<span class="text-muted font-size-sm">Chưa có chủ đề nổi bật.</span>';
      return;
    }

    container.innerHTML = `
      <div style="display:flex;flex-wrap:wrap;gap:6px;">
        ${tags.map(tag => `
          <span class="post-hashtag-pill-widget" onclick="filterHashtag('${escapeHtml(tag.name)}')">
            #${escapeHtml(tag.name)} <small style="color:#7c827d;font-weight:normal;">(${Number(tag.usage_count || 0)})</small>
          </span>
        `).join('')}
      </div>
    `;
  } catch (err) {
    console.warn('[Hashtags Widget] Không thể nạp hashtags:', err);
    if (typeof mockHashtags !== 'undefined' && Array.isArray(mockHashtags) && mockHashtags.length > 0) {
      container.innerHTML = `
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
          ${mockHashtags.map(tag => `
            <span class="post-hashtag-pill-widget" onclick="filterHashtag('${escapeHtml(tag.name)}')">
              #${escapeHtml(tag.name)} <small style="color:#7c827d;font-weight:normal;">(${Number(tag.usage_count || 0)})</small>
            </span>
          `).join('')}
        </div>
      `;
    }
  }
}
window.renderTopHashtagsWidget = renderTopHashtagsWidget;

// 5. Chức Năng Đăng Bài Tức Thì
let uploadedImageBase64 = null;

function setupImageUploadAndPaste() {
  const fileInput = document.getElementById("post-file-input");
  const editorInput = document.getElementById("post-editor-input");

  if (fileInput) {
    fileInput.addEventListener("change", (e) => {
      const file = e.target.files[0];
      if (file && file.type.startsWith("image/")) {
        processImageFile(file);
      }
    });
  }

  if (editorInput) {
    editorInput.addEventListener("paste", (e) => {
      const items = (e.clipboardData || e.originalEvent.clipboardData).items;
      for (let item of items) {
        if (item.type.indexOf("image") !== -1) {
          const file = item.getAsFile();
          processImageFile(file);
          e.preventDefault();
          break;
        }
      }
    });
  }
}

function processImageFile(file) {
  const reader = new FileReader();
  reader.onload = function (event) {
    uploadedImageBase64 = event.target.result;
    
    const previewContainer = document.getElementById("image-preview-container");
    const previewImg = document.getElementById("image-preview");
    if (previewContainer && previewImg) {
      previewImg.src = uploadedImageBase64;
      previewContainer.style.display = "block";
    }
  };
  reader.readAsDataURL(file);
}

function removeSelectedImage() {
  uploadedImageBase64 = null;
  const previewContainer = document.getElementById("image-preview-container");
  const fileInput = document.getElementById("post-file-input");
  if (previewContainer) previewContainer.style.display = "none";
  if (fileInput) fileInput.value = "";
}

async function createNewPost() {
  const title = document.getElementById("post-title-input").value.trim();
  const content = document.getElementById("post-editor-input").value.trim();
  const tagsStr = document.getElementById("post-tags-input").value.trim();
  const postType = document.getElementById("post-type-select").value;

  if (!title || !content) {
    alert("Vui lòng nhập đầy đủ tiêu đề và nội dung bài viết!");
    return;
  }

  const tagsArray = tagsStr ? tagsStr.split(',').map(t => t.trim().replace('#', '')) : ['blog'];
  try {
    let thumbnail = null;
    if (uploadedImageBase64) {
      const upload = await apiRequest('api/uploads/image', { method: 'POST', body: { image: uploadedImageBase64 } });
      thumbnail = upload.url;
    }
    await apiRequest('api/posts/create', { method: 'POST', body: { title, content, post_type: postType, hashtags: tagsArray, thumbnail } });
    await loadBackendFeed();
  } catch (error) { alert(error.message); return; }

  document.getElementById("post-title-input").value = "";
  document.getElementById("post-editor-input").value = "";
  document.getElementById("post-tags-input").value = "";
  removeSelectedImage();
}

async function toggleLike(postId, trigger, event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }
  const wrapper = trigger?.closest?.('.reaction-control');
  if (wrapper?.dataset.suppressLikeClick === '1') {
    delete wrapper.dataset.suppressLikeClick;
    return;
  }

  const numericId = Number(postId);
  const currentReaction = trigger?.getAttribute('data-reaction');
  const isReacted = trigger?.classList?.contains('is-reacted') || Boolean(currentReaction);

  // Nếu đã thả cảm xúc bất kỳ -> hủy thả (null). Nếu chưa -> thả 'like'
  const nextReaction = isReacted ? null : 'like';
  await setPostReaction(numericId, nextReaction, event);
}
window.toggleLike = toggleLike;

function sharePost(postId, event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }
  document.getElementById('share-action-modal')?.remove();
  const modal = document.createElement('div');
  modal.id = 'share-action-modal';
  modal.className = 'modal-overlay active share-action-overlay';
  modal.innerHTML = `<section class="share-action-dialog" role="dialog" aria-modal="true" aria-label="Chia sẻ bài viết" onclick="event.stopPropagation()">
    <button class="close-modal" aria-label="Đóng" onclick="document.getElementById('share-action-modal').remove()">&times;</button>
    <h3>Chia sẻ bài viết</h3>
    <button type="button" class="share-choice" onclick="copyPostLink(${Number(postId)})">${SVG_ICONS.link} Sao chép liên kết</button>
    <button type="button" class="share-choice" onclick="repostPost(${Number(postId)})">${SVG_ICONS.repost} Đăng lại lên trang cá nhân</button>
  </section>`;
  modal.addEventListener('click', event => { if (event.target === modal) modal.remove(); });
  document.body.appendChild(modal);
}

function updatePostShareDom(postId, totalShares) {
  const count = Number(totalShares || 0);
  const elements = document.querySelectorAll(
    `[data-post-id="${postId}"] .shares-count, .stat-btn-share[data-post-id="${postId}"] .shares-count, button[onclick*="sharePost(${postId}"] .shares-count, button[onclick*="sharePost(${postId}"] span`
  );
  elements.forEach(el => {
    if (el.classList.contains('shares-count') || el.closest('.stat-btn-share') || (el.parentElement && el.parentElement.getAttribute('onclick')?.includes('sharePost('))) {
      el.textContent = count;
    }
  });
}
window.updatePostShareDom = updatePostShareDom;

async function copyPostLink(postId) {
  const url = new URL(`${APP_URL}/index.php`, window.location.origin);
  url.searchParams.set('url', `post/${postId}`);
  try {
    await navigator.clipboard.writeText(url.toString());
  } catch (_) {
    const input = document.createElement('textarea');
    input.value = url.toString(); document.body.appendChild(input); input.select();
    document.execCommand('copy'); input.remove();
  }
  document.getElementById('share-action-modal')?.remove();

  try {
    const res = await apiRequest(`api/posts/${postId}/share`, { method: 'POST', body: {} });
    if (res && (res.total_shares !== undefined || res.shares_count !== undefined)) {
      const totalShares = res.total_shares ?? res.shares_count;
      updatePostShareDom(postId, totalShares);
    }
  } catch (err) {
    console.warn('Share track:', err.message);
  }
  alert('Đã sao chép liên kết bài viết.');
}

async function repostPost(postId) {
  const comment = prompt('Thêm lời dẫn cho bài đăng lại (có thể để trống):', '') ?? null;
  if (comment === null) return;
  try {
    const res = await apiRequest(`api/posts/${postId}/repost`, { method: 'POST', body: { comment } });
    document.getElementById('share-action-modal')?.remove();
    if (res && (res.total_shares !== undefined || res.shares_count !== undefined)) {
      const totalShares = res.total_shares ?? res.shares_count;
      updatePostShareDom(postId, totalShares);
    }
    await loadBackendFeed();
    alert('Đã đăng lại bài viết lên trang cá nhân.');
  } catch (error) { alert(error.message); }
}

async function sendTicket(postId) {
  try {
    const result = await apiRequest(`api/posts/${postId}/tickets`, { method: 'POST', body: {} });
    alert(result.message);
  } catch (error) { alert(error.message); }
}

// ========================================================
// Chức năng Tìm kiếm Nâng cao (Live Search Dropdown)
// Hỗ trợ gợi ý tác giả (@username/họ tên) & bài viết liên quan
// ========================================================
const SEARCH_ICONS = {
  user: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;flex-shrink:0;"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>`,
  star: `<svg width="12" height="12" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1px;margin-right:2px;display:inline-block;flex-shrink:0;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>`,
  filter: `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1px;margin-right:3px;flex-shrink:0;"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>`,
  doc: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;flex-shrink:0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>`,
  verified: `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="vertical-align:-2px;margin-left:3px;flex-shrink:0;display:inline-block;"><circle cx="12" cy="12" r="10" fill="#1d9bf0"></circle><polyline points="8 12 11 15 16 9" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"></polyline></svg>`,
  arrowRight: `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1px;margin-left:4px;"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>`,
  searchEmpty: `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-bottom:8px;display:inline-block;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>`,
  spinner: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="animation:searchSpin 0.8s linear infinite;vertical-align:-3px;margin-right:6px;display:inline-block;"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10"></path></svg>`,
  warning: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="margin-bottom:8px;display:inline-block;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`
};

function getSearchPageUrl(keyword) {
  const base = (typeof APP_URL !== 'undefined' && APP_URL) ? String(APP_URL).replace(/\/$/, '') : '';
  if (typeof window !== 'undefined' && window.location.pathname.includes('index.php')) {
    return `${base}/index.php?url=search&q=${encodeURIComponent(keyword)}`;
  }
  return `${base}/search?q=${encodeURIComponent(keyword)}`;
}

function getSearchProfileUrl(user) {
  const base = (typeof APP_URL !== 'undefined' && APP_URL) ? String(APP_URL).replace(/\/$/, '') : '';
  const userId = user.id || user.user_id;
  if (typeof window !== 'undefined' && (window.location.pathname.endsWith('index.html') || !window.location.pathname.includes('/'))) {
    return `profile.html?id=${userId}`;
  }
  return `${base}/profile.html?id=${userId}`;
}

function formatSearchTime(dateStr) {
  if (!dateStr) return '';
  try {
    const d = new Date(dateStr.replace(/-/g, '/'));
    if (isNaN(d.getTime())) return String(dateStr).slice(0, 10);
    const now = new Date();
    const diffSec = Math.floor((now - d) / 1000);
    if (diffSec < 60) return 'Vừa xong';
    if (diffSec < 3600) return `${Math.floor(diffSec / 60)} phút trước`;
    if (diffSec < 86400) return `${Math.floor(diffSec / 3600)} giờ trước`;
    if (diffSec < 2592000) return `${Math.floor(diffSec / 86400)} ngày trước`;
    return String(dateStr).slice(0, 10);
  } catch (_) {
    return String(dateStr).slice(0, 10);
  }
}

function setupSearch() {
  const searchInput = document.getElementById("smart-search-input");
  if (!searchInput) return;

  // Đảm bảo phần tử bao quanh có position relative
  const searchContainer = searchInput.closest('.blog-search') || searchInput.parentElement;
  if (searchContainer) {
    searchContainer.style.position = 'relative';
  }

  // Khởi tạo container dropdown nếu chưa có
  let dropdown = document.getElementById("search-live-dropdown");
  if (!dropdown) {
    dropdown = document.createElement("div");
    dropdown.id = "search-live-dropdown";
    dropdown.className = "search-live-dropdown";
    if (searchContainer) {
      searchContainer.appendChild(dropdown);
    } else {
      searchInput.insertAdjacentElement('afterend', dropdown);
    }
  }

  let debounceTimer = null;
  let currentSearchQuery = '';

  const performLiveSearch = async (query) => {
    const q = query.trim();
    currentSearchQuery = q;
    if (!q) {
      dropdown.style.display = 'none';
      dropdown.innerHTML = '';
      return;
    }

    // Hiển thị trạng thái đang tìm kiếm
    dropdown.innerHTML = `
      <div class="search-live-loading">
        ${SEARCH_ICONS.spinner}
        <span>Đang tìm kiếm...</span>
      </div>
    `;
    dropdown.style.display = 'block';

    try {
      const base = (typeof APP_URL !== 'undefined' && APP_URL) ? String(APP_URL).replace(/\/$/, '') : '';
      const response = await fetch(`${base}/api/search?q=${encodeURIComponent(q)}&per_page=5&api=1`, {
        headers: { 'Accept': 'application/json' }
      });

      if (!response.ok) {
        throw new Error('Lỗi máy chủ khi tìm kiếm.');
      }

      const data = await response.json();
      if (currentSearchQuery !== q) return; // Kết quả đã lỗi thời

      const matchingUsers = data.matching_users || data.users || [];
      const posts = data.posts || [];

      if (matchingUsers.length === 0 && posts.length === 0) {
        dropdown.innerHTML = `
          <div class="search-live-empty">
            ${SEARCH_ICONS.searchEmpty}
            <div>Không tìm thấy kết quả nào cho "<strong>${escapeHtml(q)}</strong>"</div>
          </div>
        `;
        return;
      }

      let html = '';

      // 1. Khối Tác giả phù hợp (Matching Authors)
      if (matchingUsers.length > 0) {
        html += `
          <div class="search-live-section">
            <div class="search-live-section-title">
              ${SEARCH_ICONS.user}
              <span>Tác giả phù hợp (${matchingUsers.length})</span>
            </div>
            <div class="search-live-list">
              ${matchingUsers.map(user => `
                <div class="search-live-user-row">
                  <a href="${getSearchProfileUrl(user)}" class="search-live-user-link" title="Xem hồ sơ ${escapeHtml(user.full_name)}">
                    <img src="${avatarUrl(user.avatar)}" class="search-live-user-avatar" alt="">
                    <div class="search-live-user-meta">
                      <div class="search-live-user-name">
                        <span>${escapeHtml(user.full_name)}</span>
                        ${user.is_verified ? `<span class="verified-badge-mini" title="Đã xác minh">${SEARCH_ICONS.verified}</span>` : ''}
                      </div>
                      <div class="search-live-user-sub">
                        <span class="search-live-handle">@${escapeHtml(user.username)}</span>
                        ${user.reputation_score ? ` · <span class="search-live-rep">${SEARCH_ICONS.star}${user.reputation_score}</span>` : ''}
                        ${user.total_posts !== undefined ? ` · <span>${user.total_posts} bài</span>` : ''}
                      </div>
                    </div>
                  </a>
                  <button type="button" class="search-live-filter-btn" data-username="${escapeHtml(user.username)}" title="Lọc bài viết của @${escapeHtml(user.username)}">
                    ${SEARCH_ICONS.filter}
                    <span>Lọc</span>
                  </button>
                </div>
              `).join('')}
            </div>
          </div>
        `;
      }

      // 2. Khối Bài viết liên quan (Related Posts)
      if (posts.length > 0) {
        html += `
          <div class="search-live-section">
            <div class="search-live-section-title">
              ${SEARCH_ICONS.doc}
              <span>Bài viết liên quan (${posts.length})</span>
            </div>
            <div class="search-live-list">
              ${posts.map(post => {
                const authorName = post.full_name || post.username || 'Tác giả';
                return `
                  <a href="${getPostDetailUrl(post.id)}" class="search-live-post-link" title="${escapeHtml(post.title)}">
                    <div class="search-live-post-title">${escapeHtml(post.title)}</div>
                    <div class="search-live-post-meta">
                      <span>Bởi <strong>${escapeHtml(authorName)}</strong></span>
                      ${post.created_at ? ` · <span>${formatSearchTime(post.created_at)}</span>` : ''}
                    </div>
                  </a>
                `;
              }).join('')}
            </div>
          </div>
        `;
      }

      // Footer xem tất cả kết quả
      html += `
        <div class="search-live-footer">
          <a href="${getSearchPageUrl(q)}" class="search-live-view-all-link">
            <span>Xem tất cả kết quả cho "<strong>${escapeHtml(q)}</strong>"</span>
            ${SEARCH_ICONS.arrowRight}
          </a>
        </div>
      `;

      dropdown.innerHTML = html;

      // Gắn sự kiện cho nút "Lọc" bài viết của tác giả
      dropdown.querySelectorAll('.search-live-filter-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const targetUname = btn.getAttribute('data-username');
          if (!targetUname) return;

          searchInput.value = '@' + targetUname;
          dropdown.style.display = 'none';

          if (typeof loadBackendFeed === 'function' && document.getElementById('feed-container')) {
            const typeSelect = document.getElementById('feed-filter-type');
            const imageSelect = document.getElementById('feed-filter-has-image');
            const extra = {};
            if (typeSelect && typeSelect.value !== 'all') extra.type = typeSelect.value;
            if (imageSelect && imageSelect.value !== 'all') extra.has_image = imageSelect.value;
            loadBackendFeed({ author_username: targetUname, ...extra }).catch(console.error);
          } else {
            window.location.href = getSearchPageUrl('@' + targetUname);
          }
        });
      });

    } catch (err) {
      console.error('Lỗi live search:', err);
      dropdown.innerHTML = `
        <div class="search-live-empty">
          ${SEARCH_ICONS.warning}
          <div>Không thể tải kết quả gợi ý. Nhấn Enter để xem trang tìm kiếm.</div>
        </div>
      `;
    }
  };

  // Sự kiện input với Debounce ~300ms
  searchInput.addEventListener("input", (e) => {
    const value = e.target.value;
    clearTimeout(debounceTimer);

    if (!value || value.trim() === '') {
      dropdown.style.display = 'none';
      dropdown.innerHTML = '';
      if (typeof handleHomeFilterChange === 'function' && document.getElementById('feed-container')) {
        handleHomeFilterChange();
      } else if (typeof loadBackendFeed === 'function' && document.getElementById('feed-container')) {
        loadBackendFeed({}).catch(console.error);
      }
      return;
    }

    const trimmed = value.trim();

    // Nếu trên trang chủ index.html có feed, cũng cập nhật feed sau 400ms
    if (typeof loadBackendFeed === 'function' && document.getElementById('feed-container')) {
      const typeSelect = document.getElementById('feed-filter-type');
      const imageSelect = document.getElementById('feed-filter-has-image');
      const extraFilters = {};
      if (typeSelect && typeSelect.value !== 'all') extraFilters.type = typeSelect.value;
      if (imageSelect && imageSelect.value !== 'all') extraFilters.has_image = imageSelect.value;

      if (trimmed.startsWith('#')) {
        loadBackendFeed({ hashtag: trimmed.slice(1), ...extraFilters }).catch(console.error);
      } else if (trimmed.startsWith('@')) {
        loadBackendFeed({ author_username: trimmed.slice(1), ...extraFilters }).catch(console.error);
      } else {
        loadBackendFeed({ q: trimmed, ...extraFilters }).catch(console.error);
      }
    }

    debounceTimer = setTimeout(() => {
      performLiveSearch(trimmed);
    }, 300);
  });

  // Khi nhấn phím: Enter để tìm kiếm (hoặc lọc trực tiếp tại trang chủ nếu có feed-container)
  searchInput.addEventListener("keydown", (e) => {
    if (e.key === 'Enter') {
      const q = searchInput.value.trim();
      dropdown.style.display = 'none';
      clearTimeout(debounceTimer);

      if (document.getElementById('feed-container') && typeof handleHomeFilterChange === 'function') {
        e.preventDefault();
        handleHomeFilterChange();
        const feedIntro = document.querySelector('.feed-intro') || document.getElementById('feed-container');
        if (feedIntro) {
          const rect = feedIntro.getBoundingClientRect();
          if (rect.top < 0 || rect.top > window.innerHeight) {
            feedIntro.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }
      } else if (q) {
        e.preventDefault();
        window.location.href = getSearchPageUrl(q);
      }
    } else if (e.key === 'Escape') {
      dropdown.style.display = 'none';
    }
  });

  // Focus lại input: hiển thị dropdown nếu đang có kết quả
  searchInput.addEventListener("focus", () => {
    if (dropdown.innerHTML.trim() !== '' && searchInput.value.trim() !== '') {
      dropdown.style.display = 'block';
    }
  });

  // Bấm ra ngoài để đóng dropdown
  document.addEventListener("click", (e) => {
    if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.style.display = 'none';
    }
  });
}

// ========================================================
// Bộ Lọc & Phân Trang Tìm Kiếm Nâng Cao (Search Page Filters)
// ========================================================
function renderSearchPagination(pagination, currentParams) {
  const container = document.getElementById('search-pagination-container');
  if (!container) return;
  if (!pagination) {
    container.innerHTML = '';
    return;
  }
  const page = Number(pagination.page || 1);
  const lastPage = Number(pagination.last_page || pagination.lastPage || 1);
  const total = Number(pagination.total || 0);

  if (lastPage <= 1) {
    container.innerHTML = '';
    return;
  }

  const buildPageUrl = (targetPage) => {
    const sp = new URLSearchParams(currentParams);
    sp.set('page', String(targetPage));
    return `${window.location.pathname}?${sp.toString()}`;
  };

  let html = `<nav class="search-pagination" style="display:flex; justify-content:center; align-items:center; gap:8px; margin:36px 0;">`;
  if (page > 1) {
    html += `
      <a href="${escapeHtml(buildPageUrl(page - 1))}" data-page="${page - 1}" class="action-btn search-page-link" style="display:inline-flex; align-items:center; gap:4px; padding:8px 14px; font-size:13px;">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
        <span>Trang trước</span>
      </a>
    `;
  }
  html += `
    <span style="font-size:13px; color:var(--muted,#7c827d);" id="search-page-info">
      Trang ${page} / ${lastPage} (Tổng ${total} bài viết)
    </span>
  `;
  if (page < lastPage) {
    html += `
      <a href="${escapeHtml(buildPageUrl(page + 1))}" data-page="${page + 1}" class="action-btn search-page-link" style="display:inline-flex; align-items:center; gap:4px; padding:8px 14px; font-size:13px;">
        <span>Trang sau</span>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
      </a>
    `;
  }
  html += `</nav>`;
  container.innerHTML = html;
}

function setupSearchPageFilters() {
  const bar = document.getElementById('search-filters-bar');
  if (!bar) return;

  const getUrlParams = () => new URLSearchParams(window.location.search);

  const syncChipsUI = (params) => {
    const activeType = params.get('type') || 'all';
    const activeImage = params.get('has_image') || 'all';
    const activeSort = params.get('sort') || 'latest';

    document.querySelectorAll('.filter-chips-group[data-filter-name="type"] .filter-chip').forEach(c => {
      c.classList.toggle('active', (c.getAttribute('data-value') || 'all') === activeType);
    });
    document.querySelectorAll('.filter-chips-group[data-filter-name="has_image"] .filter-chip').forEach(c => {
      c.classList.toggle('active', (c.getAttribute('data-value') || 'all') === activeImage);
    });
    document.querySelectorAll('.filter-chips-group[data-filter-name="sort"] .filter-chip').forEach(c => {
      c.classList.toggle('active', (c.getAttribute('data-value') || 'latest') === activeSort);
    });

    const hiddenType = document.getElementById('search-filter-type');
    const hiddenImage = document.getElementById('search-filter-has-image');
    const hiddenSort = document.getElementById('search-filter-sort');
    if (hiddenType) hiddenType.value = activeType;
    if (hiddenImage) hiddenImage.value = activeImage;
    if (hiddenSort) hiddenSort.value = activeSort;
  };

  const executeFilterFetch = async (targetParams, pushHistory = true) => {
    const postsContainer = document.getElementById('search-posts-container');
    const postsSection = document.getElementById('search-posts-section');
    const emptyState = document.getElementById('search-empty-state');
    const postsCount = document.getElementById('search-posts-count');
    const headerStats = document.getElementById('search-header-stats');
    const authorsSection = document.querySelector('.search-authors-section');

    const searchUrl = new URL(window.location.href);
    targetParams.forEach((val, key) => {
      if (val !== undefined && val !== null && val !== '') {
        searchUrl.searchParams.set(key, val);
      } else {
        searchUrl.searchParams.delete(key);
      }
    });

    if (pushHistory) {
      window.history.pushState({ search: searchUrl.search }, '', searchUrl.href);
    }
    syncChipsUI(targetParams);

    if (postsContainer) {
      postsContainer.style.opacity = '0.5';
      postsContainer.style.pointerEvents = 'none';
      postsContainer.style.transition = 'opacity 0.2s ease';
    }

    try {
      const apiUrl = new URL(searchUrl.href);
      apiUrl.searchParams.set('api', '1');

      const response = await fetch(apiUrl.href, {
        headers: { 'Accept': 'application/json' }
      });
      if (!response.ok) throw new Error('Không thể tải kết quả lọc');
      const data = await response.json();

      if (postsContainer) {
        postsContainer.style.opacity = '1';
        postsContainer.style.pointerEvents = '';
      }

      const total = data.pagination?.total ?? (data.posts ? data.posts.length : 0);
      if (postsCount) postsCount.textContent = total;

      const usersCount = (data.users && Array.isArray(data.users)) ? data.users.length : 0;
      if (headerStats) {
        headerStats.textContent = `Tìm thấy ${total} bài viết và ${usersCount} tài khoản`;
      }

      const tabPostsBtn = document.getElementById('search-tab-posts-btn');
      if (tabPostsBtn) {
        tabPostsBtn.textContent = `Bài viết (${total})`;
      }

      if (data.posts && data.posts.length > 0) {
        if (postsSection) postsSection.style.display = '';
        if (emptyState) emptyState.style.display = 'none';
        if (postsContainer) {
          postsContainer.innerHTML = data.posts.map(renderSinglePostCard).join('');
        }
        renderSearchPagination(data.pagination, targetParams);
      } else {
        if (postsContainer) postsContainer.innerHTML = '';
        if (postsSection) postsSection.style.display = 'none';
        const hasAuthors = authorsSection && authorsSection.style.display !== 'none';
        if (emptyState && !hasAuthors) {
          emptyState.style.display = '';
        }
        const paginationContainer = document.getElementById('search-pagination-container');
        if (paginationContainer) paginationContainer.innerHTML = '';
      }

      // Cuộn mượt lên vị trí bộ lọc nếu đang ở dưới
      const barTop = bar.getBoundingClientRect().top + window.scrollY - 70;
      if (window.scrollY > barTop) {
        window.scrollTo({ top: barTop, behavior: 'smooth' });
      }

    } catch (err) {
      console.error('Lỗi khi lọc bài viết tìm kiếm:', err);
      if (postsContainer) {
        postsContainer.style.opacity = '1';
        postsContainer.style.pointerEvents = '';
      }
      window.location.href = searchUrl.href;
    }
  };

  // 1. Bắt sự kiện bấm vào Chip bộ lọc (Loại bài, Hình ảnh, Sắp xếp)
  bar.addEventListener('click', (e) => {
    const chip = e.target.closest('.filter-chip');
    if (chip) {
      e.preventDefault();
      const group = chip.closest('.filter-chips-group');
      if (!group) return;
      const filterName = group.getAttribute('data-filter-name');
      const val = chip.getAttribute('data-value') || 'all';

      const params = getUrlParams();
      params.set(filterName, val);
      params.set('page', '1'); // Đổi bộ lọc luôn quay về trang 1
      executeFilterFetch(params);
      return;
    }

    const resetBtn = e.target.closest('#search-filter-reset-btn');
    if (resetBtn) {
      e.preventDefault();
      const params = getUrlParams();
      params.set('type', 'all');
      params.set('has_image', 'all');
      params.set('sort', 'latest');
      params.set('page', '1');
      executeFilterFetch(params);
      return;
    }
  });

  // 2. Bắt sự kiện phân trang
  document.addEventListener('click', (e) => {
    const pageLink = e.target.closest('.search-page-link');
    if (pageLink && document.getElementById('search-filters-bar')) {
      e.preventDefault();
      const targetPage = pageLink.getAttribute('data-page');
      if (!targetPage) return;
      const params = getUrlParams();
      params.set('page', targetPage);
      executeFilterFetch(params);
    }
  });

  // 3. Hỗ trợ nút Back / Forward trình duyệt (popstate)
  window.addEventListener('popstate', () => {
    if (document.getElementById('search-filters-bar')) {
      const params = getUrlParams();
      executeFilterFetch(params, false);
    }
  });

  // Export hàm toàn cục để đồng bộ với thanh điều hướng (Navbar)
  window.applySearchFilterParam = function(filterName, filterValue) {
    const params = getUrlParams();
    params.set(filterName, filterValue);
    params.set('page', '1');
    executeFilterFetch(params);
  };
}

// Tự động khởi chạy ngay nếu DOM đã sẵn sàng
if (document.readyState === 'complete' || document.readyState === 'interactive') {
  setupSearchPageFilters();
}

async function renderLeftSidebar() {
  const container = document.getElementById("following-list");
  if (!container) return;

  const titleEl = document.getElementById("following-title-text") || document.querySelector(".editorial-following .sidebar-title");
  const actionLink = document.getElementById("following-action-link") || document.querySelector(".editorial-following .sidebar-card > button.text-link");

  try {
    const res = await apiRequest('api/following');
    const isGuest = Boolean(res?.is_guest);
    const titleText = res?.title || (isGuest ? 'Tác giả nổi bật' : 'Những người mình theo dõi');
    const users = Array.isArray(res?.data) ? res.data : [];

    if (titleEl) {
      if (titleEl.id === 'following-title-text') {
        titleEl.textContent = titleText;
      } else {
        const kicker = titleEl.querySelector('.section-kicker');
        const kickerHtml = kicker ? kicker.outerHTML : '<span class="section-kicker">Bạn đọc</span>';
        titleEl.innerHTML = `${kickerHtml}${escapeHtml(titleText)}`;
      }
    }

    if (actionLink) {
      if (isGuest) {
        actionLink.innerHTML = `Khám phá bài viết <svg class="btn-icon-svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1px;margin-left:4px;"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>`;
      } else {
        actionLink.innerHTML = `Xem bài viết của họ <svg class="btn-icon-svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1px;margin-left:4px;"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>`;
      }
    }

    if (!users.length) {
      const emptyMsg = isGuest
        ? 'Chưa có tác giả nổi bật.'
        : 'Bạn chưa theo dõi tác giả nào.';
      const subMsg = !isGuest
        ? '<div style="margin-top:4px;font-size:11px;color:#9ca3af;">Hãy theo dõi các tác giả yêu thích để xem bài viết của họ tại đây.</div>'
        : '';
      container.innerHTML = `
        <div class="following-empty-state" style="padding:14px 4px;font-size:12.5px;color:#7c827d;text-align:center;line-height:1.5;">
          ${emptyMsg}
          ${subMsg}
        </div>`;
      return;
    }

    container.innerHTML = users.map(user => `
      <div class="sidebar-user-item" style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #f0f0eb;">
        <a href="profile.html?id=${user.id}" title="${escapeHtml(user.full_name || user.username)}">
          <img src="${avatarUrl(user.avatar)}" class="avatar avatar-sm" alt="${escapeHtml(user.full_name || user.username)}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1.5px solid #ced0d4;">
        </a>
        <div style="flex:1;min-width:0;">
          <a href="profile.html?id=${user.id}" class="user-name user-name-sm" style="font-weight:700;font-size:12px;color:#252b28;text-decoration:none;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${escapeHtml(user.full_name || user.username)}">
            ${escapeHtml(user.full_name || user.username)} ${user.is_verified ? SEARCH_ICONS.verified : ''}
          </a>
          <div class="font-size-xs text-muted" style="font-size:11px;color:#7c827d;display:flex;align-items:center;gap:3px;margin-top:2px;">
            ${SEARCH_ICONS.star} <span>${Number(user.reputation_score || 0)} điểm uy tín</span>
          </div>
        </div>
      </div>
    `).join('');
  } catch (err) {
    console.warn('Không thể tải danh sách theo dõi từ DB:', err);
    if (typeof mockUsers !== 'undefined' && Array.isArray(mockUsers)) {
      container.innerHTML = mockUsers.map(user => `
        <div class="sidebar-user-item" style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #f0f0eb;">
          <a href="profile.html?id=${user.id}">
            <img src="${avatarUrl(user.avatar)}" class="avatar avatar-sm" alt="${escapeHtml(user.full_name)}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1.5px solid #ced0d4;">
          </a>
          <div style="flex:1;min-width:0;">
            <a href="profile.html?id=${user.id}" class="user-name user-name-sm" style="font-weight:700;font-size:12px;color:#252b28;text-decoration:none;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              ${escapeHtml(user.full_name)} ${user.is_verified ? SEARCH_ICONS.verified : ''}
            </a>
            <div class="font-size-xs text-muted" style="font-size:11px;color:#7c827d;display:flex;align-items:center;gap:3px;margin-top:2px;">
              ${SEARCH_ICONS.star} <span>${user.reputation_score} điểm uy tín</span>
            </div>
          </div>
        </div>
      `).join('');
    }
  }
}

window.renderLeftSidebar = renderLeftSidebar;
window.addEventListener('following-updated', () => {
  renderLeftSidebar();
});

async function toggleFollowUser(userId) {
  if (!CURRENT_USER_ID) {
    alert('Vui lòng đăng nhập để theo dõi tác giả.');
    window.location.href = 'account.html?mode=login';
    return;
  }
  try {
    const res = await apiRequest(`api/users/${userId}/follow`, 'POST');
    renderLeftSidebar();
    window.dispatchEvent(new CustomEvent('following-updated', { detail: { userId, isFollowing: res?.is_following } }));
    return res;
  } catch (err) {
    console.error('Lỗi khi theo dõi:', err);
    alert(err.message || 'Không thể cập nhật theo dõi.');
  }
}
window.toggleFollowUser = toggleFollowUser;

function renderTopNewsWidget() {
  if (typeof window.loadTopNewsWidget === 'function') {
    window.loadTopNewsWidget(true);
    return;
  }
  document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.loadTopNewsWidget === 'function') {
      window.loadTopNewsWidget(true);
    }
  }, { once: true });
}

/**
 * Xử lý Lưu / Bỏ lưu bài viết (Bookmark Feature)
 */
async function toggleBookmark(postId, btnElement, event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }
  if (!CURRENT_USER_ID) {
    alert('Vui lòng đăng nhập để lưu bài viết.');
    window.location.href = 'account.html';
    return;
  }

  const id = Number(postId);
  if (!id) return;

  const matchingButtons = document.querySelectorAll(`.stat-btn-bookmark[data-post-id="${id}"], .action-btn-bookmark[data-post-id="${id}"]`);
  let isAlreadyProcessing = false;
  matchingButtons.forEach(b => {
    if (b.dataset.processing === 'true') isAlreadyProcessing = true;
  });
  if (isAlreadyProcessing) return;
  matchingButtons.forEach(b => b.dataset.processing = 'true');

  const btn = btnElement || matchingButtons[0];
  const isCurrentlyBookmarked = btn ? (btn.classList.contains('active') || btn.classList.contains('bookmarked')) : false;

  const setUiState = (active) => {
    document.querySelectorAll(`.stat-btn-bookmark[data-post-id="${id}"], .action-btn-bookmark[data-post-id="${id}"]`).forEach(el => {
      if (active) {
        el.classList.add('active', 'bookmarked');
        el.setAttribute('title', 'Bỏ lưu bài viết');
        const span = el.querySelector('span, .bookmark-label');
        if (span) span.textContent = 'Đã lưu';
        const svg = el.querySelector('.icon-bookmark-svg');
        if (svg) svg.setAttribute('fill', 'currentColor');
        const icon = el.querySelector('i');
        if (icon) icon.className = 'fa-solid fa-bookmark';
      } else {
        el.classList.remove('active', 'bookmarked');
        el.setAttribute('title', 'Lưu bài viết');
        const span = el.querySelector('span, .bookmark-label');
        if (span) span.textContent = 'Lưu';
        const svg = el.querySelector('.icon-bookmark-svg');
        if (svg) svg.setAttribute('fill', 'none');
        const icon = el.querySelector('i');
        if (icon) icon.className = 'fa-regular fa-bookmark';
      }
    });
  };

  try {
    if (isCurrentlyBookmarked) {
      // 1. Gọi API gỡ lưu bài viết
      await apiRequest(`api/posts/${id}/bookmark`, { method: 'DELETE' });
      // 2. Cập nhật trạng thái nút
      setUiState(false);

      // 3. Nếu đang ở trang /bookmarks thì loại bỏ card với animation mượt
      const isBookmarkPage = window.isBookmarkPage || document.getElementById('bookmark-feed-container');
      if (isBookmarkPage) {
        const card = btn ? (btn.closest('.post-card') || document.getElementById(`post-card-${id}`)) : document.getElementById(`post-card-${id}`);
        if (card) {
          card.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
          card.style.opacity = '0';
          card.style.transform = 'scale(0.95)';
          setTimeout(() => {
            card.remove();
            if (typeof checkEmptyBookmarks === 'function') {
              checkEmptyBookmarks();
            }
          }, 250);
        }
      }
    } else {
      // 1. Gọi API lưu bài viết
      await apiRequest(`api/posts/${id}/bookmark`, { method: 'POST' });
      // 2. Cập nhật trạng thái nút
      setUiState(true);
    }
  } catch (error) {
    console.error('Lỗi Bookmark:', error);
    alert(error.message || 'Không thể cập nhật trạng thái lưu bài viết.');
    // Khôi phục lại trạng thái ban đầu nếu API thất bại
    setUiState(isCurrentlyBookmarked);
  } finally {
    matchingButtons.forEach(b => delete b.dataset.processing);
  }
}
window.toggleBookmark = toggleBookmark;
window.handleBookmark = toggleBookmark;

function syncUserAvatarGlobally(newAvatar, userId, explicitUrl = null) {
  const versionedUrl = explicitUrl || (avatarUrl(newAvatar) + (avatarUrl(newAvatar).includes('?') ? '&' : '?') + 't=' + Date.now());

  // 1. Cập nhật trang cá nhân
  const profileAvatar = document.getElementById('profile-avatar-img');
  if (profileAvatar) {
    profileAvatar.style.opacity = '1';
    profileAvatar.src = versionedUrl;
  }

  // 2. Cập nhật Navbar & Dropdown Menu
  const navAvatar = document.getElementById('fb-nav-avatar');
  if (navAvatar) navAvatar.src = versionedUrl;

  document.querySelectorAll('.fb-avatar-52, .fb-avatar-img').forEach(img => {
    img.src = versionedUrl;
  });

  // 3. Cập nhật các bài viết và bình luận trong DOM của người dùng này
  document.querySelectorAll(`[data-user-id="${userId}"] .avatar, [data-user-id="${userId}"] .avatar-sm, [data-user-id="${userId}"] .post-avatar, [data-user-id="${userId}"] .comment-avatar`).forEach(img => {
    img.src = versionedUrl;
  });

  // 4. Cập nhật bộ nhớ tài khoản đã lưu (cả 2 keys để tương thích mọi view)
  try {
    ['goc-nho-remembered-accounts', 'goc_nho_remembered_accounts_v1'].forEach(storageKey => {
      const memory = JSON.parse(localStorage.getItem(storageKey) || '[]');
      let changed = false;
      memory.forEach(acc => {
        if (Number(acc.id) === Number(userId) || (typeof CURRENT_USER_NAME !== 'undefined' && acc.username === CURRENT_USER_NAME)) {
          acc.avatar = newAvatar;
          changed = true;
        }
      });
      if (changed) localStorage.setItem(storageKey, JSON.stringify(memory));
    });

    const authUser = JSON.parse(localStorage.getItem('auth_user') || 'null');
    if (authUser && (Number(authUser.id) === Number(userId) || (typeof CURRENT_USER_NAME !== 'undefined' && authUser.username === CURRENT_USER_NAME))) {
      authUser.avatar = newAvatar;
      localStorage.setItem('auth_user', JSON.stringify(authUser));
    }
  } catch (_) {}
}
window.syncUserAvatarGlobally = syncUserAvatarGlobally;





// ============================================================================
// HỆ THỐNG THÔNG BÁO: CHUÔNG TRÊN HEADER & DROPDOWN XEM NHANH
// ============================================================================

function initNotificationBell(user) {
  const wrapper = document.getElementById('nav-notification-wrapper');
  const bellBtn = document.getElementById('nav-bell-btn');
  const dropdown = document.getElementById('nav-notification-dropdown');
  const markAllBtn = document.getElementById('dropdown-mark-all-read');

  if (!wrapper || !bellBtn) return;

  if (!user) {
    wrapper.style.display = 'none';
    return;
  }

  // Hiển thị nút chuông
  wrapper.style.display = 'inline-flex';

  // Tải số lượng chưa đọc ban đầu
  refreshNotificationBadge();

  // Bật/tắt dropdown khi click chuông
  bellBtn.onclick = (e) => {
    e.stopPropagation();
    if (!dropdown) return;
    const isShowing = dropdown.style.display === 'block';
    if (isShowing) {
      dropdown.style.display = 'none';
      bellBtn.setAttribute('aria-expanded', 'false');
    } else {
      if (typeof closeFacebookDropdown === 'function') closeFacebookDropdown();
      dropdown.style.display = 'block';
      bellBtn.setAttribute('aria-expanded', 'true');
      loadDropdownNotifications();
    }
  };

  // Đóng dropdown khi click bên ngoài
  document.addEventListener('click', (e) => {
    if (dropdown && dropdown.style.display === 'block') {
      if (!dropdown.contains(e.target) && !bellBtn.contains(e.target)) {
        dropdown.style.display = 'none';
        bellBtn.setAttribute('aria-expanded', 'false');
      }
    }
  });

  // Đánh dấu tất cả đã đọc từ dropdown
  if (markAllBtn) {
    markAllBtn.onclick = async (e) => {
      e.stopPropagation();
      await markAllNotificationsAsRead();
      loadDropdownNotifications();
    };
  }
}
window.initNotificationBell = initNotificationBell;

async function refreshNotificationBadge() {
  const badge = document.getElementById('nav-bell-badge');
  const fbCount = document.getElementById('fb-menu-notif-count');
  if (!badge && !fbCount) return;

  try {
    const res = await apiRequest('api/notifications?per_page=1');
    const unread = Number(res.unread_count || 0);
    const text = unread > 99 ? '99+' : String(unread);

    if (badge) {
      if (unread > 0) {
        badge.textContent = text;
        badge.style.display = 'flex';
      } else {
        badge.style.display = 'none';
      }
    }

    if (fbCount) {
      if (unread > 0) {
        fbCount.textContent = text;
        fbCount.style.display = 'inline-flex';
      } else {
        fbCount.style.display = 'none';
      }
    }
  } catch (_) {}
}
window.refreshNotificationBadge = refreshNotificationBadge;

async function loadDropdownNotifications() {
  const listEl = document.getElementById('dropdown-notif-list');
  if (!listEl) return;

  listEl.innerHTML = '<div class="notif-dropdown-loading"><svg class="icon-spin-slow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:6px;" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10"></path></svg> Đang tải thông báo...</div>';

  try {
    const res = await apiRequest('api/notifications?per_page=6');
    const items = res.data || [];
    const unreadCount = Number(res.unread_count || 0);

    const badge = document.getElementById('nav-bell-badge');
    if (badge) {
      if (unreadCount > 0) {
        badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
        badge.style.display = 'flex';
      } else {
        badge.style.display = 'none';
      }
    }

    if (!items.length) {
      listEl.innerHTML = '<div class="notif-dropdown-empty"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#9ba69f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:8px;" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path><line x1="2" y1="2" x2="22" y2="22"></line></svg><p style="margin:0;">Chưa có thông báo nào</p></div>';
      return;
    }

    const notifTypeSvgs = {
      reaction: {
        svg: '<svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
        bg: '#fee2e2',
        color: '#ef4444'
      },
      post_reaction: {
        svg: '<svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
        bg: '#fee2e2',
        color: '#ef4444'
      },
      comment: {
        svg: '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        bg: '#e0e7ff',
        color: '#4f46e5'
      },
      repost: {
        svg: '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>',
        bg: '#ecfdf5',
        color: '#10b981'
      },
      follow: {
        svg: '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',
        bg: '#fef3c7',
        color: '#d97706'
      },
      ticket_approved: {
        svg: '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>',
        bg: '#dcfce7',
        color: '#16a34a'
      },
      ticket_rejected: {
        svg: '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
        bg: '#fee2e2',
        color: '#dc2626'
      },
      system: {
        svg: '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        bg: '#e0f2fe',
        color: '#0284c7'
      },
      system_moderation: {
        svg: '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        bg: '#e0f2fe',
        color: '#0284c7'
      }
    };

    const shieldSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>';
    const clockSvg = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1.5px;margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';

    listEl.innerHTML = items.map(item => {
      const isUnread = !item.is_read;
      const type = item.type || 'system';
      const typeMeta = notifTypeSvgs[type] || notifTypeSvgs.system;
      const targetUrl = item.target_url && item.target_url !== '#' ? item.target_url : '';
      const isSystem = !item.actor_username || type.startsWith('system') || type.startsWith('ticket');

      const avatarMarkup = (!isSystem && item.actor_avatar)
        ? `<img src="${avatarUrl(item.actor_avatar)}" class="notif-dropdown-avatar" alt="">`
        : `<div class="notif-dropdown-avatar" style="background:linear-gradient(135deg,#0284c7,#0369a1);color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(2,132,199,0.25);">${shieldSvg}</div>`;

      return `
        <div class="notif-dropdown-item ${isUnread ? 'unread' : 'read'}"
             onclick="handleDropdownItemClick(event, ${Number(item.id)}, '${escapeHtml(targetUrl)}', ${isUnread})">
          <div class="notif-dropdown-avatar-box">
            ${avatarMarkup}
            <span class="notif-dropdown-type-badge" style="background:${typeMeta.bg};color:${typeMeta.color};display:inline-flex;align-items:center;justify-content:center;">
              ${typeMeta.svg}
            </span>
          </div>
          <div class="notif-dropdown-body">
            <p class="notif-dropdown-text" style="${isUnread ? 'font-weight:600;' : ''}">${escapeHtml(item.content)}</p>
            <span class="notif-dropdown-time">${clockSvg}${escapeHtml(item.created_at)}</span>
          </div>
          ${isUnread ? '<span class="notif-dropdown-dot" title="Chưa đọc"></span>' : ''}
        </div>
      `;
    }).join('');
  } catch (err) {
    listEl.innerHTML = `<div class="notif-dropdown-empty text-danger">Không tải được thông báo: ${escapeHtml(err.message)}</div>`;
  }
}

async function handleDropdownItemClick(event, id, targetUrl, isUnread) {
  if (isUnread) {
    try {
      await apiRequest(`api/notifications/${id}/read`, { method: 'POST', body: {} });
      const badge = document.getElementById('nav-bell-badge');
      if (badge) {
        const cur = parseInt(badge.textContent, 10) || 0;
        const next = Math.max(0, cur - 1);
        badge.textContent = next > 99 ? '99+' : String(next);
        if (next === 0) badge.style.display = 'none';
      }
    } catch (_) {}
  }

  const dropdown = document.getElementById('nav-notification-dropdown');
  if (dropdown) dropdown.style.display = 'none';

  if (targetUrl && targetUrl !== '#' && targetUrl !== '') {
    if (/^https?:\/\//i.test(targetUrl)) {
      window.location.href = targetUrl;
    } else {
      const base = typeof APP_URL !== 'undefined' ? APP_URL : '';
      window.location.href = base + '/' + targetUrl.replace(/^\/+/, '');
    }
  }
}
window.handleDropdownItemClick = handleDropdownItemClick;

async function markAllNotificationsAsRead() {
  try {
    await apiRequest('api/notifications/read-all', { method: 'POST', body: {} });

    const badge = document.getElementById('nav-bell-badge');
    if (badge) {
      badge.textContent = '0';
      badge.style.display = 'none';
    }

    const fbCount = document.getElementById('fb-menu-notif-count');
    if (fbCount) fbCount.style.display = 'none';

    document.querySelectorAll('.notification-item.unread').forEach(item => {
      item.classList.remove('unread');
      item.classList.add('read');
      item.style.background = '#ffffff';
      item.style.borderColor = '#edf0eb';
      item.style.borderLeftColor = 'transparent';
      const dot = item.querySelector('.unread-dot');
      if (dot) dot.remove();
      const readBtn = item.querySelector('.btn-single-read');
      if (readBtn) readBtn.remove();
      const p = item.querySelector('p');
      if (p) p.style.fontWeight = '400';
    });

    const readAllBtn = document.getElementById('btn-read-all');
    if (readAllBtn) readAllBtn.remove();

    const subText = document.getElementById('notifications-subtitle');
    if (subText) subText.textContent = 'Tất cả thông báo đã được đọc';
  } catch (err) {
    alert(err.message || 'Không thể đánh dấu đã đọc.');
  }
}
window.markAllNotificationsAsRead = markAllNotificationsAsRead;

async function markSingleNotificationAsRead(event, id) {
  if (event) { event.stopPropagation(); event.preventDefault(); }
  try {
    await apiRequest(`api/notifications/${id}/read`, { method: 'POST', body: {} });

    const item = document.getElementById(`notif-item-${id}`);
    if (item) {
      item.classList.remove('unread');
      item.classList.add('read');
      item.style.background = '#ffffff';
      item.style.borderColor = '#edf0eb';
      item.style.borderLeftColor = 'transparent';
      const dot = item.querySelector('.unread-dot');
      if (dot) dot.remove();
      const btn = item.querySelector('.btn-single-read');
      if (btn) btn.remove();
      const p = item.querySelector('p');
      if (p) p.style.fontWeight = '400';
    }

    const badge = document.getElementById('nav-bell-badge');
    if (badge) {
      const cur = parseInt(badge.textContent, 10) || 0;
      const next = Math.max(0, cur - 1);
      badge.textContent = next > 99 ? '99+' : String(next);
      if (next === 0) badge.style.display = 'none';
    }
  } catch (_) {}
}
window.markSingleNotificationAsRead = markSingleNotificationAsRead;

/**
 * Cuộn mượt lên khung soạn bài viết & tạo hiệu ứng highlight
 * Kiểm tra xác thực: Nếu chưa đăng nhập thì mở modal/form đăng nhập
 */
async function scrollToPostComposer(event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }

  // 1. Kiểm tra trạng thái đăng nhập
  let isLogged = (CURRENT_USER_ID !== null) || (activeUser !== null && activeUser.id);

  if (!isLogged) {
    try {
      const session = await apiRequest('api/auth/me');
      if (session && session.user) {
        CURRENT_USER_ID = Number(session.user.id);
        activeUser = session.user;
        isLogged = true;
      }
    } catch (_) {
      if (window.location.protocol === 'file:' && typeof getRememberedAccounts === 'function') {
        const accs = getRememberedAccounts();
        if (accs.length > 0) isLogged = true;
      }
    }
  }

  // 2. Nếu người dùng chưa đăng nhập: Mở modal / form đăng nhập
  if (!isLogged) {
    const avatarBtn = document.getElementById('fb-avatar-trigger') || document.getElementById('user-menu-btn');
    const dropdownPanel = document.getElementById('fb-dropdown-panel');
    if (avatarBtn && dropdownPanel) {
      if (typeof slideFbMenu === 'function') slideFbMenu('main');
      dropdownPanel.style.display = 'block';
      avatarBtn.setAttribute('aria-expanded', 'true');
    }

    const wantLogin = confirm('Bạn cần đăng nhập tài khoản để viết bài mới trên Góc Nhỏ.\n\nBấm "OK" để chuyển đến trang Đăng nhập / Đăng ký ngay.');
    if (wantLogin) {
      window.location.href = 'account.html?mode=login';
    }
    return;
  }

  // 3. Nếu đã đăng nhập: Tìm khung soạn bài ở đầu bảng tin
  const titleInput = document.getElementById('post-title-input') || 
    document.querySelector('input[placeholder*="Tiêu đề bài viết"]');
  const editorInput = document.getElementById('post-editor-input') || 
    document.querySelector('textarea[placeholder*="Hôm nay bạn muốn kể câu chuyện gì"]');
  const composer = document.getElementById('create-post-card') || 
    (titleInput ? titleInput.closest('.composer-card, .post-card, section') : null) || 
    (editorInput ? editorInput.closest('.composer-card, .post-card, section') : null) || 
    document.querySelector('.composer-card');

  if (composer || titleInput || editorInput) {
    // Cuộn mượt màn hình lên đầu bảng tin: kết hợp scrollTo(top: 0) và scrollIntoView
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (composer) {
      composer.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Cuộn cột phải về đầu nếu đang ở đáy để người dùng thấy toàn cảnh
    const rightSidebar = document.querySelector('.right-sidebar, .sidebar-right, .editorial-aside');
    if (rightSidebar && rightSidebar.scrollTop > 0) {
      rightSidebar.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Nháy nhẹ viền xanh cho card soạn bài
    if (composer) {
      composer.classList.remove('composer-highlight-pulse');
      void composer.offsetWidth; // Reflow
      composer.classList.add('composer-highlight-pulse');
      setTimeout(() => {
        composer.classList.remove('composer-highlight-pulse');
      }, 1800);
    }

    // Tự động focus con trỏ chuột vào ô tiêu đề (hoặc nội dung nếu tiêu đề đã có sẵn)
    setTimeout(() => {
      let targetInput = titleInput;
      if (titleInput && titleInput.value && titleInput.value.trim() !== '' && editorInput) {
        targetInput = editorInput;
      }
      if (targetInput) {
        targetInput.focus();
        targetInput.classList.remove('input-highlight-pulse');
        void targetInput.offsetWidth;
        targetInput.classList.add('input-highlight-pulse');
        setTimeout(() => targetInput.classList.remove('input-highlight-pulse'), 1800);
      }
    }, 450);
  } else {
    // Nếu đang ở trang khác ngoài trang chủ, điều hướng về index.html#create-post-card
    const targetUrl = (window.APP_URL ? window.APP_URL + '/' : 'index.html') + '#create-post-card';
    window.location.href = targetUrl;
  }
}
window.scrollToPostComposer = scrollToPostComposer;

// Gắn sự kiện click bằng Event Delegation trên document khớp với #btn-sidebar-new-post
document.addEventListener('click', (e) => {
  const btn = e.target.closest('#btn-sidebar-new-post, #btn-write-new-post, .newsletter-cta-btn, [data-action="write-post"]');
  if (btn) {
    e.preventDefault();
    e.stopPropagation();
    scrollToPostComposer(e);
  }
});

// Tự động kích hoạt khi có anchor #create-post-card trên URL
if (window.location.hash === '#create-post-card') {
  setTimeout(() => {
    scrollToPostComposer();
  }, 500);
}
