@php
  $newsList = $topNews ?? [];
  $totalNews = count($newsList);
  $totalPages = max(1, (int)ceil($totalNews / 3));
@endphp
<div class="sidebar-card widget-top-news" id="widget-top-news-container">
  <div class="sidebar-title">
    <span class="section-kicker">Đang được quan tâm</span>
    <div class="widget-header-row">
      <h3>Tin tức nổi bật</h3>
      <div class="widget-pagination-controls" style="display:flex; align-items:center; gap:6px;">
        <span id="widget-page-info" style="font-size:11px; color:var(--muted, #7c827d); margin-right:4px;">Trang 1/{{ $totalPages }}</span>
        <button type="button" class="widget-page-btn" id="widget-prev-btn" onclick="paginateWidgetNews(-1)" title="Trang trước" disabled style="opacity:0.4; cursor:not-allowed;">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
        <button type="button" class="widget-page-btn" id="widget-next-btn" onclick="paginateWidgetNews(1)" title="Trang sau" @if($totalPages <= 1) disabled style="opacity:0.4; cursor:not-allowed;" @endif>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>
      </div>
    </div>
  </div>

  <div class="widget-news-list" id="widget-top-news-list">
    @if(!empty($newsList))
      @foreach(array_slice($newsList, 0, 3) as $idx => $news)
        @php
          $authorName = (string)($news['full_name'] ?? $news['username'] ?? 'Tác giả');
          $viewsFormatted = number_format((int)($news['views_count'] ?? $news['views'] ?? 0), 0, ',', '.');
        @endphp
        <a href="{{ url('/post/' . (int)$news['id']) }}" class="widget-news-item" onclick="if(typeof handleWidgetNewsClick === 'function'){return handleWidgetNewsClick(event, {{ (int)$news['id'] }});}">
          <span class="widget-news-rank">{{ sprintf('%02d', $idx + 1) }}</span>
          <div class="widget-news-copy">
            <strong class="widget-news-title">{{ $news['title'] ?? '' }}</strong>
            <span class="widget-news-meta" style="font-size:11px; color:var(--muted,#7c827d);">
              <span style="font-weight:600; color:var(--green,#3e6652);">{{ $authorName }}</span> · {{ $viewsFormatted }} lượt xem
            </span>
          </div>
        </a>
      @endforeach
    @else
      <p class="text-muted text-sm" style="padding:10px 0; font-size:12px; color:#888;">Đang tải tin tức nổi bật...</p>
    @endif
  </div>
</div>
