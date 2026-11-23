<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#f6f5f1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', $pageTitle ?? 'Góc Nhỏ — Blog cá nhân')</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Inter:wght@400;500;600;700&family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap&subset=vietnamese" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}?v=20261118-laravel1">
  <link rel="stylesheet" href="{{ asset('assets/css/components.css') }}?v=20261118-laravel1">
  <link rel="stylesheet" href="{{ asset('assets/css/blog-theme.css') }}?v=20261118-laravel1">
  <link rel="stylesheet" href="{{ asset('assets/css/animations.css') }}?v=20261118-laravel1">
  @stack('styles')
</head>
<body class="blog-home">
  @include('layouts.navbar')

  <main class="page-shell" id="main-content">
    <div class="editorial-layout">
      @include('layouts.left_sidebar')

      <section class="main-feed" aria-label="Bảng tin">
        @yield('content')
      </section>

      @include('layouts.right_sidebar')
    </div>
  </main>

  @include('components.ai_writer_modal')
  @include('components.ticket_modal')

  <script>window.APP_URL = "{{ url('/') }}";</script>
  <script src="{{ asset('assets/js/csrf.js') }}?v=20261118-laravel1"></script>
  <script src="{{ asset('assets/js/widget_pagination.js') }}?v=20261118-laravel1"></script>
  <script src="{{ asset('assets/js/avatar-picker.js') }}?v=20261118-laravel1"></script>
  <script src="{{ asset('assets/js/app.js') }}?v=20261118-laravel1"></script>
  <script src="{{ asset('assets/js/ai_assistant.js') }}?v=20261118-laravel1"></script>
  <script src="{{ asset('assets/js/feed_pagination.js') }}?v=20261118-laravel1"></script>
  <script src="{{ asset('assets/js/animations.js') }}?v=20261118-laravel1"></script>
  @stack('scripts')
</body>
</html>
