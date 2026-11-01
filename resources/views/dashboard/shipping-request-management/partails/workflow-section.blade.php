@php
    use App\Enums\ShippingPaymentStatusEnum as PayStatus;
    use App\Enums\StatusShippingRequestEnum as ShippingStatus;

    $status = $shippingRequest->status;
    $transitionUrl = route('dashboard.shipping-request-management.transition', $shippingRequest->id);

    $badge = match (true) {
        in_array($status, [ShippingStatus::Canceled, ShippingStatus::Unavailable], true) => 'danger',
        $status === ShippingStatus::Delivered => 'success',
        $status === ShippingStatus::ReadyForReview => 'warning',
        default => 'primary',
    };
@endphp

<div class="card card-primary card-outline mb-4 mt-1">
    <div class="card-header py-2">
        <div class="card-title">إدارة الشحن</div>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <span class="badge bg-{{ $badge }} px-3 py-2">{{ ShippingStatus::trans($status->value) }}</span>
            <span class="badge bg-{{ $shippingRequest->payment_status === PayStatus::Paid->value ? 'success' : 'secondary' }} px-3 py-2">
                {{ PayStatus::trans($shippingRequest->payment_status) }}
            </span>
        </div>

        @if ($needsRefund)
            <div class="alert alert-danger">
                <b>يلزم رد المبلغ للزبون:</b> {{ $shippingRequest->shipping_cost }} ريال دُفعت، والشحن لم يكتمل.
                <form method="POST" action="{{ route('dashboard.shipping-request-management.refund', $shippingRequest->id) }}"
                    class="mt-2" onsubmit="return confirm('تأكيد: تم رد المبلغ للزبون فعلاً؟')">
                    @csrf
                    <input type="text" name="refund_note" class="form-control mb-2" maxlength="255"
                        placeholder="ملاحظة (مثلاً: رقم التحويل)">
                    <button type="submit" class="btn btn-danger btn-sm">تسجيل أنه تم رد المبلغ</button>
                </form>
            </div>
        @endif

        {{-- Actions available from the current status --}}
        @switch($status)
            @case(ShippingStatus::AwaitingCustomerApproval)
            @case(ShippingStatus::AwaitingCustomerData)
                <div class="alert alert-secondary">بانتظار الزبون لإكمال بياناته.</div>
            @break

            @case(ShippingStatus::ReadyForReview)
                <form method="POST" action="{{ $transitionUrl }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="to" value="SearchingCarrier">
                    <button type="submit" class="btn btn-primary">بدء البحث عن شركة شحن</button>
                </form>
            @break

            @case(ShippingStatus::SearchingCarrier)
                <form method="POST" action="{{ $transitionUrl }}"
                    onsubmit="return confirm('سيصل الزبون إشعار بالمبلغ ويُطلب منه الدفع خلال {{ \App\Utils\ConfigUtils::getShippingPaymentWindowDays() }} أيام. متابعة؟')">
                    @csrf
                    <input type="hidden" name="to" value="AwaitingPayment">
                    <label class="form-label" for="shipping_cost">تكلفة الشحن الإجمالية (ريال)</label>
                    <input type="number" step="0.01" min="0.01" name="shipping_cost" id="shipping_cost"
                        class="form-control mb-2" required value="{{ $shippingRequest->shipping_cost }}">
                    <button type="submit" class="btn btn-success">إرسال تكلفة الشحن إلى الزبون</button>
                </form>
            @break

            @case(ShippingStatus::AwaitingPayment)
                <div class="alert alert-warning">
                    بانتظار دفع الزبون {{ $shippingRequest->shipping_cost }} ريال
                    @if ($shippingRequest->payment_due_at)
                        — المهلة حتى {{ $shippingRequest->payment_due_at->format('Y-m-d H:i') }}
                    @endif
                </div>
                <form method="POST" action="{{ $transitionUrl }}" class="d-inline"
                    onsubmit="return confirm('إعادة الشحنة للتسعير؟ ستُلغى مطالبة الزبون الحالية.')">
                    @csrf
                    <input type="hidden" name="to" value="SearchingCarrier">
                    <button type="submit" class="btn btn-outline-primary btn-sm">إعادة التسعير</button>
                </form>
            @break

            @case(ShippingStatus::Paid)
                <form method="POST" action="{{ $transitionUrl }}">
                    @csrf
                    <input type="hidden" name="to" value="BookingConfirmed">
                    <div class="mb-2">
                        <label class="form-label" for="carrier_name">اسم شركة الشحن</label>
                        <input type="text" name="carrier_name" id="carrier_name" class="form-control" maxlength="255"
                            required value="{{ $shippingRequest->carrier_name }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="tracking_number">رقم البوليصة / التتبع</label>
                        <input type="text" name="tracking_number" id="tracking_number" class="form-control"
                            maxlength="255" value="{{ $shippingRequest->tracking_number }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="pickup_details">بيانات الاستلام</label>
                        <textarea name="pickup_details" id="pickup_details" class="form-control" rows="3" maxlength="2000">{{ $shippingRequest->pickup_details }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-success">تأكيد الحجز</button>
                </form>
            @break

            @case(ShippingStatus::BookingConfirmed)
                <form method="POST" action="{{ $transitionUrl }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="to" value="PickedUp">
                    <button type="submit" class="btn btn-primary">تم استلام الشحنة</button>
                </form>
            @break

            @case(ShippingStatus::PickedUp)
                <form method="POST" action="{{ $transitionUrl }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="to" value="InTransit">
                    <button type="submit" class="btn btn-primary">الشحنة في الطريق</button>
                </form>
            @break

            @case(ShippingStatus::InTransit)
                <form method="POST" action="{{ $transitionUrl }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="to" value="Delivered">
                    <button type="submit" class="btn btn-success">تم التسليم</button>
                </form>
            @break
        @endswitch

        {{-- Ending the shipment without delivery --}}
        @if ($status->canTransitionTo(ShippingStatus::Unavailable) || $status->canTransitionTo(ShippingStatus::Canceled))
            <hr>
            <form method="POST" action="{{ $transitionUrl }}" id="form-close-shipping">
                @csrf
                <input type="text" name="cancel_reason" class="form-control mb-2" maxlength="255"
                    placeholder="السبب (يظهر للطرفين)">
                <input type="hidden" name="to" id="close-to" value="">
                @if ($status->canTransitionTo(ShippingStatus::Unavailable))
                    <button type="submit" class="btn btn-warning btn-sm" data-to="Unavailable"
                        data-confirm="تعذّر توفير الشحن؟ سيصل الطرفين إشعار بترتيب الشحن بأنفسهم{{ $shippingRequest->payment_status === PayStatus::Paid->value ? '، وسيلزمك رد المبلغ المدفوع' : '' }}.">
                        تعذّر توفير الشحن
                    </button>
                @endif
                @if ($status->canTransitionTo(ShippingStatus::Canceled))
                    <button type="submit" class="btn btn-outline-danger btn-sm" data-to="Canceled"
                        data-confirm="إلغاء طلب الشحن؟{{ $shippingRequest->payment_status === PayStatus::Paid->value ? ' سيلزمك رد المبلغ المدفوع.' : '' }}">
                        إلغاء الشحن
                    </button>
                @endif
            </form>
        @endif

        <hr>
        <form method="POST" action="{{ route('dashboard.shipping-request-management.notes', $shippingRequest->id) }}">
            @csrf
            <label class="form-label" for="admin_notes">ملاحظات الإدارة (داخلية)</label>
            <textarea name="admin_notes" id="admin_notes" class="form-control mb-2" rows="2" maxlength="5000">{{ $shippingRequest->admin_notes }}</textarea>
            <button type="submit" class="btn btn-outline-secondary btn-sm">حفظ الملاحظات</button>
        </form>
    </div>
</div>

<script>
    // The two "end the shipment" buttons share one form: remember which was pressed and confirm it.
    document.querySelectorAll('#form-close-shipping button[data-to]').forEach(function(button) {
        button.addEventListener('click', function(event) {
            if (!confirm(button.dataset.confirm)) {
                event.preventDefault();
                return;
            }
            document.getElementById('close-to').value = button.dataset.to;
        });
    });
</script>
