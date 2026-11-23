const mockUsers = [
  {
    id: 1,
    username: "admin",
    full_name: "RobloxAdmin",
    avatar: "assets/images/avatars/user-admin.svg",
    bio: "Quản trị viên hệ thống Góc Nhỏ · Đam mê kiến trúc phần mềm, AI & công nghệ mã nguồn mở.",
    is_verified: true,
    reputation_score: 1520,
    visit_count: 85
  },
  {
    id: 2,
    username: "demo",
    full_name: "Minh Thảo",
    avatar: "assets/images/avatars/user-demo.svg",
    bio: "Người viết blog · Yêu thích phong cách sống tối giản, trà hoa và những buổi chiều đọc sách.",
    is_verified: true,
    reputation_score: 750,
    visit_count: 48
  },
  {
    id: 3,
    username: "hoang_nam",
    full_name: "Hoàng Nam",
    avatar: "assets/images/avatars/user-hoangnam.svg",
    bio: "Kỹ sư phần mềm AI · Nghiên cứu Machine Learning, LLM và tối ưu hệ thống Backend.",
    is_verified: true,
    reputation_score: 1040,
    visit_count: 62
  },
  {
    id: 4,
    username: "thu_ha",
    full_name: "Lê Thu Hà",
    avatar: "assets/images/avatars/user-thuha.svg",
    bio: "Nhiếp ảnh gia tự do & Content Creator. Ghi lại vẻ đẹp bình dị của phố xá.",
    is_verified: false,
    reputation_score: 340,
    visit_count: 29
  },
  {
    id: 5,
    username: "quoc_bao",
    full_name: "Trần Quốc Bảo",
    avatar: "assets/images/avatars/user-quocbao.svg",
    bio: "Nhà báo công nghệ & Reviewer thiết bị số. Tìm kiếm góc nhìn sâu sắc về AI.",
    is_verified: true,
    reputation_score: 850,
    visit_count: 53
  },
  {
    id: 6,
    username: "mai_anh",
    full_name: "Nguyễn Mai Anh",
    avatar: "assets/images/avatars/user-maianh.svg",
    bio: "Product Designer tại Tech Hub · Đam mê Typography và Cà phê thủ công.",
    is_verified: false,
    reputation_score: 410,
    visit_count: 36
  }
];