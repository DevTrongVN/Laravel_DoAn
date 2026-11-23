/**
 * Quản lý AJAX Phân trang & Cuộn vô tận (Infinite Scroll) cho Bảng tin chính.
 */
let currentFeedPage = 1;
let isLoadingMoreFeed = false;
let hasMoreFeed = true;

async function loadMoreFeedPosts() {
  if (isLoadingMoreFeed || !hasMoreFeed) return;
  isLoadingMoreFeed = true;

  const feedContainer = document.getElementById('feed-container');
  if (!feedContainer) return;

  const loadingIndicator = document.getElementById('feed-loading-indicator');
  if (loadingIndicator) loadingIndicator.style.display = 'block';

  try {
    const nextPage = currentFeedPage + 1;
    const res = await apiRequest('api/feed', { params: { page: nextPage } });
    const newPosts = res.data || [];

    if (newPosts.length === 0) {
      hasMoreFeed = false;
    } else {
      currentFeedPage = nextPage;
      if (res.pagination && res.pagination.has_more === false) hasMoreFeed = false;
      newPosts.forEach(post => {
        const postEl = createPostElement(post);
        feedContainer.appendChild(postEl);
      });
    }
  } catch (error) {
    console.error('Lỗi khi tải thêm bài viết:', error);
  } finally {
    isLoadingMoreFeed = false;
    if (loadingIndicator) loadingIndicator.style.display = 'none';
  }
}

// Lắng nghe sự kiện cuộn trang để kích hoạt Infinite Scroll
window.addEventListener('scroll', () => {
  if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500) {
    loadMoreFeedPosts();
  }
});
