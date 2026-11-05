{{-- The bell's dropdown content. Rendered on page load and re-rendered by /dashboard/live/pulse. --}}
@php
    $items = [
        ['count' => $attention['shippingRefund'], 'label' => 'مبالغ شحن يلزم ردّها', 'icon' => 'bi-arrow-counterclockwise', 'tone' => 'danger', 'url' => route('dashboard.shipping-request-management.index', ['group' => 'refund'])],
        ['count' => $attention['shippingAction'], 'label' => 'طلبات شحن بانتظار إجراء', 'icon' => 'bi-truck', 'tone' => 'warning', 'url' => route('dashboard.shipping-request-management.index', ['group' => 'action'])],
        ['count' => $attention['joinRequests'], 'label' => 'طلبات انضمام شركات', 'icon' => 'bi-building-add', 'tone' => 'primary', 'url' => route('dashboard.vendors-management.join-requests.index')],
        ['count' => $attention['complaints'], 'label' => 'شكاوى جديدة', 'icon' => 'bi-flag', 'tone' => 'info', 'url' => route('dashboard.complaint-management.complaints', ['status' => 'new'])],
    ];
@endphp

<h6 class="dropdown-header">بانتظار متابعتك</h6>
@if ($attention['total'] === 0)
    <div class="all-clear m-1">
        <i class="bi bi-check2-circle fs-5"></i> لا يوجد ما يحتاج متابعتك الآن
    </div>
@else
    @foreach ($items as $item)
        @continue($item['count'] === 0)
        <a href="{{ $item['url'] }}" class="dropdown-item attention-item">
            <span class="icon tone-{{ $item['tone'] }}"><i class="bi {{ $item['icon'] }}"></i></span>
            <span class="flex-grow-1">{{ $item['label'] }}</span>
            <span class="fw-bold num">{{ $item['count'] }}</span>
        </a>
    @endforeach
@endif

@if (($recentAlerts ?? collect())->isNotEmpty())
    <div class="dropdown-divider"></div>
    <h6 class="dropdown-header">آخر التنبيهات</h6>
    @foreach ($recentAlerts as $alert)
        @php $type = $alert->alertType(); @endphp
        <a href="{{ $alert->url ?: '#' }}" class="dropdown-item alert-row" data-alert-id="{{ $alert->id }}">
            <span class="icon tone-{{ $alert->tone }}"><i class="bi {{ $type?->icon() ?? 'bi-bell' }}"></i></span>
            <span class="min-w-0 flex-grow-1">
                <span class="d-block fw-semibold text-truncate">{{ $alert->title }}</span>
                <span class="d-block small text-muted">{{ $alert->created_at?->locale('ar')->diffForHumans() }}</span>
            </span>
        </a>
    @endforeach
@endif
