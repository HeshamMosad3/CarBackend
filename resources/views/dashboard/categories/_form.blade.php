@php
    /** @var \App\Models\Category|null $category */
    $category = $category ?? null;
    $commissionType = old('commissionType', $category->commission_type ?? \App\Enums\CommissionTypeEnum::Rate->value);
    $commissionValue = old('commission', $category
        ? ($category->commission_type === \App\Enums\CommissionTypeEnum::Rate->value ? $category->commission * 100 : $category->commission)
        : '');
    $hasBrand = (bool) old('categoryHasBrand', $category ? ($category->is_category_has_brand_field ?? false) : true);
    $image = $category && $category->cat_icon_path
        ? url('uploads/categories-icon/' . $category->cat_icon_path)
        : asset('assets/dashboard/images/empty_image.png');
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" autocomplete="off">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">بيانات القسم</div>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label" for="catNameAr">اسم القسم <span class="text-danger">*</span></label>
                        <input type="text" name="catNameAr" id="catNameAr" class="form-control @error('catNameAr') is-invalid @enderror"
                            value="{{ old('catNameAr', $category->cat_name_ar ?? '') }}" maxlength="100" required
                            placeholder="مثال: قطع الغيار">
                        @error('catNameAr')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="section-label">عمولة المنصة</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label class="form-label" for="commissionType">نوع العمولة</label>
                            <select class="form-select" name="commissionType" id="commissionType">
                                @foreach (\App\Enums\CommissionTypeEnum::cases() as $case)
                                    <option value="{{ $case->value }}" @selected($case->value === $commissionType)>
                                        {{ __('messages.' . $case->value) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="commission">قيمة العمولة <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="commission" id="commission" step="0.01" min="0"
                                    class="form-control text-start @error('commission') is-invalid @enderror"
                                    value="{{ $commissionValue }}" required>
                                <span class="input-group-text" id="commissionSuffix">%</span>
                                @error('commission')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-text" id="commissionHint"></div>
                        </div>
                    </div>

                    <div class="form-check form-switch">
                        <input type="hidden" name="categoryHasBrand" value="0">
                        <input type="checkbox" class="form-check-input" name="categoryHasBrand" id="categoryHasBrand"
                            value="1" @checked($hasBrand)>
                        <label class="form-check-label" for="categoryHasBrand">
                            يطلب من العميل اختيار ماركة السيارة
                            <span class="d-block form-text mt-0">فعّلها للأقسام المرتبطة بنوع السيارة مثل قطع الغيار.</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">أيقونة القسم</div>
                </div>
                <div class="card-body">
                    <label class="image-picker mb-0" for="file">
                        <img id="previewSelectedImageId" src="{{ $image }}" alt="">
                        <span class="min-w-0">
                            <span class="picker-title d-block">{{ $category ? 'تغيير الأيقونة' : 'اختر أيقونة' }}</span>
                            <span class="picker-hint d-block">اسحب الصورة هنا أو اضغط للاختيار · 64×64 بكسل · PNG, JPG, WEBP</span>
                            <span class="picker-file picker-hint d-block text-truncate"></span>
                        </span>
                        <input type="file" class="d-none" name="file" id="file" accept=".png,.jpg,.jpeg,.webp"
                            @if (!$category) required @endif
                            onchange="previewSelectedImage(event, 'previewSelectedImageId')">
                    </label>
                    @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @if (!$category)
                        <div class="form-text mt-2"><i class="bi bi-info-circle"></i> يُضاف القسم معطّلاً، فعّله من قائمة الأقسام عندما يصبح جاهزاً.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> {{ $category ? 'حفظ التعديلات' : 'إضافة القسم' }}</button>
        <a href="{{ route('dashboard.categories.index') }}" class="btn btn-light">إلغاء</a>
    </div>
</form>

@push('my-java-script')
    <script>
        (function() {
            const type = document.getElementById('commissionType');
            const value = document.getElementById('commission');
            const suffix = document.getElementById('commissionSuffix');
            const hint = document.getElementById('commissionHint');
            const RATE = @json(\App\Enums\CommissionTypeEnum::Rate->value);

            function sync() {
                const isRate = type.value === RATE;
                suffix.textContent = isRate ? '%' : 'ر.س';
                value.max = isRate ? 100 : '';
                hint.textContent = isRate ?
                    'نسبة مئوية من قيمة عرض الشركة. مثال: 5 تعني 5%.' :
                    'مبلغ ثابت بالريال على كل عملية.';
            }
            type.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush
