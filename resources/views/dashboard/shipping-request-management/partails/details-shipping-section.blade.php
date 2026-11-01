@php
    $mapLink = fn($lat, $lng) => $lat !== null && $lng !== null
        ? 'https://www.google.com/maps/search/?api=1&query=' . $lat . ',' . $lng
        : null;
    $pickupMap = $mapLink($shippingRequest->lat_origin_vendor, $shippingRequest->lng_origin_vendor);
    $deliveryMap = $mapLink($shippingRequest->lat_destination, $shippingRequest->lng_destination);
    $isLegacy = $shippingRequest->status->isLegacy();
@endphp

@if (!$isLegacy)
    <div class="card card-primary card-outline mb-4 mt-1">
        <div class="card-header py-2">
            <div class="card-title">الأطراف</div>
        </div>
        <div class="card-body">
            <table class="table table-borderless" style="width:100%">
                <tbody>
                    <tr>
                        <th style="width:30%">التاجر</th>
                        <td>{{ $parties['vendor']->company_name_ar ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>جوال التاجر</th>
                        <td dir="ltr" class="text-end">{{ $parties['vendor']->phone_contact ?? ($parties['vendor']->phone ?? '-') }}</td>
                    </tr>
                    <tr>
                        <th>الزبون</th>
                        <td>{{ $shippingRequest->name_destination ?: ($parties['customer']->name ?? '-') }}</td>
                    </tr>
                    <tr>
                        <th>جوال الزبون</th>
                        <td dir="ltr" class="text-end">{{ $shippingRequest->phone_origin_dimensions ?: ($parties['customer']->phone ?? '-') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="card card-primary card-outline mb-4 mt-1">
    <div class="card-header py-2">
        <div class="card-title">تفاصيل طلب الشحن</div>
    </div>
    <div class="card-body">
        <table class="table table-borderless" style="width:100%">
            <tbody>
                <tr>
                    <th style="width:30%">رقم تتبع الشحن</th>
                    <td><b>{{ $shippingRequest->tracking_number ?: $shippingRequest->oto_id ?? '-' }}</b></td>
                </tr>
                <tr>
                    <th style="width:30%">الرقم</th>
                    <td>{{ $shippingRequest->id ?? '-' }}</td>
                </tr>
                <tr>
                    <th style="width:30%">رقم طلب الشحن</th>
                    <td>{{ $shippingRequest->order_number ?? '-' }}</td>
                </tr>
                <tr>
                    <th style="width:30%">رقم الطلب</th>
                    <td>{{ $shippingRequest->request_id ?? '-' }}</td>
                </tr>
                <tr>
                    <th style="width:30%">رقم الرد</th>
                    <td>{{ $shippingRequest->response_id ?? '-' }}</td>
                </tr>
                <tr>
                    <th>رقم المستلم</th>
                    <td>{{ $shippingRequest->phone_origin_dimensions ?? '-' }}</td>
                </tr>
                <tr>
                    <th>رقم هوية المستلم</th>
                    <td>{{ $shippingRequest->id_number_user ?? '-' }}</td>
                </tr>
                <tr>
                    <th>مدينة المستلم</th>
                    <td>{{ $shippingRequest->city_origin_dimensions ?? '-' }}</td>
                </tr>
                <tr>
                    <th>عنوان المستلم</th>
                    <td>{{ $shippingRequest->address_origin_dimensions ?? '-' }}</td>
                </tr>
                <tr>
                    <th>موقع التسليم</th>
                    <td>
                        @if ($deliveryMap)
                            <a href="{{ $deliveryMap }}" target="_blank" rel="noopener">عرض على الخريطة</a>
                        @else
                            -
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>رقم المرسل (الشركة)</th>
                    <td>{{ $shippingRequest->phone_origin_vendor ?? '-' }}</td>
                </tr>
                <tr>
                    <th>مدينة الإرسال (الشركة)</th>
                    <td>{{ $shippingRequest->city_origin_vendor ?? '-' }}</td>
                </tr>
                <tr>
                    <th>عنوان الإرسال (الشركة)</th>
                    <td>{{ $shippingRequest->address_origin_vendor ?? '-' }}</td>
                </tr>
                <tr>
                    <th>موقع الاستلام من المتجر</th>
                    <td>
                        @if ($pickupMap)
                            <a href="{{ $pickupMap }}" target="_blank" rel="noopener">عرض على الخريطة</a>
                        @else
                            -
                        @endif
                    </td>
                </tr>

                <tr>
                    <th>الطول</th>
                    <td>{{ $shippingRequest->length . ' سم' }}</td>
                </tr>
                <tr>
                    <th>العرض</th>
                    <td>{{ $shippingRequest->width . ' سم' }}</td>
                </tr>
                <tr>
                    <th>الارتفاع</th>
                    <td>{{ $shippingRequest->height . ' سم' }}</td>
                </tr>
                <tr>
                    <th>الوزن</th>
                    <td>{{ $shippingRequest->weight . ' كغم' }}</td>
                </tr>
                @if (is_array($shippingRequest->packages) && count($shippingRequest->packages) > 1)
                    <tr>
                        <th>الشحنات (كل الكراتين)</th>
                        <td>
                            @foreach ($shippingRequest->packages as $i => $package)
                                <div>
                                    #{{ $i + 1 }}:
                                    {{ ($package['length'] ?? '-') . ' × ' . ($package['width'] ?? '-') . ' × ' . ($package['height'] ?? '-') . ' سم' }}
                                    — {{ ($package['weight'] ?? '-') . ' كغم' }}
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @endif

                <tr>
                    <th>الحالة</th>
                    <td>
                        {{ App\Enums\StatusShippingRequestEnum::trans($shippingRequest->status->value) }}
                    </td>
                </tr>
                @if (!$isLegacy)
                    <tr>
                        <th>تكلفة الشحن (المرسلة للزبون)</th>
                        <td><b>{{ $shippingRequest->shipping_cost !== null ? $shippingRequest->shipping_cost . ' ريال' : '-' }}</b></td>
                    </tr>
                    <tr>
                        <th>حالة الدفع</th>
                        <td>{{ App\Enums\ShippingPaymentStatusEnum::trans($shippingRequest->payment_status) }}
                            @if ($shippingRequest->paid_at)
                                ({{ $shippingRequest->paid_at->format('Y-m-d H:i') }})
                            @endif
                        </td>
                    </tr>
                    @if ($shippingRequest->payment_due_at && $shippingRequest->status === \App\Enums\StatusShippingRequestEnum::AwaitingPayment)
                        <tr>
                            <th>مهلة الدفع</th>
                            <td>{{ $shippingRequest->payment_due_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endif
                    @if ($shippingRequest->carrier_name)
                        <tr>
                            <th>شركة الشحن</th>
                            <td>{{ $shippingRequest->carrier_name }}</td>
                        </tr>
                    @endif
                    @if ($shippingRequest->pickup_details)
                        <tr>
                            <th>بيانات الاستلام</th>
                            <td style="white-space: pre-line">{{ $shippingRequest->pickup_details }}</td>
                        </tr>
                    @endif
                    @if ($shippingRequest->cancel_reason)
                        <tr>
                            <th>سبب الإلغاء / التعذّر</th>
                            <td>{{ $shippingRequest->cancel_reason }}</td>
                        </tr>
                    @endif
                @endif
                <tr>
                    <th>{{ $isLegacy ? 'سعر الشحن' : 'تقدير OTO (مرجعي)' }}</th>
                    <td>{{ $shippingRequest->fee_cheapest_shipping ?? '-' }}</td>
                </tr>
                @if ($isLegacy)
                    <tr>
                        <th>عمولة التطبيق</th>
                        <td>{{ $shippingRequest->amount_rate_app ?? '-' }}</td>
                    </tr>
                @endif
                <tr>
                    <th>موافقة الشحن</th>
                    <td>{{ $shippingRequest->is_user_confirmed ? 'نعم' : 'لا' }}</td>
                </tr>
                <tr>
                    <th> تاريخ الشحن</th>
                    <td>{{ optional($shippingRequest->created_at)->format('Y-m-d H:i') ?? '-' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
