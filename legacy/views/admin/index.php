<?php
declare(strict_types=1);
/**
 * Giao diện Quản trị Hệ thống Toàn diện (Admin Dashboard)
 * 6 Phân hệ: Người dùng & Tích xanh, Bài viết & Bình luận, Báo cáo bài viết,
 * Báo cáo bình luận, Đề xuất Tin tức (Ticket & AI), Kiểm duyệt nội dung AI.
 * Bảo vệ 100% Server-side bằng Session & Auth::isAdmin().
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bảng Điều Khiển Quản Trị Hệ Thống - Góc Nhỏ MXH</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/components.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
</head>
<body class="body-admin">

  <!-- Toast Notification Container -->
  <div id="admin-toast-container" class="admin-toast-container" aria-live="polite"></div>

  <div class="admin-container">
    <!-- Header -->
    <header class="admin-header">
      <div class="admin-header-title">
        <h2><i class="fa-solid fa-gears" style="color: #0284c7;"></i> Bảng Quản Trị Hệ Thống (Admin Control Panel)</h2>
        <div id="admin-user-info" class="admin-user-info">
          <i class="fa-solid fa-spinner fa-spin"></i> Đang xác thực phiên quản trị...
        </div>
      </div>
      <div class="admin-header-actions">
        <a href="<?= APP_URL ?>/" class="action-btn btn-back-mxh"><i class="fa-solid fa-house"></i> Quay lại MXH</a>
        <a href="<?= APP_URL ?>/accounts.html" class="action-btn btn-secondary"><i class="fa-solid fa-users-viewfinder"></i> Chuyển tài khoản</a>
        <button id="admin-logout-btn" class="action-btn btn-danger"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</button>
      </div>
    </header>

    <!-- Thống kê nhanh hệ thống (Quick Stats Bar) -->
    <section class="admin-stats-grid" id="admin-stats-grid">
      <div class="stat-card">
        <div class="stat-icon stat-users"><i class="fa-solid fa-users"></i></div>
        <div class="stat-meta">
          <div class="stat-val" id="stat-total-users">-</div>
          <div class="stat-lbl">Thành viên</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-posts"><i class="fa-solid fa-newspaper"></i></div>
        <div class="stat-meta">
          <div class="stat-val" id="stat-total-posts">-</div>
          <div class="stat-lbl">Bài viết</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-tickets"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <div class="stat-meta">
          <div class="stat-val" id="stat-pending-tickets">-</div>
          <div class="stat-lbl">Đề xuất Tin tức chờ duyệt</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-reports"><i class="fa-solid fa-flag"></i></div>
        <div class="stat-meta">
          <div class="stat-val" id="stat-pending-reports">-</div>
          <div class="stat-lbl">Báo cáo vi phạm chờ duyệt</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-moderation"><i class="fa-solid fa-robot"></i></div>
        <div class="stat-meta">
          <div class="stat-val" id="stat-flagged-posts">-</div>
          <div class="stat-lbl">Nội dung AI cần rà soát</div>
        </div>
      </div>
    </section>

    <!-- Hệ thống Tab Navigation 6 Phân Hệ -->
    <nav class="admin-tab-nav" role="tablist">
      <button class="admin-tab-btn active" data-tab="users" role="tab" aria-selected="true">
        <i class="fa-solid fa-user-shield"></i>
        <span>1. Người dùng & Tích xanh</span>
      </button>
      <button class="admin-tab-btn" data-tab="posts-comments" role="tab" aria-selected="false">
        <i class="fa-solid fa-newspaper"></i>
        <span>2. Quản lý Bài viết & Bình luận</span>
      </button>
      <button class="admin-tab-btn" data-tab="post-reports" role="tab" aria-selected="false">
        <i class="fa-solid fa-flag"></i>
        <span>3. Báo cáo Bài viết & Khiếu nại</span>
        <span class="tab-badge" id="tab-badge-post-reports" style="display:none;">0</span>
      </button>
      <button class="admin-tab-btn" data-tab="comment-reports" role="tab" aria-selected="false">
        <i class="fa-solid fa-comment-slash"></i>
        <span>4. Báo cáo Bình luận</span>
        <span class="tab-badge" id="tab-badge-comment-reports" style="display:none;">0</span>
      </button>
      <button class="admin-tab-btn" data-tab="news-suggestions" role="tab" aria-selected="false">
        <i class="fa-solid fa-wand-magic-sparkles"></i>
        <span>5. Đề xuất Tin tức (Ticket & AI)</span>
        <span class="tab-badge" id="tab-badge-news" style="display:none;">0</span>
      </button>
      <button class="admin-tab-btn" data-tab="ai-moderation" role="tab" aria-selected="false">
        <i class="fa-solid fa-robot"></i>
        <span>6. Kiểm duyệt nội dung AI</span>
        <span class="tab-badge" id="tab-badge-ai" style="display:none;">0</span>
      </button>
    </nav>

    <!-- ============================================================ -->
    <!-- TAB 1: NGƯỜI DÙNG & TÍCH XANH                                 -->
    <!-- ============================================================ -->
    <section id="tab-pane-users" class="admin-tab-pane active" role="tabpanel">
      <div class="admin-card">
        <div class="admin-card-header">
          <h3><i class="fa-solid fa-user-check"></i> Quản Lý Người Dùng, Phân Quyền & Cấp/Thu Hồi Tích Xanh</h3>
          <span class="card-subtitle">Xét duyệt tích xanh dựa trên tiêu chí uy tín (≥100 pts) và lưu lịch sử kiểm toán.</span>
        </div>

        <!-- Bộ lọc Server-side -->
        <div class="admin-filter-bar">
          <div class="filter-group filter-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="users-search" placeholder="Tìm theo tên, @username, email...">
          </div>
          <div class="filter-group">
            <select id="users-filter-verified">
              <option value="">Tất cả tích xanh</option>
              <option value="1">Đã cấp tích xanh</option>
              <option value="0">Chưa có tích xanh</option>
            </select>
          </div>
          <div class="filter-group">
            <select id="users-filter-role">
              <option value="">Tất cả vai trò</option>
              <option value="user">Người dùng (User)</option>
              <option value="admin">Quản trị viên (Admin)</option>
            </select>
          </div>
          <div class="filter-group">
            <select id="users-filter-eligible">
              <option value="">Tất cả điều kiện</option>
              <option value="1">⭐ Đủ điều kiện (≥100 pts)</option>
              <option value="0">Chưa đủ điều kiện</option>
            </select>
          </div>
          <div class="filter-group">
            <select id="users-filter-sort">
              <option value="reputation_desc">Điểm uy tín (Cao → Thấp)</option>
              <option value="reputation_asc">Điểm uy tín (Thấp → Cao)</option>
              <option value="newest">Ngày tham gia (Mới nhất)</option>
              <option value="oldest">Ngày tham gia (Cũ nhất)</option>
              <option value="name_asc">Tên (A → Z)</option>
            </select>
          </div>
          <button type="button" class="btn-reset-filters" id="users-btn-reset"><i class="fa-solid fa-rotate-left"></i> Đặt lại</button>
        </div>

        <!-- Bảng dữ liệu -->
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 60px;">ID</th>
                <th>Thành viên</th>
                <th>Username</th>
                <th>Vai trò</th>
                <th>Điểm uy tín</th>
                <th>Điều kiện Tích xanh</th>
                <th>Trạng thái Tích xanh</th>
                <th>Thao tác Tích xanh</th>
                <th>Thao tác Phân quyền</th>
                <th>Lịch sử</th>
              </tr>
            </thead>
            <tbody id="admin-users-tbody">
              <tr><td colspan="10" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải dữ liệu...</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Phân trang -->
        <div class="admin-pagination-bar" id="users-pagination"></div>
      </div>
    </section>

    <!-- ============================================================ -->
    <!-- TAB 2: QUẢN LÝ BÀI VIẾT & BÌNH LUẬN                           -->
    <!-- ============================================================ -->
    <section id="tab-pane-posts-comments" class="admin-tab-pane" role="tabpanel">
      <!-- Sub-tabs chuyển đổi giữa Bài viết và Bình luận -->
      <div class="admin-subtab-bar">
        <button class="subtab-btn active" data-subtab="posts"><i class="fa-solid fa-file-lines"></i> Quản lý Bài viết</button>
        <button class="subtab-btn" data-subtab="comments"><i class="fa-solid fa-comments"></i> Quản lý Bình luận</button>
      </div>

      <!-- Phân hệ 2.1: Bài viết -->
      <div id="subtab-pane-posts" class="subtab-pane active">
        <div class="admin-card">
          <div class="admin-card-header">
            <h3><i class="fa-solid fa-file-pen"></i> Danh Sách Bài Viết Toàn Hệ Thống</h3>
            <span class="card-subtitle">Lọc theo thể loại, trạng thái an toàn, chuyển bài viết thành Tin tức hoặc xóa bài.</span>
          </div>

          <div class="admin-filter-bar">
            <div class="filter-group filter-search">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" id="posts-search" placeholder="Tìm theo tiêu đề, tác giả, nội dung...">
            </div>
            <div class="filter-group">
              <select id="posts-filter-category">
                <option value="">Tất cả thể loại</option>
                <option value="blog">Blog cá nhân</option>
                <option value="news">Tin tức</option>
              </select>
            </div>
            <div class="filter-group">
              <select id="posts-filter-status">
                <option value="">Tất cả trạng thái</option>
                <option value="safe">An toàn (Safe)</option>
                <option value="flagged">Gắn cờ (Flagged)</option>
                <option value="blocked">Đã khóa (Blocked)</option>
              </select>
            </div>
            <div class="filter-group">
              <select id="posts-filter-sort">
                <option value="newest">Mới nhất</option>
                <option value="views_desc">Lượt xem nhiều nhất</option>
                <option value="comments_desc">Nhiều bình luận nhất</option>
                <option value="oldest">Cũ nhất</option>
              </select>
            </div>
            <button type="button" class="btn-reset-filters" id="posts-btn-reset"><i class="fa-solid fa-rotate-left"></i> Đặt lại</button>
          </div>

          <div class="table-responsive">
            <table class="admin-table">
              <thead>
                <tr>
                  <th style="width:60px;">ID</th>
                  <th>Tiêu đề bài viết</th>
                  <th>Thể loại</th>
                  <th>Tác giả</th>
                  <th>Thống kê</th>
                  <th>Trạng thái</th>
                  <th>Thời gian</th>
                  <th style="width:230px;">Hành động quản trị</th>
                </tr>
              </thead>
              <tbody id="admin-posts-tbody">
                <tr><td colspan="8" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải danh sách bài viết...</td></tr>
              </tbody>
            </table>
          </div>

          <div class="admin-pagination-bar" id="posts-pagination"></div>
        </div>
      </div>

      <!-- Phân hệ 2.2: Bình luận -->
      <div id="subtab-pane-comments" class="subtab-pane">
        <div class="admin-card">
          <div class="admin-card-header">
            <h3><i class="fa-solid fa-comments"></i> Danh Sách Bình Luận Toàn Hệ Thống</h3>
            <span class="card-subtitle">Quản lý và kiểm duyệt bình luận, ẩn hoặc xóa các nội dung không phù hợp.</span>
          </div>

          <div class="admin-filter-bar">
            <div class="filter-group filter-search">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" id="comments-search" placeholder="Tìm theo nội dung bình luận, người đăng, bài viết...">
            </div>
            <div class="filter-group">
              <select id="comments-filter-status">
                <option value="">Tất cả trạng thái</option>
                <option value="safe">Hiển thị (Safe)</option>
                <option value="blocked">Đã ẩn/khóa (Blocked)</option>
              </select>
            </div>
            <div class="filter-group">
              <select id="comments-filter-sort">
                <option value="newest">Mới nhất</option>
                <option value="oldest">Cũ nhất</option>
              </select>
            </div>
            <button type="button" class="btn-reset-filters" id="comments-btn-reset"><i class="fa-solid fa-rotate-left"></i> Đặt lại</button>
          </div>

          <div class="table-responsive">
            <table class="admin-table">
              <thead>
                <tr>
                  <th style="width:60px;">ID</th>
                  <th>Nội dung bình luận</th>
                  <th>Người bình luận</th>
                  <th>Bài viết liên quan</th>
                  <th>Trạng thái</th>
                  <th>Thời gian</th>
                  <th style="width:160px;">Hành động</th>
                </tr>
              </thead>
              <tbody id="admin-comments-tbody">
                <tr><td colspan="7" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải bình luận...</td></tr>
              </tbody>
            </table>
          </div>

          <div class="admin-pagination-bar" id="comments-pagination"></div>
        </div>
      </div>
    </section>

    <!-- ============================================================ -->
    <!-- TAB 3: BÁO CÁO BÀI VIẾT & KHIẾU NẠI                           -->
    <!-- ============================================================ -->
    <section id="tab-pane-post-reports" class="admin-tab-pane" role="tabpanel">
      <!-- Sub-tabs chuyển đổi giữa Báo cáo người dùng và Khiếu nại mở khóa -->
      <div class="admin-subtab-bar">
        <button class="subtab-btn active" data-subtab="post-reports-list">
          <i class="fa-solid fa-flag"></i> Báo cáo từ người dùng
        </button>
        <button class="subtab-btn" data-subtab="post-appeals-list">
          <i class="fa-solid fa-envelope-open-text"></i> Khiếu nại mở khóa (Appeals)
          <span class="tab-badge" id="subtab-badge-appeals" style="display:none;">0</span>
        </button>
      </div>

      <!-- Phân hệ 3.1: Báo cáo bài viết từ người dùng -->
      <div id="subtab-pane-post-reports-list" class="subtab-pane active">
        <div class="admin-card">
          <div class="admin-card-header">
            <h3><i class="fa-solid fa-flag"></i> Quản Lý Báo Cáo Bài Viết Từ Người Dùng</h3>
            <span class="card-subtitle">Xét duyệt các bài viết bị người dùng báo cáo vi phạm, tự động thông báo ẩn danh cho tác giả.</span>
          </div>

          <div class="admin-filter-bar">
            <div class="filter-group filter-search">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" id="post-reports-search" placeholder="Tìm theo tiêu đề, tác giả, người báo cáo...">
            </div>
            <div class="filter-group">
              <select id="post-reports-filter-status">
                <option value="">Tất cả trạng thái</option>
                <option value="pending" selected>Chờ xử lý (Pending)</option>
                <option value="reviewed">Đã xử lý (Reviewed)</option>
                <option value="dismissed">Đã bỏ qua (Dismissed)</option>
              </select>
            </div>
            <div class="filter-group">
              <select id="post-reports-filter-reason">
                <option value="">Tất cả nhóm lý do</option>
                <option value="offensive">Nội dung xúc phạm / thù ghét</option>
                <option value="spam">Spam / Quảng cáo rác</option>
                <option value="inappropriate">Nội dung không phù hợp</option>
                <option value="misinformation">Thông tin sai lệch</option>
                <option value="harassment">Công kích / Quấy rối</option>
                <option value="impersonation">Giả mạo người khác</option>
                <option value="other">Lý do khác</option>
              </select>
            </div>
            <button type="button" class="btn-reset-filters" id="post-reports-btn-reset"><i class="fa-solid fa-rotate-left"></i> Đặt lại</button>
          </div>

          <div class="table-responsive">
            <table class="admin-table">
              <thead>
                <tr>
                  <th style="width:60px;">Mã</th>
                  <th>Bài viết bị báo cáo</th>
                  <th>Tác giả</th>
                  <th>Người báo cáo</th>
                  <th>Lý do & Chi tiết</th>
                  <th>Trạng thái báo cáo</th>
                  <th>Người & Ghi chú xử lý</th>
                  <th>Thời gian</th>
                  <th style="width:200px;">Thao tác xử lý</th>
                </tr>
              </thead>
              <tbody id="admin-post-reports-tbody">
                <tr><td colspan="9" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải danh sách báo cáo bài viết...</td></tr>
              </tbody>
            </table>
          </div>

          <div class="admin-pagination-bar" id="post-reports-pagination"></div>
        </div>
      </div>

      <!-- Phân hệ 3.2: Khiếu nại mở khóa bài viết (Post Appeals) -->
      <div id="subtab-pane-post-appeals-list" class="subtab-pane">
        <div class="admin-card">
          <div class="admin-card-header">
            <h3><i class="fa-solid fa-envelope-open-text"></i> Khiếu Nại Mở Khóa Bài Viết Từ Tác Giả</h3>
            <span class="card-subtitle">Xem xét giải trình của tác giả, chấp nhận khôi phục hiển thị hoặc từ chối kèm phản hồi.</span>
          </div>

          <div class="admin-filter-bar">
            <div class="filter-group filter-search">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" id="post-appeals-search" placeholder="Tìm theo tiêu đề, tác giả, lời giải trình...">
            </div>
            <div class="filter-group">
              <select id="post-appeals-filter-status">
                <option value="">Tất cả trạng thái</option>
                <option value="pending" selected>Chờ xem xét (Pending)</option>
                <option value="approved">Đã chấp nhận (Approved)</option>
                <option value="rejected">Đã từ chối (Rejected)</option>
              </select>
            </div>
            <button type="button" class="btn-reset-filters" id="post-appeals-btn-reset"><i class="fa-solid fa-rotate-left"></i> Đặt lại</button>
          </div>

          <div class="table-responsive">
            <table class="admin-table">
              <thead>
                <tr>
                  <th style="width:60px;">ID</th>
                  <th>Bài viết</th>
                  <th>Tác giả</th>
                  <th>Lý do khóa trước đó</th>
                  <th>Lời giải trình của tác giả</th>
                  <th>Trạng thái</th>
                  <th>Người & Phản hồi Admin</th>
                  <th>Thời gian gửi</th>
                  <th style="width:220px;">Thao tác</th>
                </tr>
              </thead>
              <tbody id="admin-post-appeals-tbody">
                <tr><td colspan="9" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải danh sách khiếu nại...</td></tr>
              </tbody>
            </table>
          </div>

          <div class="admin-pagination-bar" id="post-appeals-pagination"></div>
        </div>
      </div>
    </section>

    <!-- ============================================================ -->
    <!-- TAB 4: BÁO CÁO BÌNH LUẬN                                     -->
    <!-- ============================================================ -->
    <section id="tab-pane-comment-reports" class="admin-tab-pane" role="tabpanel">
      <div class="admin-card">
        <div class="admin-card-header">
          <h3><i class="fa-solid fa-comment-slash"></i> Quản Lý Báo Cáo Bình Luận Từ Người Dùng</h3>
          <span class="card-subtitle">Xét duyệt và xử lý bình luận vi phạm theo 5 nhóm lý do tiêu chuẩn, gửi thông báo ẩn danh.</span>
        </div>

        <div class="admin-filter-bar">
          <div class="filter-group filter-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="comment-reports-search" placeholder="Tìm theo nội dung bình luận, tác giả, người báo cáo...">
          </div>
          <div class="filter-group">
            <select id="comment-reports-filter-status">
              <option value="">Tất cả trạng thái</option>
              <option value="pending" selected>Chờ xử lý (Pending)</option>
              <option value="reviewed">Đã xử lý (Reviewed)</option>
              <option value="rejected">Đã từ chối (Rejected)</option>
            </select>
          </div>
          <div class="filter-group">
            <select id="comment-reports-filter-reason">
              <option value="">Tất cả nhóm lý do</option>
              <option value="offensive">Nội dung xúc phạm</option>
              <option value="spam">Spam / Quảng cáo</option>
              <option value="inappropriate">Nội dung không phù hợp</option>
              <option value="misinformation">Thông tin sai lệch</option>
              <option value="other">Lý do khác</option>
            </select>
          </div>
          <button type="button" class="btn-reset-filters" id="comment-reports-btn-reset"><i class="fa-solid fa-rotate-left"></i> Đặt lại</button>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width:60px;">Mã</th>
                <th>Nội dung bình luận</th>
                <th>Bài viết liên quan</th>
                <th>Tác giả bình luận</th>
                <th>Người báo cáo</th>
                <th>Lý do & Mô tả</th>
                <th>Trạng thái báo cáo</th>
                <th>Người & Ghi chú xử lý</th>
                <th>Thời gian</th>
                <th style="width:200px;">Thao tác xử lý</th>
              </tr>
            </thead>
            <tbody id="admin-comment-reports-tbody">
              <tr><td colspan="10" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải báo cáo bình luận...</td></tr>
            </tbody>
          </table>
        </div>

        <div class="admin-pagination-bar" id="comment-reports-pagination"></div>
      </div>
    </section>

    <!-- ============================================================ -->
    <!-- TAB 5: ĐỀ XUẤT TIN TỨC (TICKET & AI)                          -->
    <!-- ============================================================ -->
    <section id="tab-pane-news-suggestions" class="admin-tab-pane" role="tabpanel">
      <div class="admin-card">
        <div class="admin-card-header">
          <h3><i class="fa-solid fa-wand-magic-sparkles"></i> Đề Xuất Xét Duyệt Bài Viết → Tin Tức (Ticket & AI)</h3>
          <span class="card-subtitle">Hợp nhất 2 nguồn đề xuất: Ticket nâng cấp do người dùng tạo & Gợi ý bài viết tiềm năng từ AI.</span>
        </div>

        <div class="admin-filter-bar">
          <div class="filter-group filter-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="news-search" placeholder="Tìm theo tiêu đề bài viết, tác giả...">
          </div>
          <div class="filter-group">
            <select id="news-filter-source">
              <option value="all">Tất cả nguồn</option>
              <option value="ticket">Ticket từ tác giả</option>
              <option value="ai">AI gợi ý tiềm năng</option>
            </select>
          </div>
          <div class="filter-group">
            <select id="news-filter-status">
              <option value="">Tất cả trạng thái</option>
              <option value="pending" selected>Chờ xét duyệt (Pending)</option>
              <option value="approved">Đã duyệt Tin tức</option>
              <option value="rejected">Đã từ chối</option>
            </select>
          </div>
          <button type="button" class="btn-reset-filters" id="news-btn-reset"><i class="fa-solid fa-rotate-left"></i> Đặt lại</button>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width:70px;">ID</th>
                <th>Nguồn đề xuất</th>
                <th>Bài viết</th>
                <th>Tác giả</th>
                <th>Điểm uy tín</th>
                <th>Lý do đề xuất</th>
                <th>Trạng thái</th>
                <th>Thời gian</th>
                <th style="width:180px;">Hành động duyệt</th>
              </tr>
            </thead>
            <tbody id="admin-news-tbody">
              <tr><td colspan="9" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải danh sách đề xuất tin tức...</td></tr>
            </tbody>
          </table>
        </div>

        <div class="admin-pagination-bar" id="news-pagination"></div>
      </div>
    </section>

    <!-- ============================================================ -->
    <!-- TAB 6: KIỂM DUYỆT NỘI DUNG AI                                 -->
    <!-- ============================================================ -->
    <section id="tab-pane-ai-moderation" class="admin-tab-pane" role="tabpanel">
      <div class="admin-card">
        <div class="admin-card-header">
          <h3><i class="fa-solid fa-shield-halved"></i> Kiểm Duyệt Nội Dung AI (AI Moderation Items)</h3>
          <span class="card-subtitle">AI chỉ phân tích và đề xuất mức độ rủi ro, Quản trị viên đưa ra quyết định xử lý cuối cùng.</span>
        </div>

        <div class="admin-filter-bar">
          <div class="filter-group filter-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="ai-search" placeholder="Tìm theo tiêu đề, tác giả, lý do cảnh báo...">
          </div>
          <div class="filter-group">
            <select id="ai-filter-severity">
              <option value="">Tất cả mức độ vi phạm</option>
              <option value="high">Nghiêm trọng (High)</option>
              <option value="medium">Trung bình (Medium)</option>
              <option value="low">Nhẹ (Low)</option>
            </select>
          </div>
          <div class="filter-group">
            <select id="ai-filter-violation-type">
              <option value="">Tất cả loại vi phạm</option>
              <option value="inappropriate">Ngôn từ không phù hợp</option>
              <option value="spam">Spam / Quảng cáo</option>
              <option value="scam">Lừa đảo</option>
              <option value="hate_speech">Công kích / Thù ghét</option>
              <option value="harassment">Quấy rối / Xúc phạm</option>
              <option value="illegal">Vi phạm pháp luật / Độc hại</option>
              <option value="gambling">Cờ bạc / Cá độ</option>
            </select>
          </div>
          <div class="filter-group">
            <select id="ai-filter-mod-status">
              <option value="">Tất cả trạng thái bài</option>
              <option value="flagged">Gắn cờ cảnh báo (Flagged)</option>
              <option value="blocked">Đã khóa (Blocked)</option>
              <option value="safe">An toàn (Safe)</option>
            </select>
          </div>
          <div class="filter-group">
            <select id="ai-filter-ai-status">
              <option value="">Tất cả trạng thái AI</option>
              <option value="pending">Chờ Admin xử lý</option>
              <option value="approved">Admin đã quyết định</option>
            </select>
          </div>
          <button type="button" class="btn-reset-filters" id="ai-btn-reset"><i class="fa-solid fa-rotate-left"></i> Đặt lại</button>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width:60px;">ID</th>
                <th>Bài viết</th>
                <th>Tác giả</th>
                <th>Mức độ rủi ro</th>
                <th>Loại vi phạm AI</th>
                <th>Lý do AI phát hiện</th>
                <th>Trạng thái bài</th>
                <th>Người & Ngày duyệt</th>
                <th style="width:230px;">Quyết định của Admin</th>
              </tr>
            </thead>
            <tbody id="admin-ai-tbody">
              <tr><td colspan="9" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải danh sách kiểm duyệt AI...</td></tr>
            </tbody>
          </table>
        </div>

        <div class="admin-pagination-bar" id="ai-pagination"></div>
      </div>
    </section>

  </div>

  <!-- ============================================================ -->
  <!-- MODAL XÁC NHẬN THAO TÁC (THAY THẾ ALERT/CONFIRM MẶC ĐỊNH)      -->
  <!-- ============================================================ -->
  <div id="admin-confirm-modal" class="admin-modal-backdrop" style="display:none;" aria-hidden="true" role="dialog">
    <div class="admin-modal-box">
      <div class="admin-modal-header">
        <h4 id="admin-modal-title"><i class="fa-solid fa-triangle-exclamation text-warning"></i> Xác nhận thao tác</h4>
        <button type="button" class="admin-modal-close" id="admin-modal-close-btn">&times;</button>
      </div>
      <div class="admin-modal-body">
        <p id="admin-modal-message" class="admin-modal-text">Bạn có chắc chắn muốn thực hiện hành động này?</p>

        <!-- Dynamic Action Selection (Optional) -->
        <div id="admin-modal-action-group" class="admin-modal-field" style="display:none;">
          <label for="admin-modal-action-select" class="form-label" id="admin-modal-action-label">Tùy chọn xử lý nội dung:</label>
          <select id="admin-modal-action-select" class="form-control">
            <option value="none">Chỉ duyệt báo cáo (giữ nguyên nội dung)</option>
            <option value="hide">Ẩn / Khóa nội dung (Blocked)</option>
            <option value="delete">Xóa vĩnh viễn nội dung khỏi hệ thống</option>
          </select>
        </div>

        <!-- Input lý do / ghi chú (Optional) -->
        <div id="admin-modal-input-group" class="admin-modal-field" style="display:none;">
          <label for="admin-modal-note-input" class="form-label" id="admin-modal-note-label">Lý do / Ghi chú kiểm duyệt:</label>
          <textarea id="admin-modal-note-input" class="form-control" rows="3" placeholder="Nhập lý do gửi đến người dùng (nếu có)..."></textarea>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="action-btn btn-secondary" id="admin-modal-cancel-btn">Hủy bỏ</button>
        <button type="button" class="action-btn btn-primary" id="admin-modal-confirm-btn">Xác nhận thực hiện</button>
      </div>
    </div>
  </div>

  <!-- ============================================================ -->
  <!-- MODAL XEM LỊCH SỬ CẤP/THU HỒI TÍCH XANH                       -->
  <!-- ============================================================ -->
  <div id="admin-badge-history-modal" class="admin-modal-backdrop" style="display:none;" aria-hidden="true" role="dialog">
    <div class="admin-modal-box modal-lg">
      <div class="admin-modal-header">
        <h4><i class="fa-solid fa-clock-rotate-left"></i> Lịch Sử Cấp / Thu Hồi Tích Xanh: <span id="badge-history-username" style="color:#0284c7;"></span></h4>
        <button type="button" class="admin-modal-close" id="badge-history-close-btn">&times;</button>
      </div>
      <div class="admin-modal-body">
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Hành động</th>
                <th>Lý do thực hiện</th>
                <th>Quản trị viên thực hiện</th>
                <th>Thời gian</th>
              </tr>
            </thead>
            <tbody id="badge-history-tbody">
              <tr><td colspan="4" class="text-center py-3">Đang tải lịch sử...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button type="button" class="action-btn btn-secondary" id="badge-history-ok-btn">Đóng</button>
      </div>
    </div>
  </div>

  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
  <script src="<?= APP_URL ?>/assets/js/csrf.js?v=20261114-csrf1"></script>
  <script src="<?= APP_URL ?>/assets/js/admin.js?v=20261114-admin-v3"></script>
</body>
</html>
