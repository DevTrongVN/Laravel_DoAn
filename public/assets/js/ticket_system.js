// Tính Reputation Score & Xử lý Gửi Ticket xin Duyệt Tin Tức
const REPUTATION_THRESHOLD = 500; // Ngưỡng uy tín tối thiểu để gửi Ticket

function calculateReputationScore(user) {
  return (user.reputation_score);
}

function openTicketModal(postId) {
  const currentUser = mockUsers[1]; // Giả lập User hiện tại (Trần Thị B)
  const score = calculateReputationScore(currentUser);
  const modalBody = document.getElementById("ticket-modal-body");

  if (score < REPUTATION_THRESHOLD) {
    modalBody.innerHTML = `
      <div class="alert-box alert-danger">
        <i class="fa-solid fa-triangle-exclamation" style="color:#dc2626;"></i> <strong>Không đủ điều kiện!</strong><br>
        Điểm uy tín hiện tại của bạn là <strong>${score} điểm</strong> (Yêu cầu tối thiểu: <strong>${REPUTATION_THRESHOLD} điểm</strong>).<br>
        Tăng cường đăng bài và tương tác để đạt điều kiện gửi Ticket.
      </div>
    `;
  } else {
    modalBody.innerHTML = `
      <div class="alert-box alert-success mb-5">
        <i class="fa-solid fa-circle-check" style="color:#16a34a;"></i> <strong>Bạn đủ điều kiện!</strong> Điểm uy tín của bạn: <strong>${score} điểm</strong>.
      </div>
      <p>Yêu cầu xét duyệt bài viết này chuyển thành dạng <strong>[Tin Tức]</strong> sẽ được gửi tới Ban Quản Trị Admin.</p>
      <button class="action-btn action-btn-primary action-btn-full mt-4" id="btn-submit-ticket">Gửi Yêu Cầu Xét Duyệt</button>
    `;

    setTimeout(() => {
      const btnSubmit = document.getElementById("btn-submit-ticket");
      if (btnSubmit) {
        btnSubmit.addEventListener("click", () => {
          alert("Ticket đã gửi thành công tới Trang Quản Trị Admin!");
          document.getElementById("ticket-modal").classList.remove("active");
        });
      }
    }, 100);
  }

  document.getElementById("ticket-modal").classList.add("active");
}

document.getElementById("btn-close-ticket-modal").addEventListener("click", () => {
  document.getElementById("ticket-modal").classList.remove("active");
});