@extends('dashboard.layouts.app')
@section('title', 'الأقسام والعمولات')
@section('content')
    <x-dash.page-header title="الأقسام والعمولات" subtitle="الأقسام التي تظهر للعملاء في التطبيق، وعمولة المنصة على كل قسم."
        :crumbs="['الأقسام' => null]">
        <a href="{{ route('dashboard.categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> إضافة قسم</a>
    </x-dash.page-header>

    <div class="app-content">
        <div class="card table-card">
            @if ($categories->isEmpty())
                <x-dash.empty icon="bi-tags" title="لا توجد أقسام بعد" text="أضف أول قسم ليظهر للعملاء في التطبيق.">
                    <a href="{{ route('dashboard.categories.create') }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i> إضافة قسم</a>
                </x-dash.empty>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>القسم</th>
                                <th>العمولة</th>
                                <th>الحالة</th>
                                <th class="text-end">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $item)
                                @php
                                    $isRate = $item->commission_type === \App\Enums\CommissionTypeEnum::Rate->value;
                                    $statusOptions = collect(\App\Enums\CategoryStatusEnum::cases())->map->value;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="entity">
                                            <img class="thumb" src="{{ url('/uploads/categories-icon/' . $item->cat_icon_path) }}" alt=""
                                                loading="lazy" onerror="this.src='{{ asset('assets/dashboard/images/empty_image.png') }}'">
                                            <div class="min-w-0">
                                                <a href="{{ route('dashboard.categories.edit', $item->id) }}" class="cell-title">{{ $item->cat_name_ar }}</a>
                                                <div class="cell-sub">رقم {{ $item->id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold num">{{ $isRate ? rtrim(rtrim(number_format($item->commission * 100, 2), '0'), '.') . '%' : number_format($item->commission, 2) . ' ر.س' }}</span>
                                        <div class="cell-sub">{{ $isRate ? 'نسبة من قيمة العرض' : 'مبلغ ثابت لكل عملية' }}</div>
                                    </td>
                                    <td>
                                        @php [$label, $tone] = \App\Support\Dashboard\StatusPresenter::get('category', $item->active); @endphp
                                        <div class="dropdown d-inline-block">
                                            <button type="button" class="status-pill tone-{{ $tone }}" data-bs-toggle="dropdown">{{ $label }}</button>
                                            <ul class="dropdown-menu">
                                                <li><h6 class="dropdown-header">تغيير الحالة</h6></li>
                                                @foreach ($statusOptions as $option)
                                                    @php [$optLabel, $optTone] = \App\Support\Dashboard\StatusPresenter::get('category', $option); @endphp
                                                    <li>
                                                        <a href="#" class="dropdown-item {{ $option === $item->active ? 'active' : '' }}"
                                                            data-status-url="{{ route('dashboard.categories.update-status') }}"
                                                            data-id="{{ $item->id }}" data-status="{{ $option }}"
                                                            @if ($option === 'Inactive') data-confirm="سيختفي القسم «{{ $item->cat_name_ar }}» من التطبيق." @endif>
                                                            <span class="status-pill tone-{{ $optTone }} no-dot" style="padding:.1rem .5rem">●</span>{{ $optLabel }}
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="row-actions">
                                            <a href="{{ route('dashboard.custom-fields.index', $item->id) }}" class="btn btn-sm btn-light">
                                                <i class="bi bi-ui-checks"></i> الحقول المخصصة
                                            </a>
                                            <a href="{{ route('dashboard.categories.edit', $item->id) }}" class="btn btn-icon"
                                                data-bs-toggle="tooltip" title="تعديل"><i class="bi bi-pencil"></i></a>
                                            <button type="button" class="btn btn-icon danger" data-bs-toggle="tooltip" title="حذف"
                                                data-delete-url="{{ route('dashboard.categories.delete', $item->id) }}"
                                                data-confirm="سيتم حذف القسم «{{ $item->cat_name_ar }}». الطلبات المرتبطة به قد تتأثر.">
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
@endsection
