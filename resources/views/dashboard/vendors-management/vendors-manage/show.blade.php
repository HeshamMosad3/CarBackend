@extends('dashboard.layouts.app')
@section('title', $vendor->company_name_ar ?? 'بيانات الشركة')
@section('content')
    @php
        $statusUrl = route('dashboard.vendors-management.vendors.update-status');
        $actions = [
            'Active' => ['تفعيل الحساب', 'bi-check-circle', null],
            'Suspended' => ['تعليق مؤقت', 'bi-pause-circle', 'لن تستقبل الشركة طلبات جديدة أثناء التعليق، وسيصلها إشعار بذلك.'],
            'Inactive' => ['تعطيل الحساب', 'bi-slash-circle', 'سيتم تعطيل حساب الشركة وسيصلها إشعار بذلك.'],
            'Rejected' => ['رفض الحساب', 'bi-x-circle', 'سيتم رفض حساب الشركة وسيصلها إشعار بذلك.'],
        ];
    @endphp

    <x-dash.page-header :title="$vendor->company_name_ar ?? 'بيانات الشركة'"
        :crumbs="['الشركات' => route('dashboard.vendors-management.vendors.index'), ($vendor->company_name_ar ?? 'الشركة') => null]">
        <div class="dropdown">
            <button type="button" class="btn btn-light" data-bs-toggle="dropdown"><i class="bi bi-arrow-repeat"></i> تغيير الحالة</button>
            <ul class="dropdown-menu dropdown-menu-end">
                @foreach ($actions as $status => [$label, $icon, $confirm])
                    @continue($status === $vendor->account_status)
                    <li>
                        <a href="#" class="dropdown-item" data-status-url="{{ $statusUrl }}" data-id="{{ $vendor->user_id }}"
                            data-status="{{ $status }}" @if ($confirm) data-confirm="{{ $confirm }}" @endif>
                            <i class="bi {{ $icon }}"></i> {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <button type="button" class="btn btn-outline-danger"
            data-delete-url="{{ url('dashboard/vendors-management/vendors/delete/' . $vendor->user_id) }}"
            data-confirm="سيتم حذف الشركة «{{ $vendor->company_name_ar }}» وحسابها نهائياً."
            data-after="{{ route('dashboard.vendors-management.vendors.index') }}">
            <i class="bi bi-trash3"></i> حذف
        </button>
    </x-dash.page-header>

    <div class="app-content">
        @include('dashboard.vendors-management._profile')
    </div>
@endsection
