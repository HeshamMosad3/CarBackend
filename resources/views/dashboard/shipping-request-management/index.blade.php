@extends('dashboard.layouts.app')
@section('title', 'الشحن')
@section('content')
    @php
        $groups = [
            '' => ['الكل', null],
            'action' => ['بانتظار إجراء منك', 'warning'],
            'refund' => ['يلزم رد المبلغ', 'danger'],
            'waiting' => ['بانتظار العميل', null],
            'active' => ['قيد الشحن', null],
            'done' => ['تم التسليم', null],
            'closed' => ['ملغاة / متعذرة', null],
        ];
        $current = array_key_exists((string) request('group'), $groups) ? (string) request('group') : '';
    @endphp

    <x-dash.page-header title="الشحن" subtitle="كل طلبات الشحن. ابدأ بتبويب «بانتظار إجراء منك» لمعرفة ما يحتاج تدخلك."
        :crumbs="['الشحن' => null]" />

    <div class="app-content">
        <div class="filter-chips mb-3" id="groupFilter" role="tablist" aria-label="تصفية الشحنات">
            @foreach ($groups as $value => [$label, $tone])
                @continue($value === 'refund' && ($groupCounts['refund'] ?? 0) === 0 && $current !== 'refund')
                <button type="button" class="filter-chip {{ $value === $current ? 'active' : '' }}" data-value="{{ $value }}">
                    @if ($tone && ($groupCounts[$value] ?? 0) > 0)
                        <i class="bi bi-circle-fill text-{{ $tone }}" style="font-size: .45rem"></i>
                    @endif
                    {{ $label }} <span class="count">{{ $groupCounts[$value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        <div class="card table-card">
            <table class="table table-hover w-100" id="datatableID">
                <thead>
                    <tr>
                        <th>الشحنة</th>
                        <th>المسار</th>
                        <th>جوال المستلم</th>
                        <th>المبلغ</th>
                        <th>التاريخ</th>
                        <th>الحالة</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@push('my-java-script')
    <script>
        $(function() {
            const legacyStatusUrl = @json(route('dashboard.shipping-request-management.update-status'));
            const showUrl = @json(url('dashboard/shipping-request-management/show')) + '/';
            const deleteUrl = @json(url('dashboard/shipping-request-management/delete')) + '/';
            const LEGACY = ['Pending', 'InProgress', 'Completed'];
            let group = @json($current);

            const table = dashTable({
                url: @json(route('dashboard.shipping-request-management.index')),
                searchPlaceholder: 'ابحث برقم الشحنة، الطلب، المدينة، الجوال، أو رقم التتبع...',
                emptyTable: 'لا توجد شحنات في هذا التبويب',
                order: [[4, 'desc']],
                filters: () => ({ group }),
                rowUrl: row => showUrl + row.id,
                columns: [{
                        data: 'id',
                        render: (d, t, row) => '<a href="' + showUrl + d + '" class="cell-title">شحنة <span class="ltr">#' + esc(d) + '</span></a>' +
                            '<div class="cell-sub">طلب <span class="ltr">#' + esc(row.request_id) + '</span> · عرض <span class="ltr">#' + esc(row.response_id) + '</span></div>'
                    },
                    {
                        data: 'city_origin_vendor',
                        orderable: false,
                        render: (d, t, row) => '<span class="text-nowrap">' + orDash(d) + ' <i class="bi bi-arrow-left-short text-muted"></i> ' + orDash(row.city_origin_dimensions) + '</span>' +
                            (row.carrier_name ? '<div class="cell-sub">' + esc(row.carrier_name) + (row.tracking_number ? ' · <span class="ltr">' + esc(row.tracking_number) + '</span>' : '') + '</div>' : '')
                    },
                    { data: 'phone_origin_dimensions', orderable: false, render: d => d ? '<span class="ltr">' + esc(d) + '</span>' : orDash(d) },
                    {
                        data: 'shipping_cost',
                        orderable: false,
                        render: (d, t, row) => {
                            // New flow: the amount the admin quoted. Legacy: OTO price + app fee.
                            if (d !== null && d !== undefined) return money(d);
                            if (LEGACY.includes(row.status)) return money(Number(row.fee_cheapest_shipping || 0) + Number(row.amount_rate_app || 0));
                            return '<span class="text-muted small">لم يُسعّر بعد</span>';
                        }
                    },
                    { data: 'shipping_request_date', render: d => fmtDate(d) },
                    {
                        data: 'status',
                        orderable: false,
                        render: (d, t, row) => {
                            let html;
                            if (LEGACY.includes(d)) {
                                // Legacy shipments still use the simple three-step status.
                                html = statusMenu('shipping', d, { url: legacyStatusUrl, id: row.id, values: LEGACY });
                                if (!row.is_user_confirmed) html += '<div class="cell-sub mt-1">لم يؤكده العميل</div>';
                                return html;
                            }
                            html = statusPill('shipping', d);
                            if (row.payment_status === 'paid' && (d === 'Canceled' || d === 'Unavailable')) {
                                html += ' <span class="status-pill tone-danger no-dot"><i class="bi bi-arrow-counterclockwise"></i> يلزم رد المبلغ</span>';
                            } else if (row.payment_status === 'paid' || row.payment_status === 'refunded') {
                                html += ' ' + statusPill('payment', row.payment_status, 'no-dot');
                            }
                            return html;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-end',
                        render: (d, t, row) => rowActions({
                            view: showUrl + row.id,
                            del: deleteUrl + row.id,
                            delConfirm: 'سيتم حذف الشحنة رقم ' + row.id + '.'
                        })
                    }
                ]
            });

            $('#groupFilter').on('click', '.filter-chip', function() {
                $(this).addClass('active').siblings().removeClass('active');
                group = this.dataset.value;
                const url = new URL(window.location);
                group ? url.searchParams.set('group', group) : url.searchParams.delete('group');
                history.replaceState(null, '', url);
                table.ajax.reload();
            });
        });
    </script>
@endpush
