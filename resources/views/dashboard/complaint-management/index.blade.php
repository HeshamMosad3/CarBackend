@extends('dashboard.layouts.app')
@section('title', 'الشكاوى')
@section('live_scope', 'complaints')
@section('content')
    @php
        $chips = ['' => 'الكل', 'new' => 'جديدة', 'under_review' => 'قيد المراجعة', 'resolved' => 'تم الحل', 'rejected' => 'مرفوضة', 'closed' => 'مغلقة'];
        $total = array_sum($statusCounts);
        $current = array_key_exists((string) request('status'), $chips) ? (string) request('status') : (($statusCounts['new'] ?? 0) > 0 ? 'new' : '');
    @endphp

    <x-dash.page-header title="الشكاوى" subtitle="شكاوى العملاء والشركات. اضغط على أي شكوى لقراءتها كاملة، وحدّث حالتها بعد المتابعة."
        :crumbs="['الشكاوى' => null]" />

    <div class="app-content">
        <div class="filter-chips mb-3" id="statusFilter" role="group" aria-label="تصفية حسب الحالة">
            @foreach ($chips as $value => $label)
                <button type="button" class="filter-chip {{ $value === $current ? 'active' : '' }}" data-value="{{ $value }}">
                    {{ $label }} <span class="count" data-count-key="complaints:{{ $value }}">{{ $value === '' ? $total : $statusCounts[$value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        <div class="card table-card">
            <table class="table table-hover w-100" id="datatableID">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>صاحب الشكوى</th>
                        <th>الموضوع</th>
                        <th>الشكوى</th>
                        <th>التاريخ</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <div class="modal fade" id="complaintModal" tabindex="-1" aria-labelledby="complaintTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="complaintTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body" id="complaintBody"></div>
                <div class="modal-footer" id="complaintActions"></div>
            </div>
        </div>
    </div>
@endsection

@push('my-java-script')
    <script>
        $(function() {
            const statusUrl = @json(route('dashboard.complaint-management.update-status'));
            const requestUrl = @json(url('dashboard/requests-management/show')) + '/';
            const STATUSES = ['new', 'under_review', 'resolved', 'rejected', 'closed'];
            const userTypes = { user: 'عميل', vendor: 'شركة' };
            let status = @json($current);
            const rows = {};

            const table = dashTable({
                url: @json(route('dashboard.complaint-management.complaints')),
                searchPlaceholder: 'ابحث بالاسم، الجوال، العنوان، أو نص الشكوى...',
                emptyTable: 'لا توجد شكاوى في هذا التبويب',
                order: [[0, 'desc']],
                filters: () => ({ status }),
                columns: [
                    { data: 'id', name: 'complaints.id', render: d => '<span class="num text-muted ltr">#' + esc(d) + '</span>' },
                    {
                        data: 'name',
                        name: 'users.name',
                        render: (d, t, row) => '<div class="cell-title">' + orDash(d) + '</div>' +
                            '<div class="cell-sub">' + (userTypes[row.user_type] || '') + (row.phone ? ' · <a href="tel:' + esc(row.phone) + '" class="ltr">' + esc(row.phone) + '</a>' : '') + '</div>'
                    },
                    { data: 'subject', name: 'complaints.subject', render: (d, t, row) => '<span class="chip mb-0">' + orDash(row.subject_label) + '</span>' },
                    {
                        data: 'title',
                        orderable: false,
                        render: (d, t, row) => {
                            rows[row.id] = row;
                            return '<a href="#" class="cell-title js-open" data-id="' + esc(row.id) + '">' + orDash(d) + '</a>' +
                                '<div class="cell-sub cell-truncate">' + esc(row.description) + '</div>';
                        }
                    },
                    { data: 'date_complaint', name: 'complaints.created_at', render: d => fmtDate(d) },
                    {
                        data: 'status',
                        orderable: false,
                        render: (d, t, row) => statusMenu('complaint', d, { url: statusUrl, id: row.id, values: STATUSES })
                    }
                ]
            });

            $('#statusFilter').on('click', '.filter-chip', function() {
                $(this).addClass('active').siblings().removeClass('active');
                status = this.dataset.value;
                table.ajax.reload();
            });

            // Full complaint in a modal, with the next sensible status one click away.
            $(document).on('click', '.js-open', function(e) {
                e.preventDefault();
                const c = rows[this.dataset.id];
                if (!c) return;
                $('#complaintTitle').text(c.title || 'شكوى رقم ' + c.id);
                $('#complaintBody').html(
                    '<div class="d-flex flex-wrap gap-2 align-items-center mb-3">' + statusPill('complaint', c.status) +
                    '<span class="chip mb-0">' + esc(c.subject_label) + '</span>' +
                    '<span class="text-muted small ms-auto">' + fmtDate(c.date_complaint) + '</span></div>' +
                    '<p style="white-space: pre-line; font-size: .95rem">' + esc(c.description) + '</p>' +
                    '<dl class="detail-list mt-3">' +
                    '<div class="detail-row"><dt>صاحب الشكوى</dt><dd>' + orDash(c.name) + ' <span class="text-muted fw-normal">(' + (userTypes[c.user_type] || '—') + ')</span></dd></div>' +
                    '<div class="detail-row"><dt>الجوال</dt><dd>' + (c.phone ? '<a href="tel:' + esc(c.phone) + '" class="ltr">' + esc(c.phone) + '</a>' : orDash(null)) + '</dd></div>' +
                    (c.request_id ? '<div class="detail-row"><dt>الطلب المرتبط</dt><dd><a href="' + requestUrl + c.request_id + '">طلب <span class="ltr">#' + esc(c.request_id) + '</span></a></dd></div>' : '') +
                    '</dl>'
                );

                const next = {
                    new: [['under_review', 'بدء المراجعة', 'btn-primary']],
                    under_review: [['resolved', 'تم الحل', 'btn-success'], ['rejected', 'رفض الشكوى', 'btn-light']],
                    resolved: [['closed', 'إغلاق', 'btn-light']],
                    rejected: [['closed', 'إغلاق', 'btn-light']],
                    closed: [['under_review', 'إعادة فتح', 'btn-light']]
                }[c.status] || [];
                $('#complaintActions').html(next.map(([s, label, cls]) =>
                    '<a href="#" class="btn ' + cls + '" data-status-url="' + statusUrl + '" data-id="' + c.id + '" data-status="' + s + '" data-after="table">' + label + '</a>'
                ).join('') + (c.phone ? '<a href="tel:' + esc(c.phone) + '" class="btn btn-light"><i class="bi bi-telephone"></i> اتصال</a>' : ''));

                bootstrap.Modal.getOrCreateInstance(document.getElementById('complaintModal')).show();
            });

            $('#complaintActions').on('click', '[data-status-url]', () => bootstrap.Modal.getInstance(document.getElementById('complaintModal'))?.hide());
        });
    </script>
@endpush
