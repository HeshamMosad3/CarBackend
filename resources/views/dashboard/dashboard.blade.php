@extends('dashboard.layouts.app')
@section('title', 'الرئيسية')
@section('content')
    @php
        $hour = now()->timezone(config('app.user_timezone', config('app.timezone')))->hour;
        $greeting = $hour < 12 ? 'صباح الخير' : 'مساء الخير';
        $attentionRows = [
            ['count' => $attention['shippingRefund'], 'label' => 'مبالغ شحن يلزم ردّها للزبائن', 'hint' => 'شحنات مدفوعة أُلغيت أو تعذّر توفيرها', 'icon' => 'bi-arrow-counterclockwise', 'tone' => 'danger', 'url' => route('dashboard.shipping-request-management.index', ['group' => 'refund'])],
            ['count' => $attention['shippingAction'], 'label' => 'طلبات شحن بانتظار إجراء منك', 'hint' => 'مراجعة، تسعير، أو تأكيد حجز', 'icon' => 'bi-truck', 'tone' => 'warning', 'url' => route('dashboard.shipping-request-management.index', ['group' => 'action'])],
            ['count' => $attention['joinRequests'], 'label' => 'طلبات انضمام شركات', 'hint' => 'بانتظار القبول أو الرفض', 'icon' => 'bi-building-add', 'tone' => 'primary', 'url' => route('dashboard.vendors-management.join-requests.index')],
            ['count' => $attention['complaints'], 'label' => 'شكاوى جديدة', 'hint' => 'لم تتم مراجعتها بعد', 'icon' => 'bi-flag', 'tone' => 'info', 'url' => route('dashboard.complaint-management.complaints')],
        ];
    @endphp

    <x-dash.page-header :title="$greeting" subtitle="نظرة عامة على نشاط المنصة وما يحتاج متابعتك اليوم." />

    <div class="app-content">
        <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3">
                <x-dash.stat label="الطلبات" :value="$stats['requests']" icon="bi-clipboard-check" tone="primary"
                    :hint="$stats['openRequests'] . ' مفتوح · ' . $stats['requestsToday'] . ' اليوم'"
                    :href="route('dashboard.requests-management.index')" />
            </div>
            <div class="col-6 col-xl-3">
                <x-dash.stat label="عروض الشركات" :value="$stats['responses']" icon="bi-chat-square-text" tone="info"
                    :hint="$stats['requests'] ? 'بمعدل ' . round($stats['responses'] / max(1, $stats['requests']), 1) . ' عرض لكل طلب' : null" />
            </div>
            <div class="col-6 col-xl-3">
                <x-dash.stat label="الشركات النشطة" :value="$stats['vendors']" icon="bi-building" tone="success"
                    :hint="$attention['joinRequests'] ? $attention['joinRequests'] . ' طلب انضمام بالانتظار' : 'لا توجد طلبات انضمام'"
                    :href="route('dashboard.vendors-management.vendors.index')" />
            </div>
            <div class="col-6 col-xl-3">
                <x-dash.stat label="العملاء" :value="$stats['customers']" icon="bi-people" tone="warning"
                    :hint="$stats['customersThisWeek'] . ' جديد خلال آخر 7 أيام'" :href="route('dashboard.customers.index')" />
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header">
                        <div>
                            <div class="card-title"><i class="bi bi-graph-up icon"></i> النشاط خلال آخر 14 يوماً</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-box"><canvas id="activityChart" aria-label="مخطط النشاط اليومي"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="card-title"><i class="bi bi-bell icon"></i> بانتظار متابعتك</div>
                    </div>
                    <div class="card-body d-flex flex-column gap-2">
                        @if ($attention['total'] === 0)
                            <div class="all-clear"><i class="bi bi-check2-circle fs-4"></i> كل شيء تحت السيطرة، لا يوجد ما ينتظرك.</div>
                        @endif
                        @foreach ($attentionRows as $row)
                            <a href="{{ $row['url'] }}" class="attention-row {{ $row['count'] === 0 ? 'is-clear' : '' }}">
                                <span class="icon tone-{{ $row['count'] ? $row['tone'] : 'secondary' }}"><i class="bi {{ $row['icon'] }}"></i></span>
                                <span class="min-w-0">
                                    <span class="d-block fw-semibold">{{ $row['label'] }}</span>
                                    <span class="d-block small text-muted">{{ $row['hint'] }}</span>
                                </span>
                                <span class="count">{{ $row['count'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-7">
                <div class="card table-card h-100">
                    <div class="card-header">
                        <div class="card-title"><i class="bi bi-clock-history icon"></i> أحدث الطلبات</div>
                        <a href="{{ route('dashboard.requests-management.index') }}" class="btn btn-sm btn-light">عرض الكل</a>
                    </div>
                    @if ($recentRequests->isEmpty())
                        <x-dash.empty icon="bi-clipboard" title="لا توجد طلبات بعد" />
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>الطلب</th>
                                        <th>المدينة</th>
                                        <th>العروض</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentRequests as $req)
                                        <tr class="row-link" data-href="{{ route('dashboard.requests-management.show', $req->id) }}">
                                            <td>
                                                <div class="cell-title"><span class="ltr">#{{ $req->id }}</span> · {{ $req->cat_name_ar ?? '—' }}</div>
                                                <div class="cell-sub">{{ $req->created_at?->locale('ar')->diffForHumans() }}</div>
                                            </td>
                                            <td>{{ $req->city_name_ar ?? '—' }}</td>
                                            <td class="num">{{ $req->responses_count }}</td>
                                            <td><x-dash.status type="request" :value="$req->status" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-xl-5 d-flex flex-column gap-3">
                <div class="card table-card">
                    <div class="card-header">
                        <div class="card-title"><i class="bi bi-truck icon"></i> شحنات تنتظرك</div>
                        <a href="{{ route('dashboard.shipping-request-management.index', ['group' => 'action']) }}" class="btn btn-sm btn-light">عرض الكل</a>
                    </div>
                    @if ($shipmentsToHandle->isEmpty())
                        <x-dash.empty icon="bi-check2-circle" title="لا توجد شحنات بانتظارك" />
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <tbody>
                                    @foreach ($shipmentsToHandle as $ship)
                                        <tr class="row-link" data-href="{{ route('dashboard.shipping-request-management.show', $ship->id) }}">
                                            <td>
                                                <div class="cell-title">شحنة <span class="ltr">#{{ $ship->id }}</span></div>
                                                <div class="cell-sub">{{ $ship->city_origin_vendor ?: '—' }} <i class="bi bi-arrow-left-short"></i> {{ $ship->city_origin_dimensions ?: '—' }}</div>
                                            </td>
                                            <td class="text-end">
                                                @if ($ship->payment_status === 'paid' && in_array($ship->status->value, ['Canceled', 'Unavailable']))
                                                    <span class="status-pill tone-danger">يلزم رد المبلغ</span>
                                                @else
                                                    <x-dash.status type="shipping" :value="$ship->status" />
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="card table-card">
                    <div class="card-header">
                        <div class="card-title"><i class="bi bi-building-add icon"></i> طلبات الانضمام</div>
                        <a href="{{ route('dashboard.vendors-management.join-requests.index') }}" class="btn btn-sm btn-light">عرض الكل</a>
                    </div>
                    @if ($pendingVendors->isEmpty())
                        <x-dash.empty icon="bi-check2-circle" title="لا توجد طلبات انضمام معلّقة" />
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <tbody>
                                    @foreach ($pendingVendors as $vendor)
                                        <tr class="row-link" data-href="{{ route('dashboard.vendors-management.join-requests.show', $vendor->id) }}">
                                            <td>
                                                <div class="cell-title">{{ $vendor->company_name_ar ?: '—' }}</div>
                                                <div class="cell-sub">سجل تجاري: <span class="ltr">{{ $vendor->commercial_record ?: '—' }}</span></div>
                                            </td>
                                            <td class="text-end text-muted small">{{ $vendor->created_at?->locale('ar')->diffForHumans() }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('my-java-script')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const data = @json($chart);
            const el = document.getElementById('activityChart');
            if (!el || !window.Chart) return;

            let chart;
            function render() {
                const css = getComputedStyle(document.documentElement);
                const muted = css.getPropertyValue('--dash-muted').trim();
                const border = css.getPropertyValue('--dash-border').trim();
                const primary = css.getPropertyValue('--dash-primary').trim();
                Chart.defaults.font.family = css.getPropertyValue('--dash-font');
                if (chart) chart.destroy();

                chart = new Chart(el, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                                label: 'الطلبات',
                                data: data.requests,
                                borderColor: primary,
                                backgroundColor: primary + '22',
                                fill: true,
                                cubicInterpolationMode: 'monotone',
                                pointRadius: 3,
                            },
                            {
                                label: 'العروض',
                                data: data.responses,
                                borderColor: '#14b8a6',
                                cubicInterpolationMode: 'monotone',
                                pointRadius: 3,
                            },
                            {
                                label: 'الشحنات',
                                data: data.shipments,
                                borderColor: '#f59e0b',
                                cubicInterpolationMode: 'monotone',
                                pointRadius: 3,
                            }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        layout: { padding: { left: 8, right: 8 } },
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'top', align: 'end', labels: { color: muted, usePointStyle: true, boxWidth: 8 } },
                            tooltip: { rtl: true, textDirection: 'rtl' }
                        },
                        scales: {
                            x: { reverse: true, grid: { display: false }, ticks: { color: muted } },
                            y: { beginAtZero: true, position: 'right', grid: { color: border }, ticks: { color: muted, precision: 0 } }
                        }
                    }
                });
            }

            render();
            document.addEventListener('dash:theme', render);
        })();
    </script>
@endpush
