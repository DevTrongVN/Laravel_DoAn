# ============================================================
# start.ps1 — Khởi động đồ án Góc Nhỏ (Laravel 13) trong VS Code
# Tự động kết nối MySQL và chạy Server Laravel trên cổng 8000
# ============================================================

$PHP_DIR   = "E:\OldF\java\laragon\bin\php\php-8.3.30-Win32-vs16-x64"
$MYSQL_DIR = "E:\OldF\java\laragon\bin\mysql\mysql-8.4.3-winx64"
$PHP       = Join-Path $PHP_DIR "php.exe"
$MYSQLD    = Join-Path $MYSQL_DIR "bin\mysqld.exe"
$MY_INI    = Join-Path $MYSQL_DIR "my.ini"
$MYSQL     = Join-Path $MYSQL_DIR "bin\mysql.exe"
$ARTISAN   = Join-Path $PSScriptRoot "artisan"
$PORT      = 8000

$env:PATH = "$PHP_DIR;$MYSQL_DIR\bin;" + $env:PATH

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   🚀  Góc Nhỏ — Laravel Framework (Chạy trên VS Code)" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Kiểm tra trạng thái MySQL 8.4
$mysqlRunning = $false
try {
    $conn = Test-NetConnection -ComputerName 127.0.0.1 -Port 3306 -WarningAction SilentlyContinue
    if ($conn.TcpTestSucceeded) {
        $mysqlRunning = $true
    }
} catch {}

if (-not $mysqlRunning) {
    Write-Host "🗄️  Đang khởi động MySQL 8.4.3..." -ForegroundColor Yellow
    $wmiCmd = "`"$MYSQLD`" --defaults-file=`"$MY_INI`""
    try {
        Invoke-CimMethod -ClassName Win32_Process -MethodName Create -Arguments @{CommandLine = $wmiCmd} | Out-Null
    } catch {
        Start-Process -FilePath $MYSQLD -ArgumentList "--defaults-file=`"$MY_INI`"" -WindowStyle Hidden
    }
    Start-Sleep -Seconds 3
    Write-Host "✅  MySQL đã được khởi động ngầm thành công." -ForegroundColor Green
} else {
    Write-Host "✅  MySQL đang hoạt động (cổng 3306)." -ForegroundColor Green
}

# 2. Kiểm tra kết nối cơ sở dữ liệu
Write-Host "🔍  Kiểm tra dữ liệu database doan_manguonmo_db..." -ForegroundColor Yellow
$dbCheck = & $MYSQL --user=root --password="" --connect-timeout=4 -e "USE doan_manguonmo_db; SELECT COUNT(*) AS posts FROM posts;" 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "✅  Database kết nối thành công!" -ForegroundColor Green
} else {
    Write-Host "⚠️  Cảnh báo: Không thể truy vấn database. Vui lòng kiểm tra MySQL." -ForegroundColor Red
}

# 3. Làm sạch cache Laravel để nhận cấu hình mới nhất
Write-Host "🧹  Làm mới bộ nhớ đệm (Cache)..." -ForegroundColor Yellow
& $PHP $ARTISAN config:clear 2>&1 | Out-Null
& $PHP $ARTISAN route:clear  2>&1 | Out-Null
& $PHP $ARTISAN view:clear   2>&1 | Out-Null
Write-Host "✅  Bộ nhớ đệm đã được làm mới." -ForegroundColor Green

# 4. Khởi động Laravel Development Server
Write-Host ""
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "   🌐  Trang chủ:   http://127.0.0.1:$PORT" -ForegroundColor White
Write-Host "   👤  Tài khoản:  http://127.0.0.1:$PORT/account" -ForegroundColor White
Write-Host "   🛠️  Quản trị:   http://127.0.0.1:$PORT/admin" -ForegroundColor White
Write-Host "   🔑  TK Test:     admin@example.com / admin12345" -ForegroundColor Yellow
Write-Host "   📌  Nhấn tổ hợp phím Ctrl + C để dừng Server" -ForegroundColor Gray
Write-Host "==========================================================" -ForegroundColor Green
Write-Host ""

Set-Location $PSScriptRoot
$env:PHP_CLI_SERVER_WORKERS = "4"
& $PHP $ARTISAN serve --host=127.0.0.1 --port=$PORT
