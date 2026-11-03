@extends('dashboard.layouts.app')
@section('title', 'الشركات')
@section('content')
    <x-dash.page-header title="الشركات" subtitle="الشركات والورش المقبولة في المنصة. الطلبات الجديدة تجدها في «طلبات الانضمام»."
        :crumbs="['الشركات' => null]">
        <a href="{{ route('dashboard.vendors-management.join-requests.index') }}" class="btn btn-light">
            <i class="bi bi-building-add"></i> طلبات الانضمام
            @if (($joinCount = \App\Support\Dashboard\AdminAttention::counts()['joinRequests']) > 0)
                <span class="badge rounded-pill text-bg-danger">{{ $joinCount }}</span>
            @endif
        </a>
    </x-dash.page-header>

    <div class="app-content">
        <div class="filter-chips mb-3" id="statusFilter" role="group" aria-label="تصفية حسب الحالة">
            <button type="button" class="filter-chip active" data-value="">الكل</button>
            <button type="button" class="filter-chip" data-value="Active">نشطة</button>
            <button type="button" class="filter-chip" data-value="Suspended">معلّقة</button>
            <button type="button" class="filter-chip" data-value="Inactive">غير نشطة</button>
            <button type="button" class="filter-chip" data-value="Rejected">مرفوضة</button>
        </div>

        <div class="card table-card">
            <table class="table table-hover w-100" id="datatableID">
                <thead>
                    <tr>
                        <th>الشركة</th>
                        <th>رقم الجوال</th>
                        <th>السجل التجاري</th>
                        <th>تاريخ الانضمام</th>
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
            const statusUrl = @json(route('dashboard.vendors-management.vendors.update-status'));
            const showUrl = @json(url('dashboard/vendors-management/vendors/show')) + '/';
            const deleteUrl = @json(url('dashboard/vendors-management/vendors/delete')) + '/';
            let status = '';

            const table = dashTable({
                url: @json(route('dashboard.vendors-management.vendors.index')),
                searchPlaceholder: 'ابحث باسم الشركة أو الجوال...',
                emptyTable: 'لا توجد شركات',
                order: [[3, 'desc']],
                filters: () => ({ status }),
                rowUrl: row => showUrl + row.id,
                columns: [{
                        data: 'company_name_ar',
                        render: (d, t, row) => '<div class="entity">' + avatar(d, uploadUrl(row.logo)) +
                            '<div class="min-w-0"><a href="' + showUrl + row.id + '" class="cell-title">' + orDash(d) + '</a><div class="cell-sub">رقم ' + esc(row.id) + '</div></div></div>'
                    },
                    { data: 'phone', orderable: false, render: d => d ? '<span class="ltr">' + esc(d) + '</span>' : orDash(d) },
                    { data: 'commercial_record', render: d => d ? '<span class="ltr num">' + esc(d) + '</span>' : orDash(d) },
                    { data: 'member_since', render: d => fmtDate(d, false) },
                    {
                        data: 'status',
                        orderable: false,
                        render: (d, t, row) => statusMenu('user', d, {
                            url: statusUrl,
                            id: row.id,
                            values: ['Active', 'Suspended', 'Inactive', 'Rejected'],
                            confirm: {
                                Suspended: 'لن تستقبل الشركة طلبات جديدة أثناء التعليق، وسيصلها إشعار بذلك.',
                                Inactive: 'سيتم تعطيل حساب الشركة وسيصلها إشعار بذلك.',
                                Rejected: 'سيتم رفض حساب الشركة وسيصلها إشعار بذلك.'
                            }
                        })
                    },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-end',
                        render: (d, t, row) => rowActions({
                            view: showUrl + row.id,
                            del: deleteUrl + row.id,
                            delConfirm: 'سيتم حذف الشركة «' + (row.company_name_ar || '') + '» وحسابها نهائياً.'
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
