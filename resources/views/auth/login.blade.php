<!DOCTYPE html>
<html lang="ar" dir="rtl" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>تسجيل الدخول · {{ config('app.name') }}</title>
    <script>
        try {
            var t = localStorage.getItem('dash-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', t);
        } catch (e) {}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('assets/dashboard/css/custom.css') }}?v={{ filemtime(public_path('assets/dashboard/css/custom.css')) }}">
    <style>
        body { min-height: 100vh; display: grid; place-items: center; padding: 1.5rem 1rem; }
        .login-shell { width: 100%; max-width: 420px; }
        .login-brand { display: flex; align-items: center; gap: .8rem; justify-content: center; margin-bottom: 1.75rem; }
        .login-brand .brand-mark { width: 46px; height: 46px; font-size: 1.3rem; border-radius: 13px; }
        .login-brand .name { font-weight: 700; font-size: 1.15rem; line-height: 1.2; }
        .login-brand .name small { display: block; font-weight: 400; color: var(--dash-muted); font-size: .8rem; }
        .login-card { padding: 2rem; }
        .password-toggle { position: absolute; inset-inline-end: .5rem; top: 50%; transform: translateY(-50%); }
    </style>
</head>

<body>
    <main class="login-shell">
        <div class="login-brand">
            <span class="brand-mark"><i class="bi bi-car-front-fill"></i></span>
            <span class="name">{{ config('app.name') }}<small>لوحة تحكم الإدارة</small></span>
        </div>

        <div class="card login-card">
            <h1 class="h5 mb-1">تسجيل الدخول</h1>
            <p class="text-muted small mb-4">أدخل بيانات حساب الإدارة للمتابعة.</p>

            @if (session('status'))
                <div class="alert alert-success"><i class="bi bi-check2-circle"></i> {{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">البريد الإلكتروني</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                        class="form-control ltr text-start @error('email') is-invalid @enderror" placeholder="admin@example.com">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">كلمة المرور</label>
                    <div class="position-relative">
                        <input id="password" type="password" name="password" required autocomplete="current-password"
                            class="form-control ltr text-start @error('password') is-invalid @enderror" style="padding-inline-end: 2.75rem">
                        <button type="button" class="btn btn-icon password-toggle" id="togglePassword" aria-label="إظهار كلمة المرور">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="form-check mb-4">
                    <input id="remember_me" type="checkbox" name="remember" class="form-check-input">
                    <label for="remember_me" class="form-check-label small">تذكرني على هذا الجهاز</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2" id="loginBtn">
                    <i class="bi bi-box-arrow-in-left"></i> دخول
                </button>
            </form>
        </div>

        <p class="text-center text-muted small mt-4 mb-0">© {{ now()->year }} {{ config('app.name') }}</p>
    </main>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const input = document.getElementById('password');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            this.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            this.setAttribute('aria-label', show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
        });
        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            setTimeout(() => {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> جارٍ الدخول...';
            });
        });
    </script>
</body>

</html>
