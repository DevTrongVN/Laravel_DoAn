const personalDashboardBase = new URL('.', window.location.href).pathname.replace(/\/$/, '');
const dashboardEscape = value => String(value ?? '').replace(/[&<>"']/g, char => ({
  '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'
}[char]));

let currentDashboardSort = 'latest';

async function personalDashboardApi(route) {
  const url = new URL(`${personalDashboardBase}/index.php`, window.location.origin);
  url.searchParams.set('url', route);
  const response = await fetch(url, {credentials:'same-origin', headers:{'Accept':'application/json'}, cache:'no-store'});
  if (response.status === 401) throw new Error('AUTH_REQUIRED');
  const text = await response.text();
  let result;
  try { result = JSON.parse(text); }
  catch (_) { throw new Error(`API returned HTTP ${response.status}, not JSON: ${text.slice(0, 120)}`); }
  if (!response.ok) throw new Error(result.error || `Request failed (HTTP ${response.status}).`);
  return result;
}

function formatDashboardDate(value) {
  if (!value) return '';
  const date = new Date(String(value).replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('vi-VN', {dateStyle:'medium', timeStyle:'short'});
}

function showDashboardMessage(message, showLogin = false) {
  const box = document.getElementById('dashboard-message');
  box.innerHTML = `${dashboardEscape(message)}${showLogin ? ' <a href="account.html">Đăng nhập</a> để xem dashboard.' : ''}`;
  box.hidden = false;
}

function renderDashboardStats(summary) {
  const stats = [
    ['Bài viết', summary.posts],
    ['Lượt xem', summary.views],
    ['Lượt thích', summary.likes],
    ['Bình luận', summary.comments],
    ['Chia sẻ', summary.shares],
    ['Lượt lưu', summary.bookmarks || 0]
  ];
  document.getElementById('dashboard-stats').innerHTML = stats.map(([label, value]) =>
    `<article class="stat-card"><span class="stat-label">${label}</span><strong class="stat-value">${Number(value || 0).toLocaleString('vi-VN')}</strong></article>`
  ).join('');
}

function renderOwnPosts(posts) {
  document.getElementById('post-count').textContent = `${posts.length} bài`;
  const container = document.getElementById('my-posts');
  if (!posts.length) {
    container.innerHTML = '<div class="empty-state">Bạn chưa có bài viết nào trong danh mục này.</div>';
    return;
  }
  container.innerHTML = posts.map(post => {
    const summary = post.summary || String(post.content || '').slice(0, 260);
    const status = post.moderation_status || 'safe';
    return `<article class="post-item">
      <div class="post-topline">
        <time class="post-date">${dashboardEscape(formatDashboardDate(post.created_at))}</time>
        <span class="post-status ${dashboardEscape(status)}">${dashboardEscape(status)}</span>
      </div>
      <h3 class="post-title">${dashboardEscape(post.title)}</h3>
      ${post.thumbnail ? `<img class="post-image" src="${dashboardEscape(post.thumbnail)}" alt="Ảnh bài viết">` : ''}
      <p class="post-summary">${dashboardEscape(summary)}</p>
      <div class="post-metrics">
        <span class="metric"><i class="fa-solid fa-heart" style="color:#e11d48;"></i> ${Number(post.likes_count || 0)} lượt thích</span>
        <span class="metric">▤ ${Number(post.comments_count || 0)} bình luận</span>
        <span class="metric">↗ ${Number(post.shares_count || 0)} chia sẻ</span>
        <span class="metric"><i class="fa-solid fa-bookmark" style="color:#0d9488;"></i> ${Number(post.bookmarks_count || 0)} lượt lưu</span>
        <span class="metric">◉ ${Number(post.views_count || 0)} lượt xem</span>
        <span class="metric post-type">${post.post_type === 'news' ? 'Tin tức' : 'Blog'}</span>
      </div>
    </article>`;
  }).join('');
}

function renderActivity(activity) {
  const container = document.getElementById('my-activity');
  if (!activity.length) {
    container.innerHTML = '<div class="empty-state">Chưa có lượt thích, bình luận hoặc chia sẻ từ người khác.</div>';
    return;
  }
  const descriptions = {
    like: 'đã thích bài viết', share: 'đã chia sẻ bài viết', comment: 'đã bình luận về bài viết'
  };
  container.innerHTML = activity.map(item => {
    const type = ['like','share','comment'].includes(item.type) ? item.type : 'like';
    const actorName = item.actor_name || item.actor_username || 'Người dùng';
    const avatar = item.actor_avatar || 'assets/images/avatars/user-admin.svg';
    return `<article class="activity-item">
      <img class="activity-avatar" src="${dashboardEscape(avatar)}" alt="">
      <div class="activity-copy"><strong>${dashboardEscape(actorName)}</strong> ${descriptions[type]} <a href="index.html#feed">${dashboardEscape(item.post_title)}</a>
        ${type === 'comment' && item.content ? `<span class="activity-content">${dashboardEscape(item.content)}</span>` : ''}
        <time class="activity-date">${dashboardEscape(formatDashboardDate(item.created_at))}</time>
      </div>
    </article>`;
  }).join('');
}

async function changeDashboardSort(sortValue) {
  currentDashboardSort = sortValue;
  await loadPersonalDashboard(sortValue);
}
window.changeDashboardSort = changeDashboardSort;

async function loadPersonalDashboard(sort = 'latest') {
  const owner = document.getElementById('dashboard-owner');
  try {
    const data = await personalDashboardApi(`api/dashboard/me?sort=${encodeURIComponent(sort)}`);
    owner.textContent = `Xin chào, ${data.user.full_name} (@${data.user.username})`;
    document.getElementById('dashboard-content').hidden = false;
    renderDashboardStats(data.summary || {});
    renderOwnPosts(data.posts || []);
    renderActivity(data.activity || []);

    const sortSelect = document.getElementById('dashboard-sort-select');
    if (sortSelect && sortSelect.value !== sort) {
      sortSelect.value = sort;
    }
  } catch (error) {
    owner.textContent = '';
    showDashboardMessage(error.message === 'AUTH_REQUIRED' ? 'Vui lòng đăng nhập để xem dashboard.' : error.message, error.message === 'AUTH_REQUIRED');
  }
}

loadPersonalDashboard();
