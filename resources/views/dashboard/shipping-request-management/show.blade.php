@extends('dashboard.layouts.app')
@section('title', 'شحنة رقم ' . $shippingRequest->id)
@section('content')
    @php
        use App\Enums\StatusShippingRequestEnum as S;

        $status = $shippingRequest->status;
        $isLegacy = $status->isLegacy();

        $steps = [
            ['بيانات الزبون', [S::AwaitingCustomerApproval, S::AwaitingCustomerData]],
            ['المراجعة', [S::ReadyForReview]],
            ['التسعير', [S::SearchingCarrier]],
            ['الدفع', [S::AwaitingPayment]],
            ['الحجز', [S::Paid]],
            ['الاستلام', [S::BookingConfirmed]],
            ['في الطريق', [S::PickedUp, S::InTransit]],
            ['التسليم', [S::Delivered]],
        ];
        $currentStep = null;
        foreach ($steps as $i => [, $states]) {
            if (in_array($status, $states, true)) {
                $currentStep = $i;
            }
        }
        $stopped = in_array($status, [S::Canceled, S::Unavailable], true);
    @endphp

    <x-dash.page-header :title="'شحنة رقم ' . $shippingRequest->id"
        :subtitle="($shippingRequest->city_origin_vendor ?: '—') . ' ← ' . ($shippingRequest->city_origin_dimensions ?: '—') . ' · أُنشئت ' . optional($shippingRequest->created_at)->format('Y-m-d H:i')"
        :crumbs="['الشحن' => route('dashboard.shipping-request-management.index'), '#' . $shippingRequest->id => null]">
        @if ($shippingRequest->request_id)
            <a href="{{ route('dashboard.requests-management.show', $shippingRequest->request_id) }}" class="btn btn-light">
                <i class="bi bi-clipboard-check"></i> الطلب <span class="ltr">#{{ $shippingRequest->request_id }}</span>
            </a>
        @endif
        <button type="button" class="btn btn-outline-danger"
            data-delete-url="{{ url('dashboard/shipping-request-management/delete/' . $shippingRequest->id) }}"
            data-confirm="سيتم حذف الشحنة رقم {{ $shippingRequest->id }}."
            data-after="{{ route('dashboard.shipping-request-management.index') }}">
            <i class="bi bi-trash3"></i> حذف
        </button>
    </x-dash.page-header>

    <div class="app-content">
        @unless ($isLegacy)
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                        <x-dash.status type="shipping" :value="$status" />
                        <x-dash.status type="payment" :value="$shippingRequest->payment_status" />
                        @if ($shippingRequest->status_changed_at)
                            <span class="text-muted small ms-auto">آخر تحديث {{ $shippingRequest->status_changed_at->locale('ar')->diffForHumans() }}</span>
                        @endif
                    </div>
                    <div class="stepper {{ $stopped ? 'is-stopped' : '' }}" aria-label="مراحل الشحنة">
                        @foreach ($steps as $i => [$label])
                            @php
                                $done = $status === S::Delivered || ($currentStep !== null && $i < $currentStep);
                                $isCurrent = !$done && $currentStep === $i;
                            @endphp
                            <div class="step {{ $done ? 'done' : '' }} {{ $isCurrent ? 'current' : '' }}">
                                <span class="dot">@if ($done)<i class="bi bi-check-lg"></i>@else{{ $i + 1 }}@endif</span>
                                {{ $label }}
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endunless

        <div class="row g-3">
            <div class="col-xl-7 d-flex flex-column gap-3">
                @unless ($isLegacy)
                    @include('dashboard.shipping-request-management.partails.workflow-section')
                @endunless
                @include('dashboard.shipping-request-management.partails.shipping-section')
            </div>
            <div class="col-xl-5 d-flex flex-column gap-3">
                @include('dashboard.shipping-request-management.partails.details-shipping-section')
            </div>
        </div>
    </div>
@endsection
