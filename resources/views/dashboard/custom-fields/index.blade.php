@extends('dashboard.layouts.app')
@section('title', 'الحقول المخصصة')
@section('content')
    @php
        use App\Enums\CustomFieldTypeEnum as FieldType;
        $categoryId = request()->route('categoryId');
        $types = [
            FieldType::Text->value => ['نص قصير', 'bi-input-cursor-text'],
            FieldType::TextArea->value => ['نص طويل', 'bi-textarea-t'],
            FieldType::Number->value => ['رقم', 'bi-123'],
            FieldType::Date->value => ['تاريخ', 'bi-calendar3'],
            FieldType::File->value => ['ملف / صورة', 'bi-paperclip'],
        ];
    @endphp

    <x-dash.page-header :title="'الحقول المخصصة: ' . $categoryName"
        subtitle="حقول إضافية يعبّئها العميل عند إنشاء طلب في هذا القسم."
        :crumbs="['الأقسام' => route('dashboard.categories.index'), $categoryName => route('dashboard.categories.edit', $categoryId), 'الحقول المخصصة' => null]">
        <button type="button" class="btn btn-primary" id="btnAddField"><i class="bi bi-plus-lg"></i> إضافة حقل</button>
    </x-dash.page-header>

    <div class="app-content">
        <div class="card table-card">
            @if ($customFields->isEmpty())
                <x-dash.empty icon="bi-ui-checks" title="لا توجد حقول مخصصة"
                    text="سيرى العميل الحقول الأساسية فقط عند إنشاء طلب في هذا القسم." />
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>عنوان الحقل</th>
                                <th>المعرّف</th>
                                <th>النوع</th>
                                <th>إلزامي</th>
                                <th class="text-end">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customFields as $item)
                                <tr>
                                    <td class="cell-title">{{ $item->label_ar }}</td>
                                    <td><code class="ltr">{{ $item->field_name }}</code></td>
                                    <td>
                                        <span class="chip mb-0"><i class="bi {{ $types[$item->field_type][1] ?? 'bi-question' }}"></i>{{ $types[$item->field_type][0] ?? $item->field_type }}</span>
                                    </td>
                                    <td>
                                        @if ($item->is_required)
                                            <span class="status-pill tone-primary">إلزامي</span>
                                        @else
                                            <span class="status-pill tone-secondary">اختياري</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="row-actions">
                                            <button type="button" class="btn btn-icon js-edit-field" data-bs-toggle="tooltip" title="تعديل"
                                                data-label="{{ $item->label_ar }}" data-name="{{ $item->field_name }}"
                                                data-type="{{ $item->field_type }}" data-required="{{ $item->is_required ? 1 : 0 }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-icon danger" data-bs-toggle="tooltip" title="حذف"
                                                data-delete-url="{{ route('dashboard.custom-fields.delete', $item->id) }}"
                                                data-confirm="سيتم حذف الحقل «{{ $item->label_ar }}» من نموذج الطلب.">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="fieldModal" tabindex="-1" aria-labelledby="fieldModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('dashboard.custom-fields.save-custom-field') }}" autocomplete="off">
                @csrf
                <input type="hidden" name="categoryId" value="{{ $categoryId }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="fieldModalTitle">إضافة حقل</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="labelAr">عنوان الحقل (يظهر للعميل) <span class="text-danger">*</span></label>
                        <input type="text" name="labelAr" id="labelAr" class="form-control" value="{{ old('labelAr') }}" required
                            placeholder="مثال: رقم الهيكل">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="fieldName">المعرّف البرمجي <span class="text-danger">*</span></label>
                        <input type="text" name="fieldName" id="fieldName" class="form-control ltr text-start" value="{{ old('fieldName') }}"
                            required pattern="[A-Za-z][A-Za-z0-9_]*" placeholder="vin_number">
                        <div class="form-text" id="fieldNameHint">بالإنجليزية، بدون مسافات (حروف وأرقام و _ فقط).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">نوع الحقل</label>
                        <div class="row g-2">
                            @foreach ($types as $value => [$label, $icon])
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="fieldType" id="type_{{ $value }}" value="{{ $value }}"
                                        @checked(old('fieldType', FieldType::Text->value) === $value)>
                                    <label class="btn btn-light w-100 justify-content-start" for="type_{{ $value }}"><i class="bi {{ $icon }}"></i> {{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="isRequired" value="0">
                        <input type="checkbox" class="form-check-input" name="isRequired" id="isRequired" value="1" @checked(old('isRequired', 1))>
                        <label class="form-check-label" for="isRequired">حقل إلزامي (لا يمكن إرسال الطلب بدونه)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> حفظ</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('my-java-script')
    <style>
        .btn-check:checked + .btn-light { background: var(--dash-primary-soft); border-color: var(--dash-primary); color: var(--dash-primary); }
    </style>
    <script>
        $(function() {
            const modalEl = document.getElementById('fieldModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            const $name = $('#fieldName');

            function open(field) {
                $('#fieldModalTitle').text(field ? 'تعديل الحقل' : 'إضافة حقل');
                $('#labelAr').val(field ? field.label : '');
                // The identifier is how a field is matched on save, so it can't be renamed.
                $name.val(field ? field.name : '').prop('readonly', !!field);
                $('#fieldNameHint').text(field ? 'لا يمكن تغيير المعرّف بعد إنشاء الحقل.' : 'بالإنجليزية، بدون مسافات (حروف وأرقام و _ فقط).');
                $('input[name=fieldType][value="' + (field ? field.type : 'text') + '"]').prop('checked', true);
                $('#isRequired').prop('checked', field ? field.required == 1 : true);
                modal.show();
            }

            $('#btnAddField').on('click', () => open(null));
            $(document).on('click', '.js-edit-field', function() {
                bootstrap.Tooltip.getInstance(this)?.hide();
                open($(this).data());
            });
            modalEl.addEventListener('shown.bs.modal', () => $('#labelAr').trigger('focus'));

            @if ($errors->any())
                modal.show();
            @endif
        });
    </script>
@endpush
