/**
 * Phân trang AJAX và tích hợp API thực tế cho Widget Top Tin HOT
 * Dự án: Góc Nhỏ (Tin tức & Blog cá nhân)
 */
(function (root) {
  'use strict';

  let widgetNewsList = [];
  let currentWidgetPage = 1;
  const newsPerPage = 3;
  let isWidgetLoading = false;

  function escapeWidgetHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getBaseUrl() {
    if (typeof window.APP_URL !== 'undefined' && window.APP_URL) {
      return String(window.APP_URL).replace(/\/$/, '');
    }
    if (typeof APP_URL !== 'undefined' && APP_URL) {
      return String(APP_URL).replace(/\/$/, '');
    }
    return new URL('.', window.location.href).pathname.replace(/\/$/, '');
  }

  function getPostDetailUrl(postId) {
    const base = getBaseUrl();
    return `${base}/post/${postId}`;
  }

  async function fetchTopNewsApi() {
    // Ưu tiên sử dụng apiRequest nếu app.js đã tải
    if (typeof apiRequest === 'function') {
      const res = await apiRequest('api/widgets/news', { params: { limit: 15, _t: Date.now() } });
      return res && Array.isArray(res.data) ? res.data : [];
    }

    // Fallback qua fetch thông thường
    const base = getBaseUrl();
    const endpoint = new URL(`${base}/index.php`, window.location.origin);
    endpoint.searchParams.set('url', 'api/widgets/news');
    endpoint.searchParams.set('limit', '15');
    endpoint.searchParams.set('_t', String(Date.now()));

    const response = await fetch(endpoint, {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    });

    if (!response.ok) {
      throw new Error(`HTTP error ${response.status}`);
    }

    const json = await response.json();
    return json && Array.isArray(json.data) ? json.data : [];
  }

  function updatePaginationControls(page, totalPages) {
    const pageInfo = document.getElementById("widget-page-info");
    const prevBtn = document.getElementById("widget-prev-btn");
    const nextBtn = document.getElementById("widget-next-btn");

    if (pageInfo) {
      pageInfo.innerText = totalPages === 0 ? "Trang 0/0" : `Trang ${page}/${totalPages}`;
    }

    if (prevBtn) {
      const disabled = page <= 1 || totalPages <= 1;
      prevBtn.disabled = disabled;
      prevBtn.style.opacity = disabled ? "0.4" : "1";
      prevBtn.style.cursor = disabled ? "not-allowed" : "pointer";
    }

    if (nextBtn) {
      const disabled = page >= totalPages || totalPages <= 1;
      nextBtn.disabled = disabled;
      nextBtn.style.opacity = disabled ? "0.4" : "1";
      nextBtn.style.cursor = disabled ? "not-allowed" : "pointer";
    }
  }

  function renderWidgetPage(page) {
    const container = document.getElementById("widget-top-news-list");
    if (!container) return;

    const totalPages = Math.max(1, Math.ceil(widgetNewsList.length / newsPerPage));
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    currentWidgetPage = page;

    if (widgetNewsList.length === 0) {
      updatePaginationControls(0, 0);
      container.innerHTML = '<div class="widget-news-empty" style="padding:16px 0; font-size:12px; color:var(--muted,#7c827d); text-align:center;">Chưa có bài viết tin tức nào.</div>';
      return;
    }

    updatePaginationControls(page, totalPages);

    const start = (page - 1) * newsPerPage;
    const paginated = widgetNewsList.slice(start, start + newsPerPage);

    container.innerHTML = paginated.map((post, idx) => {
      const rank = String(start + idx + 1).padStart(2, '0');
      const authorName = (post.user && post.user.full_name) || post.full_name || post.username || 'Tác giả';
      const views = Number(post.views_count || 0).toLocaleString('vi-VN');
      const title = escapeWidgetHtml(post.title || 'Không có tiêu đề');
      const postUrl = getPostDetailUrl(post.id);

      return `
        <a href="${postUrl}" class="widget-news-item" onclick="handleWidgetNewsClick(event, ${post.id})">
          <span class="widget-news-rank">${rank}</span>
          <div class="widget-news-copy">
            <strong class="widget-news-title">${title}</strong>
            <span class="widget-news-meta" style="font-size:11px; color:var(--muted,#7c827d);">
              <span style="font-weight:600; color:var(--green,#3e6652);">${escapeWidgetHtml(authorName)}</span> · <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1.5px;margin-right:2px;display:inline-block;" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>${views} lượt xem
            </span>
          </div>
        </a>
      `;
    }).join('');
  }

  async function loadTopNewsWidget(force = false) {
    const container = document.getElementById("widget-top-news-list");
    if (!container) return;

    if (widgetNewsList.length > 0 && !force) {
      renderWidgetPage(currentWidgetPage);
      return;
    }

    if (isWidgetLoading) return;
    isWidgetLoading = true;

    try {
      container.innerHTML = `
        <div class="widget-news-loading" style="padding:16px 0; font-size:12px; color:var(--muted,#7c827d); text-align:center;">
          <i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i>Đang tải tin tức nổi bật...
        </div>
      `;
      const data = await fetchTopNewsApi();
      widgetNewsList = Array.isArray(data) ? data : [];
      currentWidgetPage = 1;
      renderWidgetPage(currentWidgetPage);
    } catch (error) {
      console.warn("[Widget Top News] Không thể tải dữ liệu từ API:", error);
      container.innerHTML = `
        <div class="widget-news-error" style="padding:16px 0; font-size:12px; color:#c53939; text-align:center;">
          Không thể tải tin tức nổi bật lúc này.
          <button type="button" class="widget-retry-btn" onclick="loadTopNewsWidget(true)" style="background:none; border:none; color:var(--green,#3e6652); text-decoration:underline; cursor:pointer; font-size:12px; font-weight:600; padding:0; margin-left:4px;">Thử lại</button>
        </div>
      `;
      updatePaginationControls(1, 1);
    } finally {
      isWidgetLoading = false;
    }
  }

  function paginateWidgetNews(direction) {
    if (widgetNewsList.length === 0 || isWidgetLoading) return;
    const totalPages = Math.max(1, Math.ceil(widgetNewsList.length / newsPerPage));
    const targetPage = currentWidgetPage + direction;
    if (targetPage >= 1 && targetPage <= totalPages) {
      renderWidgetPage(targetPage);
    }
  }

  function handleWidgetNewsClick(event, postId) {
    // Nếu trang có modal và hàm openPostModal thì mở modal
    const modalContent = document.getElementById("modal-body-content");
    if (modalContent && typeof root.openPostModal === 'function') {
      if (event && event.preventDefault) event.preventDefault();
      root.openPostModal(postId);
      return false;
    }
    // Nếu không có modal (ví dụ trên trang chi tiết /post/:id hoặc trang /news), cho phép trình duyệt điều hướng tự nhiên theo href
    return true;
  }

  // Đăng ký các hàm toàn cục
  root.loadTopNewsWidget = loadTopNewsWidget;
  root.renderWidgetPage = renderWidgetPage;
  root.paginateWidgetNews = paginateWidgetNews;
  root.handleWidgetNewsClick = handleWidgetNewsClick;

  // Tự động khởi chạy khi DOM sẵn sàng
  if (document.readyState === 'loading') {
    document.addEventListener("DOMContentLoaded", () => {
      loadTopNewsWidget();
    });
  } else {
    loadTopNewsWidget();
  }

})(typeof window !== 'undefined' ? window : this);
