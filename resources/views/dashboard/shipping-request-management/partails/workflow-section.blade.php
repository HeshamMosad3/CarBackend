@php
    use App\Enums\ShippingPaymentStatusEnum as PayStatus;
    use App\Enums\StatusShippingRequestEnum as ShippingStatus;

    $status = $shippingRequest->status;
    $transitionUrl = route('dashboard.shipping-request-management.transition', $shippingRequest->id);
    $isPaid = $shippingRequest->payment_status === PayStatus::Paid->value;
    $paymentDays = \App\Utils\ConfigUtils::getShippingPaymentWindowDays();
@endphp

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-signpost-split icon"></i> الخطوة التالية</div>
    </div>
    <div class="card-body d-flex flex-column gap-3">
        @if ($needsRefund)
            <div class="action-panel" style="border-color: rgba(var(--bs-danger-rgb), .4); background: rgba(var(--bs-danger-rgb), .06)">
                <div class="action-title text-danger"><i class="bi bi-exclamation-octagon"></i> يلزم رد {{ number_format((float) $shippingRequest->shipping_cost, 2) }} ريال للزبون</div>
                <div class="action-hint">دفع الزبون رسوم الشحن ولم يكتمل الشحن. حوّل المبلغ ثم سجّل ذلك هنا.</div>
                <form method="POST" action="{{ route('dashboard.shipping-request-management.refund', $shippingRequest->id) }}"
                    data-confirm="تأكيد: هل تم تحويل المبلغ للزبون فعلاً؟" data-confirm-text="نعم، تم الرد" class="d-flex gap-2 flex-wrap">
                    @csrf
                    <input type="text" name="refund_note" class="form-control flex-grow-1" style="max-width: 360px" maxlength="255"
                        placeholder="ملاحظة اختيارية (مثلاً: رقم التحويل)">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-check2"></i> تسجيل رد المبلغ</button>
                </form>
            </div>
        @endif

        @switch($status)
            @case(ShippingStatus::AwaitingCustomerApproval)
            @case(ShippingStatus::AwaitingCustomerData)
                <div class="alert alert-secondary mb-0"><i class="bi bi-hourglass-split"></i> بانتظار الزبون لإكمال بياناته. لا يلزمك أي إجراء الآن.</div>
            @break

            @case(ShippingStatus::ReadyForReview)
                <div class="action-panel">
                    <div class="action-title">راجع بيانات الشحنة</div>
                    <div class="action-hint">تأكد من العنوانين وأبعاد الطرد، ثم ابدأ البحث عن شركة شحن مناسبة. أسعار OTO أدناه للاستئناس.</div>
                    <form method="POST" action="{{ $transitionUrl }}">
                        @csrf
                        <input type="hidden" name="to" value="SearchingCarrier">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> بدء البحث عن شركة شحن</button>
                    </form>
                </div>
            @break

            @case(ShippingStatus::SearchingCarrier)
                <div class="action-panel">
                    <div class="action-title">أرسل تكلفة الشحن للزبون</div>
                    <div class="action-hint">سيصل الزبون إشعار بالمبلغ، وأمامه {{ $paymentDays }} أيام للدفع. يمكنك الاستعانة بأسعار الشركات أدناه.</div>
                    <form method="POST" action="{{ $transitionUrl }}"
                        data-confirm="سيصل الزبون إشعار بالمبلغ ويُطلب منه الدفع خلال {{ $paymentDays }} أيام. متابعة؟" data-confirm-text="إرسال للزبون">
                        @csrf
                        <input type="hidden" name="to" value="AwaitingPayment">
                        <label class="form-label" for="shipping_cost">تكلفة الشحن الإجمالية</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <div class="input-group" style="max-width: 260px">
                                <input type="number" step="0.01" min="0.01" name="shipping_cost" id="shipping_cost"
                                    class="form-control" required value="{{ $shippingRequest->shipping_cost }}">
                                <span class="input-group-text">ر.س</span>
                            </div>
                            <button type="submit" class="btn btn-success"><i class="bi bi-send"></i> إرسال التكلفة للزبون</button>
                        </div>
                    </form>
                </div>
            @break

            @case(ShippingStatus::AwaitingPayment)
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-credit-card"></i>
                    <div class="flex-grow-1">
                        بانتظار دفع الزبون <b>{{ number_format((float) $shippingRequest->shipping_cost, 2) }} ريال</b>
                        @if ($shippingRequest->payment_due_at)
                            — المهلة تنتهي {{ $shippingRequest->payment_due_at->locale('ar')->diffForHumans() }}
                            <span class="ltr small">({{ $shippingRequest->payment_due_at->format('Y-m-d H:i') }})</span>
                        @endif
                    </div>
                </div>
                <form method="POST" action="{{ $transitionUrl }}"
                    data-confirm="إعادة الشحنة للتسعير؟ ستُلغى مطالبة الزبون الحالية." data-confirm-text="إعادة التسعير">
                    @csrf
                    <input type="hidden" name="to" value="SearchingCarrier">
                    <button type="submit" class="btn btn-light btn-sm"><i class="bi bi-arrow-counterclockwise"></i> تعديل السعر (إعادة التسعير)</button>
                </form>
            @break

            @case(ShippingStatus::Paid)
                <div class="action-panel">
                    <div class="action-title">دفع الزبون — احجز الشحنة</div>
                    <div class="action-hint">سجّل شركة الشحن ورقم البوليصة. يمكنك أيضاً الحجز مباشرة لدى OTO من القسم أدناه.</div>
                    <form method="POST" action="{{ $transitionUrl }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="to" value="BookingConfirmed">
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label" for="carrier_name">شركة الشحن <span class="text-danger">*</span></label>
                                <input type="text" name="carrier_name" id="carrier_name" class="form-control" maxlength="255"
                                    required value="{{ $shippingRequest->carrier_name }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="tracking_number">رقم البوليصة / التتبع</label>
                                <input type="text" name="tracking_number" id="tracking_number" class="form-control ltr text-start"
                                    maxlength="255" value="{{ $shippingRequest->tracking_number ?: $shippingRequest->oto_id }}">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="waybill">بوليصة الشحن (صورة أو PDF) <span class="text-muted small">- تظهر للزبون والتاجر ويقدرون يحمّلونها</span></label>
                            <input type="file" name="waybill" id="waybill" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf">
                            <div class="form-text">اختيارية الآن، ويمكنك رفعها لاحقاً. الحد الأقصى 5 ميجابايت.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="pickup_details">بيانات الاستلام (تظهر للتاجر)</label>
                            <textarea name="pickup_details" id="pickup_details" class="form-control" rows="3" maxlength="2000"
                                placeholder="مثال: سيصل مندوب أرامكس يوم الأحد بين 10 و 2 ظهراً.">{{ $shippingRequest->pickup_details }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle"></i> تأكيد الحجز</button>
                    </form>
                </div>
            @break

            @case(ShippingStatus::BookingConfirmed)
                <div class="action-panel">
                    <div class="action-title">بانتظار استلام الشحنة من التاجر</div>
                    <div class="action-hint">عندما تستلم شركة الشحن الطرد من التاجر، حدّث الحالة.</div>
                    <form method="POST" action="{{ $transitionUrl }}">
                        @csrf
                        <input type="hidden" name="to" value="PickedUp">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-box-seam"></i> تم استلام الشحنة</button>
                    </form>
                </div>
            @break

            @case(ShippingStatus::PickedUp)
                <div class="action-panel">
                    <div class="action-title">الطرد مع شركة الشحن</div>
                    <div class="action-hint">حدّث الحالة عند انطلاق الشحنة نحو الزبون.</div>
                    <form method="POST" action="{{ $transitionUrl }}">
                        @csrf
                        <input type="hidden" name="to" value="InTransit">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-truck"></i> الشحنة في الطريق</button>
                    </form>
                </div>
            @break

            @case(ShippingStatus::InTransit)
                <div class="action-panel">
                    <div class="action-title">الشحنة في الطريق</div>
                    <div class="action-hint">أكّد التسليم عند وصول الطرد للزبون.</div>
                    <form method="POST" action="{{ $transitionUrl }}" data-confirm="تأكيد وصول الشحنة للزبون؟" data-confirm-text="تم التسليم">
                        @csrf
                        <input type="hidden" name="to" value="Delivered">
                        <button type="submit" class="btn btn-success"><i class="bi bi-check2-all"></i> تم التسليم</button>
                    </form>
                </div>
            @break

            @case(ShippingStatus::Delivered)
                <div class="all-clear"><i class="bi bi-check2-all fs-5"></i> تم تسليم الشحنة بنجاح.</div>
            @break

            @case(ShippingStatus::Canceled)
            @case(ShippingStatus::Unavailable)
                <div class="alert alert-danger mb-0">
                    <i class="bi bi-x-octagon"></i>
                    <div>
                        <b>{{ ShippingStatus::trans($status->value) }}</b>
                        @if ($shippingRequest->cancel_reason)
                            <div>السبب: {{ $shippingRequest->cancel_reason }}</div>
                        @endif
                    </div>
                </div>
            @break
        @endswitch

        {{-- The carrier's waybill: shown to the customer and the vendor in the app once uploaded --}}
        @if (in_array($status, [ShippingStatus::BookingConfirmed, ShippingStatus::PickedUp, ShippingStatus::InTransit], true))
            <div class="action-panel">
                <div class="action-title"><i class="bi bi-file-earmark-text"></i> بوليصة الشحن</div>
                @if ($shippingRequest->waybill_file)
                    <div class="action-hint">
                        مرفوعة وظاهرة للزبون والتاجر في التطبيق.
                        <a href="{{ route('dashboard.shipping-request-management.waybill.show', $shippingRequest->id) }}" target="_blank" rel="noopener">
                            عرض البوليصة ({{ $shippingRequest->waybillType() === 'pdf' ? 'PDF' : 'صورة' }})
                        </a>
                    </div>
                @else
                    <div class="action-hint">لم تُرفع بوليصة بعد. ارفعها ليتمكن الزبون والتاجر من عرضها وتحميلها من التطبيق.</div>
                @endif
                <form method="POST" action="{{ route('dashboard.shipping-request-management.waybill.upload', $shippingRequest->id) }}"
                    enctype="multipart/form-data" class="d-flex gap-2 flex-wrap">
                    @csrf
                    <input type="file" name="waybill" class="form-control flex-grow-1" style="max-width: 360px" required
                        accept="image/jpeg,image/png,image/webp,application/pdf">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> {{ $shippingRequest->waybill_file ? 'استبدال البوليصة' : 'رفع البوليصة' }}</button>
                </form>
            </div>
        @endif

        @if ($status->canTransitionTo(ShippingStatus::Unavailable) || $status->canTransitionTo(ShippingStatus::Canceled))
            <details class="border rounded-3 p-3" style="border-color: var(--dash-border) !important">
                <summary class="fw-semibold text-danger" style="cursor: pointer">إنهاء الشحنة دون تسليم</summary>
                <form method="POST" action="{{ $transitionUrl }}" class="mt-3" data-confirm="متابعة؟" data-confirm-danger>
                    @csrf
                    <label class="form-label" for="cancel_reason">السبب (يظهر للتاجر والزبون)</label>
                    <input type="text" name="cancel_reason" id="cancel_reason" class="form-control mb-2" maxlength="255"
                        placeholder="مثال: لا تتوفر شركة شحن تغطي مدينة الزبون">
                    <div class="d-flex gap-2 flex-wrap">
                        @if ($status->canTransitionTo(ShippingStatus::Unavailable))
                            <button type="submit" name="to" value="Unavailable" class="btn btn-warning btn-sm"
                                data-confirm="تعذّر توفير الشحن؟ سيصل الطرفين إشعار بترتيب الشحن بأنفسهم{{ $isPaid ? '، وسيلزمك رد المبلغ المدفوع' : '' }}.">
                                <i class="bi bi-slash-circle"></i> تعذّر توفير الشحن
                            </button>
                        @endif
                        @if ($status->canTransitionTo(ShippingStatus::Canceled))
                            <button type="submit" name="to" value="Canceled" class="btn btn-outline-danger btn-sm"
                                data-confirm="إلغاء طلب الشحن؟{{ $isPaid ? ' سيلزمك رد المبلغ المدفوع.' : '' }}">
                                <i class="bi bi-x-lg"></i> إلغاء الشحن
                            </button>
                        @endif
                    </div>
                </form>
            </details>
        @endif

        <form method="POST" action="{{ route('dashboard.shipping-request-management.notes', $shippingRequest->id) }}">
            @csrf
            <label class="form-label" for="admin_notes"><i class="bi bi-lock"></i> ملاحظات داخلية (لا تظهر لأحد غير الإدارة)</label>
            <textarea name="admin_notes" id="admin_notes" class="form-control mb-2" rows="2" maxlength="5000">{{ $shippingRequest->admin_notes }}</textarea>
            <button type="submit" class="btn btn-light btn-sm"><i class="bi bi-save"></i> حفظ الملاحظات</button>
        </form>
    </div>
</div>
