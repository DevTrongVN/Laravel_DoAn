<div class="modal-overlay" id="ticket-modal" style="display:none;">
  <div class="modal-content modal-content-ticket">
    <div class="modal-header">
      <div class="flex items-center gap-2">
        <i class="fa-solid fa-certificate text-warning"></i>
        <h3>Gửi Ticket xin xét duyệt Tin tức</h3>
      </div>
      <button type="button" class="close-modal" onclick="closeModal('ticket-modal')">&times;</button>
    </div>
    <div class="modal-body flex-col gap-sm">
      <div class="alert alert-info" id="ticket-reputation-info">
        <span>Điểm uy tín hiện tại của bạn: <strong id="ticket-current-score">...</strong></span>
        <span>(Ngưỡng yêu cầu: <strong id="ticket-threshold">500</strong> điểm)</span>
      </div>
      <p class="text-sm text-muted">
        Bài viết sau khi được Admin phê duyệt sẽ được gắn cờ <strong>[Tin tức]</strong>, hiển thị nổi bật trên Bảng tin chuyên mục và thanh Widget Tin tức HOT.
      </p>
      <input type="hidden" id="ticket-post-id" value="">
    </div>
    <div class="modal-footer flex justify-between">
      <button type="button" class="action-btn" onclick="closeModal('ticket-modal')">Hủy bỏ</button>
      <button type="button" class="action-btn action-btn-primary" id="btn-submit-ticket" onclick="confirmSubmitTicket()">
        <i class="fa-solid fa-paper-plane"></i> Gửi Ticket ngay
      </button>
    </div>
  </div>
</div>
