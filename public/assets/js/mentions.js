/**
 * Hệ thống @Mention động theo username đăng nhập (Facebook-style) cho Góc Nhỏ:
 * - Luôn hiển thị @username màu xanh biển, rê chuột đậm + gạch chân, trỏ đúng trang cá nhân theo user_id
 * - Tự động chèn @username khi nhấn Trả lời (không lặp, tự đổi khi chuyển người trả lời, Hủy chỉ xóa phần tag tự động)
 * - Autocomplete khi gõ @ trong các ô nhập bình luận
 * - Chuyển đổi sang token chuẩn @[user:ID] khi gửi lên backend (tự cập nhật khi user đổi username)
 * - Escape XSS tuyệt đối khi render HTML
 */
(function () {
  'use strict';

  const inputMentionsMap = new WeakMap(); // inputEl -> Map<string, number> (username -> userId)
  const autoReplyState = new WeakMap();   // inputEl -> { userId: number, username: string }

  let dropdownEl = null;
  let activeInput = null;
  let activeTriggerStart = -1;
  let activeItems = [];
  let activeIndex = 0;
  let debounceTimer = null;
  let lastFetchSeq = 0;

  function detectBaseUrl() {
    if (typeof window.APP_URL === 'string' && window.APP_URL) {
      return window.APP_URL.replace(/\/+$/, '');
    }
    const p = window.location.pathname || '';
    const idx = p.indexOf('/public');
    if (idx !== -1) return p.slice(0, idx + 7);
    return '/DoAnMaNguonMo/public';
  }

  function escHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeRegex(str) {
    return String(str).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function resolveAvatar(av) {
    if (typeof window.avatarUrl === 'function') return window.avatarUrl(av);
    const base = detectBaseUrl();
    if (!av) return `${base}/assets/images/avatars/default.svg`;
    if (/^https?:\/\//i.test(av)) return av;
    if (av.startsWith('/')) return `${base}${av}`;
    return `${base}/${av}`;
  }

  /** Render nội dung bình luận an toàn XSS với @[user:ID] -> thẻ <a> xanh biển hiển thị @username đăng nhập */
  function formatCommentContentWithMentions(content, mentionsMap) {
    const text = String(content ?? '');
    const map = mentionsMap && typeof mentionsMap === 'object' ? mentionsMap : {};
    const base = detectBaseUrl();
    const parts = text.split(/(@\[user:\d+\])/g);

    return parts.map(part => {
      const m = part.match(/^@\[user:(\d+)\]$/);
      if (!m) {
        return escHtml(part).replace(/\n/g, '<br>');
      }
      const uid = m[1];
      const info = map[uid] || map[Number(uid)] || null;
      if (info && info.exists !== false) {
        const displayUsername = info.username || info.full_name || `user#${uid}`;
        const href = info.profile_url || `${base}/profile.html?id=${uid}`;
        return `<a href="${escHtml(href)}" class="comment-mention-link" data-mention-user-id="${uid}" onclick="event.stopPropagation()">@${escHtml(displayUsername)}</a>`;
      }
      if (info && info.username) {
        return `<a href="${base}/profile.html?id=${uid}" class="comment-mention-link" data-mention-user-id="${uid}" onclick="event.stopPropagation()">@${escHtml(info.username)}</a>`;
      }
      return `<a href="${base}/profile.html?id=${uid}" class="comment-mention-link" data-mention-user-id="${uid}" onclick="event.stopPropagation()">@user#${uid}</a>`;
    }).join('');
  }

  /** Đăng ký ánh xạ @username -> userId cho một ô nhập cụ thể */
  function registerMentionOnInput(inputEl, userId, username) {
    if (!inputEl || !userId || !username) return;
    let map = inputMentionsMap.get(inputEl);
    if (!map) {
      map = new Map();
      inputMentionsMap.set(inputEl, map);
    }
    map.set(String(username), Number(userId));
  }

  /** Chuyển các @username đã đăng ký (từ Trả lời tự động hoặc Autocomplete) thành token @[user:ID] trước khi gửi */
  function serializeMentionsFromInput(inputEl, fallbackText) {
    let text = inputEl ? String(inputEl.value ?? '') : String(fallbackText ?? '');
    if (!inputEl) return text;

    const map = inputMentionsMap.get(inputEl);
    if (!map || map.size === 0) return text;

    // Sắp xếp username dài trước để tránh khớp một phần
    const entries = Array.from(map.entries()).sort((a, b) => b[0].length - a[0].length);
    for (const [uname, userId] of entries) {
      if (!uname || !userId) continue;
      const escaped = escapeRegex(uname);
      const re = new RegExp(`@${escaped}(?=$|[\\s.,!?;:()\\]\\}])`, 'gu');
      text = text.replace(re, `@[user:${userId}]`);
    }
    return text;
  }

  function clearInputMentions(inputEl) {
    if (!inputEl) return;
    inputMentionsMap.delete(inputEl);
    autoReplyState.delete(inputEl);
  }

  /**
   * Tự động chèn "@username " khi nhấn Trả lời một bình luận:
   * - Không chèn lặp nếu bấm Trả lời cùng 1 người nhiều lần
   * - Nếu chuyển sang trả lời người khác -> thay đúng phần tag tự động cũ bằng @username mới, giữ nguyên nội dung user tự gõ
   * - Đăng ký ánh xạ username -> userId để serialize thành @[user:ID]
   */
  function applyAutoReplyMention(inputEl, userId, username) {
    const uid = Number(userId || 0);
    const uname = String(username || '').trim();
    if (!inputEl || uid <= 0 || !uname) return;

    const prev = autoReplyState.get(inputEl);
    let currentVal = String(inputEl.value ?? '');

    // 1. Kiểm tra nếu đang ở đúng prefix của chính user này -> không chèn lặp
    const sameTagRegex = new RegExp(`^\\s*@${escapeRegex(uname)}(?:\\s+|$)`, 'u');
    if (sameTagRegex.test(currentVal)) {
      if (!currentVal.startsWith(`@${uname} `) && currentVal.trim() === `@${uname}`) {
        inputEl.value = `@${uname} `;
      }
      registerMentionOnInput(inputEl, uid, uname);
      autoReplyState.set(inputEl, { userId: uid, username: uname });
      const endPos = inputEl.value.length;
      inputEl.focus();
      try { inputEl.setSelectionRange(endPos, endPos); } catch (_) {}
      return;
    }

    // 2. Nếu trước đó đã tự động chèn tag của người khác -> gỡ đúng prefix tự động cũ, giữ nguyên chữ người dùng đã gõ
    if (prev && prev.username) {
      const oldPrefixRegex = new RegExp(`^\\s*@${escapeRegex(prev.username)}(?:\\s+|$)`, 'u');
      if (oldPrefixRegex.test(currentVal)) {
        currentVal = currentVal.replace(oldPrefixRegex, '');
        const map = inputMentionsMap.get(inputEl);
        if (map && !new RegExp(`@${escapeRegex(prev.username)}(?=$|[\\s.,!?;:()\\]\\}])`, 'u').test(currentVal)) {
          map.delete(prev.username);
        }
      }
    }

    // 3. Chèn "@username " vào đầu ô nhập, giữ nguyên phần nội dung còn lại
    const rest = currentVal.replace(/^\s+/, '');
    inputEl.value = rest ? `@${uname} ${rest}` : `@${uname} `;

    registerMentionOnInput(inputEl, uid, uname);
    autoReplyState.set(inputEl, { userId: uid, username: uname });

    const caretPos = inputEl.value.length;
    inputEl.focus();
    try { inputEl.setSelectionRange(caretPos, caretPos); } catch (_) {}
  }

  /**
   * Khi nhấn Hủy trả lời:
   * Chỉ xóa phần "@username " đã được chèn tự động, tuyệt đối không xóa nội dung người dùng tự viết sau đó.
   */
  function clearAutoReplyMention(inputEl) {
    if (!inputEl) return;
    const prev = autoReplyState.get(inputEl);
    if (!prev || !prev.username) return;

    const currentVal = String(inputEl.value ?? '');
    const prefixRegex = new RegExp(`^\\s*@${escapeRegex(prev.username)}(?:\\s+|$)`, 'u');
    if (prefixRegex.test(currentVal)) {
      inputEl.value = currentVal.replace(prefixRegex, '');
    }

    const map = inputMentionsMap.get(inputEl);
    if (map && !new RegExp(`@${escapeRegex(prev.username)}(?=$|[\\s.,!?;:()\\]\\}])`, 'u').test(inputEl.value)) {
      map.delete(prev.username);
    }
    autoReplyState.delete(inputEl);
  }

  /** Reset hoàn toàn trạng thái reply/mention khi mở modal mới hoặc chuyển bài viết */
  function resetReplyMentionState(inputEl) {
    if (!inputEl) return;
    clearAutoReplyMention(inputEl);
    clearInputMentions(inputEl);
  }

  /** Đổi token @[user:ID] -> @username khi mở sửa bình luận */
  function contentTokensToReadable(content, mentionsMap) {
    const map = mentionsMap && typeof mentionsMap === 'object' ? mentionsMap : {};
    return String(content ?? '').replace(/@\[user:(\d+)\]/g, (_, uid) => {
      const info = map[uid] || map[Number(uid)];
      if (info && info.exists !== false) {
        return `@${info.username || info.full_name}`;
      }
      return '';
    });
  }

  function isMentionTarget(el) {
    if (!el || (el.tagName !== 'INPUT' && el.tagName !== 'TEXTAREA')) return false;
    if (el.dataset && el.dataset.mentionInput === '1') return true;
    const id = el.id || '';
    return id === 'modal-comment-input' || id.startsWith('comment-input-');
  }

  function ensureDropdown() {
    if (dropdownEl && document.body.contains(dropdownEl)) return dropdownEl;
    dropdownEl = document.createElement('div');
    dropdownEl.id = 'mention-autocomplete-dropdown';
    dropdownEl.className = 'mention-autocomplete-dropdown';
    dropdownEl.setAttribute('role', 'listbox');
    dropdownEl.style.display = 'none';
    document.body.appendChild(dropdownEl);
    return dropdownEl;
  }

  function hideDropdown() {
    if (dropdownEl) {
      dropdownEl.style.display = 'none';
      dropdownEl.innerHTML = '';
    }
    activeItems = [];
    activeIndex = 0;
    activeTriggerStart = -1;
  }

  function positionDropdown(inputEl) {
    const dd = ensureDropdown();
    const rect = inputEl.getBoundingClientRect();
    const ddWidth = Math.min(Math.max(rect.width, 260), 360);
    const spaceBelow = window.innerHeight - rect.bottom;
    const estHeight = Math.min(260, Math.max(80, activeItems.length * 46 + 12));

    dd.style.width = `${ddWidth}px`;
    dd.style.left = `${Math.max(8, Math.min(rect.left + window.scrollX, window.innerWidth - ddWidth - 8))}px`;

    if (spaceBelow >= estHeight || rect.top < estHeight) {
      dd.style.top = `${rect.bottom + window.scrollY + 4}px`;
    } else {
      dd.style.top = `${Math.max(8, rect.top + window.scrollY - estHeight - 4)}px`;
    }
  }

  function renderDropdown() {
    const dd = ensureDropdown();
    if (!activeInput || activeItems.length === 0) {
      hideDropdown();
      return;
    }

    dd.innerHTML = activeItems.map((u, idx) => {
      const uname = escHtml(u.username || '');
      const fullName = escHtml(u.full_name || u.username || '');
      const av = escHtml(resolveAvatar(u.avatar));
      const verified = u.is_verified ? '<span class="mention-verified-dot" title="Đã xác minh">✓</span>' : '';
      const activeCls = idx === activeIndex ? 'is-active' : '';
      return `<button type="button" class="mention-option ${activeCls}" role="option" data-mention-idx="${idx}">
        <img src="${av}" class="mention-option-avatar" alt="">
        <span class="mention-option-meta">
          <span class="mention-option-name">@${uname}${verified}</span>
          <span class="mention-option-username">${fullName}</span>
        </span>
      </button>`;
    }).join('');

    dd.style.display = 'block';
    positionDropdown(activeInput);

    dd.querySelectorAll('.mention-option').forEach(btn => {
      btn.addEventListener('mousedown', (e) => {
        e.preventDefault();
        const idx = Number(btn.getAttribute('data-mention-idx'));
        selectMention(idx);
      });
    });
  }

  function selectMention(idx) {
    if (!activeInput || !activeItems[idx]) return;
    const user = activeItems[idx];
    const uname = String(user.username || '').trim();
    if (!uname) return;

    const val = activeInput.value;
    const caret = activeInput.selectionStart ?? val.length;
    const before = val.slice(0, activeTriggerStart);
    const after = val.slice(caret);
    const inserted = `@${uname} `;

    activeInput.value = before + inserted + after;
    const newPos = before.length + inserted.length;
    activeInput.focus();
    try { activeInput.setSelectionRange(newPos, newPos); } catch (_) {}

    registerMentionOnInput(activeInput, user.id, uname);
    activeInput.dispatchEvent(new Event('input', { bubbles: true }));
    hideDropdown();
  }

  async function queryUsers(q) {
    const seq = ++lastFetchSeq;
    const base = detectBaseUrl();
    const url = `${base}/api/users/search?q=${encodeURIComponent(q)}&limit=8`;
    try {
      const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      const json = await res.json();
      if (seq !== lastFetchSeq) return;
      activeItems = Array.isArray(json.data) ? json.data : [];
      activeIndex = 0;
      renderDropdown();
    } catch (_) {
      hideDropdown();
    }
  }

  function handleInputEvent(e) {
    const el = e.target;
    if (!isMentionTarget(el)) return;

    const val = el.value || '';
    const caret = el.selectionStart ?? val.length;
    const textBeforeCaret = val.slice(0, caret);

    const m = textBeforeCaret.match(/(^|\s)@([^\s@]{0,30})$/);
    if (!m) {
      if (activeInput === el) hideDropdown();
      return;
    }

    const query = m[2];
    activeInput = el;
    activeTriggerStart = caret - query.length - 1;

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => queryUsers(query), 120);
  }

  function handleKeyDown(e) {
    if (!dropdownEl || dropdownEl.style.display === 'none' || !activeInput || e.target !== activeInput) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = (activeIndex + 1) % Math.max(1, activeItems.length);
      renderDropdown();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = (activeIndex - 1 + activeItems.length) % Math.max(1, activeItems.length);
      renderDropdown();
    } else if (e.key === 'Enter' || e.key === 'Tab') {
      if (activeItems[activeIndex]) {
        e.preventDefault();
        e.stopPropagation();
        selectMention(activeIndex);
      }
    } else if (e.key === 'Escape') {
      e.preventDefault();
      hideDropdown();
    }
  }

  document.addEventListener('input', handleInputEvent, true);
  document.addEventListener('keydown', handleKeyDown, true);
  document.addEventListener('click', (e) => {
    if (dropdownEl && dropdownEl.style.display !== 'none') {
      if (e.target !== activeInput && !dropdownEl.contains(e.target)) {
        hideDropdown();
      }
    }
  });

  function highlightCommentFromHash() {
    const hash = window.location.hash || '';
    const m = hash.match(/^#comment-(\d+)$/);
    if (!m) return;
    const el = document.getElementById(`comment-${m[1]}`);
    if (!el) return;
    setTimeout(() => {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      el.classList.add('comment-highlight-target');
      setTimeout(() => el.classList.remove('comment-highlight-target'), 3200);
    }, 180);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', highlightCommentFromHash);
  } else {
    highlightCommentFromHash();
  }
  window.addEventListener('hashchange', highlightCommentFromHash);

  window.formatCommentContentWithMentions = formatCommentContentWithMentions;
  window.serializeMentionsFromInput = serializeMentionsFromInput;
  window.clearInputMentions = clearInputMentions;
  window.registerMentionOnInput = registerMentionOnInput;
  window.applyAutoReplyMention = applyAutoReplyMention;
  window.clearAutoReplyMention = clearAutoReplyMention;
  window.resetReplyMentionState = resetReplyMentionState;
  window.contentTokensToReadable = contentTokensToReadable;
})();
