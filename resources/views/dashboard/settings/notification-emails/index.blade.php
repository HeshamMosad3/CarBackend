@extends('dashboard.layouts.app')
@section('title', 'التنبيهات')
@section('content')
    <x-dash.page-header title="التنبيهات"
        subtitle="كيف تصلك الأشياء التي تحتاج منك إجراءً: إشعار وصوت في هذا المتصفح، وبريد إلكتروني لمن تختارهم."
        :crumbs="['الإعدادات' => null, 'التنبيهات' => null]" />

    <div class="app-content">
        <div class="row g-3">

            {{-- ============ This browser ============ --}}
            <div class="col-xl-5">
                <div class="card h-100">
                    <div class="card-header">
                        <div>
                            <div class="card-title"><i class="bi bi-window-stack icon"></i> هذا المتصفح</div>
                            <div class="card-subtitle">تُحفظ هذه الخيارات في هذا المتصفح فقط.</div>
                        </div>
                    </div>
                    <div class="card-body d-flex flex-column gap-4">

                        <div>
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                <div>
                                    <div class="fw-bold">إشعارات المتصفح</div>
                                    <div class="small text-muted" id="desktopStatusText">…</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" id="pageDesktopBtn">تفعيل الإشعارات</button>
                            </div>
                        </div>

                        <div>
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                <div class="fw-bold"><i class="bi bi-volume-up"></i> صوت التنبيهات</div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="pageSoundToggle" aria-label="صوت التنبيهات">
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-volume-down text-muted"></i>
                                <input type="range" class="form-range flex-grow-1" id="pageVolume" min="0.1" max="1" step="0.05" aria-label="مستوى الصوت">
                                <i class="bi bi-volume-up text-muted"></i>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button type="button" class="btn btn-light btn-sm" id="pageTestSound"><i class="bi bi-play-circle"></i> جرّب الصوت</button>
                                <button type="button" class="btn btn-light btn-sm" id="pageTestUrgent"><i class="bi bi-exclamation-octagon"></i> جرّب صوت التنبيه العاجل</button>
                            </div>
                            <div class="form-text">تتطلب المتصفحات أن تتفاعل مع الصفحة مرة واحدة على الأقل قبل أن يُسمح بتشغيل الصوت.</div>
                        </div>

                        <div>
                            <div class="fw-bold mb-2">ما الذي ينبّهني في هذا المتصفح؟</div>
                            <div class="d-flex flex-column gap-2" id="browserTypes">
                                @foreach ($types as $type)
                                    <label class="d-flex align-items-center gap-3 p-2 rounded-3 border" style="border-color: var(--dash-border) !important; cursor: pointer">
                                        <span class="icon tone-{{ $type['tone'] }}" style="width:34px;height:34px;border-radius:10px;display:grid;place-items:center;flex-shrink:0"><i class="bi {{ $type['icon'] }}"></i></span>
                                        <span class="flex-grow-1 min-w-0">
                                            <span class="d-block fw-semibold">{{ $type['label'] }}</span>
                                            <span class="d-block small text-muted">{{ $type['description'] }}</span>
                                        </span>
                                        <span class="form-check form-switch m-0">
                                            <input class="form-check-input js-browser-type" type="checkbox" role="switch" data-type="{{ $type['key'] }}">
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ Email ============ --}}
            <div class="col-xl-7 d-flex flex-column gap-3">
                <div class="card table-card">
                    <div class="card-header">
                        <div>
                            <div class="card-title"><i class="bi bi-envelope-at icon"></i> الإيميلات التي تصلها التنبيهات <span class="text-muted fw-normal">({{ $emails->count() }})</span></div>
                            <div class="card-subtitle">لكل بريد: تشغيل أو إيقاف، وأنواع التنبيهات التي يستلمها. التغيير يُحفظ فور الضغط.</div>
                        </div>
                    </div>

                    @if ($emails->isEmpty())
                        <x-dash.empty icon="bi-envelope-exclamation" title="لا توجد عناوين مسجلة"
                            text="لن يصلك أي بريد عند وجود إجراء مطلوب. أضف بريداً أدناه." />
                    @else
                        <div class="list-group list-group-flush" id="recipients">
                            @foreach ($emails as $row)
                                @php $subscribed = $row->subscribedTypes(); @endphp
                                <div class="list-group-item p-3 recipient {{ $row->is_active ? '' : 'is-off' }}" style="background: transparent"
                                    data-update-url="{{ route('dashboard.settings.notification-emails.update', $row->id) }}">
                                    <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
                                        <span class="avatar avatar-sm"><i class="bi bi-envelope"></i></span>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="fw-semibold ltr text-end text-truncate">{{ $row->email }}</div>
                                            @if ($row->label)<div class="small text-muted">{{ $row->label }}</div>@endif
                                        </div>
                                        <div class="form-check form-switch m-0" data-bs-toggle="tooltip" title="تشغيل / إيقاف كل التنبيهات لهذا البريد">
                                            <input class="form-check-input js-active" type="checkbox" role="switch" @checked($row->is_active) aria-label="تشغيل">
                                        </div>
                                        <button type="button" class="btn btn-icon js-test" data-bs-toggle="tooltip" title="إرسال رسالة تجريبية"
                                            data-test-url="{{ route('dashboard.settings.notification-emails.test', $row->id) }}">
                                            <i class="bi bi-send"></i>
                                        </button>
                                        <form action="{{ route('dashboard.settings.notification-emails.destroy', $row->id) }}" method="POST" class="d-inline"
                                            data-confirm="لن يستلم {{ $row->email }} أي تنبيهات بعد الآن." data-confirm-danger data-confirm-text="حذف">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-icon danger" data-bs-toggle="tooltip" title="حذف"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </div>
                                    <div class="type-chips">
                                        @foreach ($types as $type)
                                            <button type="button" class="type-chip {{ in_array($type['key'], $subscribed, true) ? 'on' : '' }}"
                                                data-type="{{ $type['key'] }}" title="{{ $type['description'] }}">
                                                <i class="bi {{ $type['icon'] }}"></i> {{ $type['label'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="bi bi-plus-circle icon"></i> إضافة بريد</div>
                    </div>
                    <div class="card-body">
                        @if ($suggestions->isNotEmpty())
                            <div class="mb-3">
                                <div class="small text-muted mb-1">حسابات الإدارة الحالية:</div>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($suggestions as $suggestion)
                                        <button type="button" class="btn btn-sm btn-light ltr js-suggest" data-email="{{ $suggestion }}"><i class="bi bi-plus"></i> {{ $suggestion }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <form action="{{ route('dashboard.settings.notification-emails.store') }}" method="POST" id="addEmailForm">
                            @csrf
                            <div class="row g-2 mb-3">
                                <div class="col-md-7">
                                    <label class="form-label" for="email">البريد الإلكتروني</label>
                                    <input type="email" name="email" id="email" class="form-control ltr text-start @error('email') is-invalid @enderror"
                                        value="{{ old('email') }}" placeholder="name@example.com" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" for="label">اسم أو ملاحظة (اختياري)</label>
                                    <input type="text" name="label" id="label" class="form-control" value="{{ old('label') }}" maxlength="80" placeholder="مثال: مدير العمليات">
                                </div>
                            </div>
                            <div class="form-label">التنبيهات التي يستلمها</div>
                            <div class="type-chips mb-3" id="newTypes">
                                @foreach ($types as $type)
                                    <label class="type-chip on" title="{{ $type['description'] }}">
                                        <input type="checkbox" name="events[]" value="{{ $type['key'] }}" checked hidden>
                                        <i class="bi {{ $type['icon'] }}"></i> {{ $type['label'] }}
                                    </label>
                                @endforeach
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> إضافة</button>
                            <span class="text-muted small ms-2">يُرسل البريد من: <span class="ltr">{{ $mailFrom }}</span></span>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('css_styles')
    <style>
        .type-chips { display: flex; flex-wrap: wrap; gap: .4rem; }
        .type-chip {
            display: inline-flex; align-items: center; gap: .4rem; cursor: pointer; user-select: none;
            padding: .3rem .75rem; border-radius: 999px; font-size: .78rem; font-weight: 600;
            border: 1px solid var(--dash-border); background: var(--dash-surface); color: var(--dash-muted);
            transition: all .15s;
        }
        .type-chip:hover { border-color: var(--dash-muted); color: var(--dash-text); }
        .type-chip.on { background: var(--dash-primary-soft); border-color: var(--dash-primary); color: var(--dash-primary); }
        .type-chip.on::after { content: "\f26e"; font-family: "bootstrap-icons"; font-size: .8rem; }
        .recipient.is-off .type-chips, .recipient.is-off .avatar { opacity: .45; }
    </style>
@endpush

@push('my-java-script')
    <script>
        $(function() {
            // ------------------------------------------------------------
            // Email recipients: every click saves straight away
            // ------------------------------------------------------------
            function save($row, data) {
                return dashRequest('PATCH', $row.data('update-url'), data).catch(function() {});
            }

            $('#recipients').on('change', '.js-active', function() {
                const $row = $(this).closest('.recipient');
                $row.toggleClass('is-off', !this.checked);
                save($row, { is_active: this.checked ? 1 : 0 });
            });

            $('#recipients').on('click', '.type-chip', function() {
                const $row = $(this).closest('.recipient');
                $(this).toggleClass('on');
                const events = $row.find('.type-chip.on').map(function() { return this.dataset.type; }).get();
                save($row, { events_sent: 1, events: events });
            });

            $('#recipients').on('click', '.js-test', function() {
                busy(true, 'جارٍ إرسال الرسالة التجريبية...');
                dashRequest('POST', this.dataset.testUrl).catch(function() {}).finally(function() { busy(false); });
            });

            // New address: the chips are checkboxes in disguise
            $('#newTypes').on('change', 'input', function() { $(this).closest('.type-chip').toggleClass('on', this.checked); });
            $('#newTypes').on('click', '.type-chip', function(e) {
                if (e.target.tagName === 'INPUT') return;
                e.preventDefault();
                const input = $(this).find('input')[0];
                input.checked = !input.checked;
                $(this).toggleClass('on', input.checked);
            });
            $('.js-suggest').on('click', function() { $('#email').val(this.dataset.email).trigger('focus'); });

            // ------------------------------------------------------------
            // This browser: sound, notifications, which alert types
            // ------------------------------------------------------------
            const live = window.DashLive;
            if (!live) return;

            function render() {
                const p = live.prefs();
                $('#pageSoundToggle').prop('checked', !!p.sound);
                $('#pageVolume').val(p.volume);
                $('.js-browser-type').each(function() { this.checked = p.types[this.dataset.type] !== false; });

                const permission = live.desktop.permission();
                const $btn = $('#pageDesktopBtn');
                const text = {
                    default: ['لم تُفعَّل بعد. اضغط «تفعيل» ليسألك المتصفح عن السماح.', 'تفعيل الإشعارات', true],
                    granted: [p.desktop ? 'مفعّلة: ستظهر إشعارات حتى لو كنت في تبويب آخر.' : 'مسموح بها في المتصفح لكنك أوقفتها هنا.', p.desktop ? 'إيقاف مؤقت' : 'تشغيل', false],
                    denied: ['يحظرها المتصفح لهذا الموقع. اضغط على رمز القفل بجانب عنوان الصفحة واسمح بالإشعارات ثم أعد تحميل الصفحة.', 'محظورة', false],
                    unsupported: ['هذا المتصفح لا يدعم الإشعارات.', 'غير مدعومة', false]
                }[permission];
                $('#desktopStatusText').text(text[0]);
                $btn.text(text[1]).prop('disabled', permission === 'denied' || permission === 'unsupported')
                    .toggleClass('btn-primary', text[2]).toggleClass('btn-light', !text[2]);
            }

            $('#pageDesktopBtn').on('click', function() {
                if (live.desktop.permission() === 'default') {
                    live.enableDesktop().then(render);
                } else if (live.desktop.permission() === 'granted') {
                    const p = live.prefs(); p.desktop = !p.desktop; live.savePrefs(p);
                }
            });
            $('#pageSoundToggle').on('change', function() { const p = live.prefs(); p.sound = this.checked; live.savePrefs(p); });
            $('#pageVolume').on('input change', function() { const p = live.prefs(); p.volume = parseFloat(this.value); live.savePrefs(p); });
            $('#pageVolume').on('change', function() { live.sound.play(false, parseFloat(this.value)); });
            $('#pageTestSound').on('click', function() { live.sound.unlock(); setTimeout(function() { live.sound.play(false, live.prefs().volume); }, 120); });
            $('#pageTestUrgent').on('click', function() { live.sound.unlock(); setTimeout(function() { live.sound.play(true, live.prefs().volume); }, 120); });
            $('.js-browser-type').on('change', function() {
                const p = live.prefs();
                p.types[this.dataset.type] = this.checked;
                live.savePrefs(p);
            });

            document.addEventListener('dash:prefs', render);
            document.addEventListener('dash:controls', render);
            render();
        });
    </script>
@endpush
