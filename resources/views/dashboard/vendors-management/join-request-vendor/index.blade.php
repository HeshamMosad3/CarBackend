@extends('dashboard.layouts.app')
@section('title', 'طلبات الانضمام')
@section('content')
    <x-dash.page-header title="طلبات الانضمام" subtitle="شركات وورش سجّلت وتنتظر المراجعة. افتح الطلب لمراجعة السجل التجاري ثم اقبله أو ارفضه."
        :crumbs="['الشركات' => route('dashboard.vendors-management.vendors.index'), 'طلبات الانضمام' => null]" />

    <div class="app-content">
        <div class="card table-card">
            <table class="table table-hover w-100" id="datatableID">
                <thead>
                    <tr>
                        <th>الشركة</th>
                        <th>رقم الجوال</th>
                        <th>السجل التجاري</th>
                        <th>تاريخ الطلب</th>
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
            const showUrl = @json(url('dashboard/vendors-management/join-requests/show')) + '/';
            const deleteUrl = @json(url('dashboard/vendors-management/join-requests/delete')) + '/';

            dashTable({
                url: @json(route('dashboard.vendors-management.join-requests.index')),
                searchPlaceholder: 'ابحث باسم الشركة أو الجوال...',
                emptyTable: 'لا توجد طلبات انضمام بانتظار المراجعة 🎉',
                order: [[3, 'asc']],
                rowUrl: row => showUrl + row.id,
                columns: [{
                        data: 'company_name_ar',
                        render: (d, t, row) => '<div class="entity">' + avatar(d, uploadUrl(row.logo)) +
                            '<div class="min-w-0"><a href="' + showUrl + row.id + '" class="cell-title">' + orDash(d) + '</a><div class="cell-sub">رقم ' + esc(row.id) + '</div></div></div>'
                    },
                    { data: 'phone', orderable: false, render: d => d ? '<span class="ltr">' + esc(d) + '</span>' : orDash(d) },
                    { data: 'commercial_record', render: d => d ? '<span class="ltr num">' + esc(d) + '</span>' : orDash(d) },
                    { data: 'member_since', render: d => fmtDate(d) },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-end',
                        render: (d, t, row) => '<div class="row-actions"><a href="' + showUrl + row.id + '" class="btn btn-sm btn-primary">مراجعة الطلب</a>' +
                            rowActions({ del: deleteUrl + row.id, delConfirm: 'سيتم حذف طلب الانضمام وحساب الشركة نهائياً. لإبلاغ الشركة بالسبب استخدم «رفض» من صفحة الطلب.' })
                            .replace('<div class="row-actions">', '')
                    }
                ]
            });
        });
    </script>
@endpush
