@php
    $attention = \App\Support\Dashboard\AdminAttention::counts();
    $menu = [
        ['header' => null, 'items' => [
            ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'bi-grid-1x2', 'label' => 'الرئيسية'],
        ]],
        ['header' => 'العمليات', 'items' => [
            ['route' => 'dashboard.requests-management.index', 'match' => ['dashboard.requests-management.*', 'dashboard.response-management.*'], 'icon' => 'bi-clipboard-check', 'label' => 'الطلبات والعروض'],
            ['route' => 'dashboard.shipping-request-management.index', 'match' => 'dashboard.shipping-request-management.*', 'icon' => 'bi-truck', 'label' => 'الشحن', 'badge' => $attention['shipping']],
            ['route' => 'dashboard.complaint-management.complaints', 'match' => 'dashboard.complaint-management.*', 'icon' => 'bi-flag', 'label' => 'الشكاوى', 'badge' => $attention['complaints']],
        ]],
        ['header' => 'الحسابات', 'items' => [
            ['route' => 'dashboard.vendors-management.vendors.index', 'match' => 'dashboard.vendors-management.vendors.*', 'icon' => 'bi-building', 'label' => 'الشركات'],
            ['route' => 'dashboard.vendors-management.join-requests.index', 'match' => 'dashboard.vendors-management.join-requests.*', 'icon' => 'bi-building-add', 'label' => 'طلبات الانضمام', 'badge' => $attention['joinRequests']],
            ['route' => 'dashboard.customers.index', 'match' => 'dashboard.customers.*', 'icon' => 'bi-people', 'label' => 'العملاء'],
        ]],
        ['header' => 'الإعدادات', 'items' => [
            ['route' => 'dashboard.categories.index', 'match' => ['dashboard.categories.*', 'dashboard.custom-fields.*'], 'icon' => 'bi-tags', 'label' => 'الأقسام والعمولات'],
            ['route' => 'dashboard.settings.notification-emails.index', 'match' => 'dashboard.settings.notification-emails.*', 'icon' => 'bi-envelope-at', 'label' => 'إيميلات الإشعارات'],
            ['route' => 'dashboard.logs.index', 'match' => 'dashboard.logs.*', 'icon' => 'bi-terminal', 'label' => 'سجلات النظام'],
        ]],
    ];
@endphp

<aside class="app-sidebar shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="brand-link">
            <span class="brand-mark"><i class="bi bi-car-front-fill"></i></span>
            <span class="brand-text">{{ config('app.name') }}<small>لوحة تحكم الإدارة</small></span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <nav>
            <ul class="nav sidebar-menu flex-column" role="menu">
                @foreach ($menu as $section)
                    @if ($section['header'])
                        <li class="nav-header">{{ $section['header'] }}</li>
                    @endif
                    @foreach ($section['items'] as $item)
                        @php $active = request()->routeIs(...(array) $item['match']); @endphp
                        <li class="nav-item">
                            <a href="{{ route($item['route']) }}" class="nav-link {{ $active ? 'active' : '' }}"
                                @if ($active) aria-current="page" @endif>
                                <i class="nav-icon bi {{ $item['icon'] }}"></i>
                                <p>
                                    {{ $item['label'] }}
                                    @if (!empty($item['badge']))
                                        <span class="nav-badge">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                    @endif
                                </p>
                            </a>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </nav>
    </div>
</aside>
