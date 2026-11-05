@extends('dashboard.layouts.app')
@section('title', 'طلب رقم ' . $requestDetails->request_id)
@section('content')
    @php
        $r = $requestDetails;
        $statusUrl = route('dashboard.requests-management.update-status');
        $statusConfirm = [
            'closed' => 'لن تتمكن الشركات من تقديم عروض جديدة على هذا الطلب.',
            'canceled' => 'سيُلغى الطلب ولن يظهر للشركات.',
        ];
        $available = $r->responses->where('status', '!=', 'unavailable');
        $lowest = $available->min('price');
        $customerLogo = $r->user_logo ? url('uploads/' . $r->user_logo) : null;
    @endphp

    <x-dash.page-header :title="'طلب رقم ' . $r->request_id" :subtitle="($r->cat_name_ar ?? '') . ' · ' . $r->request_date"
        :crumbs="['الطلبات' => route('dashboard.requests-management.index'), '#' . $r->request_id => null]">
        <div class="dropdown">
            <button type="button" class="btn btn-light" data-bs-toggle="dropdown">
                <x-dash.status type="request" :value="$r->request_status" /> <i class="bi bi-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">تغيير حالة الطلب</h6></li>
                @foreach (['open', 'completed', 'closed', 'canceled'] as $status)
                    @continue($status === $r->request_status)
                    <li>
                        <a href="#" class="dropdown-item" data-status-url="{{ $statusUrl }}" data-id="{{ $r->request_id }}"
                            data-status="{{ $status }}" @isset($statusConfirm[$status]) data-confirm="{{ $statusConfirm[$status] }}" @endisset>
                            <x-dash.status type="request" :value="$status" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <button type="button" class="btn btn-outline-danger"
            data-delete-url="{{ url('dashboard/requests-management/delete/' . $r->request_id) }}"
            data-confirm="سيتم حذف الطلب رقم {{ $r->request_id }} وكل ما يتعلق به."
            data-after="{{ route('dashboard.requests-management.index') }}">
            <i class="bi bi-trash3"></i> حذف
        </button>
    </x-dash.page-header>

    <div class="app-content">
        <div class="row g-3">
            <div class="col-lg-8 d-flex flex-column gap-3" data-live-region="request-main">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="bi bi-card-text icon"></i> تفاصيل الطلب</div>
                    </div>
                    <div class="card-body">
                        @if ($r->description)
                            <p class="mb-4" style="white-space: pre-line; font-size: .95rem">{{ $r->description }}</p>
                        @endif
                        <dl class="detail-list">
                            <x-dash.detail label="القسم">{{ $r->cat_name_ar }}</x-dash.detail>
                            <x-dash.detail label="تاريخ الطلب"><span class="ltr">{{ $r->request_date }}</span></x-dash.detail>
                            <x-dash.detail label="مدينة العميل">{{ $r->city_customer_name_ar }}</x-dash.detail>
                            <x-dash.detail label="المدن المستهدفة">
                                @foreach ($r->cities as $city)<span class="chip">{{ $city }}</span>@endforeach
                            </x-dash.detail>
                            <x-dash.detail label="الماركات">
                                @foreach ($r->brandsNames as $brand)<span class="chip">{{ $brand }}</span>@endforeach
                            </x-dash.detail>
                            @foreach ($r->customFields as $field)
                                <x-dash.detail :label="$field['key']" style="white-space: pre-line">{{ $field['value'] }}</x-dash.detail>
                            @endforeach
                        </dl>
                    </div>
                </div>

                @if ($r->requestImages->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title"><i class="bi bi-images icon"></i> الصور المرفقة <span class="text-muted fw-normal">({{ $r->requestImages->count() }})</span></div>
                        </div>
                        <div class="card-body">
                            <div class="gallery">
                                @foreach ($r->requestImages as $image)
                                    @php $src = route('uploads-private', ['filename' => $image->image_name]); @endphp
                                    <a href="{{ $src }}" target="_blank" rel="noopener"><img src="{{ $src }}" alt="صورة مرفقة بالطلب" loading="lazy"></a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card table-card" id="offers">
                    <div class="card-header">
                        <div class="card-title"><i class="bi bi-chat-square-text icon"></i> عروض الشركات <span class="text-muted fw-normal">({{ $r->responses->count() }})</span></div>
                    </div>
                    @if ($r->responses->isEmpty())
                        <x-dash.empty icon="bi-hourglass" title="لا توجد عروض بعد" text="ستظهر هنا عروض الشركات فور تقديمها." />
                    @else
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>الشركة</th>
                                        <th>التوفر</th>
                                        <th>السعر</th>
                                        <th>الضمان</th>
                                        <th>ملاحظات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($r->responses as $offer)
                                        <tr>
                                            <td>
                                                @if ($offer->vendor_user_id)
                                                    <a href="{{ route('dashboard.vendors-management.vendors.show', $offer->vendor_user_id) }}" class="cell-title">{{ $offer->company_name_ar ?? '—' }}</a>
                                                @else
                                                    <span class="cell-title">{{ $offer->company_name_ar ?? '—' }}</span>
                                                @endif
                                                <div class="cell-sub ltr text-end">{{ $offer->response_date ?? $offer->created_at?->format('Y-m-d H:i') }}</div>
                                            </td>
                                            <td><x-dash.status type="response" :value="$offer->status" /></td>
                                            <td class="text-nowrap">
                                                @if ($offer->price !== null)
                                                    <span class="fw-semibold num">{{ number_format($offer->price, 2) }}</span> <span class="text-muted small">ر.س</span>
                                                    @if ($lowest !== null && (float) $offer->price === (float) $lowest && $available->count() > 1 && $offer->status !== 'unavailable')
                                                        <span class="status-pill tone-success no-dot ms-1">الأقل</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $offer->warranty ?: '—' }}</td>
                                            <td class="text-muted small" style="max-width: 260px; white-space: pre-line">{{ $offer->note ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-lg-4 d-flex flex-column gap-3" data-live-region="request-side">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="bi bi-person icon"></i> العميل</div>
                    </div>
                    <div class="card-body">
                        <div class="entity mb-3">
                            <span class="avatar">
                                @if ($customerLogo)
                                    <img src="{{ $customerLogo }}" alt="" onerror="this.parentNode.textContent='{{ mb_substr($r->user_name ?? '؟', 0, 1) }}'">
                                @else
                                    {{ mb_substr($r->user_name ?? '؟', 0, 1) }}
                                @endif
                            </span>
                            <div class="min-w-0">
                                <div class="cell-title">{{ $r->user_name ?: 'بدون اسم' }}</div>
                                <div class="cell-sub">رقم الحساب {{ $r->user_id }}</div>
                            </div>
                            @if ($r->user_status)
                                <x-dash.status type="user" :value="$r->user_status" class="ms-auto" />
                            @endif
                        </div>
                        @if ($r->user_phone)
                            <a href="tel:{{ $r->user_phone }}" class="btn btn-light w-100"><i class="bi bi-telephone"></i> <span class="ltr">{{ $r->user_phone }}</span></a>
                        @endif
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-6 col-lg-12 col-xl-6">
                        <x-dash.stat label="العروض" :value="$r->responses->count()" icon="bi-chat-square-text" tone="info" />
                    </div>
                    <div class="col-6 col-lg-12 col-xl-6">
                        <x-dash.stat label="أقل سعر" :value="$lowest !== null ? number_format($lowest, 2) : '—'" icon="bi-tag" tone="success"
                            :hint="$lowest !== null ? 'ريال سعودي' : null" />
                    </div>
                </div>

                @if ($r->shipments->isNotEmpty())
                    <div class="card table-card">
                        <div class="card-header">
                            <div class="card-title"><i class="bi bi-truck icon"></i> الشحنات</div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <tbody>
                                    @foreach ($r->shipments as $ship)
                                        <tr class="row-link" data-href="{{ route('dashboard.shipping-request-management.show', $ship->id) }}">
                                            <td>
                                                <div class="cell-title">شحنة <span class="ltr">#{{ $ship->id }}</span></div>
                                                <div class="cell-sub">عرض <span class="ltr">#{{ $ship->response_id }}</span></div>
                                            </td>
                                            <td class="text-end"><x-dash.status type="shipping" :value="$ship->status" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
