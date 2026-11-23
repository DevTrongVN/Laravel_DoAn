<div class="modal-overlay" id="ai-writer-modal" style="display:none;">
  <div class="modal-content modal-content-ai" style="max-width: 660px; width: 100%;">
    <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding-bottom:14px; border-bottom:1px solid var(--border,#e4e8e2);">
      <div class="flex items-center gap-2" style="display:flex; align-items:center; gap:8px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--green,#3e6652); vertical-align:-3px;" aria-hidden="true">
          <path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.21 1.21 0 0 0 1.72 0L21.64 5.36a1.21 1.21 0 0 0 0-1.72Z"/>
          <path d="m14 7 3 3"/>
          <path d="M5 6v4"/>
          <path d="M19 14v4"/>
          <path d="M10 2v2"/>
          <path d="M7 8H3"/>
          <path d="M21 16h-4"/>
        </svg>
        <h3 style="margin:0; font-size:18px; font-weight:700;">AI Editor – Trợ lý biên tập nội dung</h3>
      </div>
      <button type="button" class="close-modal" onclick="cancelAiSuggestion()" aria-label="Đóng">&times;</button>
    </div>

    <div class="modal-body flex-col gap-sm" style="display:flex; flex-direction:column; gap:12px; padding:16px 0;">
      <div>
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; margin-bottom:6px;">
          <label for="ai-writer-original" class="text-sm font-semibold" style="font-size:13px; font-weight:600;">Nội dung cần biên tập:</label>
          <span id="ai-writer-scope-badge" style="display:inline-flex; align-items:center; font-size:11.5px; font-weight:600; padding:3px 9px; border-radius:999px; background:#f1f5f2; color:#3e5246;">Đang chỉnh sửa toàn bộ nội dung bài viết</span>
        </div>
        <textarea id="ai-writer-original" class="input-field" rows="4" placeholder="Nhập, dán hoặc bôi đen một đoạn trong bài viết để AI biên tập..." style="width:100%; resize:vertical; font-family:inherit;"></textarea>
      </div>

      <div style="display:flex; flex-direction:column; gap:10px; background:#f9faf8; padding:12px 14px; border-radius:10px; border:1px solid #edf0ec;">
        <div class="ai-mode-chips" role="group" aria-label="Chế độ biên tập AI">
          <button type="button" class="ai-mode-chip" data-ai-mode="spellcheck" onclick="selectAiMode('spellcheck')">Sửa chính tả</button>
          <button type="button" class="ai-mode-chip" data-ai-mode="natural" onclick="selectAiMode('natural')">Viết tự nhiên</button>
          <button type="button" class="ai-mode-chip" data-ai-mode="concise" onclick="selectAiMode('concise')">Rút gọn</button>
          <button type="button" class="ai-mode-chip" data-ai-mode="expand" onclick="selectAiMode('expand')">Mở rộng</button>
          <button type="button" class="ai-mode-chip is-active" data-ai-mode="improve" onclick="selectAiMode('improve')">Cải thiện diễn đạt</button>
          <button type="button" class="ai-mode-chip" data-ai-mode="professional" onclick="selectAiMode('professional')">Chuyên nghiệp</button>
          <button type="button" class="ai-mode-chip" data-ai-mode="literary" onclick="selectAiMode('literary')">Văn chương</button>
          <button type="button" class="ai-mode-chip" data-ai-mode="friendly" onclick="selectAiMode('friendly')">Thân thiện</button>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
          <div style="display:flex; align-items:center; gap:8px;">
            <label for="ai-writer-tone" style="font-size:13px; font-weight:600; color:var(--text,#1e2621);">Chế độ:</label>
            <select id="ai-writer-tone" class="input-field select-auto-width" style="padding:6px 12px; border-radius:8px; font-size:13px; cursor:pointer;">
              <option value="improve" selected>Cải thiện diễn đạt (giữ nguyên ý)</option>
              <option value="spellcheck">Sửa lỗi chính tả &amp; dấu câu</option>
              <option value="natural">Viết lại rõ ràng, tự nhiên hơn</option>
              <option value="concise">Rút gọn nội dung súc tích</option>
              <option value="expand">Mở rộng &amp; làm phong phú nội dung</option>
              <option value="professional">Giọng văn Nghiêm túc &amp; Chuyên nghiệp</option>
              <option value="literary">Giọng văn Văn chương &amp; Sâu lắng</option>
              <option value="friendly">Giọng văn Thân thiện &amp; Gần gũi</option>
            </select>
          </div>
          <button type="button" class="action-btn action-btn-primary" id="ai-writer-submit-btn" onclick="requestAiImprovement()" style="padding:8px 18px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;" aria-hidden="true">
              <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>
            </svg> Biên tập bằng AI
          </button>
        </div>
      </div>

      <div id="ai-writer-status" style="display:none; padding:10px 14px; border-radius:8px; font-size:13px;"></div>

      <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
          <label for="ai-writer-result" class="text-sm font-semibold" style="font-size:13px; font-weight:600;">Xem trước kết quả AI (bạn có thể chỉnh trực tiếp trước khi áp dụng):</label>
          <span style="font-size:11px; color:var(--muted,#7c827d);">Không tự ghi đè bài gốc</span>
        </div>
        <textarea id="ai-writer-result" class="input-field" rows="5" placeholder="Kết quả biên tập sẽ hiển thị tại đây để bạn xem trước..." style="width:100%; resize:vertical; font-family:inherit; background:#ffffff;"></textarea>
      </div>
    </div>

    <div class="modal-footer flex justify-between" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; padding-top:14px; border-top:1px solid var(--border,#e4e8e2);">
      <button type="button" class="action-btn" id="ai-writer-cancel-btn" onclick="cancelAiSuggestion()" style="padding:8px 14px;">Hủy &amp; Giữ bản gốc</button>
      <div style="display:flex; align-items:center; gap:8px;">
        <button type="button" class="action-btn" id="ai-writer-copy-btn" onclick="copyAiSuggestion()" style="padding:8px 14px;">Sao chép kết quả</button>
        <button type="button" class="action-btn action-btn-primary" id="ai-writer-apply-btn" onclick="applyAiSuggestion()" style="padding:8px 18px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;" aria-hidden="true">
            <polyline points="20 6 9 17 4 12"/>
          </svg> Áp dụng &amp; Thay thế
        </button>
      </div>
    </div>
  </div>
</div>
