@php
    use App\Enums\StatusShippingRequestEnum as ShippingStatus;

    $status = $shippingRequest->status;
    $isLegacy = $status->isLegacy();
    // All of OTO's delivery options: the admin picks one to book for legacy shipments, uses them as a
    // price reference while a new-flow shipment is reviewed/quoted, and may optionally book one once paid.
    $showOto = $status === ShippingStatus::Pending
        || in_array($status, [ShippingStatus::ReadyForReview, ShippingStatus::SearchingCarrier, ShippingStatus::Paid], true);
    $canCreateOtoOrder = $status === ShippingStatus::Pending || $status === ShippingStatus::Paid;
    $deliveryCompanies = $deliveryCompanies ?? [];
    $canSelect = $canCreateOtoOrder && !$shippingRequest->oto_id;
@endphp

@if ($isLegacy || $showOto || $shippingRequest->oto_id)
    <div class="card table-card">
        <div class="card-header">
            <div>
                <div class="card-title"><i class="bi bi-building icon"></i> شركات الشحن المتاحة عبر OTO</div>
                <div class="card-subtitle">
                    {{ $isLegacy ? 'اختر الشركة المناسبة لك وللزبون ثم أنشئ الطلب.' : 'أسعار مرجعية تساعدك في التسعير' . ($canSelect ? '، ويمكنك الحجز مباشرة.' : '.') }}
                </div>
            </div>
            @if ($isLegacy)
                <x-dash.status type="shipping" :value="$status" />
            @endif
        </div>

        @if ($shippingRequest->oto_id)
            <div class="card-body pb-0">
                <div class="alert alert-success"><i class="bi bi-check2-circle"></i>
                    <div>تم إنشاء الطلب لدى OTO. رقم الشحنة: <b class="ltr">{{ $shippingRequest->oto_id }}</b></div>
                </div>
            </div>
        @endif

        @if (!$showOto)
            @unless ($shippingRequest->oto_id)
                <x-dash.empty icon="bi-info-circle" :title="ShippingStatus::trans($status->value)" text="لا يلزم اختيار شركة شحن في هذه المرحلة." />
            @endunless
        @elseif (!$shippingRequest->is_user_confirmed)
            <x-dash.empty icon="bi-hourglass-split" title="بانتظار تأكيد الزبون" text="ستظهر أسعار شركات الشحن بعد أن يؤكد الزبون طلب الشحن." />
        @elseif (count($deliveryCompanies) === 0)
            <x-dash.empty icon="bi-truck" title="لا توجد شركات شحن متاحة"
                text="لم تُرجع OTO أي خيار لهذا المسار والأبعاد. تحقق من اسمَي المدينتين وأبعاد الطرد." />
        @else
            <div class="px-3 pt-3 d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted small">ترتيب حسب:</span>
                <div class="filter-chips" id="shipping-companies-sort">
                    <button type="button" class="filter-chip active" data-sort="price-asc"><i class="bi bi-sort-numeric-down"></i> الأرخص</button>
                    <button type="button" class="filter-chip" data-sort="time-asc"><i class="bi bi-lightning-charge"></i> الأسرع</button>
                    <button type="button" class="filter-chip" data-sort="price-desc"><i class="bi bi-sort-numeric-up"></i> الأغلى</button>
                </div>
                <span class="text-muted small ms-auto">{{ count($deliveryCompanies) }} خيار</span>
            </div>

            <form id="form-query-shipping" method="POST" action="{{ route('dashboard.shipping-request-management.create-order-shipping') }}"
                data-confirm="تأكيد" data-confirm-text="إنشاء الطلب">
                @csrf
                <input type="hidden" name="shippingRequestId" value="{{ $shippingRequest->id }}">

                <div class="table-responsive mt-2">
                    <table class="table table-hover" id="shipping-companies-table">
                        <thead>
                            <tr>
                                @if ($canSelect)<th style="width:1%"></th>@endif
                                <th>الشركة</th>
                                <th>السعر</th>
                                <th>مدة التوصيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deliveryCompanies as $i => $company)
                                @php
                                    $optionId = $company['deliveryOptionId'] ?? null;
                                    $optionName = $company['deliveryOptionName'] ?? null;
                                    $companyName = $company['deliveryCompanyName'] ?? '-';
                                    $logo = $company['logo'] ?? null;
                                @endphp
                                <tr class="{{ $canSelect && $optionId ? 'carrier-option' : '' }}"
                                    data-price="{{ (float) ($company['price'] ?? 0) }}"
                                    data-time="{{ $company['avgDeliveryTime'] ?? '' }}">
                                    @if ($canSelect)
                                        <td>
                                            @if ($optionId)
                                                <input class="form-check-input" type="radio" name="deliveryOptionId"
                                                    id="delivery-option-{{ $i }}" value="{{ $optionId }}"
                                                    data-label="{{ $companyName }} — {{ $company['price'] ?? '-' }} ر.س">
                                            @endif
                                        </td>
                                    @endif
                                    <td>
                                        <label class="entity mb-0" for="delivery-option-{{ $i }}" style="cursor: inherit">
                                            @if ($logo)
                                                <img src="{{ $logo }}" alt="" class="thumb" style="width:36px;height:36px" loading="lazy" onerror="this.remove()">
                                            @endif
                                            <span class="min-w-0">
                                                <span class="cell-title d-block">{{ $companyName }}</span>
                                                @if ($optionName && $optionName !== $companyName)
                                                    <span class="cell-sub d-block">{{ $optionName }}</span>
                                                @endif
                                            </span>
                                            @if ($i === 0)
                                                <span class="status-pill tone-success no-dot">الأرخص</span>
                                            @endif
                                        </label>
                                    </td>
                                    <td class="text-nowrap"><span class="fw-semibold num">{{ $company['price'] ?? '-' }}</span> <span class="text-muted small">ر.س</span></td>
                                    <td>{{ $company['avgDeliveryTime'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($canSelect)
                    <div class="card-footer d-flex align-items-center gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary" id="btn-query-shipping" disabled>
                            <i class="bi bi-box-arrow-up-left"></i>
                            {{ $isLegacy ? 'إنشاء طلب الشحن مع الشركة المختارة' : 'الحجز لدى OTO مع الشركة المختارة' }}
                        </button>
                        <span class="text-muted small" id="selected-carrier-label">اختر شركة من القائمة</span>
                    </div>
                @endif
            </form>

            <script>
                (function() {
                    const tbody = document.querySelector('#shipping-companies-table tbody');
                    const form = document.getElementById('form-query-shipping');
                    const submitBtn = document.getElementById('btn-query-shipping');
                    const label = document.getElementById('selected-carrier-label');

                    // OTO gives delivery time as free text ("1-3 Days", "24 - 48 hours"...):
                    // average its numbers, in days. Unreadable times sort last.
                    function days(text) {
                        const nums = (text || '').match(/\d+(\.\d+)?/g);
                        if (!nums) return Infinity;
                        const avg = nums.reduce((s, n) => s + parseFloat(n), 0) / nums.length;
                        return /hour|ساع/i.test(text) ? avg / 24 : avg;
                    }

                    const sorters = {
                        'price-asc': (a, b) => a.dataset.price - b.dataset.price,
                        'price-desc': (a, b) => b.dataset.price - a.dataset.price,
                        'time-asc': (a, b) => {
                            const d = days(a.dataset.time) - days(b.dataset.time);
                            return Number.isNaN(d) ? 0 : (d || a.dataset.price - b.dataset.price);
                        },
                    };

                    document.querySelectorAll('#shipping-companies-sort [data-sort]').forEach(btn => {
                        btn.addEventListener('click', () => {
                            document.querySelectorAll('#shipping-companies-sort [data-sort]').forEach(b => b.classList.toggle('active', b === btn));
                            Array.from(tbody.rows).sort(sorters[btn.dataset.sort]).forEach(row => tbody.appendChild(row));
                        });
                    });

                    if (!submitBtn) return;

                    tbody.addEventListener('click', e => {
                        const row = e.target.closest('tr');
                        const radio = row && row.querySelector('input[type=radio]');
                        if (radio && e.target !== radio) {
                            radio.checked = true;
                            radio.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });

                    tbody.addEventListener('change', () => {
                        const checked = form.querySelector('input[name=deliveryOptionId]:checked');
                        submitBtn.disabled = !checked;
                        label.textContent = checked ? 'المختارة: ' + checked.dataset.label : 'اختر شركة من القائمة';
                        form.dataset.confirm = checked ? 'إنشاء طلب الشحن لدى OTO مع: ' + checked.dataset.label + '؟' : '';
                        Array.from(tbody.rows).forEach(row => row.classList.toggle('is-selected', !!checked && row.contains(checked)));
                    });
                })();
            </script>
        @endif
    </div>
@endif
