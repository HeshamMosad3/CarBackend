@php
    $attention = \App\Support\Dashboard\AdminAttention::counts();
    $recentAlerts = \App\Models\AdminAlert::latest('id')->limit(8)->get();
    $admin = auth('admin')->user();
    $adminLabel = $admin->email ?? 'الإدارة';
@endphp

<nav class="app-header navbar navbar-expand">
    <div class="container-fluid px-3">
        <ul class="navbar-nav align-items-center">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="إظهار/إخفاء القائمة">
                    <i class="bi bi-list fs-5"></i>
                </a>
            </li>
            <li class="nav-item d-none d-sm-block">
                {{-- Green while the dashboard is receiving changes live; amber if it is only checking every few seconds. --}}
                <span id="liveStatus" class="live-pill is-connecting" title="جارٍ الاتصال بالتحديث المباشر">
                    <span class="dot"></span><span class="live-text">مباشر</span>
                </span>
            </li>
        </ul>

        <ul class="navbar-nav ms-auto align-items-center gap-1">
            <li class="nav-item">
                <a class="nav-link" href="#" data-theme-toggle data-bs-toggle="tooltip" data-bs-placement="bottom"
                    title="الوضع الليلي" aria-label="تبديل الوضع الليلي">
                    <i data-theme-icon class="bi bi-moon-stars"></i>
                </a>
            </li>

            <li class="nav-item dropdown">
                <a class="nav-link position-relative" id="bellToggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" href="#"
                    aria-label="ما يحتاج متابعتك">
                    <i class="bi bi-bell fs-6" id="bellIcon"></i>
                    <span class="header-badge" id="bellBadge" @if ($attention['total'] === 0) hidden @endif>{{ $attention['total'] > 99 ? '99+' : $attention['total'] }}</span>
                </a>
                <div class="dropdown-menu dropdown-menu-end attention-menu">
                    <div id="attentionMenuBody">
                        @include('dashboard.included.attention-menu')
                    </div>

                    <div class="dropdown-divider"></div>
                    {{-- How this browser alerts the admin. Kept per browser (the settings page explains each part). --}}
                    <div class="live-controls px-2 pb-1">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <span class="small fw-semibold"><i class="bi bi-volume-up"></i> صوت التنبيهات</span>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="liveSoundToggle" aria-label="صوت التنبيهات">
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <span class="small fw-semibold"><i class="bi bi-window-stack"></i> إشعارات المتصفح</span>
                            <button type="button" class="btn btn-sm btn-light" id="liveDesktopBtn">…</button>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-light flex-grow-1" id="liveTestBtn"><i class="bi bi-play-circle"></i> جرّب التنبيه</button>
                            <a href="{{ route('dashboard.settings.notification-emails.index') }}" class="btn btn-sm btn-light flex-grow-1"><i class="bi bi-sliders"></i> الإعدادات</a>
                        </div>
                    </div>
                </div>
            </li>

            <li class="nav-item dropdown">
                <a href="#" class="nav-link user-chip" data-bs-toggle="dropdown" aria-label="حساب المدير">
                    <span class="avatar avatar-sm">{{ mb_strtoupper(mb_substr($adminLabel, 0, 1)) }}</span>
                    <span class="d-none d-md-inline text-body fw-semibold small">{{ $adminLabel }}</span>
                    <i class="bi bi-chevron-down small d-none d-md-inline"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end" style="min-width: 230px">
                    <div class="px-2 py-2 mb-1 border-bottom">
                        <div class="fw-bold">مدير النظام</div>
                        <div class="text-muted small text-truncate ltr text-end">{{ $adminLabel }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" data-no-lock>
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-left"></i> تسجيل الخروج
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </div>
</nav>
