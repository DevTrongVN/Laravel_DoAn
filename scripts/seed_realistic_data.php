<?php
declare(strict_types=1);

/**
 * Script Seeder tạo bộ dữ liệu mẫu chân thực, quy mô lớn cho dự án "Góc Nhỏ".
 * Chạy qua CLI: php scripts/seed_realistic_data.php
 * 
 * Tối ưu hiệu năng:
 * - Chuẩn bị hash mật khẩu 1 lần duy nhất ('123456').
 * - Batch Multi-row Inserts (200 - 500 dòng/lô) kết hợp Transaction.
 * - Bảo toàn 100% tài khoản Admin và tài khoản test hiện có.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Lỗi: Script này chỉ được phép thực thi trong giao diện dòng lệnh (CLI).\n");
}

set_time_limit(0);
ini_set('memory_limit', '512M');

$startTime = microtime(true);

echo "=================================================================\n";
echo "🚀 BẮT ĐẦU QUÁ TRÌNH SEED DỮ LIỆU MẪU CHÂN THỰC - DỰ ÁN 'GÓC NHỎ'\n";
echo "=================================================================\n\n";

// 1. KẾT NỐI DATABASE
$configFile = __DIR__ . '/../config/database.php';
if (!file_exists($configFile)) {
    fwrite(STDERR, "Không tìm thấy file cấu hình database tại: {$configFile}\n");
    exit(1);
}
$dbConfig = require $configFile;

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbConfig['host'], $dbConfig['port'], $dbConfig['dbname'], $dbConfig['charset']);
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    echo "✓ Đã kết nối thành công tới CSDL: {$dbConfig['dbname']}\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Lỗi kết nối CSDL: " . $e->getMessage() . "\n");
    exit(1);
}

// Hàm hỗ trợ tạo slug chuẩn tiếng Việt không dấu
function vietnameseSlug(string $str): string {
    $trans = [
        'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
        'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
        'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
        'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
        'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y',
        'đ'=>'d',
        'À'=>'a','Á'=>'a','Ạ'=>'a','Ả'=>'a','Ã'=>'a','Â'=>'a','Ầ'=>'a','Ấ'=>'a','Ậ'=>'a','Ẩ'=>'a','Ẫ'=>'a','Ă'=>'a','Ằ'=>'a','Ắ'=>'a','Ặ'=>'a','Ẳ'=>'a','Ẵ'=>'a',
        'È'=>'e','É'=>'e','Ẹ'=>'e','Ẻ'=>'e','Ẽ'=>'e','Ê'=>'e','Ề'=>'e','Ế'=>'e','Ệ'=>'e','Ể'=>'e','Ễ'=>'e',
        'Ì'=>'i','Í'=>'i','Ị'=>'i','Ỉ'=>'i','Ĩ'=>'i',
        'Ò'=>'o','Ó'=>'o','Ọ'=>'o','Ỏ'=>'o','Õ'=>'o','Ô'=>'o','Ồ'=>'o','Ố'=>'o','Ộ'=>'o','Ổ'=>'o','Ỗ'=>'o','Ơ'=>'o','Ờ'=>'o','Ớ'=>'o','Ợ'=>'o','Ở'=>'o','Ỡ'=>'o',
        'Ù'=>'u','Ú'=>'u','Ụ'=>'u','Ủ'=>'u','Ũ'=>'u','Ư'=>'u','Ừ'=>'u','Ứ'=>'u','Ự'=>'u','Ử'=>'u','Ữ'=>'u',
        'Ỳ'=>'y','Ý'=>'y','Ỵ'=>'y','Ỷ'=>'y','Ỹ'=>'y',
        'Đ'=>'d'
    ];
    $clean = strtr($str, $trans);
    $clean = preg_replace('/[^a-zA-Z0-9\s-]/', '', $clean);
    $clean = preg_replace('/[\s-]+/', '-', trim($clean));
    return strtolower(trim($clean, '-'));
}

// 2. TẬP DỮ LIỆU TỰ NHIÊN TIẾNG VIỆT
$hoList = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương', 'Lý', 'Đoàn', 'Lâm', 'Trịnh', 'Mai', 'Đinh', 'Lương', 'Thái', 'Tô', 'Hà'];
$demList = ['Văn', 'Thị', 'Đức', 'Hoàng', 'Minh', 'Hữu', 'Tuấn', 'Đình', 'Thanh', 'Ngọc', 'Quang', 'Quốc', 'Bảo', 'Trọng', 'Duy', 'Nhật', 'Khánh', 'Anh', 'Thùy', 'Kim', 'Phương', 'Hải', 'Hồng', 'Tiến'];
$tenList = [
    'An', 'Anh', 'Bảo', 'Bình', 'Cường', 'Dũng', 'Đạt', 'Đức', 'Dương', 'Duy', 'Giang', 'Hải', 'Hào', 'Hiếu', 'Hoàng', 'Hùng', 'Huy',
    'Khang', 'Khánh', 'Khoa', 'Kiên', 'Lâm', 'Long', 'Minh', 'Nam', 'Nghĩa', 'Nguyên', 'Nhân', 'Nhật', 'Phong', 'Phúc', 'Quân', 'Quang',
    'Sang', 'Sơn', 'Tài', 'Tâm', 'Thắng', 'Thịnh', 'Thông', 'Thuận', 'Tiến', 'Toàn', 'Trí', 'Trung', 'Trường', 'Tuấn', 'Tùng', 'Việt', 'Vinh',
    'Bích', 'Châu', 'Chi', 'Cúc', 'Diệp', 'Dung', 'Duyên', 'Hà', 'Hạnh', 'Hằng', 'Hân', 'Hiền', 'Hoa', 'Hoài', 'Hương', 'Hường', 'Huyền',
    'Lan', 'Linh', 'Loan', 'Ly', 'Mai', 'Mi', 'My', 'Nga', 'Ngân', 'Ngọc', 'Nhung', 'Như', 'Oanh', 'Phương', 'Phượng', 'Quỳnh', 'Quyên',
    'Thảo', 'Thi', 'Thu', 'Thùy', 'Thủy', 'Thư', 'Thương', 'Thúy', 'Tiên', 'Trang', 'Trâm', 'Trúc', 'Tuyết', 'Uyên', 'Vân', 'Vy', 'Yến'
];

$avatars = [
    '/assets/images/avatars/user-admin.svg',
    '/assets/images/avatars/user-demo.svg',
    '/assets/images/avatars/user-hoangnam.svg',
    '/assets/images/avatars/user-thuha.svg',
    '/assets/images/avatars/user-quocbao.svg',
    '/assets/images/avatars/user-maianh.svg',
    '/assets/images/avatars/default.svg'
];

$bios = [
    'Lập trình viên đam mê mã nguồn mở, cà phê và sách công nghệ.',
    'Thích viết lách, chụp ảnh film và ghi lại những điều bình dị thường nhật.',
    'Kỹ sư phần mềm thích tìm hiểu về AI, Clean Code và kiến trúc hệ thống.',
    'Sinh viên CNTT luôn tò mò về thế giới lập trình web và UI/UX.',
    'Người đọc sách, thích chia sẻ góc nhìn và những bài học cuộc sống.',
    'Yêu công nghệ, đam mê khởi nghiệp và chia sẻ kinh nghiệm thực tế.',
    'Frontend developer yêu cái đẹp, minimalist và viết blog công nghệ.',
    'Backend engineer đam mê tối ưu hóa hiệu năng, cơ sở dữ liệu và bảo mật.',
    'Thích khám phá những quán cà phê yên tĩnh và đọc sách vào mỗi cuối tuần.',
    'Chia sẻ những góc nhìn đa chiều về cuộc sống, công việc và sự cân bằng tâm trí.',
    'Yêu thích triết lý sống tối giản (Minimalism) và phát triển bản thân bền vững.',
    'Người viết tự do, thích ghi chép những trải nghiệm học tập và làm việc mỗi ngày.'
];

// Danh sách Hashtag phong phú
$seedHashtags = [
    'congnghe', 'laptrinh', 'php', 'ai', 'gocchiase', 'sachhay', 'caphe', 'cuocsong',
    'sinhvien', 'huit', 'webdev', 'coder', 'javascript', 'mysql', 'cleancode', 'hocweb',
    'tamchuyen', 'chill', 'doc_sach', 'remote', 'phattrienbanthan', 'backend', 'frontend',
    'database', 'kinhnghiem', 'khoinghiep', 'tulap', 'kynang', 'congdong', 'gocnho'
];

// Mẫu bài viết chân thực theo các chủ đề đa dạng
$articlePool = [
    [
        'topic' => 'Công nghệ & Lập trình',
        'title' => 'Tại sao tôi quyết định loại bỏ Framework nặng nề và quay về với PHP thuần?',
        'summary' => 'Sau nhiều năm phụ thuộc vào các framework đồ sộ, tôi nhận ra việc thấu hiểu bản chất kiến trúc MVC thuần mang lại hiệu năng vượt trội và sự tự do tuyệt đối.',
        'paragraphs' => [
            "Khi mới bắt đầu sự nghiệp lập trình, tôi thường có xu hướng tìm kiếm những framework phổ biến nhất với hy vọng chúng sẽ giải quyết mọi bài toán kiến trúc. Tuy nhiên, sau nhiều năm bảo trì các hệ thống lớn, tôi nhận thấy sự cồng kềnh và lớp trừu tượng quá mức đôi khi trở thành rào cản lớn đối với tốc độ tải trang và khả năng làm chủ mã nguồn.",
            "Quay trở lại với kiến trúc PHP thuần hướng đối tượng (Vanilla PHP MVC), mọi thứ trở nên sáng sủa hơn bao giờ hết. Chúng ta có toàn quyền kiểm soát luồng request, tối ưu hóa từng câu truy vấn PDO, và loại bỏ hoàn toàn hàng chục nghìn dòng thư viện không cần thiết của bên thứ ba. Kết quả là thời gian phản hồi API giảm xuống dưới 15ms ngay cả trên các gói máy chủ cấu hình khiêm tốn.",
            "Điều quan trọng nhất không phải là công cụ bạn sử dụng, mà là tư duy tổ chức mã nguồn và sự thấu hiểu sâu sắc cách mà web server tương tác với cơ sở dữ liệu. Hãy học cách tự xây dựng Router, Middleware và Controller của riêng bạn trước khi phụ thuộc vào bất kỳ framework nào."
        ]
    ],
    [
        'topic' => 'Công nghệ & Lập trình',
        'title' => 'Nghệ thuật tối ưu hóa truy vấn MySQL với chỉ mục Indexing chuẩn mực',
        'summary' => 'Chỉ mục (Index) là chìa khóa vàng giúp hệ thống cơ sở dữ liệu xử lý hàng triệu bản ghi trong chớp mắt nếu bạn biết cách thiết kế đúng.',
        'paragraphs' => [
            "Trong quá trình vận hành các ứng dụng có lượng người dùng tăng trưởng nhanh, vấn đề nghẽn cổ chai hầu như luôn xuất phát từ cơ sở dữ liệu chứ không phải ở tầng code xử lý logic. Một truy vấn `SELECT` không có chỉ mục phù hợp có thể quét toàn bộ bảng (Full Table Scan) và khiến CPU máy chủ chạm ngưỡng 100%.",
            "Bằng việc phân tích `EXPLAIN` cho từng câu query chậm và áp dụng các chỉ mục tổng hợp (Composite Index) theo thứ tự chọn lọc cao nhất, tốc độ truy xuất trang bảng tin đã được cải thiện từ 1.8 giây xuống còn 25 mili-giây. Đặc biệt, việc tránh lạm dụng quá nhiều index trên các bảng có tần suất ghi cao giúp cân bằng tối ưu giữa tốc độ đọc và chi phí lưu trữ.",
            "Hãy luôn kiểm tra execution plan của mỗi truy vấn trước khi triển khai lên môi trường sản phẩm. Một chỉ mục chuẩn xác đáng giá hơn rất nhiều lần việc nâng cấp phần cứng máy chủ."
        ]
    ],
    [
        'topic' => 'Công nghệ & Lập trình',
        'title' => 'Tự xây dựng hệ sinh thái phòng chống CSRF và XSS bảo mật cho ứng dụng web',
        'summary' => 'Bảo mật không phải là tính năng bổ sung ở phút cuối, mà phải là nền tảng cốt lõi được xây dựng xuyên suốt từ Backend đến Frontend.',
        'paragraphs' => [
            "Các cuộc tấn công Cross-Site Request Forgery (CSRF) và Cross-Site Scripting (XSS) vẫn luôn nằm trong nhóm lỗ hổng phổ biến nhất theo thống kê của OWASP. Nhiều lập trình viên thường chủ quan nghĩ rằng chỉ cần kiểm tra session là đủ an toàn, nhưng thực tế trình duyệt sẽ tự động đính kèm cookie của nạn nhân trong mọi request chuyển tiếp.",
            "Giải pháp bền vững là áp dụng mô hình Đồng bộ Token (Synchronizer Token Pattern): phát sinh token ngẫu nhiên mã hóa cryptographically secure, lưu trong session máy chủ và cookie `SameSite=Lax`, đồng thời bắt buộc client gửi kèm qua HTTP Header `X-CSRF-TOKEN` cho mọi request thay đổi trạng thái (POST, PUT, DELETE).",
            "Bên cạnh đó, việc áp dụng Content Security Policy (CSP) chặt chẽ và làm sạch dữ liệu đầu ra với `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` sẽ dựng nên bức tường phòng thủ kiên cố trước mọi nỗ lực tiêm mã độc."
        ]
    ],
    [
        'topic' => 'Góc nhìn cuộc sống',
        'title' => 'Học cách sống chậm lại giữa thời đại của sự tức thì và thông tin dồn dập',
        'summary' => 'Khi mọi thứ quanh ta đều đòi hỏi sự phản hồi ngay lập tức, khả năng dừng lại một nhịp để lắng nghe chính mình trở thành một năng lực xa xỉ.',
        'paragraphs' => [
            "Chúng ta đang sống trong một kỷ nguyên mà tốc độ được tôn sùng như thước đo tối thượng của sự thành công. Tin nhắn phải được hồi đáp trong vài giây, video phải truyền tải trọn vẹn thông điệp trong 15 giây, và thành công dường như phải đến trước tuổi ba mươi. Áp lực vô hình ấy khiến tâm trí chúng ta luôn trong trạng thái căng thẳng và phân mảnh.",
            "Sống chậm lại không có nghĩa là lười biếng hay tụt hậu. Đó là sự lựa chọn có ý thức để hiện diện trọn vẹn với những gì mình đang làm: thưởng thức trọn vẹn hương vị một tách trà sớm, đọc hết một trang sách mà không ngắt quãng kiểm tra thông báo điện thoại, và lắng nghe một người bạn bằng tất cả sự chân thành.",
            "Khi bạn học được cách giảm bớt nhịp sống bên ngoài, không gian nội tâm bên trong sẽ mở ra. Đó cũng là lúc những ý tưởng sáng tạo và những cảm xúc sâu sắc nhất bắt đầu nảy mầm."
        ]
    ],
    [
        'topic' => 'Góc nhìn cuộc sống',
        'title' => 'Bước ra khỏi vùng an toàn: Nỗi sợ hãi và bài học trưởng thành',
        'summary' => 'Vùng an toàn là nơi ấm áp và dễ chịu nhất, nhưng ở đó không có điều gì mới mẻ có thể lớn lên.',
        'paragraphs' => [
            "Mỗi người chúng ta đều có một ranh giới vô hình được tạo nên bởi những thói quen quen thuộc, những công việc quen tay và những mối quan hệ an toàn. Mỗi khi có ý định vượt qua ranh giới ấy để thử sức với một dự án mới, một ngôn ngữ lập trình mới hay một môi trường làm việc xa lạ, tiếng nói sợ hãi luôn xuất hiện đầu tiên.",
            "Tôi từng mất gần nửa năm đắn đo trước khi quyết định nhận phụ trách một kiến trúc hệ thống hoàn toàn mới mà bản thân chưa từng thử nghiệm ở quy mô lớn. Cảm giác bỡ ngỡ ban đầu thực sự không hề dễ chịu, nhưng chính áp lực phải tìm tòi, đọc tài liệu thấu đêm và đối mặt với các lỗi phát sinh đã giúp tôi tiến bộ vượt bậc chỉ trong vài tháng.",
            "Thất bại trong vùng an toàn không giúp bạn mạnh mẽ hơn; chỉ có những va vấp khi bạn dám dấn thân ra biển lớn mới tôi luyện nên bản lĩnh thực sự của một con người trưởng thành."
        ]
    ],
    [
        'topic' => 'Cà phê & Sách',
        'title' => '5 cuốn sách đã định hình tư duy lập trình và phong cách sống của tôi',
        'summary' => 'Những trang sách không chỉ mang đến kiến thức kỹ thuật đơn thuần, mà còn truyền cảm hứng về sự chỉn chu và đạo đức nghề nghiệp.',
        'paragraphs' => [
            "Trong tủ sách của một kỹ sư công nghệ, bên cạnh các tài liệu kỹ thuật chuyên sâu về thuật toán hay kiến trúc, luôn cần có những cuốn sách dạy về tư duy và triết lý làm nghề. Cuốn 'Clean Code' của Robert C. Martin và 'The Pragmatic Programmer' của Andrew Hunt là hai tác phẩm gối đầu giường đã dạy tôi rằng: viết mã cho máy tính hiểu là việc dễ, viết mã cho con người cùng làm việc hiểu được mới là đẳng cấp của sự chuyên nghiệp.",
            "Bên cạnh sách chuyên ngành, cuốn 'Lối sống tối giản của người Nhật' của Sasaki Fumio và 'Bắt trẻ đồng xanh' của J.D. Salinger lại mang đến sự cân bằng cần thiết sau những giờ làm việc căng thẳng với màn hình. Chúng nhắc nhở tôi về giá trị của những điều mộc mạc và sự thanh thản trong tâm hồn.",
            "Hãy dành ra ít nhất 30 phút mỗi ngày trước khi đi ngủ để đọc sách. Đó là khoản đầu tư có lợi nhuận kép cao nhất mà bạn có thể dành tặng cho tương lai của chính mình."
        ]
    ],
    [
        'topic' => 'Cà phê & Sách',
        'title' => 'Phương pháp ghi chép Zettelkasten: Xây dựng bộ não thứ hai cho lập trình viên',
        'summary' => 'Cách tổ chức và liên kết các mẩu ghi chú rời rạc thành một mạng lưới tri thức sống động, phục vụ cho việc học tập và sáng tạo lâu dài.',
        'paragraphs' => [
            "Hầu hết chúng ta đều từng trải qua cảm giác đọc rất nhiều tài liệu hay, lưu lại hàng trăm bài viết bookmark trên trình duyệt nhưng rồi vài tuần sau chẳng còn nhớ được bao nhiêu. Vấn đề không nằm ở trí nhớ của bạn, mà nằm ở phương pháp lưu trữ thụ động theo dạng ngăn kéo rời rạc.",
            "Phương pháp Zettelkasten do nhà xã hội học Niklas Luhmann khởi xướng hoạt động theo nguyên lý mạng lưới nơ-ron: mỗi ghi chú là một nguyên tử ý tưởng độc lập (atomic note), được liên kết hai chiều với các ý tưởng liên quan khác. Khi bạn viết một bài blog mới hay giải quyết một bài toán kiến trúc, bạn chỉ cần lần theo các liên kết có sẵn để kết nối các mảnh ghép tri thức lại với nhau.",
            "Bắt đầu với một công cụ ghi chép đơn giản và duy trì thói quen diễn giải lại kiến thức bằng ngôn ngữ của chính mình. Sau một năm, bạn sẽ ngạc nhiên trước kho tàng tri thức khổng lồ mà mình đã tích lũy được."
        ]
    ],
    [
        'topic' => 'Kinh nghiệm học tập & Nghề nghiệp',
        'title' => 'Lộ trình tự học lập trình web hiệu quả từ con số 0 dành cho sinh viên IT',
        'summary' => 'Đừng để bị lạc lối giữa rừng công nghệ mới; hãy tập trung vào các nguyên lý cốt lõi trước khi chạy theo các xu hướng nhất thời.',
        'paragraphs' => [
            "Một trong những sai lầm phổ biến nhất của các bạn sinh viên mới vào ngành là cố gắng học cùng lúc quá nhiều công nghệ: vừa học React, vừa học Vue, vừa tìm hiểu Docker và Kubernetes trong khi nền tảng HTML, CSS và JavaScript căn bản vẫn chưa thực sự vững vàng. Điều này dẫn đến sự hoang mang và hội chứng 'kẻ mạo danh' (Imposter Syndrome).",
            "Lời khuyên chân thành của tôi là hãy đi thật chậm nhưng chắc ở giai đoạn đầu. Nắm vững cách thức hoạt động của giao thức HTTP, cách trình duyệt render cây DOM, cách tổ chức cơ sở dữ liệu quan hệ và tư duy giải thuật. Khi bạn đã hiểu rõ nguyên lý vận hành cốt lõi, việc học thêm bất kỳ framework hay thư viện mới nào cũng chỉ mất vài tuần.",
            "Quan trọng hơn cả, hãy bắt tay vào thực hiện các dự án thực tế ngay từ sớm. Việc tự tay làm một ứng dụng hoàn chỉnh từ A đến Z - dù là một trang blog nhỏ hay ứng dụng ghi chú - sẽ dạy bạn nhiều bài học giá trị hơn hàng chục giờ xem video hướng dẫn thụ động."
        ]
    ],
    [
        'topic' => 'Tin tức công nghệ',
        'title' => 'Góc nhìn: Trí tuệ nhân tạo (AI) và tương lai của kỹ sư phần mềm trong kỷ nguyên mới',
        'summary' => 'AI sẽ không thay thế lập trình viên, nhưng những lập trình viên biết tận dụng sức mạnh của AI sẽ thay thế những người đứng ngoài cuộc.',
        'paragraphs' => [
            "Sự phát triển bùng nổ của các mô hình ngôn ngữ lớn (LLM) và các trợ lý AI hỗ trợ lập trình đã và đang thay đổi hoàn toàn cách chúng ta viết code mỗi ngày. Những công việc mang tính lặp đi lặp lại như viết mã boilerplate, tạo unit test cơ bản hay tra cứu cú pháp giờ đây có thể được hoàn thành trong vài giây chỉ với một câu lệnh prompt chuẩn xác.",
            "Tuy nhiên, giá trị cốt lõi của một kỹ sư phần mềm thực thụ chưa bao giờ nằm ở việc gõ phím nhanh. Giá trị ấy nằm ở khả năng thấu hiểu bài toán nghiệp vụ, thiết kế kiến trúc hệ thống chịu tải cao, tư duy phản biện để phát hiện các lỗ hổng logic tiềm ẩn và khả năng giao tiếp, cộng tác cùng đồng đội.",
            "Thay vì lo lắng bị thay thế, hãy chủ động biến AI thành người trợ thủ đắc lực trong quy trình làm việc hàng ngày của bạn. Người chiến thắng trong cuộc chuyển dịch này là người biết kết hợp tư duy chiều sâu của con người với tốc độ xử lý vượt trội của máy móc."
        ]
    ],
    [
        'topic' => 'Tin tức công nghệ',
        'title' => 'Bảo mật ứng dụng web: Những bài học đắt giá sau sự cố rò rỉ dữ liệu',
        'summary' => 'Một phân tích chuyên sâu về các nguyên nhân phổ biến dẫn đến mất an toàn thông tin và giải pháp phòng ngừa thực tế cho nhà phát triển.',
        'paragraphs' => [
            "Trong thế giới công nghệ hiện đại, ranh giới giữa một hệ thống an toàn và một thảm họa bảo mật đôi khi chỉ cách nhau một dòng cấu hình sai sót. Từ việc để lộ API key trong mã nguồn public, cấu hình quyền truy cập thư mục uploads lỏng lẻo cho đến việc tin tưởng mù quáng vào dữ liệu gửi lên từ phía người dùng.",
            "Các cuộc kiểm tra bảo mật định kỳ (Security Audit) và nguyên tắc 'Zero Trust' cần được áp dụng nghiêm ngặt trong mọi khâu phát triển phần mềm. Mọi input từ người dùng phải được coi là không an toàn cho đến khi được kiểm chứng và làm sạch; mọi mật khẩu phải được băm bằng các thuật toán mạnh mẽ như Bcrypt hoặc Argon2id với độ phức tạp cao.",
            "Đầu tư cho bảo mật luôn là khoản đầu tư tiết kiệm chi phí nhất so với những thiệt hại khổng lồ về uy tín và tài chính khi sự cố thực sự xảy ra."
        ]
    ]
];

// Danh sách bình luận mẫu tự nhiên
$sampleComments = [
    'Bài viết rất hay và thực tế, cảm ơn tác giả đã chia sẻ tâm huyết!',
    'Góc nhìn rất mới mẻ, mình cũng từng gặp tình huống tương tự trong dự án trước.',
    'Hoàn toàn đồng ý với quan điểm của bạn, đặc biệt là đoạn phân tích về tối ưu hóa.',
    'Bài viết bổ ích quá, mình xin phép bookmark lại để đọc kỹ hơn sau giờ làm.',
    'Chuẩn luôn bạn ơi, lập trình viên ngoài kỹ thuật thì tư duy hệ thống cũng cực kỳ quan trọng.',
    'Cảm ơn bạn, nhờ bài này mà mình hiểu rõ hơn về cách tổ chức kiến trúc code sạch.',
    'Cách hành văn nhẹ nhàng và sâu lắng, đọc cảm giác rất thư thái giữa ngày làm việc bận rộn.',
    'Mình xin hỏi thêm là trong trường hợp dữ liệu lớn thì giải pháp này có cần điều chỉnh gì không?',
    'Tuyệt vời! Mong chờ các bài viết chia sẻ kinh nghiệm tiếp theo của tác giả.',
    'Đồng quan điểm với bạn. Thói quen đọc sách mỗi ngày đã giúp mình thay đổi rất nhiều.',
    'Một bài viết chất lượng cho cộng đồng Góc Nhỏ, thả tim nhiệt tình!',
    'Phần kết luận rất đắt giá, cảm ơn người viết nhiều nhé!',
    'Mình vừa áp dụng thử cách này vào dự án hiện tại, kết quả cải thiện rõ rệt luôn.',
    'Một góc nhìn sâu sắc và truyền nhiều cảm hứng tích cực cho những bạn mới bắt đầu.',
    'Cảm ơn vì những chia sẻ rất chân thành và bổ ích!'
];

// 3. TẠO 1.000 TÀI KHOẢN NGƯỜI DÙNG (USERS)
echo "--- BƯỚC 1: TẠO 1.000 TÀI KHOẢN NGƯỜI DÙNG ---\n";

// Lấy danh sách username và email hiện có để tuyệt đối không trùng lặp
$existingUsersStmt = $pdo->query('SELECT username, email FROM users');
$existingUsernames = [];
$existingEmails = [];
while ($row = $existingUsersStmt->fetch()) {
    $existingUsernames[strtolower($row['username'])] = true;
    $existingEmails[strtolower($row['email'])] = true;
}

$commonPasswordHash = password_hash('123456', PASSWORD_DEFAULT);
$usersToInsert = [];
$targetUserCount = 1000;
$createdUsernames = [];

$userSuffixes = ['_dev', '_tech', '_coder', '_blog', '_writer', '_hub', '_life', '_daily', '_pro', ''];

for ($i = 0; $i < $targetUserCount; $i++) {
    $ho = $hoList[array_rand($hoList)];
    $dem = $demList[array_rand($demList)];
    $ten = $tenList[array_rand($tenList)];
    $fullName = "{$ho} {$dem} {$ten}";

    $baseSlug = vietnameseSlug("{$dem} {$ten}");
    if ($baseSlug === '') $baseSlug = 'user';

    // Tạo username duy nhất
    $attempt = 0;
    do {
        $attempt++;
        $suffix = ($attempt === 1) ? $userSuffixes[array_rand($userSuffixes)] : '_' . random_int(10, 9999);
        $candidateUsername = substr($baseSlug . $suffix, 0, 36);
        $candidateUsernameLower = strtolower($candidateUsername);
    } while (isset($existingUsernames[$candidateUsernameLower]) || isset($createdUsernames[$candidateUsernameLower]));

    $createdUsernames[$candidateUsernameLower] = true;
    $username = $candidateUsername;

    // Email duy nhất
    $domains = ['gmail.com', 'outlook.com', 'yahoo.com', 'icloud.com'];
    $domain = $domains[array_rand($domains)];
    $cleanUserForEmail = str_replace('_', '', $username);
    $email = "{$cleanUserForEmail}@{$domain}";
    if (isset($existingEmails[strtolower($email)])) {
        $email = "{$cleanUserForEmail}" . random_int(100, 9999) . "@{$domain}";
    }
    $existingEmails[strtolower($email)] = true;

    $avatar = $avatars[array_rand($avatars)];
    $bio = $bios[array_rand($bios)];

    // Phân bổ điểm uy tín (reputation_score)
    // ~75% có 10-250 điểm, ~18% có 251-499 điểm, ~7% có 500-1500 điểm (được cấp tích xanh)
    $randPercent = random_int(1, 100);
    if ($randPercent <= 75) {
        $reputation = random_int(10, 250);
        $isVerified = 0;
    } elseif ($randPercent <= 93) {
        $reputation = random_int(251, 499);
        $isVerified = 0;
    } else {
        $reputation = random_int(500, 1500);
        $isVerified = 1; // 7% tài khoản uy tín cao được tích xanh
    }

    $createdAt = date('Y-m-d H:i:s', time() - random_int(0, 90 * 86400));

    $usersToInsert[] = [
        'username'         => $username,
        'email'            => $email,
        'password'         => $commonPasswordHash,
        'full_name'        => $fullName,
        'avatar'           => $avatar,
        'bio'              => $bio,
        'role'             => 'user',
        'is_verified'      => $isVerified,
        'reputation_score' => $reputation,
        'created_at'       => $createdAt
    ];
}

// Chèn batch users (mỗi lô 250 dòng)
$userChunkSize = 250;
$pdo->beginTransaction();
$userInsertSql = 'INSERT INTO users (username, email, password, full_name, avatar, bio, `role`, is_verified, reputation_score, created_at) VALUES ';
foreach (array_chunk($usersToInsert, $userChunkSize) as $chunk) {
    $values = [];
    $params = [];
    foreach ($chunk as $u) {
        $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $params[] = $u['username'];
        $params[] = $u['email'];
        $params[] = $u['password'];
        $params[] = $u['full_name'];
        $params[] = $u['avatar'];
        $params[] = $u['bio'];
        $params[] = $u['role'];
        $params[] = $u['is_verified'];
        $params[] = $u['reputation_score'];
        $params[] = $u['created_at'];
    }
    $stmt = $pdo->prepare($userInsertSql . implode(', ', $values));
    $stmt->execute($params);
}
$pdo->commit();
echo "✓ Đã chèn thành công " . count($usersToInsert) . " tài khoản người dùng mới.\n";

// Lấy toàn bộ ID và thông tin người dùng trong CSDL để tạo bài viết và quan hệ
$allUsersStmt = $pdo->query('SELECT id, is_verified FROM users');
$allUsers = $allUsersStmt->fetchAll();
$allUserIds = array_column($allUsers, 'id');
$verifiedMap = [];
foreach ($allUsers as $u) {
    $verifiedMap[$u['id']] = (int)$u['is_verified'];
}

// 4. TẠO BỘ HASHTAGS PHỔ BIẾN
echo "\n--- BƯỚC 2: KHỞI TẠO BỘ HASHTAGS TIẾNG VIỆT & CÔNG NGHỆ ---\n";
$hashtagStmt = $pdo->prepare('INSERT INTO hashtags (name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
$hashtagMap = []; // name => id
foreach ($seedHashtags as $tag) {
    $hashtagStmt->execute([$tag]);
    $tagId = (int)$pdo->lastInsertId();
    if ($tagId <= 0) {
        $findStmt = $pdo->prepare('SELECT id FROM hashtags WHERE name = ?');
        $findStmt->execute([$tag]);
        $tagId = (int)$findStmt->fetchColumn();
    }
    $hashtagMap[$tag] = $tagId;
}
$hashtagIds = array_values($hashtagMap);
echo "✓ Đã khởi tạo/đồng bộ " . count($hashtagMap) . " hashtags.\n";

// 5. TẠO 3.000 – 7.000 BÀI VIẾT (POSTS)
echo "\n--- BƯỚC 3: TẠO BÀI VIẾT TỰ NHIÊN ĐA DẠNG CHỦ ĐỀ ---\n";
// Mỗi user tạo ngẫu nhiên từ 3 đến 6 bài viết -> ~4.500 bài viết
$postsToInsert = [];
$totalTargetPosts = 0;

foreach ($allUserIds as $uId) {
    $postCountForUser = random_int(3, 6);
    for ($p = 0; $p < $postCountForUser; $p++) {
        $template = $articlePool[array_rand($articlePool)];
        $titlePrefixes = ['', 'Góc nhìn: ', 'Chia sẻ: ', 'Trải nghiệm: ', 'Bàn về: '];
        $prefix = (random_int(1, 100) <= 35) ? $titlePrefixes[array_rand($titlePrefixes)] : '';
        $title = $prefix . $template['title'];

        // Thêm một chút biến tấu nhẹ nếu bị trùng
        if (random_int(1, 100) <= 25) {
            $variations = [' - Phần 1', ' - Góc nhìn cá nhân', ' sau 5 năm làm nghề', ' và những bài học thực tế', ' trong kỷ nguyên số'];
            $title .= $variations[array_rand($variations)];
        }

        $baseSlug = vietnameseSlug($title);
        $uniqueSuffix = bin2hex(random_bytes(3));
        $slug = substr($baseSlug, 0, 220) . '-' . $uniqueSuffix;

        $summary = $template['summary'];
        $content = implode("\n\n", $template['paragraphs']);

        // Phân loại: Tin tức (news) ~15%, Blog ~85%
        // Tài khoản có tích xanh có xác suất đăng bài Tin tức cao hơn
        $isVerified = $verifiedMap[$uId] ?? 0;
        $newsChance = $isVerified ? 40 : 12;
        $postType = (random_int(1, 100) <= $newsChance) ? 'news' : 'blog';

        // Lượt xem ngẫu nhiên 15 - 5.000
        $views = random_int(15, 5000);

        // Trạng thái kiểm duyệt: ~98.5% safe, ~1% flagged, ~0.5% blocked
        $modRand = random_int(1, 1000);
        $moderationStatus = 'safe';
        $aiSeverity = null;
        $aiViolationType = null;
        $aiReason = null;

        if ($modRand <= 10) { // 1% flagged
            $moderationStatus = 'flagged';
            $aiSeverity = 'medium';
            $aiViolationType = 'inappropriate';
            $aiReason = 'Hệ thống AI phát hiện nội dung có tần suất chia sẻ nhanh hoặc cụm từ nhạy cảm cần quản trị viên xem xét.';
        } elseif ($modRand <= 15) { // 0.5% blocked
            $moderationStatus = 'blocked';
            $aiSeverity = 'high';
            $aiViolationType = 'spam';
            $aiReason = 'Nội dung bị chặn tự động do phát hiện cấu trúc liên kết trùng lặp và dấu hiệu phát tán thư rác.';
        }

        // Đề xuất tin tức AI: ~1.8% bài viết
        $aiNewsSuggested = 0;
        $aiNewsReason = null;
        $aiStatus = null;
        if ($postType === 'blog' && $moderationStatus === 'safe' && random_int(1, 100) <= 2) {
            $aiNewsSuggested = 1;
            $aiNewsReason = 'Bài viết có hàm lượng phân tích kỹ thuật sâu sắc, bố cục mạch lạc và giá trị thời sự cao, đề xuất chuyển đổi sang chuyên mục Tin tức.';
            $aiStatus = 'pending';
        }

        $createdAt = date('Y-m-d H:i:s', time() - random_int(0, 90 * 86400));

        $postsToInsert[] = [
            'user_id'            => $uId,
            'title'              => $title,
            'slug'               => $slug,
            'summary'            => $summary,
            'content'            => $content,
            'post_type'          => $postType,
            'moderation_status'  => $moderationStatus,
            'ai_severity'        => $aiSeverity,
            'ai_violation_type'  => $aiViolationType,
            'ai_reason'          => $aiReason,
            'ai_news_suggested'  => $aiNewsSuggested,
            'ai_news_reason'     => $aiNewsReason,
            'ai_status'          => $aiStatus,
            'views_count'        => $views,
            'created_at'         => $createdAt
        ];
    }
}

// Chèn batch bài viết (mỗi lô 300 dòng)
$postChunkSize = 300;
$pdo->beginTransaction();
$postInsertSql = 'INSERT INTO posts (user_id, title, slug, summary, content, post_type, moderation_status, ai_severity, ai_violation_type, ai_reason, ai_news_suggested, ai_news_reason, ai_status, views_count, created_at) VALUES ';

foreach (array_chunk($postsToInsert, $postChunkSize) as $chunk) {
    $values = [];
    $params = [];
    foreach ($chunk as $p) {
        $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $params[] = $p['user_id'];
        $params[] = $p['title'];
        $params[] = $p['slug'];
        $params[] = $p['summary'];
        $params[] = $p['content'];
        $params[] = $p['post_type'];
        $params[] = $p['moderation_status'];
        $params[] = $p['ai_severity'];
        $params[] = $p['ai_violation_type'];
        $params[] = $p['ai_reason'];
        $params[] = $p['ai_news_suggested'];
        $params[] = $p['ai_news_reason'];
        $params[] = $p['ai_status'];
        $params[] = $p['views_count'];
        $params[] = $p['created_at'];
    }
    $stmt = $pdo->prepare($postInsertSql . implode(', ', $values));
    $stmt->execute($params);
}
$pdo->commit();
echo "✓ Đã chèn thành công " . count($postsToInsert) . " bài viết mới.\n";

// Lấy toàn bộ ID bài viết để gắn hashtag, reactions và comments
$allPostIds = $pdo->query('SELECT id FROM posts')->fetchAll(PDO::FETCH_COLUMN);

// 6. GẮN HASHTAGS CHO BÀI VIẾT (POST_HASHTAGS)
echo "\n--- BƯỚC 4: GẮN HASHTAGS CHO BÀI VIẾT ---\n";
$postHashtagRows = [];
foreach ($allPostIds as $postId) {
    $tagCount = random_int(1, 3);
    $chosenKeys = (array)array_rand($hashtagIds, $tagCount);
    foreach ($chosenKeys as $k) {
        $postHashtagRows[] = [(int)$postId, $hashtagIds[$k]];
    }
}

$pdo->beginTransaction();
$phInsertSql = 'INSERT IGNORE INTO post_hashtags (post_id, hashtag_id) VALUES ';
foreach (array_chunk($postHashtagRows, 500) as $chunk) {
    $values = [];
    $params = [];
    foreach ($chunk as $row) {
        $values[] = '(?, ?)';
        $params[] = $row[0];
        $params[] = $row[1];
    }
    $stmt = $pdo->prepare($phInsertSql . implode(', ', $values));
    $stmt->execute($params);
}
$pdo->commit();
echo "✓ Đã gắn " . count($postHashtagRows) . " liên kết hashtag cho bài viết.\n";

// 7. TẠO LƯỢT THEO DÕI CHÉO (FOLLOWS) ~4.000 LƯỢT
echo "\n--- BƯỚC 5: TẠO ~4.000 LƯỢT THEO DÕI (FOLLOWS) ---\n";
$followPairs = [];
$totalFollowsTarget = 4000;
$userCount = count($allUserIds);

for ($i = 0; $i < $totalFollowsTarget * 1.3; $i++) {
    $follower = $allUserIds[random_int(0, $userCount - 1)];
    $following = $allUserIds[random_int(0, $userCount - 1)];
    if ($follower !== $following) {
        $followPairs["{$follower}_{$following}"] = [$follower, $following];
    }
    if (count($followPairs) >= $totalFollowsTarget) break;
}

$pdo->beginTransaction();
$followInsertSql = 'INSERT IGNORE INTO follows (follower_id, following_id, visit_count, created_at) VALUES ';
foreach (array_chunk($followPairs, 500) as $chunk) {
    $values = [];
    $params = [];
    foreach ($chunk as $pair) {
        $values[] = '(?, ?, ?, ?)';
        $params[] = $pair[0];
        $params[] = $pair[1];
        $params[] = random_int(0, 45); // visit_count
        $params[] = date('Y-m-d H:i:s', time() - random_int(0, 90 * 86400));
    }
    $stmt = $pdo->prepare($followInsertSql . implode(', ', $values));
    $stmt->execute($params);
}
$pdo->commit();
echo "✓ Đã tạo thành công " . count($followPairs) . " lượt theo dõi chéo.\n";

// 8. TẠO CẢM XÚC BÀI VIẾT (POST_REACTIONS) ~12.000 LƯỢT
echo "\n--- BƯỚC 6: TẠO ~12.000 LƯỢT CẢM XÚC BÀI VIẾT (LIKE, LOVE, HAHA, SAD, ANGRY) ---\n";
$reactionTypes = ['like', 'love', 'haha', 'sad', 'angry'];
$reactionWeights = [55, 25, 12, 5, 3]; // like & love chiếm đa số
$reactionPool = [];
foreach ($reactionTypes as $idx => $t) {
    for ($w = 0; $w < $reactionWeights[$idx]; $w++) {
        $reactionPool[] = $t;
    }
}

$reactionPairs = [];
$totalReactionsTarget = 12000;
$postsCount = count($allPostIds);

for ($i = 0; $i < $totalReactionsTarget * 1.4; $i++) {
    $uId = $allUserIds[random_int(0, $userCount - 1)];
    $pId = $allPostIds[random_int(0, $postsCount - 1)];
    $key = "{$uId}_{$pId}";
    if (!isset($reactionPairs[$key])) {
        $reactionPairs[$key] = [
            'user_id'    => $uId,
            'post_id'    => $pId,
            'reaction'   => $reactionPool[array_rand($reactionPool)],
            'created_at' => date('Y-m-d H:i:s', time() - random_int(0, 90 * 86400))
        ];
    }
    if (count($reactionPairs) >= $totalReactionsTarget) break;
}

$pdo->beginTransaction();
$reactInsertSql = 'INSERT IGNORE INTO post_reactions (user_id, post_id, reaction, created_at) VALUES ';
foreach (array_chunk($reactionPairs, 500) as $chunk) {
    $values = [];
    $params = [];
    foreach ($chunk as $r) {
        $values[] = '(?, ?, ?, ?)';
        $params[] = $r['user_id'];
        $params[] = $r['post_id'];
        $params[] = $r['reaction'];
        $params[] = $r['created_at'];
    }
    $stmt = $pdo->prepare($reactInsertSql . implode(', ', $values));
    $stmt->execute($params);
}
$pdo->commit();
echo "✓ Đã tạo thành công " . count($reactionPairs) . " lượt tương tác cảm xúc bài viết.\n";

// 9. TẠO 6.000 BÌNH LUẬN TIẾNG VIỆT (COMMENTS)
echo "\n--- BƯỚC 7: TẠO ~6.000 BÌNH LUẬN TIẾNG VIỆT TỰ NHIÊN ---\n";
$commentsToInsert = [];
$totalCommentsTarget = 6000;

for ($i = 0; $i < $totalCommentsTarget; $i++) {
    $uId = $allUserIds[random_int(0, $userCount - 1)];
    $pId = $allPostIds[random_int(0, $postsCount - 1)];
    $content = $sampleComments[array_rand($sampleComments)];
    $createdAt = date('Y-m-d H:i:s', time() - random_int(0, 85 * 86400));

    $commentsToInsert[] = [
        'post_id'           => $pId,
        'user_id'           => $uId,
        'content'           => $content,
        'moderation_status' => 'safe',
        'created_at'        => $createdAt
    ];
}

$pdo->beginTransaction();
$commentInsertSql = 'INSERT INTO comments (post_id, user_id, content, moderation_status, created_at) VALUES ';
foreach (array_chunk($commentsToInsert, 500) as $chunk) {
    $values = [];
    $params = [];
    foreach ($chunk as $c) {
        $values[] = '(?, ?, ?, ?, ?)';
        $params[] = $c['post_id'];
        $params[] = $c['user_id'];
        $params[] = $c['content'];
        $params[] = $c['moderation_status'];
        $params[] = $c['created_at'];
    }
    $stmt = $pdo->prepare($commentInsertSql . implode(', ', $values));
    $stmt->execute($params);
}
$pdo->commit();
echo "✓ Đã tạo thành công " . count($commentsToInsert) . " bình luận tiếng Việt mới.\n";

// 9.5. ĐỒNG BỘ ĐIỂM UY TÍN THỰC TẾ DỰA TRÊN DỮ LIỆU TƯƠNG TÁC GỐC
require_once __DIR__ . '/../app/Services/ReputationService.php';
$repSync = (new ReputationService())->recalculateAll();
echo "✓ Đã đồng bộ điểm uy tín thực tế cho {$repSync['total_users']} người dùng (cập nhật {$repSync['updated_rows']} bản ghi).\n";

// 10. TỔNG KẾT VÀ THỐNG KÊ TOÀN DIỆN
$duration = round(microtime(true) - $startTime, 2);

$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$verifiedUsers = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_verified = 1')->fetchColumn();
$totalPosts = (int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
$newsPosts = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE post_type = 'news'")->fetchColumn();
$flaggedPosts = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE moderation_status = 'flagged'")->fetchColumn();
$aiNewsPosts = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE ai_news_suggested = 1")->fetchColumn();
$totalComments = (int)$pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
$totalReactions = (int)$pdo->query('SELECT COUNT(*) FROM post_reactions')->fetchColumn();
$totalFollows = (int)$pdo->query('SELECT COUNT(*) FROM follows')->fetchColumn();
$totalHashtags = (int)$pdo->query('SELECT COUNT(*) FROM hashtags')->fetchColumn();
$totalPostHashtags = (int)$pdo->query('SELECT COUNT(*) FROM post_hashtags')->fetchColumn();

echo "\n=================================================================\n";
echo "🎉 HOÀN THÀNH SEEDING DỮ LIỆU THÀNH CÔNG TRONG {$duration} GIÂY!\n";
echo "=================================================================\n";
echo "📊 THỐNG KÊ CƠ SỞ DỮ LIỆU HIỆN TẠI:\n";
echo "  • Tổng Người dùng (users):            {$totalUsers} tài khoản ({$verifiedUsers} tài khoản tích xanh)\n";
echo "  • Tổng Bài viết (posts):              {$totalPosts} bài viết ({$newsPosts} bài Tin tức, {$flaggedPosts} bài gắn cờ, {$aiNewsPosts} bài AI đề xuất)\n";
echo "  • Tổng Bình luận (comments):          {$totalComments} bình luận\n";
echo "  • Tổng Tương tác cảm xúc (reactions): {$totalReactions} lượt thả cảm xúc\n";
echo "  • Tổng Lượt theo dõi (follows):       {$totalFollows} lượt theo dõi chéo\n";
echo "  • Tổng Hashtags:                      {$totalHashtags} tags ({$totalPostHashtags} lượt gắn vào bài viết)\n";
echo "  • Mật khẩu chung cho các user mẫu:    123456\n";
echo "=================================================================\n";
