<!DOCTYPE html>
<html lang="ar" dir="rtl" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · لوحة تحكم {{ config('app.name') }}</title>

    {{-- Apply the saved theme before anything paints, so dark mode never flashes white. --}}
    <script>
        (function() {
            try {
                var t = localStorage.getItem('dash-theme') ||
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-bs-theme', t);
            } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="{{ asset('assets/dashboard/css/adminlte.rtl.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/dashboard/css/custom.css') }}?v={{ filemtime(public_path('assets/dashboard/css/custom.css')) }}">
    @stack('css_styles')
</head>

<body class="layout-fixed sidebar-expand-lg" data-live-scope="@yield('live_scope')">
    <div class="app-wrapper">
        @include('dashboard.included.header')
        @include('dashboard.included.sidebar')

        <main class="app-main">
            @yield('content')
        </main>

        @include('dashboard.included.footer')
    </div>

    <script>
        var baseUrl = @json(url('/') . '/');
        window.DASH_STATUS = @json(\App\Support\Dashboard\StatusPresenter::all());
        window.DASH_LIVE = @json(\App\Support\Dashboard\LiveConfig::make($__env->yieldContent('live_scope')));
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.7/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js"></script>
    <script src="{{ asset('assets/dashboard/js/adminlte.js') }}"></script>
    <script src="{{ asset('assets/dashboard/js/custom.js') }}?v={{ filemtime(public_path('assets/dashboard/js/custom.js')) }}"></script>
    {{-- Live updates: websocket client (Reverb speaks the Pusher protocol) + the dashboard's live script. --}}
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <script src="{{ asset('assets/dashboard/js/live.js') }}?v={{ filemtime(public_path('assets/dashboard/js/live.js')) }}"></script>

    {{-- Flash messages and validation errors from the last request --}}
    <script>
        $(function() {
            @if (session()->has('success'))
                toast(@json(session('success')));
            @endif
            @if (session()->has('error'))
                toast(@json(session('error')), 'error');
            @endif
            @if ($errors->any())
                showErrors(@json($errors->all()));
            @endif
        });
    </script>

    @stack('my-java-script')
</body>

</html>
