@extends('dashboard.layouts.app')
@section('title', 'العملاء')
@section('content')
    <x-dash.page-header title="العملاء" subtitle="حسابات العملاء المسجّلين في التطبيق. الحساب غير النشط لا يستطيع إنشاء طلبات."
        :crumbs="['العملاء' => null]" />

    <div class="app-content">
        <div class="card table-card">
            <table class="table table-hover w-100" id="datatableID">
                <thead>
                    <tr>
                        <th>العميل</th>
                        <th>رقم الجوال</th>
                        <th>تاريخ التسجيل</th>
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
            const statusUrl = @json(route('dashboard.customers.update-status'));
            const deleteUrl = @json(url('dashboard/customers/delete')) + '/';

            dashTable({
                url: @json(route('dashboard.customers.index')),
                searchPlaceholder: 'ابحث بالاسم أو الجوال أو الرقم...',
                emptyTable: 'لا يوجد عملاء مسجّلون بعد',
                order: [[2, 'desc']],
                columns: [{
                        data: 'name',
                        render: (d, t, row) => '<div class="entity">' + avatar(row.name, uploadUrl(row.logo)) +
                            '<div class="min-w-0"><div class="cell-title">' + orDash(row.name) + '</div><div class="cell-sub">رقم ' + esc(row.id) + '</div></div></div>'
                    },
                    { data: 'phone', render: d => d ? '<a href="tel:' + esc(d) + '" class="ltr">' + esc(d) + '</a>' : orDash(d) },
                    { data: 'created_at', render: d => fmtDate(d, false) },
                    {
                        data: 'status',
                        render: (d, t, row) => statusMenu('user', d, {
                            url: statusUrl,
                            id: row.id,
                            values: ['Active', 'Inactive'],
                            confirm: { Inactive: 'لن يتمكن هذا العميل من استخدام التطبيق حتى تعيد تفعيله.' }
                        })
                    },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-end',
                        render: (d, t, row) => rowActions({
                            del: deleteUrl + row.id,
                            delConfirm: 'سيتم حذف حساب العميل «' + (row.name || row.phone) + '» وطلباته.'
                        })
                    }
                ]
            });
        });
    </script>
@endpush
