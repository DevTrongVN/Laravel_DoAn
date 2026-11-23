# Hướng dẫn push GitHub và chạy dự án

**Repo:** `https://github.com/DevTrongVN/DoAnMaNguonMo`  
**Nhánh chính:** `main`  
**Commit mới nhất lúc viết:** `c1cc454` — `fix: sua kich co o thong ke va dao dung chieu cao thu gon xem them`

Tài liệu này hướng dẫn push commit ở máy phát triển lên GitHub và để bạn bè clone/pull, cài dependency, cấu hình database và chạy ứng dụng Laravel trên Windows/Laragon. Không commit `.env`, `vendor/`, `node_modules/` hoặc dữ liệu tài khoản riêng.

## 1. Push code lên GitHub

Mở PowerShell hoặc Git Bash:

```powershell
cd E:\OldF\java\laragon\www\DoAnMaNguonMo
git status --short --branch
git fetch origin
git log -5 --oneline --decorate
```

- `cd` vào thư mục dự án; đổi đường dẫn nếu repo ở nơi khác.
- `git status` cho biết nhánh, file chưa commit và trạng thái so với remote.
- `git fetch origin` cập nhật thông tin GitHub nhưng không sửa working tree.
- `git log` xem các commit mới nhất trước khi gửi.

Nếu có thay đổi cần commit, xem lại trước:

```powershell
git diff
git diff --cached
```

Sau khi xác nhận nội dung đúng, thêm file và commit:

```powershell
git add <ten-file-hoac-thu-muc>
git diff --cached
git commit -m "feat: mo ta ngan gon thay doi"
```

Thay `<ten-file-hoac-thu-muc>` bằng đường dẫn cụ thể. Có thể dùng `git add -A` sau khi đã xem `git status` nếu muốn thêm mọi thay đổi. `git diff --cached` kiểm tra phần sẽ được lưu. Commit gợi ý cho tài liệu này: `docs: them huong dan push va cai dat du an`.

Đẩy nhánh `main` lên GitHub:

```powershell
git status --short --branch
git push origin main
git status --short --branch
```

`git push origin main` gửi các commit local của nhánh `main` lên remote `origin`. Nếu Git báo remote có commit mới, không dùng `--force`; chạy `git pull --rebase origin main`, xử lý xung đột nếu có, rồi push lại. GitHub có thể yêu cầu xác thực qua Git Credential Manager hoặc SSH key.

### Tình trạng repo lúc viết

Working tree sạch, nhánh `main` ở `c1cc454` và đi trước `origin/main` 22 commit. Năm commit gần nhất:

- `c1cc454` — `fix: sua kich co o thong ke va dao dung chieu cao thu gon xem them`
- `b71465f` — `feat: lam moi giao dien trang ca nhan phong cach hoat hinh doodle tuoi sang`
- `a1404cf` — `feat: cap nhat background ben phai mau trang va tang co chu ben trai`
- `55cce19` — `feat: ket hop hoan hao bo cuc 2 cot voi dong chu nghieng ben trai va quy trinh doodle 2 buoc ben phai`
- `ef96969` — `feat: chuyen giao dien xac thuc ve bo cuc 2 cot voi dong chu nghieng nghe thuat ben trai va form dang nhap ben phai`

22 commit chưa push còn gồm việc chuyển dự án sang Laravel 13. Số liệu trên sẽ cũ theo thời gian; hãy kiểm tra lại bằng `git status` và `git log origin/main..HEAD --oneline` trước khi push.

## 2. Bạn bè clone hoặc cập nhật

Lần đầu:

```powershell
cd C:\laragon\www
git clone https://github.com/DevTrongVN/DoAnMaNguonMo.git
cd DoAnMaNguonMo
```

Đã clone trước đó:

```powershell
cd C:\laragon\www\DoAnMaNguonMo
git status
git switch main
git pull --ff-only origin main
```

`git pull --ff-only` cập nhật code nếu nhánh local không bị lệch với GitHub. Nếu có thay đổi riêng, hãy commit hoặc sao lưu trước.

## 3. Cài và chạy Laravel trên Windows

### Yêu cầu

- Windows 10/11 và Laragon có Apache, MySQL 8.x.
- PHP 8.3 trở lên, Composer 2, Node.js và npm.
- PHP extensions cần thiết gồm `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `dom`, `filter`, `hash`, `iconv`, `json`, `pdo`, `session`, `tokenizer` và `xml`.

Kiểm tra công cụ:

```powershell
php -v
composer --version
node --version
npm --version
```

Phiên bản PHP trong `composer.json` là `^8.3`. Nếu terminal không nhận `php`, mở Laragon Terminal hoặc thêm PHP của Laragon vào `PATH`.

### Cài dependency

```powershell
composer install
npm install
npm run build
```

`composer install` cài các PHP package theo `composer.lock`; `npm install` cài công cụ frontend; `npm run build` biên dịch tài nguyên frontend. Nếu repo không có `package-lock.json`, npm sẽ phân giải version theo `package.json`.

### Cấu hình môi trường

```powershell
Copy-Item .env.example .env
```

Mở `.env` và đặt cấu hình local, ví dụ Laragon MySQL:

```dotenv
APP_NAME="Goc Nho"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=doan_manguonmo_db
DB_USERNAME=root
DB_PASSWORD=
```

Tạo database rỗng `doan_manguonmo_db` trong phpMyAdmin/Laragon. Đây là cấu hình ví dụ; dùng thông tin MySQL đúng trên máy của bạn mình. Không gửi hoặc commit `.env`.

### Khởi tạo khóa và database

```powershell
php artisan key:generate
```

Repo có `database/database_full.sql`, `database/schema.sql` và các migration SQL. Nếu cần schema/dữ liệu ứng dụng, import SQL thích hợp vào database rỗng bằng phpMyAdmin sau khi kiểm tra nội dung. Chỉ chạy migration Laravel sau khi xác nhận nó phù hợp với schema hiện có:

```powershell
php artisan migrate
```

Không import SQL vào database đang có dữ liệu nếu chưa sao lưu. Một số tệp migration là SQL thuần và không được Laravel tự chạy như migration PHP; xem nội dung và thứ tự trước khi áp dụng.

### Khởi động web

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Mở `http://127.0.0.1:8000`. Dừng server bằng `Ctrl+C`. Khi phát triển frontend cần Vite hot reload, mở terminal thứ hai và chạy `npm run dev`, giữ tiến trình này hoạt động.

## 4. Kiểm tra sau cài đặt

```powershell
php artisan about
php artisan route:list
```

Nếu trang báo thiếu bảng, kiểm tra các biến `DB_*`, trạng thái MySQL và database/schema đang dùng. Nếu CSS/JS chưa cập nhật, chạy lại `npm run build` rồi tải trang bằng `Ctrl+F5`.

## 5. Quy trình commit/push về sau

```powershell
git status --short
git diff
git add <file-can-luu>
git diff --cached
git commit -m "fix: mo ta loi da sua"
git push origin main
```

Dùng `feat:` cho tính năng, `fix:` cho sửa lỗi, `docs:` cho tài liệu và `refactor:` cho thay đổi cấu trúc. Không đưa `TAI_KHOAN_TEST.txt`, `.env`, mật khẩu, API key, database cá nhân hay file upload vào commit.
