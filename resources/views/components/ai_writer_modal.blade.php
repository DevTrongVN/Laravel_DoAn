<div class="modal-overlay" id="ai-writer-modal" style="display:none;">
  <div class="modal-content modal-content-ai" style="max-width: 620px; width: 100%;">
    <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding-bottom:14px; border-bottom:1px solid var(--border,#e4e8e2);">
      <div class="flex items-center gap-2" style="display:flex; align-items:center; gap:8px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--green,#3e6652);" aria-hidden="true">
          <path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.21 1.21 0 0 0 1.72 0L21.64 5.36a1.21 1.21 0 0 0 0-1.72Z"/>
          <path d="m14 7 3 3"/>
        </svg>
        <h3 style="margin:0; font-size:18px; font-weight:700;">AI Trợ lý viết & Tinh chỉnh văn phong</h3>
      </div>
      <button type="button" class="close-modal" onclick="closeAIModal('ai-writer-modal')" aria-label="Đóng">&times;</button>
    </div>

    <div class="modal-body flex-col gap-sm" style="display:flex; flex-direction:column; gap:12px; padding:18px 0;">
      <div>
        <label for="ai-writer-original" style="display:block; margin-bottom:6px; font-size:13px; font-weight:600;">Nội dung bài viết ban đầu:</label>
        <textarea id="ai-writer-original" class="input-field" rows="4" placeholder="Nhập hoặc dán đoạn văn bạn muốn AI trau chuốt..." style="width:100%; resize:vertical; font-family:inherit;"></textarea>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; background:#f9faf8; padding:10px 14px; border-radius:10px; border:1px solid #edf0ec;">
        <div style="display:flex; align-items:center; gap:8px;">
          <label for="ai-writer-tone" style="font-size:13px; font-weight:600;">Phong cách:</label>
          <select id="ai-writer-tone" class="input-field select-auto-width" style="padding:6px 12px; border-radius:8px; font-size:13px; cursor:pointer;">
            <option value="professional">Nghiêm túc & Chuyên nghiệp</option>
            <option value="literary">Văn chương & Sâu lắng</option>
            <option value="concise">Ngắn gọn & Súc tích</option>
          </select>
        </div>

        <button type="button" class="action-btn action-btn-primary" id="ai-writer-submit-btn" onclick="requestAiImprovement()" style="padding:8px 18px; font-weight:600;">
          Chỉnh sửa văn phong
        </button>
      </div>

      <div id="ai-writer-status" style="display:none; padding:10px 14px; border-radius:8px; font-size:13px;"></div>

      <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
          <label for="ai-writer-result" style="font-size:13px; font-weight:600;">Gợi ý từ AI (bạn có thể tự sửa lại nếu cần):</label>
          <span style="font-size:11px; color:var(--muted,#7c827d);">Có thể chỉnh sửa trực tiếp</span>
        </div>
        <textarea id="ai-writer-result" class="input-field" rows="5" placeholder="Nội dung đã được biên tập sẽ xuất hiện tại đây..." style="width:100%; resize:vertical; font-family:inherit; background:#ffffff;"></textarea>
      </div>
    </div>

    <div class="modal-footer flex justify-between" style="display:flex; justify-content:space-between; align-items:center; padding-top:14px; border-top:1px solid var(--border,#e4e8e2);">
      <button type="button" class="action-btn" onclick="closeAIModal('ai-writer-modal')" style="padding:8px 16px;">Đóng</button>
      <button type="button" class="action-btn action-btn-primary" id="ai-writer-apply-btn" onclick="applyAiSuggestion()" style="padding:8px 18px; font-weight:600;">
        Áp dụng vào bài viết
      </button>
    </div>
  </div>
</div>
