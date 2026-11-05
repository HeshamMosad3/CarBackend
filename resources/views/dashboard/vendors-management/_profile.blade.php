@php
    $expiry = $vendor->date_expire_commercial_record ? \Carbon\Carbon::parse($vendor->date_expire_commercial_record) : null;
    $expired = $expiry && $expiry->isPast();
    $expiresSoon = $expiry && !$expired && $expiry->lte(now()->addDays(30));
    $logo = $vendor->logo ? url('uploads/' . $vendor->logo) : null;
@endphp

<div class="row g-3">
    <div class="col-lg-8" data-live-region="vendor-main">
        <div class="card mb-3">
            <div class="card-body">
                <div class="profile-head">
                    <span class="avatar avatar-lg">
                        @if ($logo)
                            <img src="{{ $logo }}" alt="" onerror="this.parentNode.textContent='{{ mb_substr($vendor->company_name_ar ?? '؟', 0, 1) }}'">
                        @else
                            {{ mb_substr($vendor->company_name_ar ?? '؟', 0, 1) }}
                        @endif
                    </span>
                    <div class="min-w-0 flex-grow-1">
                        <h2>{{ $vendor->company_name_ar ?: '—' }}</h2>
                        @if ($vendor->company_name_en)
                            <div class="text-muted ltr text-end d-inline-block">{{ $vendor->company_name_en }}</div>
                        @endif
                        <div class="mt-2 d-flex gap-2 flex-wrap align-items-center">
                            <x-dash.status type="user" :value="$vendor->account_status" />
                            <span class="text-muted small"><i class="bi bi-calendar3"></i> سجّلت {{ $vendor->member_since }}</span>
                        </div>
                    </div>
                </div>
                @if ($vendor->description)
                    <p class="mt-3 mb-0 text-muted" style="white-space: pre-line">{{ $vendor->description }}</p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-file-earmark-text icon"></i> بيانات التسجيل</div>
            </div>
            <div class="card-body">
                @if ($expired)
                    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon"></i> السجل التجاري منتهي منذ {{ $expiry->locale('ar')->diffForHumans(null, true) }}.</div>
                @elseif ($expiresSoon)
                    <div class="alert alert-warning"><i class="bi bi-hourglass-split"></i> السجل التجاري ينتهي خلال {{ $expiry->locale('ar')->diffForHumans(null, true) }}.</div>
                @endif

                <dl class="detail-list">
                    <x-dash.detail label="رقم الحساب">{{ $vendor->user_id }}</x-dash.detail>
                    <x-dash.detail label="جوال التسجيل">
                        @if ($vendor->phone)<a href="tel:{{ $vendor->phone }}" class="ltr">{{ $vendor->phone }}</a>@endif
                    </x-dash.detail>
                    <x-dash.detail label="رقم التواصل">
                        @if ($vendor->phone_contact)
                            <a href="tel:{{ $vendor->phone_contact }}" class="ltr">{{ $vendor->phone_contact }}</a>
                            @if ($vendor->is_hide_phone_contact)
                                <span class="status-pill tone-secondary ms-1">مخفي عن العملاء</span>
                            @endif
                        @endif
                    </x-dash.detail>
                    <x-dash.detail label="السجل التجاري" class="ltr text-end">{{ $vendor->commercial_record }}</x-dash.detail>
                    <x-dash.detail label="انتهاء السجل التجاري">
                        @if ($expiry)
                            <span class="ltr {{ $expired ? 'text-danger' : '' }}">{{ $expiry->format('Y-m-d') }}</span>
                        @endif
                    </x-dash.detail>
                    <x-dash.detail label="صورة السجل التجاري">
                        @if ($vendorDocument)
                            <a href="{{ route('uploads-private', ['filename' => $vendorDocument]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light">
                                <i class="bi bi-box-arrow-up-left"></i> فتح المستند
                            </a>
                        @endif
                    </x-dash.detail>
                    @if ($vendor->verification_notes)
                        <x-dash.detail label="ملاحظة المراجعة / سبب الرفض" style="white-space: pre-line">{{ $vendor->verification_notes }}</x-dash.detail>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4" data-live-region="vendor-side">
        @isset($responsesCount)
            <div class="mb-3">
                <x-dash.stat label="العروض المقدّمة" :value="$responsesCount" icon="bi-chat-square-text" tone="info" />
            </div>
        @endisset

        <div class="card mb-3">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-geo-alt icon"></i> المدن التي تغطيها</div>
            </div>
            <div class="card-body">
                @forelse ($vendorCities as $city)
                    <span class="chip">{{ $city->city_name_ar }}</span>
                @empty
                    <span class="text-muted">لم تحدد مدناً</span>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-tags icon"></i> الخدمات</div>
            </div>
            <div class="card-body">
                @forelse ($vendorCategories as $cat)
                    <span class="chip">{{ $cat->cat_name_ar }}</span>
                @empty
                    <span class="text-muted">لم تحدد خدمات</span>
                @endforelse
            </div>
        </div>
    </div>
</div>
