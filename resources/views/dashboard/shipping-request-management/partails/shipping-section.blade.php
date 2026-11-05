@php
    use App\Enums\StatusShippingRequestEnum as ShippingStatus;
    use App\Support\Shipping\PickupMethod;

    $status = $shippingRequest->status;
    $isLegacy = $status->isLegacy();
    // All of OTO's delivery options: the admin picks one to book for legacy shipments, uses them as a
    // price reference while a new-flow shipment is reviewed/quoted, and may optionally book one once paid.
    $showOto = $status === ShippingStatus::Pending
        || in_array($status, [ShippingStatus::ReadyForReview, ShippingStatus::SearchingCarrier, ShippingStatus::Paid], true);
    $canCreateOtoOrder = $status === ShippingStatus::Pending || $status === ShippingStatus::Paid;
    $deliveryCompanies = $deliveryCompanies ?? [];
    $canSelect = $canCreateOtoOrder && !$shippingRequest->oto_id;
    // Choosing a company fills the pricing / confirming forms, so it is allowed while pricing too.
    $canPick = ($canCreateOtoOrder || $status === ShippingStatus::SearchingCarrier) && !$shippingRequest->oto_id;
    $savedOption = (string) $shippingRequest->oto_delivery_option_id;
    $tones = ['dropoffOnly' => 'secondary', 'freePickup' => 'success', 'paidPickup' => 'success', 'freePickupDropoff' => 'info'];
@endphp

@if ($isLegacy || $showOto || $shippingRequest->oto_id)
    <div class="card table-card">
        <div class="card-header">
            <div>
                <div class="card-title"><i class="bi bi-building icon"></i> شركات الشحن المتاحة عبر OTO</div>
                <div class="card-subtitle">
                    @if ($canPick)
                        اختر شركة: يُعبّأ اسمها وسعرها وطريقة التسليم في النموذج تلقائياً.
                    @else
                        أسعار مرجعية تساعدك في التسعير.
                    @endif
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
            <div class="px-3 pt-3 d-flex flex-column gap-2">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted small">ترتيب حسب:</span>
                    <div class="filter-chips" id="shipping-companies-sort">
                        <button type="button" class="filter-chip active" data-sort="price-asc"><i class="bi bi-sort-numeric-down"></i> الأرخص</button>
                        <button type="button" class="filter-chip" data-sort="time-asc"><i class="bi bi-lightning-charge"></i> الأسرع</button>
                        <button type="button" class="filter-chip" data-sort="price-desc"><i class="bi bi-sort-numeric-up"></i> الأغلى</button>
                    </div>
                    <span class="text-muted small ms-auto" id="shipping-companies-count">{{ count($deliveryCompanies) }} خيار</span>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted small">طريقة التسليم للشركة:</span>
                    <div class="filter-chips" id="shipping-companies-filter">
                        <button type="button" class="filter-chip active" data-filter="">الكل</button>
                        <button type="button" class="filter-chip" data-filter="pickup"><i class="bi bi-house-door"></i> استلام من الباب</button>
                        <button type="button" class="filter-chip" data-filter="dropoff"><i class="bi bi-shop"></i> تسليم للفرع</button>
                    </div>
                </div>
            </div>

            <form id="form-query-shipping" method="POST" action="{{ route('dashboard.shipping-request-management.create-order-shipping') }}"
                data-confirm="تأكيد" data-confirm-text="إنشاء الطلب">
                @csrf
                <input type="hidden" name="shippingRequestId" value="{{ $shippingRequest->id }}">
                <input type="hidden" name="carrierName" id="oto-carrier-name">
                <input type="hidden" name="carrierPickupSupport" id="oto-carrier-support">
                <input type="hidden" name="pickupMethod" id="oto-pickup-method">

                <div class="table-responsive mt-2">
                    <table class="table table-hover" id="shipping-companies-table">
                        <thead>
                            <tr>
                                @if ($canPick)<th style="width:1%"></th>@endif
                                <th>الشركة</th>
                                <th>طريقة التسليم</th>
                                <th>السعر</th>
                                <th>مدة التوصيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deliveryCompanies as $i => $company)
                                @php
                                    $optionId = $company['deliveryOptionId'] ?? null;
                                    $optionName = $company['deliveryOptionName'] ?? ($company['deliveryCompanyName'] ?? '-');
                                    $support = $company['pickupDropoff'] ?? null;
                                    $allow = PickupMethod::allowed($support);
                                    $logo = $company['logo'] ?? null;
                                    $cutoff = $company['pickupCutOffTime'] ?? null;
                                    $branches = $company['checkAllBranches'] ?? null;
                                @endphp
                                <tr class="{{ $canPick && $optionId ? 'carrier-option' : '' }}"
                                    data-id="{{ $optionId }}" data-name="{{ $optionName }}" data-support="{{ $support }}"
                                    data-allow="{{ implode(',', $allow) }}"
                                    data-price="{{ (float) ($company['price'] ?? 0) }}"
                                    data-time="{{ $company['avgDeliveryTime'] ?? '' }}"
                                    data-text-pickup="{{ PickupMethod::instructions('pickup', $optionName) }}"
                                    data-text-dropoff="{{ PickupMethod::instructions('dropoff', $optionName) }}">
                                    @if ($canPick)
                                        <td>
                                            @if ($optionId)
                                                <input class="form-check-input" type="radio" name="deliveryOptionId"
                                                    id="delivery-option-{{ $i }}" value="{{ $optionId }}"
                                                    data-label="{{ $optionName }} — {{ $company['price'] ?? '-' }} ر.س"
                                                    @checked($savedOption !== '' && $savedOption === (string) $optionId)>
                                            @endif
                                        </td>
                                    @endif
                                    <td>
                                        <label class="entity mb-0" for="delivery-option-{{ $i }}" style="cursor: inherit">
                                            @if ($logo)
                                                <img src="{{ $logo }}" alt="" class="thumb" style="width:36px;height:36px" loading="lazy" onerror="this.remove()">
                                            @endif
                                            <span class="min-w-0">
                                                <span class="cell-title d-block">{{ $optionName }}</span>
                                                @if (!empty($company['maxFreeWeight']))
                                                    <span class="cell-sub d-block">وزن حتى {{ $company['maxFreeWeight'] }} كغ</span>
                                                @endif
                                            </span>
                                            @if ($i === 0)
                                                <span class="status-pill tone-success no-dot" data-cheapest-badge>الأرخص</span>
                                            @endif
                                        </label>
                                    </td>
                                    <td>
                                        <span class="status-pill tone-{{ $tones[$support] ?? 'secondary' }} no-dot">{{ PickupMethod::supportLabel($support) }}</span>
                                        @if ($cutoff && in_array('pickup', $allow, true))
                                            <div class="cell-sub mt-1">آخر موعد للاستلام <span class="ltr">{{ $cutoff }}</span></div>
                                        @endif
                                        @if ($branches && in_array('dropoff', $allow, true))
                                            <div class="cell-sub"><a href="{{ $branches }}" target="_blank" rel="noopener">فروع الشركة <i class="bi bi-box-arrow-up-left"></i></a></div>
                                        @endif
                                    </td>
                                    <td class="text-nowrap"><span class="fw-semibold num">{{ $company['price'] ?? '-' }}</span> <span class="text-muted small">ر.س</span></td>
                                    <td>{{ $company['avgDeliveryTime'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($canPick)
                    <div class="card-footer d-flex align-items-center gap-2 flex-wrap">
                        <span class="small" id="selected-carrier-label">اختر شركة من القائمة</span>
                        @if ($status === ShippingStatus::SearchingCarrier)
                            <button type="button" class="btn btn-light btn-sm" id="use-quote-price" disabled><i class="bi bi-cash-coin"></i> استخدم سعر الشركة كتكلفة للزبون</button>
                        @endif
                        @if ($canSelect)
                            <button type="submit" class="btn btn-primary ms-auto" id="btn-query-shipping" disabled>
                                <i class="bi bi-box-arrow-up-left"></i>
                                {{ $isLegacy ? 'إنشاء طلب الشحن مع الشركة المختارة' : 'الحجز لدى OTO مع الشركة المختارة' }}
                            </button>
                        @endif
                    </div>
                @endif
            </form>

            <script>
                (function() {
                    const table = document.getElementById('shipping-companies-table');
                    const tbody = table.querySelector('tbody');
                    const form = document.getElementById('form-query-shipping');
                    const $ = (id) => document.getElementById(id);
                    const submitBtn = $('btn-query-shipping');
                    const useBtn = $('use-quote-price');
                    const label = $('selected-carrier-label');
                    let selectedRow = null;

                    // ---- sorting and filtering ----
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
                    document.querySelectorAll('#shipping-companies-filter [data-filter]').forEach(btn => {
                        btn.addEventListener('click', () => {
                            document.querySelectorAll('#shipping-companies-filter [data-filter]').forEach(b => b.classList.toggle('active', b === btn));
                            let shown = 0;
                            Array.from(tbody.rows).forEach(row => {
                                const ok = !btn.dataset.filter || row.dataset.allow.split(',').includes(btn.dataset.filter);
                                row.hidden = !ok;
                                if (ok) shown++;
                            });
                            $('shipping-companies-count').textContent = shown + ' خيار';
                        });
                    });

                    if (!submitBtn && !useBtn && !table.querySelector('input[type=radio]')) return;

                    // ---- choosing a company fills the forms ----
                    // Forms that live in the "next step" card above: pricing (carrier fields are hidden there)
                    // and confirming shipping (visible, editable). Missing ones are simply skipped.
                    function put(id, value) {
                        const el = $(id);
                        if (!el) return;
                        el.value = value;
                        // Tell the live-refresh code the admin's form has changed, so it won't wipe it.
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    function currentMethod() {
                        const checked = document.querySelector('input[name="pickup_method"]:checked');
                        return checked ? checked.value : ((($('q_method') || {}).value) || '');
                    }

                    function applyMethod(method, allow) {
                        document.querySelectorAll('input[name="pickup_method"]').forEach(radio => {
                            radio.disabled = !allow.includes(radio.value);
                            radio.checked = radio.value === method;
                            const wrap = radio.closest('.method-option');
                            if (wrap) wrap.classList.toggle('is-disabled', radio.disabled);
                        });
                        put('q_method', method);
                        const oto = $('oto-pickup-method');
                        if (oto) oto.value = method;

                        // The text the vendor reads: only replaced if the admin hasn't written their own.
                        const details = $('pickup_details');
                        if (details && selectedRow) {
                            const text = selectedRow.dataset['text' + method.charAt(0).toUpperCase() + method.slice(1)] || '';
                            if (!details.value.trim() || details.value === details.dataset.auto) {
                                details.value = text;
                                details.dataset.auto = text;
                                details.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        }
                    }

                    function choose(row) {
                        selectedRow = row;
                        const allow = row.dataset.allow.split(',');
                        const name = row.dataset.name;

                        put('carrier_name', name);
                        put('q_carrier_name', name);
                        put('oto_delivery_option_id', row.dataset.id);
                        put('q_option_id', row.dataset.id);
                        put('carrier_pickup_support', row.dataset.support);
                        put('q_support', row.dataset.support);
                        put('oto-carrier-name', name);
                        put('oto-carrier-support', row.dataset.support);

                        // Keep the method already chosen if this company allows it; otherwise the first it does.
                        let method = currentMethod();
                        if (!method || !allow.includes(method)) method = allow.includes('pickup') ? 'pickup' : 'dropoff';
                        applyMethod(method, allow);

                        // While pricing: suggest the company's price, unless the admin typed their own.
                        const cost = $('shipping_cost');
                        if (cost && (!cost.value || cost.dataset.auto === '1')) {
                            cost.value = row.dataset.price;
                            cost.dataset.auto = '1';
                            cost.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        if (useBtn) useBtn.disabled = false;
                        const quoteLabel = $('quoteCarrierLabel');
                        if (quoteLabel) quoteLabel.textContent = name;

                        label.innerHTML = '<i class="bi bi-check2-circle text-success"></i> المختارة: <b></b>';
                        label.querySelector('b').textContent = name;
                        if (submitBtn) submitBtn.disabled = false;
                        if (form) form.dataset.confirm = 'إنشاء طلب الشحن لدى OTO مع: ' + name + '؟';
                        Array.from(tbody.rows).forEach(r => r.classList.toggle('is-selected', r === row));
                    }

                    tbody.addEventListener('click', e => {
                        const row = e.target.closest('tr');
                        const radio = row && row.querySelector('input[type=radio]');
                        if (!radio || e.target.closest('a')) return;
                        if (e.target !== radio) radio.checked = true;
                        choose(row);
                    });

                    // Changing the method in the confirm form re-fills the vendor's instructions.
                    document.querySelectorAll('input[name="pickup_method"]').forEach(radio => {
                        radio.addEventListener('change', () => {
                            const allow = selectedRow ? selectedRow.dataset.allow.split(',') : ['pickup', 'dropoff'];
                            applyMethod(radio.value, allow);
                        });
                    });
                    const cost = $('shipping_cost');
                    if (cost) cost.addEventListener('input', e => { if (e.isTrusted) cost.dataset.auto = '0'; });
                    if (useBtn) useBtn.addEventListener('click', () => {
                        if (!selectedRow || !cost) return;
                        cost.value = selectedRow.dataset.price;
                        cost.dataset.auto = '1';
                        cost.dispatchEvent(new Event('input', { bubbles: true }));
                    });

                    // The company chosen earlier (while pricing, or in an earlier visit) is pre-selected.
                    const saved = table.querySelector('input[type=radio]:checked');
                    if (saved) {
                        choose(saved.closest('tr'));
                        // Restoring a saved choice isn't an edit: don't hold back live updates because of it.
                        const region = table.closest('[data-live-region]');
                        if (region) delete region.dataset.dirty;
                    }
                })();
            </script>
        @endif
    </div>
@endif
