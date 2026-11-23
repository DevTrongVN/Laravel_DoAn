/**
 * Quản lý AI Editor: Biên tập văn bản thực tế (chính tả, viết tự nhiên, rút gọn, mở rộng,
 * cải thiện diễn đạt, thay đổi giọng văn), hỗ trợ chỉnh sửa đúng đoạn văn bản đang bôi đen,
 * xem trước, sao chép, áp dụng thay thế hoặc hủy giữ nguyên bản gốc.
 */

const AI_SVG_ICONS = {
  sparkles: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px; margin-right:5px;" aria-hidden="true"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>`,
  spinner: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="ai-spinner" style="vertical-align:-2px; margin-right:6px;" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`,
  warning: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2.5px; margin-right:6px;" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
  success: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2.5px; margin-right:6px;" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
  error: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2.5px; margin-right:6px;" aria-hidden="true"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`
};

const AI_MODE_LABELS = {
  spellcheck:   'Sửa lỗi chính tả',
  natural:      'Viết rõ ràng, tự nhiên',
  concise:      'Rút gọn súc tích',
  expand:       'Mở rộng nội dung',
  improve:      'Cải thiện diễn đạt',
  professional: 'Giọng chuyên nghiệp',
  literary:     'Giọng văn chương',
  friendly:     'Giọng thân thiện',
};

let lastActiveEditor = null;
let aiSelectionState = {
  editor: null,
  start: 0,
  end: 0,
  isSelection: false,
  originalFullText: '',
};
const trackedSelections = new WeakMap();

function aiEscapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function getAiCsrfToken() {
  if (typeof window.getCsrfToken === 'function') return window.getCsrfToken();
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : '';
}

function recordEditorSelection(el) {
  if (!el || (el.tagName !== 'TEXTAREA' && el.tagName !== 'INPUT')) return;
  const start = Number(el.selectionStart ?? 0);
  const end = Number(el.selectionEnd ?? 0);
  trackedSelections.set(el, { start, end });
}

function getActivePostEditor(explicitEditorId = null) {
  if (explicitEditorId) {
    const el = document.getElementById(explicitEditorId);
    if (el) return el;
  }

  const editModalInput = document.getElementById('edit-post-content');
  if (editModalInput && (editModalInput.offsetWidth > 0 || editModalInput.offsetHeight > 0)) {
    return editModalInput;
  }

  const profileEditInput = document.getElementById('profile-edit-post-content');
  if (profileEditInput && (profileEditInput.offsetWidth > 0 || profileEditInput.offsetHeight > 0)) {
    return profileEditInput;
  }

  const feedInput = document.getElementById('post-editor-input');
  if (feedInput) {
    return feedInput;
  }

  if (lastActiveEditor && document.body.contains(lastActiveEditor)) {
    return lastActiveEditor;
  }

  return editModalInput || profileEditInput || null;
}

function setAiStatus(message, type = 'info') {
  const statusEl = document.getElementById('ai-writer-status');
  if (!statusEl) return;
  const styles = {
    loading: { bg: '#f0fdf4', color: '#15803d', border: '#bbf7d0', icon: AI_SVG_ICONS.spinner },
    success: { bg: '#f0fdf4', color: '#15803d', border: '#bbf7d0', icon: AI_SVG_ICONS.success },
    warning: { bg: '#fffbeb', color: '#92400e', border: '#fde68a', icon: AI_SVG_ICONS.warning },
    error:   { bg: '#fef2f2', color: '#b91c1c', border: '#fecaca', icon: AI_SVG_ICONS.error },
    info:    { bg: '#f8fafc', color: '#334155', border: '#e2e8f0', icon: AI_SVG_ICONS.sparkles },
  };
  const cfg = styles[type] || styles.info;
  statusEl.style.display = 'block';
  statusEl.style.background = cfg.bg;
  statusEl.style.color = cfg.color;
  statusEl.style.border = `1px solid ${cfg.border}`;
  statusEl.innerHTML = `${cfg.icon}${message}`;
}

function updateAiScopeBadge() {
  const badge = document.getElementById('ai-writer-scope-badge');
  if (!badge) return;
  if (aiSelectionState.isSelection) {
    const len = aiSelectionState.end - aiSelectionState.start;
    badge.style.display = 'inline-flex';
    badge.textContent = `Đang chỉnh sửa đoạn văn bản đã bôi đen (${len} ký tự) — chỉ thay thế đúng đoạn này`;
    badge.style.background = '#e0f2fe';
    badge.style.color = '#0369a1';
  } else {
    badge.style.display = 'inline-flex';
    badge.textContent = 'Đang chỉnh sửa toàn bộ nội dung bài viết';
    badge.style.background = '#f1f5f2';
    badge.style.color = '#3e5246';
  }
}

function selectAiMode(mode) {
  const toneEl = document.getElementById('ai-writer-tone');
  if (toneEl && mode) {
    toneEl.value = mode;
  }
  document.querySelectorAll('.ai-mode-chip').forEach(btn => {
    btn.classList.toggle('is-active', btn.dataset.aiMode === (toneEl ? toneEl.value : mode));
  });
}

function openAIModal(modalId = 'ai-writer-modal') {
  const modal = document.getElementById(modalId);
  if (!modal) return;

  modal.classList.add('active');
  modal.classList.add('open');
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';

  const originalInput = document.getElementById('ai-writer-original');
  if (originalInput) {
    setTimeout(() => {
      originalInput.focus();
      originalInput.selectionStart = originalInput.selectionEnd = originalInput.value.length;
    }, 60);
  }
}

function closeAIModal(modalId = 'ai-writer-modal') {
  const modal = document.getElementById(modalId);
  if (!modal) return;

  modal.classList.remove('active');
  modal.classList.remove('open');
  modal.style.display = 'none';
  document.body.style.overflow = '';
}

function showAiToast(message, type = 'success') {
  let container = document.getElementById('ai-toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'ai-toast-container';
    container.setAttribute('aria-live', 'polite');
    container.style.cssText = `
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 100000;
      display: flex;
      flex-direction: column;
      gap: 10px;
      pointer-events: none;
    `;
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `ai-toast ai-toast-${type}`;
  toast.style.cssText = `
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 18px;
    background: ${type === 'error' ? '#b91c1c' : '#1b4d3e'};
    color: #ffffff;
    font-size: 13.5px;
    font-weight: 600;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(27, 77, 62, 0.28), 0 4px 10px rgba(0, 0, 0, 0.08);
    pointer-events: auto;
    opacity: 0;
    transform: translateY(12px) scale(0.96);
    transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1), transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  `;

  const checkSvg = `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>`;
  toast.innerHTML = `${checkSvg}<span>${aiEscapeHtml(message)}</span>`;
  container.appendChild(toast);

  requestAnimationFrame(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0) scale(1)';
  });

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(8px) scale(0.96)';
    setTimeout(() => {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 280);
  }, 2800);
}

const prevCloseModal = window.closeModal;
window.closeModal = function(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('active');
    modal.classList.remove('open');
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }
  if (typeof prevCloseModal === 'function' && prevCloseModal !== window.closeModal) {
    try { prevCloseModal(modalId); } catch (_) {}
  }
};

const prevOpenModal = window.openModal;
window.openModal = function(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
    modal.classList.add('open');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
  if (typeof prevOpenModal === 'function' && prevOpenModal !== window.openModal) {
    try { prevOpenModal(modalId); } catch (_) {}
  }
};

/**
 * Gửi yêu cầu biên tập văn bản tới backend AI (/api/ai/improve)
 */
async function requestAiImprovement(explicitMode = null) {
  const inputEl = document.getElementById('ai-writer-original');
  const resultEl = document.getElementById('ai-writer-result');
  const toneEl = document.getElementById('ai-writer-tone');
  const submitBtn = document.getElementById('ai-writer-submit-btn');

  if (explicitMode && toneEl) {
    selectAiMode(explicitMode);
  }

  let content = inputEl ? inputEl.value.trim() : '';
  if (!content) {
    const activeEd = getActivePostEditor();
    if (activeEd && activeEd.value.trim()) {
      content = activeEd.value.trim();
      if (inputEl) inputEl.value = content;
    }
  }

  if (!content) {
    setAiStatus('Vui lòng nhập hoặc chọn đoạn văn bản cần chỉnh sửa trước.', 'error');
    return;
  }

  const mode = (explicitMode || (toneEl ? toneEl.value : 'improve') || 'improve');
  const modeLabel = AI_MODE_LABELS[mode] || mode;

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = `${AI_SVG_ICONS.spinner}Đang biên tập (${aiEscapeHtml(modeLabel)})...`;
  }

  setAiStatus(`AI đang xử lý chế độ <strong>${aiEscapeHtml(modeLabel)}</strong>, vui lòng chờ trong giây lát...`, 'loading');

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 25000);

  try {
    let res;
    if (typeof apiRequest === 'function') {
      res = await apiRequest('api/ai/improve', {
        method: 'POST',
        body: { content, mode, tone: mode },
        signal: controller.signal,
      });
    } else {
      const base = (typeof APP_URL === 'string' && APP_URL) ? APP_URL.replace(/\/+$/, '') : '';
      const csrf = getAiCsrfToken();
      const endpoint = base ? `${base}/api/ai/improve` : '/api/ai/improve';
      const resp = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        },
        body: JSON.stringify({ content, mode, tone: mode }),
        signal: controller.signal,
      });
      res = await resp.json();
      if (!resp.ok) {
        throw new Error(res?.error || `HTTP ${resp.status}`);
      }
    }

    const improvedText = String(res.improved || res.rewritten || '').trim();
    if (!improvedText) {
      throw new Error('Không nhận được nội dung biên tập từ máy chủ.');
    }

    if (resultEl) {
      resultEl.value = improvedText;
    }

    const modelSuffix = res.model ? ` (${aiEscapeHtml(res.model)})` : '';
    const engineBadge = res.engine === 'gemini'
      ? `Xử lý qua <strong>Gemini AI${modelSuffix}</strong>`
      : 'Xử lý qua <strong>Trợ lý AI Tiếng Việt nội bộ (Offline NLP)</strong>';
    const noticeHtml = res.notice ? `<div style="margin-top:4px;font-size:12px;opacity:0.9;">${aiEscapeHtml(res.notice)}</div>` : '';

    setAiStatus(
      `Hoàn tất chế độ <strong>${aiEscapeHtml(modeLabel)}</strong> (${engineBadge}). Xem trước kết quả bên dưới, nhấn <strong>Áp dụng</strong> để thay thế hoặc <strong>Hủy</strong> để giữ nguyên bản gốc.${noticeHtml}`,
      'success'
    );
  } catch (error) {
    const errMsg = error?.name === 'AbortError'
      ? 'Yêu cầu AI quá thời gian chờ (timeout).'
      : (error?.message || 'Lỗi kết nối dịch vụ AI.');
    setAiStatus(
      `Không thể xử lý yêu cầu AI: ${aiEscapeHtml(errMsg)} Nội dung gốc của bạn vẫn được giữ nguyên vẹn.`,
      'error'
    );
  } finally {
    clearTimeout(timeoutId);
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = `${AI_SVG_ICONS.sparkles} Biên tập bằng AI`;
    }
  }
}

/**
 * Chấp nhận kết quả AI và thay thế vào trình soạn thảo (ưu tiên đúng đoạn đã bôi đen)
 */
function applyAiSuggestion() {
  const resultEl = document.getElementById('ai-writer-result');
  const suggestion = resultEl ? resultEl.value.trim() : '';

  if (!suggestion) {
    setAiStatus('Chưa có kết quả AI để áp dụng. Hãy bấm "Biên tập bằng AI" trước.', 'warning');
    return;
  }

  const targetEditor = (aiSelectionState.editor && document.body.contains(aiSelectionState.editor))
    ? aiSelectionState.editor
    : getActivePostEditor();

  if (targetEditor) {
    if (aiSelectionState.isSelection && aiSelectionState.end > aiSelectionState.start) {
      const currentVal = targetEditor.value;
      const before = currentVal.slice(0, aiSelectionState.start);
      const after = currentVal.slice(aiSelectionState.end);
      targetEditor.value = before + suggestion + after;
      const newEnd = before.length + suggestion.length;
      try { targetEditor.setSelectionRange(before.length, newEnd); } catch (_) {}
    } else {
      targetEditor.value = suggestion;
    }

    targetEditor.dispatchEvent(new Event('input', { bubbles: true }));
    targetEditor.dispatchEvent(new Event('change', { bubbles: true }));

    targetEditor.focus();
    targetEditor.style.transition = 'box-shadow 0.3s ease';
    targetEditor.style.boxShadow = '0 0 0 3px rgba(62, 102, 82, 0.28)';
    setTimeout(() => { targetEditor.style.boxShadow = ''; }, 1200);
  }

  const msg = aiSelectionState.isSelection
    ? 'Đã áp dụng kết quả AI vào đúng đoạn văn bản đã chọn'
    : 'Đã áp dụng nội dung biên tập từ AI vào bài viết';

  closeAIModal('ai-writer-modal');
  showAiToast(msg);
}

/**
 * Sao chép kết quả AI vào Clipboard
 */
async function copyAiSuggestion() {
  const resultEl = document.getElementById('ai-writer-result');
  const text = resultEl ? resultEl.value.trim() : '';
  if (!text) {
    setAiStatus('Chưa có kết quả AI để sao chép.', 'warning');
    return;
  }
  try {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(text);
    } else {
      resultEl.select();
      document.execCommand('copy');
    }
    showAiToast('Đã sao chép kết quả AI vào bộ nhớ tạm');
  } catch (_) {
    resultEl.select();
    document.execCommand('copy');
    showAiToast('Đã sao chép kết quả AI');
  }
}

/**
 * Hủy kết quả AI và giữ nguyên 100% nội dung ban đầu
 */
function cancelAiSuggestion() {
  const resultEl = document.getElementById('ai-writer-result');
  const statusEl = document.getElementById('ai-writer-status');
  if (resultEl) resultEl.value = '';
  if (statusEl) statusEl.style.display = 'none';
  closeAIModal('ai-writer-modal');
  showAiToast('Đã hủy kết quả AI — giữ nguyên văn bản gốc');
}

/**
 * Mở AI Editor Modal: luôn đồng bộ đúng đoạn văn bản đang bôi đen hoặc toàn bộ nội dung từ ô soạn thảo
 */
function openAiWriterModal(explicitEditorId = null) {
  const editor = getActivePostEditor(explicitEditorId);
  lastActiveEditor = editor;

  const inputEl = document.getElementById('ai-writer-original');
  const resultEl = document.getElementById('ai-writer-result');
  const statusEl = document.getElementById('ai-writer-status');
  if (statusEl) statusEl.style.display = 'none';
  if (resultEl) resultEl.value = '';

  aiSelectionState = {
    editor: editor,
    start: 0,
    end: 0,
    isSelection: false,
    originalFullText: editor ? String(editor.value || '') : '',
  };

  if (editor && inputEl) {
    const liveStart = Number(editor.selectionStart ?? 0);
    const liveEnd = Number(editor.selectionEnd ?? 0);
    const saved = trackedSelections.get(editor);
    const selStart = (liveEnd > liveStart) ? liveStart : (saved && saved.end > saved.start ? saved.start : 0);
    const selEnd = (liveEnd > liveStart) ? liveEnd : (saved && saved.end > saved.start ? saved.end : 0);

    if (selEnd > selStart && selEnd <= editor.value.length) {
      const selectedSegment = editor.value.slice(selStart, selEnd);
      if (selectedSegment.trim() !== '') {
        aiSelectionState.start = selStart;
        aiSelectionState.end = selEnd;
        aiSelectionState.isSelection = true;
        inputEl.value = selectedSegment;
      } else {
        inputEl.value = editor.value;
      }
    } else {
      inputEl.value = editor.value;
    }
  }

  updateAiScopeBadge();
  const toneEl = document.getElementById('ai-writer-tone');
  selectAiMode(toneEl ? toneEl.value : 'improve');
  openAIModal('ai-writer-modal');
}

function initAiModalListeners() {
  const modal = document.getElementById('ai-writer-modal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        closeAIModal('ai-writer-modal');
      }
    });
  }

  const toneEl = document.getElementById('ai-writer-tone');
  if (toneEl) {
    toneEl.addEventListener('change', () => selectAiMode(toneEl.value));
  }

  // Ghi nhận vùng bôi đen trên các trình soạn thảo bài viết trước khi nhấn nút mở AI
  ['post-editor-input', 'edit-post-content', 'profile-edit-post-content'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    ['select', 'mouseup', 'keyup'].forEach(ev => {
      el.addEventListener(ev, () => recordEditorSelection(el));
    });
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' || e.key === 'Esc') {
      const m = document.getElementById('ai-writer-modal');
      if (m && (m.style.display === 'flex' || m.classList.contains('active') || m.classList.contains('open'))) {
        closeAIModal('ai-writer-modal');
      }
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAiModalListeners);
} else {
  initAiModalListeners();
}

window.requestAiImprovement = requestAiImprovement;
window.applyAiSuggestion = applyAiSuggestion;
window.copyAiSuggestion = copyAiSuggestion;
window.cancelAiSuggestion = cancelAiSuggestion;
window.selectAiMode = selectAiMode;
window.openAiWriterModal = openAiWriterModal;
window.openAIModal = openAIModal;
window.closeAIModal = closeAIModal;
window.showAiToast = showAiToast;
