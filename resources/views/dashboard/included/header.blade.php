@php
    $attention = \App\Support\Dashboard\AdminAttention::counts();
    $admin = auth('admin')->user();
    $adminLabel = $admin->email ?? 'الإدارة';
    $attentionItems = [
        [
            'count' => $attention['shippingRefund'],
            'label' => 'مبالغ شحن يلزم ردّها',
            'icon' => 'bi-arrow-counterclockwise',
            'tone' => 'danger',
            'url' => route('dashboard.shipping-request-management.index', ['group' => 'refund']),
        ],
        [
            'count' => $attention['shippingAction'],
            'label' => 'طلبات شحن بانتظار إجراء',
            'icon' => 'bi-truck',
            'tone' => 'warning',
            'url' => route('dashboard.shipping-request-management.index', ['group' => 'action']),
        ],
        [
            'count' => $attention['joinRequests'],
            'label' => 'طلبات انضمام شركات',
            'icon' => 'bi-building-add',
            'tone' => 'primary',
            'url' => route('dashboard.vendors-management.join-requests.index'),
        ],
        [
            'count' => $attention['complaints'],
            'label' => 'شكاوى جديدة',
            'icon' => 'bi-flag',
            'tone' => 'info',
            'url' => route('dashboard.complaint-management.complaints'),
        ],
    ];
@endphp

<nav class="app-header navbar navbar-expand">
    <div class="container-fluid px-3">
        <ul class="navbar-nav align-items-center">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="إظهار/إخفاء القائمة">
                    <i class="bi bi-list fs-5"></i>
                </a>
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
                <a class="nav-link position-relative" data-bs-toggle="dropdown" href="#" aria-label="ما يحتاج متابعتك">
                    <i class="bi bi-bell fs-6"></i>
                    @if ($attention['total'] > 0)
                        <span class="header-badge">{{ $attention['total'] > 99 ? '99+' : $attention['total'] }}</span>
                    @endif
                </a>
                <div class="dropdown-menu dropdown-menu-end attention-menu">
                    <h6 class="dropdown-header">بانتظار متابعتك</h6>
                    @if ($attention['total'] === 0)
                        <div class="all-clear m-1">
                            <i class="bi bi-check2-circle fs-5"></i> لا يوجد ما يحتاج متابعتك الآن
                        </div>
                    @else
                        @foreach ($attentionItems as $item)
                            @continue($item['count'] === 0)
                            <a href="{{ $item['url'] }}" class="dropdown-item attention-item">
                                <span class="icon tone-{{ $item['tone'] }}"><i class="bi {{ $item['icon'] }}"></i></span>
                                <span class="flex-grow-1">{{ $item['label'] }}</span>
                                <span class="fw-bold num">{{ $item['count'] }}</span>
                            </a>
                        @endforeach
                    @endif
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
