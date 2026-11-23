@extends('layouts.app')

@section('title', $pageTitle ?? 'Trung tâm thông báo — Góc Nhỏ')

@section('content')
  <div class="post-card" style="margin-bottom: 16px; display:flex; justify-content:space-between; align-items:center;">
    <div>
      <h2 style="margin:0 0 4px;">Trung tâm thông báo</h2>
      <p class="text-muted" style="margin:0; font-size:13.5px;">Bạn có <strong>{{ (int)($unread_count ?? 0) }}</strong> thông báo chưa đọc.</p>
    </div>
    <button type="button" class="action-btn action-btn-primary" onclick="markAllNotificationsPageRead()">Đánh dấu tất cả đã đọc</button>
  </div>

  <div class="post-card">
    @forelse($notifications as $n)
      <div style="display:flex; align-items:flex-start; gap:12px; padding:12px 0; border-bottom:1px solid #f1f5f9; {{ empty($n['is_read']) ? 'background:#f0fdfa; padding:12px; border-radius:8px; margin-bottom:6px;' : '' }}">
        <img src="{{ \App\Support\UnicodeHelper::avatarUrl($n['actor_avatar'] ?? '/assets/images/avatars/user-admin.svg') }}" class="avatar avatar-sm" alt="">
        <div style="flex:1;">
          <p style="margin:0 0 4px; font-size:14px;">{{ $n['content'] }}</p>
          <small class="text-muted">{{ $n['created_at'] ?? '' }}</small>
          @if(!empty($n['target_url']))
            <a href="{{ url($n['target_url']) }}" style="margin-left:10px; font-size:12.5px; color:#0d9488; font-weight:600;">Xem chi tiết &rarr;</a>
          @endif
        </div>
      </div>
    @empty
      <p class="text-muted" style="text-align:center; padding:24px 0;">Chưa có thông báo nào.</p>
    @endforelse
  </div>

  <script>
    async function markAllNotificationsPageRead() {
      try {
        await apiRequest('api/notifications/read-all', { method: 'POST', body: {} });
        window.location.reload();
      } catch (e) {
        alert(e.message || 'Không thể đánh dấu đã đọc.');
      }
    }
  </script>
@endsection
