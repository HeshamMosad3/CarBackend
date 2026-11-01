@extends('dashboard.layouts.app')
@section('title', 'إدارة طلبات الشحن')
@section('content')
    <main class="app-main">
        <div class="app-content-header py-2">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-start">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item active" aria-current="page">
                                إدارة طلبات الشحن
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="app-content">
            <div class="container-fluid">
                <div class="card card-primary card-outline mb-4 mt-1">
                    <div class="card-header py-2">
                        <div class="card-title">إدارة طلبات الشحن</div>
                    </div>
                    <div class="card-body">
                        {{-- Search Filter  --}}
                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <div class="form-floating">
                                    <select class="form-control form-select form-control-sm" id="confirmShippingFilter">
                                        <option value="1" selected>الطلبات المؤكدة</option>
                                        <option value="">الكل</option>
                                    </select>
                                    <label for="confirmShippingFilter">الطلبات</label>
                                </div>
                            </div>
                            <div class="col-md-2 mb-3">
                                <button class="btn btn-primary h-100 w-100" id="btnSearchFilterDataTableId">فلترة</button>
                            </div>
                        </div>
                        {{-- end Search Filter  --}}
                        <div class="table-responsive mt-2" style="padding-bottom: 120px;">
                            <table class="table table-hover nowrap " id="datatableID"
                                style="width:100%; padding-bottom: 100px;">
                                <thead>
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th class="text-center">رقم الطلب</th>
                                        <th class="text-center">رقم الرد</th>
                                        <th class="text-center">مدينة الإرسال</th>
                                        <th class="text-center">مدينة الإستلام</th>
                                        <th class="text-center">رقم المستلم</th>
                                        <th class="text-center">مبلغ الشحن</th>
                                        <th class="text-center">المبلغ الإجمالي</th>
                                        <th class="text-center">موافقة الشحن</th>
                                        <th class="text-center">التاريخ</th>
                                        <th class="text-center">الحالة</th>
                                        <th class="text-center"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection

@push('my-java-script')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @include('dashboard.included.toast-message')
    @include('shared.show-alert-validation-error')
    <script>
        $(document).ready(function() {
            var statusLabels = @json(collect(\App\Enums\StatusShippingRequestEnum::cases())->mapWithKeys(fn($case) => [$case->value => \App\Enums\StatusShippingRequestEnum::trans($case->value)]));

            var _columns = eval(
                '[{"columns" : [{"data": "id", "className": "text-center align-middle"}, {"data": "request_id", "className": "text-center align-middle"}, {"data": "response_id", "className": "text-center align-middle"}, {"data": "city_origin_vendor", "className": "text-center align-middle"},{"data": "city_origin_dimensions", "className": "text-center align-middle"},{"data": "phone_origin_dimensions", "className": "text-center align-middle"},{"data": "fee_cheapest_shipping", "className": "text-center align-middle"}]}]'
            );

            _columns[0].columns.push({
                "data": null,
                "orderable": false,
                "className": "text-center",
                "render": function(data, type, row, meta) {
                    // New flow: the amount the admin quoted. Legacy shipments: OTO price + app fee.
                    if (data.shipping_cost !== null && data.shipping_cost !== undefined) {
                        return data.shipping_cost;
                    }
                    return data.fee_cheapest_shipping + data.amount_rate_app;
                }
            });

            _columns[0].columns.push({
                "data": null,
                "orderable": false,
                "className": "text-center",
                "render": function(data, type, row, meta) {
                    if (data.is_user_confirmed) {
                        return '<span class="badge bg-success px-3 py-2">نعم</span>';
                    } else {
                        return '<span class="badge bg-danger px-3 py-2">لا</span>';
                    }
                }
            });

            _columns[0].columns.push({
                "data": null,
                "orderable": false,
                "className": "text-center",
                "render": function(data, type, row, meta) {
                    return data.shipping_request_date;
                }
            });

            _columns[0].columns.push({
                "data": null,
                "orderable": false,
                "className": "text-center align-middle",
                "render": function(data, type, row, meta) {
                    var text =
                        '<div class="dropdown"> <a class="btn btn-white btn-sm dropdown-toggle btn-rounded-dropdown" href="javascript:void(0)" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
                    if (data.status == '{{ 'Pending' }}') {
                        text +=
                            '<i class="fa-regular fa-circle-dot text-primary me-1"></i><span class="mx-2">قيد الانتظار</span></a>';
                    } else if (data.status == '{{ 'InProgress' }}') {
                        text +=
                            '<i class="fa-regular fa-circle-dot text-warning me-1"></i><span class="mx-2">جاري المعالجة</span></a>';
                    } else if (data.status == '{{ 'Completed' }}') {
                        text +=
                            '<i class="fa-regular fa-circle-dot text-success me-1"></i><span class="mx-2">مكتمل</span></a>';
                    } else {
                        // New-flow shipments are managed from the details page, not from this dropdown.
                        var label = statusLabels[data.status] || data.status;
                        var color = (data.status == 'Canceled' || data.status == 'Unavailable') ? 'danger' :
                            (data.status == 'Delivered' ? 'success' :
                                (data.status == 'ReadyForReview' ? 'warning' : 'primary'));
                        var badges = '<span class="badge bg-' + color + ' px-3 py-2">' + label + '</span>';

                        if (data.payment_status == 'paid' && (data.status == 'Canceled' || data.status == 'Unavailable')) {
                            badges += ' <span class="badge bg-danger px-2 py-2">يلزم رد المبلغ</span>';
                        } else if (data.payment_status == 'paid') {
                            badges += ' <span class="badge bg-success px-2 py-2">مدفوع</span>';
                        } else if (data.payment_status == 'refunded') {
                            badges += ' <span class="badge bg-secondary px-2 py-2">تم رد المبلغ</span>';
                        }
                        return badges;
                    }

                    text += '<ul class="dropdown-menu">' +
                        '<li><a class="dropdown-item statusUpdateDropdownId" href="javascript:void(0)" data-id="' +
                        data.id +
                        '" data-status="Pending"><i class="fa-regular fa-circle-dot text-primary mx-2"></i><span class="mx-2">قيد الانتظار</span></a></li>' +

                        '<li><a class="dropdown-item statusUpdateDropdownId" href="javascript:void(0)" data-id="' +
                        data.id +
                        '" data-status="InProgress"><i class="fa-regular fa-circle-dot text-warning mx-2"></i><span class="mx-2">جاري المعالجة</span></a></li>' +

                        '<li><a class="dropdown-item statusUpdateDropdownId" href="javascript:void(0)" data-id="' +
                        data.id +
                        '" data-status="Completed"><i class="fa-regular fa-circle-dot text-success mx-2"></i><span class="mx-2">مكتمل</span></a></li>' +
                        '</ul>';
                    return text += '</div>';
                }
            });

            _columns[0].columns.push({
                "data": null,
                "orderable": false,
                "className": "text-center align-middle",
                "render": function(data, type, row, meta) {
                    var text =
                        '<div class="dropdown dropdown-action mx-3"><a  class="dropdown dropdown-toggle action-icon" data-bs-toggle="dropdown" href="javascript:void(0)" aria-expanded="false"><i class="fa-solid fa-ellipsis-vertical fs-4 fw-bold"></i></a><div class="dropdown-menu">';

                    text += '<a href="' +
                        baseUrl + 'dashboard/shipping-request-management/show/' + data.id +
                        '" class="dropdown-item"><i class="fa-solid fa-eye me-2 color-hint"></i>عرض</a>';

                    text += '<a id="deleteActionDropdownId" data-id="' + data.id +
                        '" data-action="' +
                        baseUrl + 'dashboard/shipping-request-management/delete/' + data
                        .id +
                        '" class="dropdown-item mt-1" href="javascript:void(0)"><i class="fa-solid fa-trash-can me-2 color-hint"></i>حذف</a>';

                    return text += '</div></div>';
                }
            });
            let myDatatable = customDatatable({
                url: '{{ 'dashboard/shipping-request-management' }}',
                dataFilter: function(d) {
                    d.confirmShippingFilter = $('#confirmShippingFilter').val();
                },
                columns: _columns
            });

            $(document).on('click', 'a.statusUpdateDropdownId', function(e) {
                let id = $(this).data('id');
                let status = $(this).data('status');
                let formData = new FormData();
                formData.append('id', id);
                formData.append('status', status);
                ajax_setup();
                postDataAjax({
                    url: baseUrl + `dashboard/shipping-request-management/update-status`,
                    formData: formData,
                    success: function(res) {
                        if (res.success == true) {
                            $('#datatableID').DataTable().ajax.reload();
                            swalToast({
                                title: res.message
                            });
                        } else {
                            swalToast({
                                title: res.message,
                                icon: 'error'
                            });
                        }
                    }
                });
            });

            $(document).on('click', 'a#deleteActionDropdownId', function(e) {
                e.preventDefault();
                var dataDelete = $(this).data('id');
                var myUrl = $(this).data('action');
                swalAlertDeleteConfirm({
                    isConfirmed: function() {
                        deleteDataAjax({
                            url: myUrl,
                            success: function(res) {
                                if (res.success) {
                                    $('#datatableID').DataTable().ajax.reload();
                                } else {
                                    swalToast({
                                        title: res.message,
                                        icon: 'error'
                                    });
                                }
                            }
                        });
                    }
                });
            });
        })
    </script>
@endpush
