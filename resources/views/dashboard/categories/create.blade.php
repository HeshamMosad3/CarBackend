@extends('dashboard.layouts.app')
@section('title', 'إضافة قسم')
@section('content')
    <x-dash.page-header title="إضافة قسم"
        :crumbs="['الأقسام' => route('dashboard.categories.index'), 'إضافة قسم' => null]" />

    <div class="app-content">
        @include('dashboard.categories._form', ['action' => route('dashboard.categories.store')])
    </div>
@endsection
