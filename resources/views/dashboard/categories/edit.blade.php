@extends('dashboard.layouts.app')
@section('title', 'تعديل قسم')
@section('content')
    <x-dash.page-header :title="'تعديل: ' . $category->cat_name_ar"
        :crumbs="['الأقسام' => route('dashboard.categories.index'), $category->cat_name_ar => null]">
        <a href="{{ route('dashboard.custom-fields.index', $category->id) }}" class="btn btn-light"><i class="bi bi-ui-checks"></i> الحقول المخصصة</a>
    </x-dash.page-header>

    <div class="app-content">
        @include('dashboard.categories._form', ['action' => route('dashboard.categories.update', ['id' => $category->id])])
    </div>
@endsection
