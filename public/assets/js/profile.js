const SVG_ICONS = {
  comment: '<svg class="btn-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
  share: '<svg class="btn-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>',
  bookmark: (active = false) => `<svg class="btn-icon-svg icon-bookmark-svg" width="14" height="14" viewBox="0 0 24 24" fill="${active ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>`,
  ticket: '<svg class="btn-icon-svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 11v2"/><path d="M13 17v2"/></svg>',
  link: '<svg class="btn-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
  repost: '<svg class="btn-icon-svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>'
};
﻿let CURRENT_USER_ID = null;
const APP_URL = (function () {
  if (typeof window !== 'undefined' && window.APP_URL) {
    return String(window.APP_URL).replace(/\/$/, '');
  }
  let base = '';
  try {
    const scriptEl = document.currentScript || document.querySelector('script[src*="assets/js/"]');
    if (scriptEl && scriptEl.src) {
      base = new URL('../..', scriptEl.src).pathname.replace(/\/$/, '');
    }
  } catch (_) {}
  if (!base && typeof location !== 'undefined') {
    const match = location.pathname.match(/^(.*?\/public)(?:\/|$)/);
    base = match ? match[1].replace(/\/$/, '') : new URL('.', location.href).pathname.replace(/\/$/, '');
  }
  if (!base) base = '';
  if (typeof window !== 'undefined') window.APP_URL = base;
  return base;
})();
const PROFILE_API_BASE = APP_URL;
const AVATAR_CACHE_VERSION = Date.now();
function avatarUrl(value) {
  const raw = String(value || '').trim();
  const fallback = `${PROFILE_API_BASE}/assets/images/avatars/default.svg`;
  if (!raw) return `${fallback}?v=${AVATAR_CACHE_VERSION}`;
  if (/^(data:|blob:|https?:|\/\/)/i.test(raw)) return raw;
  const path = raw.startsWith('/') && /^\/(assets|uploads)\//i.test(raw) ? raw.slice(1) : raw;
  try {
    const url = new URL(path, `${window.location.origin}${PROFILE_API_BASE}/`);
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
const profileEscape = value => {
  const str = String(value ?? '');
  const normalized = typeof str.normalize === 'function' ? str.normalize('NFC') : str;
  return normalized.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
};
const PROFILE_REACTIONS = {like:'👍', love:'❤️', haha:'😄', sad:'😢', angry:'😠'};
const PROFILE_REACTION_LABELS = {like:'Đã thích', love:'Yêu thích', haha:'Haha', sad:'Buồn', angry:'Phẫn nộ'};
function getProfileCsrfCookie() {
  return window.Csrf ? window.Csrf.getCookie() : (document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)?.[1] || null);
}

async function getProfileCsrfToken() {
  if (window.Csrf) {
    return window.Csrf.fetchToken(PROFILE_API_BASE);
  }
  let token = getProfileCsrfCookie();
  if (!token) {
    try {
      const res = await fetch(`${PROFILE_API_BASE}/index.php?url=api/csrf-token`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });
      if (res.ok) {
        const data = await res.json();
        token = data.csrf_token || getProfileCsrfCookie();
      }
    } catch (_) {}
  }
  return token || '';
}

async function profileApi(route, body = null, isRetry = false) {
  const url = new URL(`${PROFILE_API_BASE}/index.php`, window.location.origin);
  url.searchParams.set('url', route);
  const method = body === null ? 'GET' : 'POST';
  const headers = {'Accept':'application/json'};
  if (body !== null) headers['Content-Type'] = 'application/json';
  if (method === 'POST') {
    if (window.Csrf) {
      await window.Csrf.attach(headers, PROFILE_API_BASE);
    } else {
      const token = await getProfileCsrfToken();
      if (token) headers['X-CSRF-TOKEN'] = token;
    }
  }
  const response = await fetch(url, {method,credentials:'same-origin',headers,body:body === null ? undefined:JSON.stringify(body)});
  const text = await response.text();
  let result;
  try { result = JSON.parse(text); }
  catch (_) { throw new Error(`API returned non-JSON (HTTP ${response.status}): ${text.slice(0, 120)}`); }
  if (!response.ok) {
    if (response.status === 403 && result && result.code === 'CSRF_TOKEN_INVALID' && !isRetry) {
      if (window.Csrf) {
        window.Csrf.clear();
        await window.Csrf.fetchToken(PROFILE_API_BASE);
      } else {
        document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/';
        await getProfileCsrfToken();
      }
      return profileApi(route, body, true);
    }
    throw new Error(result.error || `API request failed (HTTP ${response.status}).`);
  }
  if (result && result.csrf_token && window.Csrf) {
    window.Csrf.setToken(result.csrf_token);
  }
  return result;
}

function handleProfileReactionEnter(event) {
  const ctrl = event.currentTarget;
  if (!ctrl) return;
  ctrl.classList.remove('reaction-closing');
  ctrl.classList.add('show-reactions');
}

function handleProfileReactionLeave(event) {
  const ctrl = event.currentTarget;
  if (!ctrl) return;
  ctrl.classList.remove('show-reactions');
}

function profileReactionMarkup(post) {
  const current = post.my_reaction || (post.is_liked ? 'like' : null);
  const isReacted = Boolean(current && PROFILE_REACTIONS[current]);
  const reactionAttr = isReacted ? `data-reaction="${current}"` : '';
  const reactedClass = isReacted ? 'active is-reacted' : '';
  const emoji = isReacted ? (PROFILE_REACTIONS[current] || '👍') : '👍';
  const label = isReacted ? (PROFILE_REACTION_LABELS[current] || 'Đã thích') : 'Thích';
  const likesCount = Number(post.likes_count ?? post.like_count ?? post.total_reactions ?? post.reaction_count ?? 0);

  const counts = post.reaction_counts || {};
  const breakdownParts = [];
  Object.entries(PROFILE_REACTIONS).forEach(([k, em]) => {
    const c = Number(counts[k] || 0);
    if (c > 0) breakdownParts.push(`${em} ${c}`);
  });
  const breakdownText = breakdownParts.length > 0 ? breakdownParts.join(' · ') : (likesCount > 0 ? `👍 ${likesCount}` : 'Chưa có tương tác');

  const choices = Object.entries(PROFILE_REACTIONS).map(([key, em]) => {
    const rLabel = PROFILE_REACTION_LABELS[key] || key;
    return `<button type="button" class="reaction-choice" title="${profileEscape(rLabel)}" aria-label="${profileEscape(rLabel)}" onclick="event.preventDefault(); event.stopPropagation(); setProfileReaction(${Number(post.id)}, '${key}', event)">${em}</button>`;
  }).join('');

  return `<div class="reaction-control" data-post-id="${Number(post.id)}"
               onmouseenter="handleProfileReactionEnter(event)"
               onmouseleave="handleProfileReactionLeave(event)"
               onpointerdown="startProfileReactionPress(event)"
               onpointerup="endProfileReactionPress(event)"
               onpointercancel="endProfileReactionPress(event)">
            <button type="button" class="stat-btn reaction-trigger ${reactedClass}" ${reactionAttr}
                    onclick="event.preventDefault(); event.stopPropagation(); toggleProfileLike(${Number(post.id)}, this, event)"
                    title="${profileEscape(breakdownText)}" aria-label="Cảm xúc bài viết">
              <span class="reaction-icon">${emoji}</span>
              <span class="likes-count">${likesCount}</span>
              <span class="reaction-label">${label}</span>
            </button>
            <div class="reaction-picker" role="group" aria-label="Chọn cảm xúc">
              ${choices}
            </div>
            <div class="reaction-tooltip">${profileEscape(breakdownText)}</div>
          </div>`;
}
let profileReactionTimer = null;
function startProfileReactionPress(event) {
  const wrapper = event.currentTarget;
  if (!wrapper) return;
  wrapper.dataset.longPressTriggered = '0';
  clearTimeout(profileReactionTimer);
  profileReactionTimer = setTimeout(() => {
    wrapper.classList.remove('reaction-closing');
    wrapper.classList.add('show-reactions');
    wrapper.dataset.longPressTriggered = '1';
  }, 400);
}
function endProfileReactionPress(event) {
  clearTimeout(profileReactionTimer);
  const wrapper = event?.currentTarget;
  if (!wrapper || event.target.closest('.reaction-choice')) return;
  if (wrapper.dataset.longPressTriggered === '1') {
    wrapper.dataset.suppressLikeClick = '1';
    setTimeout(() => delete wrapper.dataset.suppressLikeClick, 800);
  }
}
let CURRENT_PROFILE_ID = null;

function profileGetBranchDescendants(parentId, allComments) {
  const pId = Number(parentId);
  const directChildren = allComments.filter(c => Number(c.parent_id || 0) === pId);
  let descendants = [];
  for (const child of directChildren) {
    descendants.push(child);
    const sub = profileGetBranchDescendants(child.id, allComments);
    if (sub.length > 0) descendants = descendants.concat(sub);
  }
  return descendants;
}

function profileCommentCard(comment, post, comments, depth) {
  const id = Number(comment.id);
  const visualDepth = Math.min(Number(depth || 0), 4);
  if (comment.is_deleted) {
    return `<article id="comment-${id}" class="comment-item comment-item-deleted comment-depth-${visualDepth}" data-comment-id="${id}" data-depth="${depth}" style="--comment-depth:${visualDepth}">
      <div class="comment-deleted-avatar" aria-hidden="true">×</div>
      <div class="comment-content-box">
        <div class="comment-bubble comment-bubble-deleted">
          <p class="comment-text comment-deleted-text">[Bình luận này đã bị xóa]</p>
        </div>
      </div>
    </article>`;
  }

  const authorId = Number(comment.user_id ?? comment.user?.id), user = comment.user || comment;
  const canEdit = CURRENT_USER_ID !== null && authorId === Number(CURRENT_USER_ID);
  const postUserId = Number(post?.user?.id ?? post?.user_id ?? 0);
  const canDelete = canEdit || (CURRENT_USER_ID !== null && postUserId === Number(CURRENT_USER_ID));
  const targetPostId = Number(post?.id ?? comment.post_id ?? 0);

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
        replyToBadge = `<div class="comment-reply-to-badge"><span class="reply-arrow">↳ Trả lời</span> @${profileEscape(pUsername)}</div>`;
      }
    }
  }

  const authorUsername = String(user.username || comment.username || '');
  const formattedContent = typeof window.formatCommentContentWithMentions === 'function'
    ? window.formatCommentContentWithMentions(comment.content, comment.mentions_map)
    : profileEscape(comment.content);
  const authorHref = `profile.html?id=${authorId}`;

  return `<article id="comment-${id}" class="comment-item comment-depth-${visualDepth}" data-comment-id="${id}" data-depth="${depth}" data-author-id="${authorId}" data-author-username="${profileEscape(authorUsername)}" style="--comment-depth:${visualDepth}">
    <a href="${authorHref}"><img src="${avatarUrl(user.avatar)}" class="avatar avatar-sm comment-avatar" alt=""></a>
    <div class="comment-content-box">
      <div class="comment-bubble">
        <a href="${authorHref}" class="comment-author-link"><strong>${profileEscape(user.full_name || user.username || 'Người dùng')}</strong></a>
        ${replyToBadge}
        <p class="comment-text">${formattedContent}</p>
        ${comment.edited_at ? '<small class="comment-edited">đã chỉnh sửa</small>' : ''}
      </div>
      <div class="comment-actions">
        ${profileCommentReactionMarkup(comment, targetPostId)}
        <button type="button" class="comment-action" onclick="event.preventDefault(); event.stopPropagation(); profileReplyToComment(${id}, ${authorId}, '${profileEscape(authorUsername).replace(/'/g, "\\'")}')">Trả lời</button>
        ${canDelete ? `
          <details class="comment-menu" onclick="event.stopPropagation()">
            <summary onclick="event.stopPropagation()">•••</summary>
            <div class="comment-menu-items">
              ${canEdit ? `<button type="button" onclick="event.stopPropagation(); this.closest('details').open=false; profileEditComment(${id}, ${targetPostId})">Sửa</button>` : ''}
              <button type="button" class="danger" onclick="event.stopPropagation(); this.closest('details').open=false; profileDeleteComment(${id}, ${targetPostId})">Xóa</button>
            </div>
          </details>` : ''}
      </div>
    </div>
  </article>`;
}

function profileCommentNode(comment, post, comments, depth = 0) {
  return profileCommentCard(comment, post, comments, depth);
}

function profileCommentReactionMarkup(comment, postId = null) {
  if (!comment) return '';
  const current = comment.my_reaction || comment.reaction || null;
  const isReacted = Boolean(current && PROFILE_REACTIONS[current]);
  const reactionAttr = isReacted ? `data-reaction="${current}"` : '';
  const reactedClass = isReacted ? 'active is-reacted' : '';
  const emoji = isReacted ? (PROFILE_REACTIONS[current] || '👍') : '👍';
  const label = isReacted ? (PROFILE_REACTION_LABELS[current] || 'Đã thích') : 'Thích';
  const likesCount = Number(comment.likes_count ?? comment.likes ?? comment.total_reactions ?? comment.like_count ?? 0);
  const targetPostId = Number(postId ?? comment.post_id ?? 0);
  const commentId = Number(comment.id || 0);

  const choices = Object.entries(PROFILE_REACTIONS).map(([key, em]) => {
    const rLabel = PROFILE_REACTION_LABELS[key] || key;
    return `<button type="button" class="reaction-choice" title="${profileEscape(rLabel)}" aria-label="${profileEscape(rLabel)}" onclick="event.preventDefault(); event.stopPropagation(); setProfileCommentReaction(${commentId}, ${targetPostId}, '${key}', event)">${em}</button>`;
  }).join('');

  return `<div class="reaction-control comment-reaction-control" data-comment-id="${commentId}"
               onmouseenter="handleProfileReactionEnter(event)"
               onmouseleave="handleProfileReactionLeave(event)"
               onpointerdown="startProfileReactionPress(event)"
               onpointerup="endProfileReactionPress(event)"
               onpointercancel="endProfileReactionPress(event)">
            <button type="button" class="comment-action reaction-trigger ${reactedClass}" ${reactionAttr}
                    onclick="event.preventDefault(); event.stopPropagation(); profileLikeComment(${commentId}, ${targetPostId}, '${current || ''}', this, event)">
              <span class="reaction-icon">${emoji}</span>
              <span class="reaction-label">${label}</span>
              <span class="likes-count">· ${likesCount}</span>
            </button>
            <div class="reaction-picker">${choices}</div>
          </div>`;
}

function commentReactionMarkup(comment, postId = null) {
  return profileCommentReactionMarkup(comment, postId);
}
window.profileCommentReactionMarkup = profileCommentReactionMarkup;
window.commentReactionMarkup = profileCommentReactionMarkup;

function profileCommentThreads(comments, post) {
  if (!comments || comments.length === 0) {
    return '<p class="text-muted text-center py-2">Chưa có bình luận.</p>';
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
    const rootHtml = profileCommentCard(root, post, validComments, 0);

    // Cấp 1: Bình luận con trực tiếp
    const tier1Children = childrenByParent.get(rootId) || [];
    let tier1Html = '';

    if (tier1Children.length > 0) {
      const tier1CardsHtml = tier1Children.map(c1 => {
        const c1Id = Number(c1.id);
        const c1Html = profileCommentCard(c1, post, validComments, 1);

        // Cấp 2 và tất cả các cấp sâu hơn: gom phẳng thành một danh sách duy nhất, không lồng thêm DOM
        const tier2Descendants = collectDescendants(c1Id);
        tier2Descendants.sort(sortFn);

        let tier2Html = '';
        if (tier2Descendants.length > 0) {
          const tier2CardsHtml = tier2Descendants.map(desc => profileCommentCard(desc, post, validComments, 2)).join('');
          tier2Html = `<div class="comment-replies comment-replies-tier2">${tier2CardsHtml}</div>`;
        }

        return `<div class="comment-branch-tier1" data-comment-id="${c1Id}">${c1Html}${tier2Html}</div>`;
      }).join('');

      tier1Html = `<div class="comment-replies comment-replies-tier1">${tier1CardsHtml}</div>`;
    }

    return `<div class="comment-branch-root" data-root-id="${rootId}" data-comment-id="${rootId}">${rootHtml}${tier1Html}</div>`;
  }).join('');
}

document.addEventListener("DOMContentLoaded", async () => {
  const urlParams = new URLSearchParams(window.location.search);
  try {
    const session = await profileApi('api/auth/me');
    const currentUser = session.user || null;
    if (currentUser) rememberAccount(currentUser);
    CURRENT_USER_ID = currentUser ? Number(currentUser.id) : null;
    setupProfileNav(currentUser);
    const userId = parseInt(urlParams.get('id')) || CURRENT_USER_ID || 2;
    CURRENT_PROFILE_ID = userId;
    const profile = await profileApi(`api/users/${userId}`);
    const user = profile.user;
    mockUsers.splice(0, mockUsers.length, user);
    mockPosts.splice(0, mockPosts.length, ...profile.posts);
    renderProfileHeader(user, profile);
    renderProfileFeed(userId);
    if (CURRENT_USER_ID && CURRENT_USER_ID === Number(userId)) {
      loadPersonalProfileInsights();
    }
  } catch (error) {
    document.getElementById('profile-header').textContent = error.message;
  }

  window.addEventListener('click', (e) => {
    const modal = document.getElementById('post-modal');
    if (e.target === modal) closeModal('post-modal');
  });
});

let allPersonalActivities = [];
let isActivityExpanded = false;

function renderPersonalActivities() {
  const activityList = document.getElementById('profile-insight-activity-list');
  const toggleWrapper = document.getElementById('profile-insight-toggle-wrapper');
  const toggleBtn = document.getElementById('btn-toggle-activity');
  const countLabel = document.getElementById('profile-insight-activity-count');
  if (!activityList) return;

  if (!allPersonalActivities.length) {
    activityList.innerHTML = '<p class="profile-insight-empty">Chưa có tương tác từ người khác.</p>';
    activityList.style.maxHeight = 'none';
    if (toggleWrapper) toggleWrapper.style.display = 'none';
    if (countLabel) countLabel.textContent = '';
    return;
  }

  if (countLabel) {
    countLabel.textContent = `(${allPersonalActivities.length} hoạt động)`;
  }

  const actions = {
    like: 'đã thích',
    share: 'đã chia sẻ',
    comment: 'đã bình luận về'
  };

  const displayed = isActivityExpanded ? allPersonalActivities : allPersonalActivities.slice(0, 3);

  activityList.innerHTML = displayed.map(item => {
    const actor = item.actor_name || item.actor_username || 'Người dùng';
    return `<article class="profile-insight-activity-item"><strong>${profileEscape(actor)}</strong> ${actions[item.type] || ''} bài <b>${profileEscape(item.post_title)}</b>${item.type === 'comment' && item.content ? `<p>${profileEscape(item.content)}</p>` : ''}<time>${profileEscape(item.created_at)}</time></article>`;
  }).join('');

  if (allPersonalActivities.length > 3) {
    if (toggleWrapper) toggleWrapper.style.display = 'block';
    if (toggleBtn) {
      if (isActivityExpanded) {
        // ĐANG MỞ RỘNG (Kích thước to hơn rõ rệt, hiển thị tất cả các mục) -> Nút bấm mời gọi "Thu gọn"
        toggleBtn.innerHTML = `<span>Thu gọn</span> <i class="fa-solid fa-chevron-up"></i>`;
        activityList.style.maxHeight = '560px';
      } else {
        // ĐANG THU GỌN (Kích thước nhỏ hơn gọn gàng, chỉ hiển thị 3 mục) -> Nút bấm mời gọi "Xem thêm"
        toggleBtn.innerHTML = `<span>Xem thêm (${allPersonalActivities.length - 3} tương tác)</span> <i class="fa-solid fa-chevron-down"></i>`;
        activityList.style.maxHeight = '210px';
      }
      if (!toggleBtn.dataset.bound) {
        toggleBtn.dataset.bound = 'true';
        toggleBtn.addEventListener('click', () => {
          isActivityExpanded = !isActivityExpanded;
          renderPersonalActivities();
        });
      }
    }
  } else {
    if (toggleWrapper) toggleWrapper.style.display = 'none';
    activityList.style.maxHeight = 'none';
  }
}

async function loadPersonalProfileInsights() {
  const section = document.getElementById('personal-insights');
  try {
    const data = await profileApi('api/dashboard/me');
    const summary = data.summary || {};
    const labels = [
      ['Bài viết', summary.posts], ['Lượt xem', summary.views],
      ['Lượt thích', summary.likes], ['Bình luận', summary.comments],
      ['Chia sẻ', summary.shares]
    ];
    document.getElementById('profile-insight-stats').innerHTML = labels.map(([label, value]) =>
      `<div class="profile-insight-stat"><span>${label}</span><strong>${Number(value || 0).toLocaleString('vi-VN')}</strong></div>`
    ).join('');
    allPersonalActivities = data.activity || [];
    renderPersonalActivities();
    section.hidden = false;
  } catch (error) {
    section.innerHTML = `<p class="profile-insight-empty">${profileEscape(error.message)}</p>`;
    section.hidden = false;
  }
}

let activeProfileUser = null;

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
    const res = await profileApi('api/auth/profile', payload);
    feedback.className = 'fb-feedback-msg success';
    feedback.textContent = 'Đã cập nhật thông tin thành công!';
    if (res.user) {
      activeProfileUser = res.user;
      rememberAccount(res.user);
      const bioEl = document.getElementById('profile-bio-text');
      if (bioEl && CURRENT_USER_ID && Number(CURRENT_USER_ID) === Number(res.user.id)) {
        bioEl.textContent = res.user.bio || 'Chưa có tiểu sử.';
      }
      const avatarSrc = avatarUrl(res.user.avatar);
      document.querySelectorAll('#fb-nav-avatar, .fb-avatar-52').forEach(image => { image.src = avatarSrc; });
      const nameDisplays = document.querySelectorAll('.fb-full-name');
      nameDisplays.forEach(el => { el.textContent = res.user.full_name; });
      const handleDisplays = document.querySelectorAll('.fb-user-handle');
      handleDisplays.forEach(el => { el.textContent = '@' + res.user.username; });
      const navName = document.getElementById('fb-nav-username');
      if (navName) navName.textContent = res.user.full_name ? res.user.full_name.split(' ').slice(-1)[0] : res.user.username;
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
    await profileApi('api/auth/profile', { current_password, new_password });
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
    await profileApi('api/auth/logout', {});
  } catch (_) {}
  window.location.href = 'index.html';
}
window.fbLogout = fbLogout;

function setupProfileNav(user) {
  activeProfileUser = user;
  const adminLink = document.getElementById('nav-admin-link');
  if (adminLink) {
    adminLink.style.display = 'inline-flex';
    if (user && user.role === 'admin') {
      adminLink.innerHTML = '<i class="fa-solid fa-sliders"></i> Admin';
      adminLink.title = 'Trang quản trị (Admin Dashboard)';
    } else {
      adminLink.innerHTML = '<i class="fa-solid fa-sliders"></i> Quản trị';
      adminLink.title = 'Trang quản trị';
    }
  }

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
            <img src="${avatarUrl(acc.avatar)}" class="fb-switch-user-avatar" alt="${profileEscape(acc.fullName)}">
            <div class="fb-switch-user-info">
              <span class="fb-switch-user-name">${profileEscape(acc.fullName)}</span>
              <span class="fb-switch-user-role">@${profileEscape(acc.username)} · ${profileEscape(acc.roleLabel)}</span>
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
                  <img src="${avatarUrl(user.avatar)}" alt="${profileEscape(user.full_name)}" class="fb-avatar-52">
                  <span class="fb-active-dot" title="Đang trực tuyến"></span>
                </div>
                <div class="fb-profile-titles">
                  <div class="fb-name-row">
                    <span class="fb-full-name">${profileEscape(user.full_name)}</span>
                    ${user.is_verified ? '<i class="fa-solid fa-circle-check fb-verified-badge" title="Tài khoản đã xác minh"></i>' : ''}
                  </div>
                  <div class="fb-handle-row">
                    <span class="fb-user-handle">@${profileEscape(user.username)}</span>
                    ${roleBadge}
                  </div>
                </div>
              </a>
              <div class="fb-profile-view-link-wrap">
                <a href="profile.html?id=${user.id}" class="fb-profile-view-link">
                  <svg class="fb-inline-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg>
                  <span>Xem trang cá nhân của bạn</span>
                </a>
              </div>
            </div>

            <div class="fb-sep"></div>

            <!-- Các mục menu chính -->
            <div class="fb-menu-group">
              <!-- Trang chủ: Icon ngôi nhà -->
              <a href="index.html" class="fb-nav-item" title="Về trang chủ / bảng tin">
                <div class="fb-circle-icon home">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
                </div>
                <div class="fb-nav-text">
                  <div class="fb-nav-title">Trang chủ</div>
                  <div class="fb-nav-desc">Bảng tin bài viết & tin tức mới</div>
                </div>
              </a>

              <!-- Cài đặt & Tài khoản: Icon bánh răng, mÅ©i tên > sang sub-menu -->
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
                <input type="text" id="fb-set-fullname" class="fb-field-input" value="${profileEscape(user.full_name)}" placeholder="Nhập họ và tên mới" required maxlength="120">
              </div>
              <div class="fb-field-group">
                <label class="fb-field-label" for="fb-set-username">Tên đăng nhập (@username)</label>
                <input type="text" id="fb-set-username" class="fb-field-input" value="${profileEscape(user.username)}" placeholder="3-40 ký tự chữ, số, _" required pattern="[A-Za-z0-9_]{3,40}">
              </div>
              <div class="fb-field-group">
                <label class="fb-field-label" for="fb-set-bio">Tiểu sử</label>
                <textarea id="fb-set-bio" class="fb-field-input" rows="3" maxlength="300" style="resize:vertical;font-family:inherit;font-size:13px;line-height:1.45;" placeholder="Viết vài dòng giới thiệu bản thân...">${profileEscape(user.bio || '')}</textarea>
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
    // Khách chưa đăng nhập
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
              Đăng nhập để viết bài, bình luận, tương tác và quản lý hồ sơ.
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

  setupSettingsModal(user);
}

function openBioSettingsDirect(event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }
  const targetUser = (typeof activeProfileUser !== 'undefined' && activeProfileUser)
                  || (typeof currentProfileData !== 'undefined' && currentProfileData?.user)
                  || (typeof currentUser !== 'undefined' && currentUser)
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
    user = (typeof activeProfileUser !== 'undefined' && activeProfileUser)
        || (typeof currentProfileData !== 'undefined' && currentProfileData?.user)
        || (typeof currentUser !== 'undefined' && currentUser)
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
    modal.style.display = 'flex';
    return;
  }
  listEl.innerHTML = accounts.map(acc => {
    const isCurrent = currentUser && currentUser.username === acc.username;
    return `
      <div class="fb-switch-user-card ${isCurrent ? 'active' : ''}" style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:10px;border:1px solid ${isCurrent ? '#1877f2' : '#e4e6eb'};margin-bottom:10px;background:${isCurrent ? '#e7f3ff' : '#fff'};">
        <div style="display:flex;align-items:center;gap:12px;">
          <img src="${avatarUrl(acc.avatar)}" class="fb-switch-user-avatar" alt="${profileEscape(acc.fullName)}" style="width:46px;height:46px;border-radius:50%;object-fit:cover;border:1.5px solid #ced0d4;">
          <div>
            <div style="font-weight:700;font-size:14px;color:#050505;">${profileEscape(acc.fullName)}</div>
            <div style="font-size:12px;color:#65676b;">@${profileEscape(acc.username)} · ${profileEscape(acc.role === 'admin' ? 'Quản trị viên' : 'Người dùng')}</div>
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
}
window.openSwitchModal = openSwitchModal;

function closeSwitchModal() {
  const modal = document.getElementById('switch-account-modal');
  if (modal) {
    modal.classList.remove('active');
    modal.style.display = 'none';
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
        await profileApi('api/auth/logout', {});
      } catch (_) {}
      window.location.href = 'index.html';
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
      const res = await profileApi('api/auth/profile', payload);
      msgEl.textContent = (res.message || 'Cập nhật thành công!');
      msgEl.style.color = '#15803d';
      if (res.user) {
        rememberAccount(res.user);
        const bioEl = document.getElementById('profile-bio-text');
        if (bioEl && CURRENT_USER_ID && Number(CURRENT_USER_ID) === Number(res.user.id)) {
          bioEl.textContent = res.user.bio || 'Chưa có tiểu sử.';
        }
      }
      setTimeout(() => {
        closeSettingsModal();
        window.location.reload();
      }, 1000);
    } catch (err) {
      msgEl.textContent = err.message;
      msgEl.style.color = '#dc2626';
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Lưu thay đổi'; }
    }
  });
}

let currentProfileData = null;
let selectedAvatarFile = null;

function renderProfileHeader(user, profile) {
  if (profile) currentProfileData = profile;
  const isOwner = CURRENT_USER_ID && Number(CURRENT_USER_ID) === Number(user.id);
  const isCustomAvatar = user.avatar && (user.avatar.includes('/uploads/avatars/') || user.avatar.includes('uploads/avatars/'));

  // 1. Render Header Trang Cá Nhân (Phong cách Hoạt hình / Doodle Tươi mới)
  document.getElementById("profile-header").innerHTML = `
    <!-- Cover Banner Hoạt Hình Doodle Tươi Sáng -->
    <div class="profile-cover-banner">
      <div class="profile-cover-doodles">
        <svg class="cover-doodle-cloud cover-cloud-1" width="68" height="40" viewBox="0 0 24 24" fill="rgba(255,255,255,0.85)" stroke="#38bdf8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>
        </svg>
        <svg class="cover-doodle-cloud cover-cloud-2" width="52" height="32" viewBox="0 0 24 24" fill="rgba(255,255,255,0.75)" stroke="#38bdf8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>
        </svg>
        <svg class="cover-doodle-star cover-star-1" width="26" height="26" viewBox="0 0 24 24" fill="#fde047" stroke="#eab308" stroke-width="1.5">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
        <svg class="cover-doodle-star cover-star-2" width="20" height="20" viewBox="0 0 24 24" fill="#fde047" stroke="#eab308" stroke-width="1.5">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
        </svg>
        <svg class="cover-doodle-sparkle" width="24" height="24" viewBox="0 0 24 24" fill="#34d399">
          <path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3z"/>
        </svg>
        <div class="cover-badge-pill">
          <span>✨ Góc Nhỏ · Không gian sáng tạo</span>
        </div>
      </div>
    </div>

    <div class="profile-card-body">
      <div class="profile-avatar-container">
        <div class="profile-avatar-ring">
          <img src="${avatarUrl(user.avatar)}" class="profile-avatar-large" id="profile-avatar-img" alt="Avatar">
          ${isOwner ? `
            <button type="button" class="btn-change-avatar" id="btn-trigger-avatar-upload" title="Đổi ảnh đại diện">
              <i class="fa-solid fa-camera"></i>
            </button>
          ` : ''}
        </div>
      </div>
      <h2 class="profile-name-heading">${profileEscape(user.full_name)} ${user.is_verified ? '<span class="verified-badge" title="Tài khoản đã xác thực">✓</span>' : ''}</h2>
      <div class="profile-username-pill">@${profileEscape(user.username)}</div>

      <!-- KHỐI TIỂU SỬ VÀ NÚT BÊN DƯỚI (Speech Bubble / Cozy Note) -->
      <div class="profile-bio-container">
        <div class="profile-bio-bubble">
          <span class="bio-doodle-quote bio-quote-left">“</span>
          <p class="font-size-base" id="profile-bio-text">${profileEscape(user.bio || 'Chưa có tiểu sử.')}</p>
          <span class="bio-doodle-quote bio-quote-right">”</span>
        </div>
        ${isOwner ? `
          <div class="profile-bio-btn-wrap">
            <button type="button" class="btn-edit-bio-below" id="btn-edit-bio" onclick="openBioSettingsDirect(event)" title="Thay đổi cài đặt tài khoản">
              <i class="fa-solid fa-pencil"></i> Chỉnh sửa tiểu sử & Cài đặt
            </button>
          </div>
        ` : ''}
      </div>

      <!-- HUY HIỆU THỐNG KÊ (Sticker Badges) -->
      <div class="profile-badges-row">
        <span class="badge badge-news profile-sticker-badge badge-reputation">
          <i class="fa-solid fa-award"></i> Điểm uy tín: <strong>${user.reputation_score} pts</strong>
        </span>
        <span class="badge badge-blog profile-sticker-badge badge-followers">
          <i class="fa-solid fa-heart"></i> <strong>${(profile && profile.followers_count) || 0}</strong> người theo dõi
        </span>
        <span class="badge profile-sticker-badge badge-posts">
          <i class="fa-solid fa-book-open"></i> <strong>${(profile && profile.posts ? profile.posts.length : mockPosts.length)}</strong> bài viết
        </span>
      </div>

      ${isOwner ? `
        <div class="avatar-actions-bar">
          <button type="button" class="action-btn action-btn-sm btn-cartoon-action btn-action-settings" id="btn-open-settings-profile" onclick="openBioSettingsDirect(event)">
            <i class="fa-solid fa-gear"></i> Cài đặt tài khoản
          </button>
          <button type="button" class="action-btn action-btn-sm btn-cartoon-action btn-action-camera" id="btn-open-avatar-modal">
            <i class="fa-solid fa-camera"></i> Đổi avatar
          </button>
          ${isCustomAvatar ? `
            <button type="button" class="action-btn action-btn-sm action-btn-outline btn-cartoon-action btn-action-reset" id="btn-reset-avatar-action">
              <i class="fa-solid fa-rotate-left"></i> Mặc định
            </button>
          ` : ''}
        </div>
      ` : (CURRENT_USER_ID ? `
        <div class="avatar-actions-bar">
          <button class="action-btn action-btn-primary btn-cartoon-action btn-action-follow" onclick="toggleFollow(${user.id})">
            <i class="fa-solid ${profile.is_following ? 'fa-user-check' : 'fa-user-plus'}"></i> ${profile.is_following ? 'Bỏ theo dõi' : 'Theo dõi'}
          </button>
        </div>
      ` : '')}
    </div>
  `;

  if (isOwner) {
    attachAvatarUploadEvents(user);
  }
}

function attachAvatarUploadEvents(user) {
  const triggerBtn = document.getElementById('btn-trigger-avatar-upload');
  const openModalBtn = document.getElementById('btn-open-avatar-modal');
  const fileInput = document.getElementById('avatar-file-input') || document.getElementById('custom-avatar-file-input');
  const previewModal = document.getElementById('avatar-preview-modal');
  const previewImg = document.getElementById('avatar-preview-image');
  const fileDetails = document.getElementById('avatar-file-details');
  const feedback = document.getElementById('avatar-upload-feedback');
  const closeBtn = document.getElementById('btn-close-avatar-modal');
  const cancelBtn = document.getElementById('btn-cancel-avatar-modal');
  const confirmBtn = document.getElementById('btn-confirm-avatar-upload');
  const resetBtn = document.getElementById('btn-reset-avatar-action');
  const editBioBtn = document.getElementById('btn-edit-bio');
  const openSettingsBtn = document.getElementById('btn-open-settings-profile');

  if (editBioBtn) {
    editBioBtn.onclick = (e) => openBioSettingsDirect(e);
  }

  if (openSettingsBtn) {
    openSettingsBtn.onclick = (e) => openBioSettingsDirect(e);
  }

  const openFileChooser = () => {
    if (fileInput) {
      fileInput.value = '';
      fileInput.click();
    }
  };

  if (triggerBtn) triggerBtn.onclick = openFileChooser;
  if (openModalBtn) openModalBtn.onclick = openFileChooser;

  const closePreviewModal = () => {
    if (previewModal) {
      previewModal.classList.remove('active');
      previewModal.style.display = 'none';
    }
    selectedAvatarFile = null;
    if (feedback) { feedback.textContent = ''; feedback.className = ''; }
    if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Lưu ảnh đại diện'; }
    if (cancelBtn) cancelBtn.disabled = false;
  };

  if (closeBtn) closeBtn.onclick = closePreviewModal;
  if (cancelBtn) cancelBtn.onclick = closePreviewModal;

  // Xử lý sự kiện khi người dùng chọn file ảnh từ máy tính
  if (fileInput) {
    fileInput.onchange = async () => {
      const file = fileInput.files?.[0];
      if (!file) return;

      // 1. Kiểm tra định dạng ảnh hợp lệ (jpg, jpeg, png, webp, gif)
      const validMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
      const validExtensions = /\.(jpe?g|png|webp|gif)$/i;
      const fileType = (file.type || '').toLowerCase();
      const fileName = file.name || '';

      if (!validMimeTypes.includes(fileType) && !validExtensions.test(fileName)) {
        alert('Định dạng ảnh không hợp lệ. Vui lòng chọn file ảnh JPG, JPEG, PNG, WEBP hoặc GIF.');
        fileInput.value = '';
        return;
      }

      // 2. Kiểm tra dung lượng tối đa (<= 5MB)
      const maxSize = 5 * 1024 * 1024;
      if (file.size <= 0 || file.size > maxSize) {
        alert('Dung lượng ảnh vượt quá giới hạn 5 MB. Vui lòng chọn ảnh nhỏ hơn.');
        fileInput.value = '';
        return;
      }

      // 3. Hiển thị trạng thái đang tải
      const originalOpenText = openModalBtn ? openModalBtn.innerHTML : '';
      const originalTriggerText = triggerBtn ? triggerBtn.innerHTML : '';
      if (openModalBtn) {
        openModalBtn.disabled = true;
        openModalBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang tải lên...';
      }
      if (triggerBtn) {
        triggerBtn.disabled = true;
        triggerBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
      }

      // Xem trước tức thời trên ảnh hồ sơ
      const profileAvatarImg = document.getElementById('profile-avatar-img');
      let tempBlobUrl = null;
      try {
        tempBlobUrl = URL.createObjectURL(file);
        if (profileAvatarImg) {
          profileAvatarImg.style.opacity = '0.65';
          profileAvatarImg.src = tempBlobUrl;
        }
      } catch (_) {}

      try {
        // 4. Tạo FormData và gắn CSRF Token
        const formData = new FormData();
        formData.append('avatar', file);

        const headers = {};
        if (window.Csrf) {
          await window.Csrf.attach(headers, APP_URL);
        } else if (typeof getProfileCsrfToken === 'function') {
          const token = await getProfileCsrfToken();
          if (token) headers['X-CSRF-TOKEN'] = token;
        }

        const endpoint = new URL(`${APP_URL}/index.php`, window.location.origin);
        endpoint.searchParams.set('url', 'api/users/avatar');

        const response = await fetch(endpoint, {
          method: 'POST',
          headers,
          credentials: 'same-origin',
          body: formData
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
          throw new Error(data.error || data.message || 'Tải ảnh đại diện lên thất bại.');
        }

        // 5. Cập nhật ảnh đại diện mới với timestamp chống cache (?t=...)
        const rawAvatar = data.avatar || data.avatar_url;
        let finalUrl = data.avatar_url || data.url || rawAvatar;
        if (!finalUrl.startsWith('http') && !finalUrl.startsWith('blob:')) {
          finalUrl = `${PROFILE_API_BASE}${finalUrl.startsWith('/') ? '' : '/'}${finalUrl}`;
        }
        const cacheBuster = `t=${Date.now()}`;
        finalUrl = finalUrl.includes('?') ? `${finalUrl}&${cacheBuster}` : `${finalUrl}?${cacheBuster}`;

        if (profileAvatarImg) {
          profileAvatarImg.style.opacity = '1';
          profileAvatarImg.src = finalUrl;
        }

        // Cập nhật avatar thanh điều hướng
        const navAvatar = document.getElementById('fb-nav-avatar');
        if (navAvatar) {
          navAvatar.src = finalUrl;
        }
        document.querySelectorAll('.fb-avatar-52, .fb-avatar-img').forEach(img => {
          img.src = finalUrl;
        });

        // Đồng bộ dữ liệu người dùng và bộ nhớ tài khoản
        user.avatar = rawAvatar;
        syncUserAvatarGlobally(rawAvatar, Number(user.id), finalUrl);

        alert(data.message || 'Cập nhật ảnh đại diện thành công!');

        // Đóng preview modal nếu đang mở
        closePreviewModal();

        // Render lại header để cập nhật lại nút "Mặc định"
        renderProfileHeader(user, currentProfileData);

      } catch (err) {
        if (profileAvatarImg) {
          profileAvatarImg.style.opacity = '1';
          profileAvatarImg.src = avatarUrl(user.avatar);
        }
        alert(err.message || 'Lỗi khi tải ảnh đại diện lên máy chủ.');
      } finally {
        if (tempBlobUrl) {
          try { URL.revokeObjectURL(tempBlobUrl); } catch (_) {}
        }
        fileInput.value = '';
        if (openModalBtn) {
          openModalBtn.disabled = false;
          openModalBtn.innerHTML = originalOpenText || '<i class="fa-solid fa-camera"></i> Đổi avatar';
        }
        if (triggerBtn) {
          triggerBtn.disabled = false;
          triggerBtn.innerHTML = originalTriggerText || '<i class="fa-solid fa-camera"></i>';
        }
      }
    };
  }

  // Khôi phục avatar về mặc định
  if (resetBtn) {
    resetBtn.onclick = async () => {
      if (!confirm('Bạn có chắc chắn muốn khôi phục ảnh đại diện mặc định?')) return;
      resetBtn.disabled = true;
      resetBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang khôi phục...';
      try {
        const headers = {};
        if (window.Csrf) {
          await window.Csrf.attach(headers, APP_URL);
        } else if (typeof getProfileCsrfToken === 'function') {
          const token = await getProfileCsrfToken();
          if (token) headers['X-CSRF-TOKEN'] = token;
        }

        const endpoint = new URL(`${APP_URL}/index.php`, window.location.origin);
        endpoint.searchParams.set('url', 'api/users/avatar');

        const response = await fetch(endpoint, {
          method: 'DELETE',
          headers,
          credentials: 'same-origin'
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
          throw new Error(data.error || 'Khôi phục ảnh đại diện thất bại.');
        }

        user.avatar = data.avatar;
        syncUserAvatarGlobally(data.avatar, Number(user.id));
        alert(data.message || 'Đã khôi phục ảnh đại diện mặc định.');
        renderProfileHeader(user, currentProfileData);
      } catch (err) {
        alert(err.message || 'Lỗi khi khôi phục ảnh đại diện.');
        resetBtn.disabled = false;
        resetBtn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Mặc định';
      }
    };
  }
}

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

// Toggle Bookmark trên Profile nếu app.js chưa nạp
if (typeof window.toggleBookmark === 'undefined') {
  window.toggleBookmark = async function (postId, btnElement, event) {
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
        await profileApi(`api/posts/${id}/unbookmark`, {});
        setUiState(false);
      } else {
        await profileApi(`api/posts/${id}/bookmark`, {});
        setUiState(true);
      }
    } catch (err) {
      alert(err.message || 'Không thể cập nhật trạng thái lưu bài viết.');
      setUiState(isCurrentlyBookmarked);
    } finally {
      matchingButtons.forEach(b => delete b.dataset.processing);
    }
  };
  window.handleBookmark = window.toggleBookmark;
}

// Render Feed trên Profile tương tự Trang chủ
function renderProfileFeed(userId) {
  const userPosts = mockPosts.filter(p => Number(p.user.id) === Number(userId));
  const container = document.getElementById("profile-feed-container");
  if (!container) return;

  if (userPosts.length === 0) {
    container.innerHTML = `<div class="post-card"><p>Người dùng này chưa có bài đăng nào.</p></div>`;
    return;
  }

  container.innerHTML = userPosts.map(post => {
    const isMyPost = Number(post.user?.id || post.user_id) === Number(CURRENT_USER_ID);
    const postIsSafe = !post.moderation_status || post.moderation_status === 'safe';
    const commentCount = Number(
      post.comments_count ??
      post.comment_count ??
      post.total_comments ??
      (Array.isArray(post.comments) ? post.comments.length : 0)
    );
    const shareCount = Number(post.shares_count ?? post.total_shares ?? post.share_count ?? 0);
    const viewsCount = Number(post.views_count ?? post.views ?? post.view_count ?? 0);

    return `
      <div class="post-card" id="post-card-${post.id}" data-post-id="${post.id}">
        <div class="post-header">
          <a href="profile.html?id=${post.user.id}">
            <img src="${avatarUrl(post.user.avatar)}" class="avatar" alt="Avatar">
          </a>
          <div class="user-info">
            <a href="profile.html?id=${post.user.id}" class="user-name">
              ${profileEscape(post.user.full_name)} ${post.user.is_verified ? '<span class="verified-badge">✓</span>' : ''}
            </a>
            <span class="font-size-sm text-muted">${post.created_at} · <span title="${viewsCount.toLocaleString()} lượt xem"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:2px;display:inline-block;" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>${viewsCount.toLocaleString()}</span></span>
          </div>
          <span class="badge ${post.post_type === 'news' ? 'badge-news' : 'badge-blog'} ml-auto">
            ${post.post_type === 'news' ? 'Tin tức' : 'Blog'}
          </span>
          ${isMyPost && !postIsSafe ? `<span class="badge badge-${profileEscape(post.moderation_status)}">${profileEscape(post.moderation_status)}</span>` : ''}
        </div>
        
        <!-- Bấm Tiêu đề để mở Pop-up Xem Chi Tiết -->
        ${post.original_id ? `<p class="repost-comment">${profileEscape(post.share_comment || '')}</p><article class="repost-preview" onclick="event.stopPropagation(); openPostModal(${Number(post.original_id)})"><strong>${profileEscape(post.original_title || 'Bài viết gốc')}</strong><p>${profileEscape(post.original_summary || String(post.original_content || '').slice(0,180))}</p><small>Đăng bởi ${profileEscape(post.original_author || '')}</small></article>` : (post.share_comment || (post.title && post.title.startsWith('Đăng lại:') && !post.original_id) ? `<p class="repost-comment">${profileEscape(post.share_comment || '')}</p><article class="repost-preview repost-deleted"><em><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Bài viết gốc đã bị xóa hoặc không còn khả dụng.</em></article>` : `<div class="post-title ${postIsSafe ? 'cursor-pointer' : ''}" ${postIsSafe ? `onclick="event.stopPropagation(); openPostModal(${post.id})"` : ''}>${profileEscape(post.title)}</div><p class="post-summary">${profileEscape(post.summary || post.content.substring(0, 120) + '...')}</p>`)}

        <!-- Hashtags -->
        <div class="post-hashtags">
          ${post.hashtags ? post.hashtags.map(tag => `
            <span class="post-hashtag-pill">#${profileEscape(tag)}</span>
          `).join('') : ''}
        </div>

        ${post.thumbnail ? `<img src="${post.thumbnail}" class="post-image ${postIsSafe ? 'cursor-pointer' : ''}" ${postIsSafe ? `onclick="event.stopPropagation(); openPostModal(${post.id})"` : ''} alt="Thumbnail">` : ''}

        <!-- Nút Tương Tác -->
        <div class="post-interactive-stats">
          ${!postIsSafe ? '<span class="text-muted">Bài viết đang được kiểm duyệt; tương tác tạm ẩn.</span>' : `
          ${profileReactionMarkup(post)}
          
          <button type="button" class="stat-btn" onclick="event.preventDefault(); event.stopPropagation(); openPostModal(${post.id})">
            ${SVG_ICONS.comment} <span>${commentCount}</span> Bình luận
          </button>
          
          <button type="button" class="stat-btn stat-btn-share" data-post-id="${post.id}" onclick="event.preventDefault(); event.stopPropagation(); sharePost(${post.id}, ${userId}, event)">
            ${SVG_ICONS.share} <span class="shares-count">${shareCount}</span> Chia sẻ
          </button>

          <button type="button" class="stat-btn stat-btn-bookmark ${post.is_bookmarked ? 'active' : ''}" data-post-id="${post.id}" onclick="event.preventDefault(); event.stopPropagation(); toggleBookmark(${post.id}, this, event)">
            ${SVG_ICONS.bookmark(post.is_bookmarked)} <span>${post.is_bookmarked ? 'Đã lưu' : 'Lưu'}</span>
          </button>

          ${isMyPost
            ? `<details class="comment-menu post-menu" onclick="event.stopPropagation()"><summary aria-label="Tùy chọn bài viết" onclick="event.stopPropagation()">•••</summary><div class="comment-menu-items"><button type="button" onclick="event.stopPropagation(); this.closest('details').open=false; profileEditPost(${Number(post.id)})">Sửa bài viết</button><button type="button" class="danger" onclick="event.stopPropagation(); this.closest('details').open=false; profileDeletePost(${Number(post.id)})">Xóa bài viết</button></div></details>`
            : (CURRENT_USER_ID !== null ? `<button type="button" class="stat-btn" onclick="event.preventDefault(); event.stopPropagation(); profileReportPost(${Number(post.id)}, event)"><span class="report-exclamation-icon" aria-hidden="true">!</span> Báo cáo</button>` : '')}

          ${isMyPost && post.post_type === 'blog' && Number(post.user.reputation_score || 0) >= 100 ? `
            <button type="button" class="stat-btn stat-btn-ticket" onclick="event.preventDefault(); event.stopPropagation(); sendTicket(${post.id}, event)">
              ${SVG_ICONS.ticket} Gửi Ticket
            </button>
          ` : ''}
          `}
        </div>
      </div>
    `;
  }).join('');

  if (!container.dataset.cardClickBound) {
    container.dataset.cardClickBound = "1";
    container.addEventListener('click', (event) => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) return;

      if (event.target.closest('button, a, input, textarea, select, details, summary, .post-interactive-stats, .post-actions, .reaction-control, .reaction-picker, .reaction-choice, .reaction-trigger, .stat-btn, .action-btn, .btn-bookmark, .stat-btn-bookmark, .comment-menu, .post-hashtags, [onclick]:not(.cursor-pointer):not(.post-title):not(.post-body)')) {
        return;
      }

      const targetContent = event.target.closest('.post-title, .post-summary, .post-image, .repost-preview');
      if (targetContent) {
        const card = event.target.closest('.post-card');
        const postId = card?.dataset?.postId;
        if (postId) {
          event.preventDefault();
          event.stopPropagation();
          openPostModal(Number(postId));
        }
      }
    });
  }
}

function openProfileActionModal(title, markup, submitHandler) {
  let modal = document.getElementById('post-action-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'post-action-modal';
    modal.className = 'modal-overlay';
    document.body.appendChild(modal);
  }
  modal.innerHTML = `<div class="modal-content post-action-modal-content" role="dialog" aria-modal="true" aria-labelledby="post-action-modal-title">
    <button type="button" class="close-modal" aria-label="Đóng cửa sổ">×</button><h2 id="post-action-modal-title"></h2>${markup}
  </div>`;
  modal.querySelector('#post-action-modal-title').textContent = title;
  const close = () => modal.classList.remove('active');
  modal.querySelector('.close-modal').addEventListener('click', close);
  modal.onclick = event => { if (event.target === modal) close(); };
  modal.querySelector('form').addEventListener('submit', async event => {
    event.preventDefault();
    const submit = modal.querySelector('[type="submit"]');
    submit.disabled = true;
    try { await submitHandler(new FormData(event.currentTarget)); }
    catch (error) { alert(error.message || 'Không thể hoàn tất thao tác.'); submit.disabled = false; }
  });
  modal.classList.add('active');
  return modal;
}

async function profileEditPost(postId) {
  try {
    const post = (await profileApi(`api/posts/${postId}`)).data;
    const modal = openProfileActionModal('Chỉnh sửa bài viết đã đăng', `<form class="post-action-form">
      <label for="profile-edit-post-title">Tiêu đề bài viết</label>
      <input class="input-field" id="profile-edit-post-title" name="title" maxlength="200" required>
      <label for="profile-edit-post-content">Nội dung bài viết</label>
      <textarea class="input-field" id="profile-edit-post-content" name="content" rows="9" maxlength="50000" required></textarea>
      <div class="post-action-form-buttons"><button type="button" class="action-btn profile-action-cancel">Hủy</button><button type="submit" class="action-btn action-btn-primary">Lưu thay đổi</button></div>
    </form>`, async formData => {
      await profileApi(`api/posts/${postId}/edit`, { title: formData.get('title'), content: formData.get('content') });
      modal.classList.remove('active');
      location.reload();
    });
    modal.querySelector('[name="title"]').value = post.title || '';
    modal.querySelector('[name="content"]').value = post.content || '';
    modal.querySelector('.profile-action-cancel').addEventListener('click', () => modal.classList.remove('active'));
    modal.querySelector('[name="title"]').focus();
  } catch (error) { alert(error.message); }
}

async function profileDeletePost(postId) {
  if (!confirm('Bạn có chắc muốn xóa bài viết này?')) return;
  try {
    await profileApi(`api/posts/${postId}/delete`, {});
    location.reload();
  } catch (error) { alert(error.message); }
}

function profileReportPost(postId, event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }
  const modal = openProfileActionModal('Báo cáo bài viết', `<form class="post-action-form">
    <p class="post-report-intro">Cho quản trị viên biết vấn đề bạn gặp trong bài viết này.</p>
    <label for="profile-report-reason">Lý do báo cáo</label>
    <select class="input-field" id="profile-report-reason" name="reason" required>
      <option value="">Chọn lý do</option><option value="inappropriate">Nội dung không phù hợp</option>
      <option value="impersonation">Giả mạo hoặc thông tin sai lệch</option><option value="harassment">Công kích hoặc quấy rối người khác</option>
    </select>
    <label for="profile-report-details">Mô tả nội dung cần báo cáo</label>
    <textarea class="input-field" id="profile-report-details" name="details" rows="5" maxlength="1000" placeholder="Mô tả cụ thể nội dung hoặc lý do bạn báo cáo (không bắt buộc)"></textarea>
    <div class="post-action-form-buttons"><button type="button" class="action-btn profile-action-cancel">Hủy</button><button type="submit" class="action-btn action-btn-primary">Gửi báo cáo</button></div>
  </form>`, async formData => {
    const result = await profileApi(`api/posts/${postId}/report`, { reason: formData.get('reason'), details: formData.get('details') });
    modal.classList.remove('active');
    alert(result.message || 'Đã gửi báo cáo đến quản trị viên.');
  });
  modal.querySelector('.profile-action-cancel').addEventListener('click', () => modal.classList.remove('active'));
}

// Chức năng Pop-up Modal xem chi tiết bài viết & Bình luận
async function openPostModal(postId, event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }
  let post = mockPosts.find(p => Number(p.id) === Number(postId));
  try { post = (await profileApi(`api/posts/${postId}`)).data; }
  catch (error) { if (!post) { alert(error.message); return; } }
  if (!post) return;

  const postComments = Array.isArray(post.comments) ? post.comments : [];
  const commentCount = Number(
    post.comments_count ??
    post.comment_count ??
    post.total_comments ??
    postComments.length
  );
  const shareCount = Number(post.shares_count ?? post.total_shares ?? post.share_count ?? 0);
  const viewsCount = Number(post.views_count ?? post.views ?? post.view_count ?? 0);
  const modalBody = document.getElementById("modal-body-content");

  modalBody.innerHTML = `
    <div class="post-header">
      <a href="profile.html?id=${post.user.id}"><img src="${avatarUrl(post.user.avatar)}" class="avatar" alt="Avatar"></a>
      <div class="user-info">
        <a href="profile.html?id=${post.user.id}" class="user-name">${profileEscape(post.user.full_name)}</a>
        <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--text-muted, #64748b); margin-top:2px;">
          <span class="font-size-sm text-muted">${post.created_at || ''}</span>
          <span aria-hidden="true">•</span>
          <span style="display:inline-flex; align-items:center; gap:4px;" title="${viewsCount.toLocaleString()} lượt xem">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            <span>${viewsCount.toLocaleString()} lượt xem</span>
          </span>
        </div>
      </div>
      <span class="badge ${post.post_type === 'news' ? 'badge-news' : 'badge-blog'} ml-auto">${post.post_type === 'news' ? 'Tin tức' : 'Blog'}</span>
    </div>

    ${post.original_id ? `<p class="repost-comment">${profileEscape(post.share_comment || '')}</p><article class="repost-preview" onclick="openPostModal(${Number(post.original_id)})"><strong>${profileEscape(post.original_title || 'Bài viết gốc')}</strong><p>${profileEscape(post.original_summary || String(post.original_content || '').slice(0,240))}</p><small>Đăng bởi ${profileEscape(post.original_author || '')}</small></article>` : (post.share_comment || (post.title && post.title.startsWith('Đăng lại:') && !post.original_id) ? `<p class="repost-comment">${profileEscape(post.share_comment || '')}</p><article class="repost-preview repost-deleted"><em><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Bài viết gốc đã bị xóa hoặc không còn khả dụng.</em></article>` : `<h2 class="font-size-xl mb-3 mt-3">${profileEscape(post.title)}</h2><div class="post-content-full">${profileEscape(post.content).replace(/\n/g, '<br>')}</div>`)}

    <div class="post-hashtags mt-3">
      ${post.hashtags ? post.hashtags.map(tag => `
        <span class="post-hashtag-pill">#${tag}</span>
      `).join('') : ''}
    </div>

    ${post.thumbnail ? `<img src="${post.thumbnail}" class="post-image mt-3 post-image-zoomable" alt="Ảnh bài viết" role="button" tabindex="0" title="Nhấn để xem ảnh lớn" onclick="openImageViewer(this.src, this.alt)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openImageViewer(this.src,this.alt)}">` : ''}

    <div class="post-interactive-stats mt-4">
      ${profileReactionMarkup(post)}
      <button type="button" class="stat-btn">${SVG_ICONS.comment} <span>${commentCount}</span> Bình luận</button>
      <button type="button" class="stat-btn stat-btn-share" data-post-id="${post.id}" onclick="event.preventDefault(); event.stopPropagation(); sharePost(${post.id}, ${post.user.id}, event)">${SVG_ICONS.share} <span class="shares-count">${shareCount}</span> Chia sẻ</button>
      ${Number(post.user.id) === Number(CURRENT_USER_ID)
        ? `<details class="comment-menu post-menu" onclick="event.stopPropagation()"><summary aria-label="Tùy chọn bài viết" onclick="event.stopPropagation()">•••</summary><div class="comment-menu-items"><button type="button" onclick="event.stopPropagation(); this.closest('details').open=false; profileEditPost(${Number(post.id)})">Sửa bài viết</button><button type="button" class="danger" onclick="event.stopPropagation(); this.closest('details').open=false; profileDeletePost(${Number(post.id)})">Xóa bài viết</button></div></details>`
        : (CURRENT_USER_ID !== null ? `<button type="button" class="stat-btn" onclick="event.preventDefault(); event.stopPropagation(); profileReportPost(${Number(post.id)}, event)"><span class="report-exclamation-icon" aria-hidden="true">!</span> Báo cáo</button>` : '')}
    </div>

    <!-- Danh Sách Bình Luận -->
    <div class="comments-section">
      <h4 class="mb-3">Bình luận (${commentCount})</h4>
      <div id="modal-comments-list"></div>

      <div class="comment-input-box">
        <input type="text" id="modal-comment-input" data-mention-input="1" autocomplete="off" class="input-field" placeholder="Viết bình luận... (gõ @ để nhắc tên)">
        <button class="action-btn action-btn-primary" onclick="addCommentFromModal(${post.id}, ${post.user.id})">Gửi</button>
      </div>
    </div>
  `;

  const commentList = modalBody.querySelector('#modal-comments-list');
  if (commentList) commentList.innerHTML = profileCommentThreads(postComments, post);
  const commentParent = document.createElement('input');
  commentParent.type = 'hidden'; commentParent.id = 'modal-comment-parent-id';
  modalBody.querySelector('.comment-input-box')?.prepend(commentParent);
  document.getElementById("post-modal").classList.add("active");
}

function closeModal(modalId) {
  document.getElementById(modalId).classList.remove("active");
}

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

async function addCommentFromModal(postId, userId) {
  const input = document.getElementById("modal-comment-input");
  const rawText = input?.value.trim() || '';
  if (!rawText) return;
  const text = typeof window.serializeMentionsFromInput === 'function'
    ? window.serializeMentionsFromInput(input, rawText).trim()
    : rawText;
  if (!text) return;

  try {
    const parentInput = document.getElementById('modal-comment-parent-id');
    const parentId = parentInput?.value || null;
    await profileApi(`api/posts/${postId}/comments`, {content:text, parent_id:parentId});
    if (parentInput) parentInput.value = '';
    const modalInd = document.getElementById('modal-replying-indicator');
    if (modalInd) modalInd.style.display = 'none';
    if (input) {
      input.value = '';
      input.placeholder = 'Viết bình luận... (gõ @ để nhắc tên)';
      if (typeof window.clearInputMentions === 'function') window.clearInputMentions(input);
    }
    await reloadProfile(userId);
    await openPostModal(postId);
  } catch (error) { alert(error.message); }
}

function profileReplyToComment(commentId, arg2, arg3, arg4) {
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
  const displayTarget = uname ? `@${uname}` : `bình luận #${commentId}`;

  const parent = document.getElementById('modal-comment-parent-id') || (postId ? document.getElementById(`comment-parent-id-${postId}`) : null);
  const input = document.getElementById('modal-comment-input') || (postId ? document.getElementById(`comment-input-${postId}`) : null);
  if (parent && input) {
    parent.value = String(commentId);
    input.placeholder = `Đang trả lời ${displayTarget}...`;
    let modalInd = document.getElementById('modal-replying-indicator') || (postId ? document.getElementById(`comment-replying-indicator-${postId}`) : null);
    if (!modalInd && input.parentElement) {
      modalInd = document.createElement('div');
      modalInd.id = 'modal-replying-indicator';
      modalInd.className = 'comment-replying-indicator';
      modalInd.style.cssText = 'display:flex; font-size:12px; color:#0d9488; margin-bottom:6px; align-items:center; gap:6px;';
      modalInd.innerHTML = '<span>↳ Đang trả lời <span class="reply-target-id"></span></span><button type="button" onclick="cancelReplyComment()" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:11px;text-decoration:underline;padding:0;">(Hủy)</button>';
      input.parentElement.parentElement.insertBefore(modalInd, input.parentElement);
    }
    if (modalInd) {
      modalInd.style.display = 'flex';
      const sp = modalInd.querySelector('.reply-target-id');
      if (sp) sp.textContent = displayTarget;
    }
    if (uid > 0 && uname && typeof window.applyAutoReplyMention === 'function') {
      window.applyAutoReplyMention(input, uid, uname);
    } else {
      input.focus();
    }
  }
}
async function setProfileCommentReaction(commentId, postId, reaction, event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }
  try { await profileApi(`api/comments/${commentId}/like`, {reaction}); await openPostModal(postId); }
  catch (error) { alert(error.message); }
}
async function profileLikeComment(commentId, postId, currentReaction, trigger, event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }
  const wrapper = trigger?.closest('.reaction-control');
  if (wrapper?.dataset.suppressLikeClick === '1') { delete wrapper.dataset.suppressLikeClick; return; }
  await setProfileCommentReaction(commentId, postId, currentReaction === 'like' ? null : 'like', event);
}
async function profileEditComment(commentId, postId) {
  const oldText = document.querySelector(`article[data-comment-id="${commentId}"] .comment-text`)?.textContent || '';
  const content = prompt('Chỉnh sửa bình luận:', oldText);
  if (content === null || !content.trim()) return;
  try { await profileApi(`api/comments/${commentId}/edit`, {content:content.trim()}); await openPostModal(postId); }
  catch (error) { alert(error.message); }
}
async function profileDeleteComment(commentId, postId) {
  if (!confirm('Bạn chắc chắn muốn xóa bình luận này?')) return;
  try {
    const res = await profileApi(`api/comments/${commentId}/delete`, {});
    const deletedIds = Array.isArray(res?.deleted_ids) ? res.deleted_ids : [Number(commentId)];

    // Xóa ngay lập tức khỏi DOM toàn bộ nhánh bình luận bị xóa
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

    const targetPostId = Number(postId || res?.post_id);
    const newCount = (typeof res?.comment_count === 'number') ? res.comment_count : null;

    if (newCount !== null) {
      const modalHeading = document.querySelector('.comments-section h4');
      if (modalHeading) modalHeading.textContent = `Bình luận (${newCount})`;

      if (targetPostId) {
        document.querySelectorAll(`.post-card[data-post-id="${targetPostId}"] .comments-count, button[onclick*="openPostModal(${targetPostId})"] .comments-count`).forEach(span => {
          span.textContent = newCount;
        });
      }
    }

    const commentList = document.getElementById('modal-comments-list');
    if (commentList && !commentList.querySelector('.comment-item, article')) {
      commentList.innerHTML = '<p class="text-muted text-center py-2">Chưa có bình luận.</p>';
    }

    await reloadProfile(CURRENT_PROFILE_ID);
    if (document.getElementById('post-modal') && document.getElementById('post-modal').style.display !== 'none' && targetPostId) {
      const freshRes = await profileApi(`api/posts/${targetPostId}`);
      if (freshRes?.data) {
        const mList = document.getElementById('modal-comments-list');
        if (mList) mList.innerHTML = profileCommentThreads(freshRes.data.comments || [], freshRes.data);
      }
    }
  } catch (error) { alert(error.message); }
}

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

  const targetPostId = postId || (typeof CURRENT_POST_ID !== 'undefined' ? CURRENT_POST_ID : null);
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
window.profileReplyToComment = profileReplyToComment;
window.replyToComment = profileReplyToComment;
window.replyComment = profileReplyToComment;
window.profileEditComment = profileEditComment;
window.editComment = profileEditComment;
window.profileDeleteComment = profileDeleteComment;
window.deleteComment = profileDeleteComment;
window.setProfileCommentReaction = setProfileCommentReaction;
window.setCommentReaction = setProfileCommentReaction;
window.reactComment = setProfileCommentReaction;
window.profileLikeComment = profileLikeComment;
window.likeComment = profileLikeComment;
window.addCommentFromModal = addCommentFromModal;
window.profileCommentCard = profileCommentCard;
window.renderCommentCard = profileCommentCard;
window.renderCommentItem = profileCommentCard;
window.profileCommentThreads = profileCommentThreads;
window.renderCommentThreads = profileCommentThreads;
window.renderCommentTree = profileCommentThreads;

function updateProfileReactionDom(postId, result) {
  const numericId = Number(postId);
  const myReaction = result.my_reaction || null;
  const isReacted = Boolean(myReaction && PROFILE_REACTIONS[myReaction]);
  const likesCount = Number(result.likes_count ?? 0);
  const reactionCounts = result.reaction_counts || {};

  if (typeof mockPosts !== 'undefined' && Array.isArray(mockPosts)) {
    const postItem = mockPosts.find(p => Number(p.id) === numericId);
    if (postItem) {
      postItem.my_reaction = myReaction;
      postItem.is_liked = isReacted;
      postItem.likes_count = likesCount;
      postItem.reaction_counts = reactionCounts;
    }
  }

  const breakdownParts = [];
  Object.entries(PROFILE_REACTIONS).forEach(([k, em]) => {
    const c = Number(reactionCounts[k] || 0);
    if (c > 0) breakdownParts.push(`${em} ${c}`);
  });
  const breakdownText = breakdownParts.length > 0 ? breakdownParts.join(' · ') : (likesCount > 0 ? `👍 ${likesCount}` : 'Chưa có tương tác');

  const controls = document.querySelectorAll(`.reaction-control[data-post-id="${numericId}"]`);
  controls.forEach(ctrl => {
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
    if (iconEl) iconEl.textContent = isReacted ? (PROFILE_REACTIONS[myReaction] || '👍') : '👍';

    const labelEl = trigger.querySelector('.reaction-label');
    if (labelEl) labelEl.textContent = isReacted ? (PROFILE_REACTION_LABELS[myReaction] || 'Đã thích') : 'Thích';

    const countEl = trigger.querySelector('.likes-count');
    if (countEl) countEl.textContent = Number(likesCount).toLocaleString('vi-VN');

    trigger.setAttribute('title', breakdownText);
    const tooltipEl = ctrl.querySelector('.reaction-tooltip');
    if (tooltipEl) tooltipEl.textContent = breakdownText;
  });
}
window.updateProfileReactionDom = updateProfileReactionDom;

async function setProfileReaction(postId, reaction, event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }

  const numericId = Number(postId);
  const targetControl = event?.target?.closest?.('.reaction-control') ||
    document.querySelector(`.reaction-control[data-post-id="${numericId}"]`);

  if (targetControl) {
    targetControl.classList.remove('show-reactions');
    targetControl.classList.add('reaction-closing');
    setTimeout(() => targetControl.classList.remove('reaction-closing'), 300);
  }

  const trigger = targetControl?.querySelector('.reaction-trigger');
  const currentReaction = trigger?.getAttribute('data-reaction') || null;

  let nextReaction = reaction;
  if (reaction && reaction === currentReaction) {
    nextReaction = null;
  }

  try {
    const res = await profileApi(`api/posts/${numericId}/reaction`, { reaction: nextReaction });
    if (res && res.success) {
      updateProfileReactionDom(numericId, res);
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
window.setProfileReaction = setProfileReaction;

async function toggleLike(postId, userId, trigger, event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }

  let actualTrigger = trigger;
  let actualEvent = event;
  if (trigger && (trigger.currentTarget || trigger.target || typeof trigger.preventDefault === 'function')) {
    actualEvent = trigger;
    actualTrigger = null;
  }
  if (!actualTrigger && actualEvent && actualEvent.currentTarget) {
    actualTrigger = actualEvent.currentTarget;
  }
  if (!actualTrigger && typeof userId === 'object' && userId && userId.nodeType) {
    actualTrigger = userId;
  }

  const wrapper = actualTrigger?.closest?.('.reaction-control') ||
    document.querySelector(`.reaction-control[data-post-id="${Number(postId)}"]`);
  if (wrapper?.dataset.suppressLikeClick === '1') {
    delete wrapper.dataset.suppressLikeClick;
    return;
  }

  const btn = wrapper?.querySelector('.reaction-trigger') || actualTrigger;
  const currentReaction = btn?.getAttribute('data-reaction');
  const isReacted = btn?.classList?.contains('is-reacted') || Boolean(currentReaction);

  const nextReaction = isReacted ? null : 'like';
  await setProfileReaction(Number(postId), nextReaction, actualEvent);
}
window.toggleLike = toggleLike;
window.toggleProfileLike = toggleLike;

function sharePost(postId, userId, event) {
  let actualEvent = event;
  if (userId && (userId.currentTarget || userId.target || typeof userId.preventDefault === 'function')) {
    actualEvent = userId;
  }
  if (actualEvent) {
    if (typeof actualEvent.preventDefault === 'function') actualEvent.preventDefault();
    if (typeof actualEvent.stopPropagation === 'function') actualEvent.stopPropagation();
  }
  document.getElementById('share-action-modal')?.remove();
  const modal = document.createElement('div');
  modal.id = 'share-action-modal'; modal.className = 'modal-overlay active share-action-overlay';
  modal.innerHTML = `<section class="share-action-dialog" role="dialog" aria-modal="true" onclick="event.stopPropagation()"><button type="button" class="close-modal" onclick="document.getElementById('share-action-modal').remove()">&times;</button><h3>Chia sẻ bài viết</h3><button type="button" class="share-choice" onclick="event.stopPropagation(); profileCopyPostLink(${Number(postId)})">${SVG_ICONS.link} Sao chép liên kết</button><button type="button" class="share-choice" onclick="event.stopPropagation(); profileRepost(${Number(postId)})">${SVG_ICONS.repost} Đăng lại lên trang cá nhân</button></section>`;
  modal.addEventListener('click', event => { if (event.target === modal) modal.remove(); });
  document.body.appendChild(modal);
}

function updateProfileShareDom(postId, totalShares) {
  const count = Number(totalShares || 0);
  const elements = document.querySelectorAll(
    `[data-post-id="${postId}"] .shares-count, .stat-btn-share[data-post-id="${postId}"] .shares-count, button[onclick*="sharePost(${postId}"] .shares-count, button[onclick*="sharePost(${postId}"] span`
  );
  elements.forEach(el => {
    if (el.classList.contains('shares-count') || el.closest('.stat-btn-share') || (el.parentElement && el.parentElement.getAttribute('onclick')?.includes('sharePost('))) {
      el.textContent = count;
    }
  });
  if (Array.isArray(mockPosts)) {
    const p = mockPosts.find(item => Number(item.id) === Number(postId));
    if (p) p.shares_count = count;
  }
}
window.updateProfileShareDom = updateProfileShareDom;

async function profileCopyPostLink(postId) {
  const url = new URL(`${PROFILE_API_BASE}/index.php`, window.location.origin);
  url.searchParams.set('url', `post/${postId}`);
  try { await navigator.clipboard.writeText(url.toString()); }
  catch (_) { const box = document.createElement('textarea'); box.value = url.toString(); document.body.appendChild(box); box.select(); document.execCommand('copy'); box.remove(); }
  document.getElementById('share-action-modal')?.remove();
  try {
    const res = await profileApi(`api/posts/${postId}/share`, {});
    if (res && (res.total_shares !== undefined || res.shares_count !== undefined)) {
      const totalShares = res.total_shares ?? res.shares_count;
      updateProfileShareDom(postId, totalShares);
    }
  } catch (err) {
    console.warn('Share track:', err.message);
  }
  alert('Đã sao chép liên kết bài viết.');
}

async function profileRepost(postId) {
  const comment = prompt('Thêm lời dẫn cho bài đăng lại (có thể để trống):', '');
  if (comment === null) return;
  try {
    const res = await profileApi(`api/posts/${postId}/repost`, {comment});
    document.getElementById('share-action-modal')?.remove();
    if (res && (res.total_shares !== undefined || res.shares_count !== undefined)) {
      const totalShares = res.total_shares ?? res.shares_count;
      updateProfileShareDom(postId, totalShares);
    }
    await reloadProfile(CURRENT_PROFILE_ID);
    alert('Đã đăng lại bài viết lên trang cá nhân.');
  } catch (error) { alert(error.message); }
}

async function sendTicket(postId, event) {
  if (event) {
    if (typeof event.preventDefault === 'function') event.preventDefault();
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
  }
  try { const result = await profileApi(`api/posts/${postId}/tickets`, {}); alert(result.message); }
  catch (error) { alert(error.message); }
}

async function toggleFollow(userId) {
  try {
    const res = await profileApi(`api/users/${userId}/follow`, {});
    const result = await profileApi(`api/users/${userId}`);
    renderProfileHeader(result.user, result);
    window.dispatchEvent(new CustomEvent('following-updated', { detail: { userId, isFollowing: res?.is_following } }));
    if (typeof window.renderLeftSidebar === 'function') {
      window.renderLeftSidebar();
    }
  } catch (error) { alert(error.message); }
}

async function reloadProfile(userId) {
  const result = await profileApi(`api/users/${userId}`);
  mockUsers.splice(0, mockUsers.length, result.user);
  mockPosts.splice(0, mockPosts.length, ...result.posts);
  renderProfileHeader(result.user, result);
  renderProfileFeed(userId);
}
