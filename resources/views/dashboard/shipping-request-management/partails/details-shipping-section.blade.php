@php
    $mapLink = fn($lat, $lng) => $lat !== null && $lng !== null
        ? 'https://www.google.com/maps/search/?api=1&query=' . $lat . ',' . $lng
        : null;
    $pickupMap = $mapLink($shippingRequest->lat_origin_vendor, $shippingRequest->lng_origin_vendor);
    $deliveryMap = $mapLink($shippingRequest->lat_destination, $shippingRequest->lng_destination);
    $isLegacy = $shippingRequest->status->isLegacy();
    $packages = is_array($shippingRequest->packages) ? $shippingRequest->packages : [];
    $vendor = $parties['vendor'] ?? null;
    $customer = $parties['customer'] ?? null;
    $sar = fn($v) => $v !== null && $v !== '' ? number_format((float) $v, 2) . ' ر.س' : null;
@endphp

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-geo-alt icon"></i> المسار</div>
    </div>
    <div class="card-body">
        <div class="section-label"><i class="bi bi-shop"></i> من: التاجر</div>
        <dl class="detail-list mb-3">
            @unless ($isLegacy)
                <x-dash.detail label="التاجر">{{ $vendor->company_name_ar ?? '' }}</x-dash.detail>
            @endunless
            <x-dash.detail label="الجوال">
                @php $vendorPhone = $shippingRequest->phone_origin_vendor ?: ($vendor->phone_contact ?? ($vendor->phone ?? null)); @endphp
                @if ($vendorPhone)<a href="tel:{{ $vendorPhone }}" class="ltr">{{ $vendorPhone }}</a>@endif
            </x-dash.detail>
            <x-dash.detail label="المدينة">{{ $shippingRequest->city_origin_vendor }}</x-dash.detail>
            <x-dash.detail label="العنوان">{{ $shippingRequest->address_origin_vendor }}</x-dash.detail>
            @if ($pickupMap)
                <x-dash.detail label="الموقع"><a href="{{ $pickupMap }}" target="_blank" rel="noopener"><i class="bi bi-map"></i> عرض على الخريطة</a></x-dash.detail>
            @endif
        </dl>

        <div class="section-label"><i class="bi bi-person"></i> إلى: المستلم</div>
        <dl class="detail-list">
            <x-dash.detail label="الاسم">{{ $shippingRequest->name_destination ?: ($customer->name ?? '') }}</x-dash.detail>
            <x-dash.detail label="الجوال">
                @php $customerPhone = $shippingRequest->phone_origin_dimensions ?: ($customer->phone ?? null); @endphp
                @if ($customerPhone)<a href="tel:{{ $customerPhone }}" class="ltr">{{ $customerPhone }}</a>@endif
            </x-dash.detail>
            <x-dash.detail label="رقم الهوية" class="ltr text-end">{{ $shippingRequest->id_number_user }}</x-dash.detail>
            <x-dash.detail label="المدينة">{{ $shippingRequest->city_origin_dimensions }}</x-dash.detail>
            <x-dash.detail label="العنوان">{{ $shippingRequest->address_origin_dimensions }}</x-dash.detail>
            @if ($deliveryMap)
                <x-dash.detail label="الموقع"><a href="{{ $deliveryMap }}" target="_blank" rel="noopener"><i class="bi bi-map"></i> عرض على الخريطة</a></x-dash.detail>
            @endif
        </dl>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-box-seam icon"></i> الطرد</div>
        @if (count($packages) > 1)
            <span class="status-pill tone-info no-dot">{{ count($packages) }} كراتين</span>
        @endif
    </div>
    <div class="card-body">
        <div class="row g-2 text-center mb-{{ count($packages) > 1 ? 3 : 0 }}">
            @foreach (['الطول' => [$shippingRequest->length, 'سم'], 'العرض' => [$shippingRequest->width, 'سم'], 'الارتفاع' => [$shippingRequest->height, 'سم'], 'الوزن' => [$shippingRequest->weight, 'كغ']] as $label => [$value, $unit])
                <div class="col-3">
                    <div class="p-2 rounded-3" style="background: var(--dash-surface-2)">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fw-bold num">{{ $value ?? '—' }} <span class="small text-muted fw-normal">{{ $unit }}</span></div>
                    </div>
                </div>
            @endforeach
        </div>
        @if (count($packages) > 1)
            <ul class="list-unstyled mb-0 small">
                @foreach ($packages as $i => $package)
                    <li class="d-flex justify-content-between py-1 border-bottom" style="border-color: var(--dash-border) !important">
                        <span class="text-muted">كرتون {{ $i + 1 }}</span>
                        <span class="num">{{ ($package['length'] ?? '-') . '×' . ($package['width'] ?? '-') . '×' . ($package['height'] ?? '-') }} سم · {{ $package['weight'] ?? '-' }} كغ</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-cash-coin icon"></i> المبالغ</div>
    </div>
    <div class="card-body">
        <dl class="detail-list">
            @if ($isLegacy)
                <x-dash.detail label="سعر الشحن (OTO)">{{ $sar($shippingRequest->fee_cheapest_shipping) }}</x-dash.detail>
                <x-dash.detail label="عمولة التطبيق">{{ $sar($shippingRequest->amount_rate_app) }}</x-dash.detail>
                <x-dash.detail label="الإجمالي">{{ $sar((float) $shippingRequest->fee_cheapest_shipping + (float) $shippingRequest->amount_rate_app) }}</x-dash.detail>
                <x-dash.detail label="تأكيد الزبون">
                    @if ($shippingRequest->is_user_confirmed)
                        <span class="status-pill tone-success">مؤكد</span>
                    @else
                        <span class="status-pill tone-secondary">لم يؤكد</span>
                    @endif
                </x-dash.detail>
            @else
                <x-dash.detail label="تكلفة الشحن للزبون">{{ $sar($shippingRequest->shipping_cost) }}</x-dash.detail>
                <x-dash.detail label="الدفع">
                    <x-dash.status type="payment" :value="$shippingRequest->payment_status" />
                    @if ($shippingRequest->paid_at)
                        <span class="text-muted small ltr ms-1">{{ $shippingRequest->paid_at->format('Y-m-d H:i') }}</span>
                    @endif
                </x-dash.detail>
                @if ($shippingRequest->refunded_at)
                    <x-dash.detail label="تاريخ رد المبلغ"><span class="ltr">{{ $shippingRequest->refunded_at->format('Y-m-d H:i') }}</span></x-dash.detail>
                @endif
                @if ($shippingRequest->refund_note)
                    <x-dash.detail label="ملاحظة الرد">{{ $shippingRequest->refund_note }}</x-dash.detail>
                @endif
                <x-dash.detail label="تقدير OTO (مرجعي)">{{ $sar($shippingRequest->fee_cheapest_shipping ?: null) }}</x-dash.detail>
            @endif
        </dl>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-upc-scan icon"></i> التتبع والمعرّفات</div>
    </div>
    <div class="card-body">
        <dl class="detail-list">
            <x-dash.detail label="شركة الشحن">{{ $shippingRequest->carrier_name }}</x-dash.detail>
            <x-dash.detail label="طريقة التسليم للشركة">{{ \App\Support\Shipping\PickupMethod::label($shippingRequest->pickup_method) }}</x-dash.detail>
            <x-dash.detail label="رقم التتبع" class="ltr text-end">{{ $shippingRequest->tracking_number ?: $shippingRequest->oto_id }}</x-dash.detail>
            @if ($shippingRequest->pickup_details)
                <x-dash.detail label="بيانات الاستلام" style="white-space: pre-line">{{ $shippingRequest->pickup_details }}</x-dash.detail>
            @endif
            <x-dash.detail label="رقم طلب الشحن" class="ltr text-end">{{ $shippingRequest->order_number }}</x-dash.detail>
            <x-dash.detail label="الطلب / العرض">
                @if ($shippingRequest->request_id)
                    <a href="{{ route('dashboard.requests-management.show', $shippingRequest->request_id) }}#offers">طلب <span class="ltr">#{{ $shippingRequest->request_id }}</span></a>
                    · عرض <span class="ltr">#{{ $shippingRequest->response_id }}</span>
                @endif
            </x-dash.detail>
        </dl>
    </div>
</div>
