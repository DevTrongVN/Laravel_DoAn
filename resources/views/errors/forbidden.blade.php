<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>403 - Cấm truy cập — Góc Nhỏ</title>
  <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f8fafc; color: #1e293b; }
    .error-card { background: #fff; padding: 36px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); max-width: 440px; text-align: center; }
    h1 { font-size: 24px; color: #dc2626; margin-top: 0; }
    p { color: #64748b; font-size: 14px; line-height: 1.6; }
    a { display: inline-block; margin-top: 16px; padding: 10px 20px; background: #0d9488; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; }
  </style>
</head>
<body>
  <div class="error-card">
    <h1>403 - Cấm truy cập</h1>
    <p>Tài khoản của bạn không có quyền Quản trị viên (Admin) để truy cập trang này.</p>
    <a href="{{ url('/') }}">Quay lại trang chủ</a>
  </div>
</body>
</html>
