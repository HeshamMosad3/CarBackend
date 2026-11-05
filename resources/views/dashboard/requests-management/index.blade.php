@extends('dashboard.layouts.app')
@section('title', 'الطلبات والعروض')
@section('live_scope', 'requests')
@section('content')
    @php
        $chips = ['' => 'الكل', 'open' => 'مفتوحة', 'completed' => 'مكتملة', 'closed' => 'مغلقة', 'canceled' => 'ملغية'];
        $total = array_sum($statusCounts);
    @endphp

    <x-dash.page-header title="الطلبات والعروض" subtitle="طلبات العملاء وعروض الشركات عليها. افتح أي طلب لرؤية تفاصيله وكل العروض المقدّمة."
        :crumbs="['الطلبات' => null]" />

    <div class="app-content">
        <div class="filter-chips mb-3" id="statusFilter" role="group" aria-label="تصفية حسب الحالة">
            @foreach ($chips as $value => $label)
                <button type="button" class="filter-chip {{ $value === '' ? 'active' : '' }}" data-value="{{ $value }}">
                    {{ $label }} <span class="count" data-count-key="requests:{{ $value }}">{{ $value === '' ? $total : $statusCounts[$value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        <div class="card table-card">
            <table class="table table-hover w-100" id="datatableID">
                <thead>
                    <tr>
                        <th>الطلب</th>
                        <th>العميل</th>
                        <th>المدينة</th>
                        <th>التاريخ</th>
                        <th>العروض</th>
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
            const statusUrl = @json(route('dashboard.requests-management.update-status'));
            const showUrl = @json(url('dashboard/requests-management/show')) + '/';
            const deleteUrl = @json(url('dashboard/requests-management/delete')) + '/';
            let status = '';

            const table = dashTable({
                url: @json(route('dashboard.requests-management.index')),
                searchPlaceholder: 'ابحث برقم الطلب، القسم، المدينة، أو العميل...',
                emptyTable: 'لا توجد طلبات',
                order: [[0, 'desc']],
                filters: () => ({ status }),
                rowUrl: row => showUrl + row.request_id,
                columns: [{
                        data: 'request_id',
                        render: (d, t, row) => '<a href="' + showUrl + d + '" class="cell-title ltr d-inline-block">#' + esc(d) + '</a><div class="cell-sub">' + orDash(row.cat_name_ar) + '</div>'
                    },
                    { data: 'customer_name', orderable: false, render: d => orDash(d) },
                    { data: 'city_customer_name_ar', orderable: false, render: d => orDash(d) },
                    { data: 'request_date', render: d => fmtDate(d) },
                    {
                        data: 'count_response',
                        render: d => Number(d) > 0 ?
                            '<span class="status-pill tone-primary no-dot"><i class="bi bi-chat-square-text"></i> ' + esc(d) + '</span>' :
                            '<span class="text-muted small">لا توجد عروض</span>'
                    },
                    {
                        data: 'request_status',
                        orderable: false,
                        render: (d, t, row) => statusMenu('request', d, {
                            url: statusUrl,
                            id: row.request_id,
                            values: ['open', 'completed', 'closed', 'canceled'],
                            confirm: {
                                closed: 'لن تتمكن الشركات من تقديم عروض جديدة على هذا الطلب.',
                                canceled: 'سيُلغى الطلب ولن يظهر للشركات.'
                            }
                        })
                    },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-end',
                        render: (d, t, row) => rowActions({
                            view: showUrl + row.request_id,
                            del: deleteUrl + row.request_id,
                            delConfirm: 'سيتم حذف الطلب رقم ' + row.request_id + ' وكل ما يتعلق به.'
                        })
                    }
                ]
            });

            $('#statusFilter').on('click', '.filter-chip', function() {
                $(this).addClass('active').siblings().removeClass('active');
                status = this.dataset.value;
                table.ajax.reload();
            });
        });
    </script>
@endpush
