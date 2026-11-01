@php
    use App\Enums\StatusShippingRequestEnum as ShippingStatus;

    $status = $shippingRequest->status;
    $isLegacy = $status->isLegacy();
    // OTO's cheapest company: the original action source for legacy shipments, a price reference
    // for the admin while a new-flow shipment is reviewed/quoted, and an optional booking once paid.
    $showOto = $status === ShippingStatus::Pending
        || in_array($status, [ShippingStatus::ReadyForReview, ShippingStatus::SearchingCarrier, ShippingStatus::Paid], true);
    $canCreateOtoOrder = $status === ShippingStatus::Pending || $status === ShippingStatus::Paid;
@endphp

@if (!$isLegacy)
    @include('dashboard.shipping-request-management.partails.workflow-section')
@endif

<div class="card card-primary card-outline mb-4 mt-1">
    <div class="card-header py-2">
        <div class="card-title">أرخص شركة شحن{{ $isLegacy ? '' : ' (OTO - للاستئناس)' }}</div>
    </div>
    <div class="card-body">
        @if ($shippingRequest->oto_id)
            <div class="alert alert-success">
                تم إنشاء طلب الشحن بنجاح مع شركة الشحن، رقم تتبع الشحنة هو: <b>{{ $shippingRequest->oto_id }}</b>
            </div>
        @endif

        @if (!$showOto)
            <div class="alert alert-info">
                {{ ShippingStatus::trans($status->value) }}
            </div>
        @elseif (!$shippingRequest->is_user_confirmed)
            <div class="alert alert-warning">
                في إنتظار تأكيد المستخدم لطلب الشحن
            </div>
        @elseif (!isset($cheapestCompany))
            <div class="alert alert-info">
                لا يوجد شركات شحن متاحة لهذا الطلب
            </div>
        @elseif (isset($cheapestCompany) && count($cheapestCompany) > 0)
            <table class="table table-borderless mb-5" style="width:100%">
                <tbody>
                    <tr>
                        <th style="width:30%">إسم الشركة</th>
                        <td>{{ $cheapestCompany['deliveryCompanyName'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="width:30%">السعر</th>
                        <td>{{ $cheapestCompany['price'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="width:30%">مدة التوصيل المتوقعة</th>
                        <td>{{ $cheapestCompany['avgDeliveryTime'] ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
            @if ($canCreateOtoOrder && !$shippingRequest->oto_id)
                <form id="form-query-shipping" method="POST"
                    action="{{ route('dashboard.shipping-request-management.create-order-shipping') }}">
                    @csrf
                    <input type="hidden" name="shippingRequestId" value="{{ $shippingRequest->id }}">
                    <input type="hidden" name="deliveryOptionId" value="{{ $cheapestCompany['deliveryOptionId'] }}">
                    <button type="submit" class="btn btn-primary" id="btn-query-shipping">
                        {{ $isLegacy ? 'طلب الشحن' : 'إنشاء الطلب لدى OTO (اختياري)' }}
                    </button>
                </form>
            @endif
        @endif

    </div>
</div>
