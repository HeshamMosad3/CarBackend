@extends('dashboard.layouts.app')
@section('title', 'مراجعة طلب انضمام')
@section('content')
    @php $isPending = $vendor->account_status === \App\Enums\StatusUserEnum::Pending->value; @endphp

    <x-dash.page-header :title="'طلب انضمام: ' . ($vendor->company_name_ar ?? '')"
        :subtitle="$isPending ? 'راجع بيانات الشركة وصورة السجل التجاري قبل اتخاذ القرار. ستصل الشركة إشعاراً بالنتيجة.' : null"
        :crumbs="['طلبات الانضمام' => route('dashboard.vendors-management.join-requests.index'), ($vendor->company_name_ar ?? 'الطلب') => null]">
        @if ($isPending)
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                <i class="bi bi-x-lg"></i> رفض
            </button>
            <form method="POST" action="{{ route('dashboard.vendors-management.join-requests.active-status') }}"
                data-confirm="قبول «{{ $vendor->company_name_ar }}»؟ ستتمكن الشركة من تسجيل الدخول واستقبال الطلبات فوراً."
                data-confirm-text="قبول الشركة">
                @csrf
                <input type="hidden" name="userId" value="{{ $vendor->user_id }}">
                <button type="submit" class="btn btn-success"><i class="bi bi-check2"></i> قبول الشركة</button>
            </form>
        @else
            <a href="{{ route('dashboard.vendors-management.vendors.show', $vendor->user_id) }}" class="btn btn-light">
                <i class="bi bi-box-arrow-up-left"></i> فتح ملف الشركة
            </a>
        @endif
    </x-dash.page-header>

    <div class="app-content">
        @unless ($isPending)
            <div class="alert alert-info"><i class="bi bi-info-circle"></i> تمت مراجعة هذا الطلب مسبقاً، وحالة الحساب الآن موضحة أدناه.</div>
        @endunless

        @include('dashboard.vendors-management._profile')
    </div>

    @if ($isPending)
        <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST"
                    action="{{ route('dashboard.vendors-management.join-requests.rejected-status', ['userId' => $vendor->user_id]) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectModalTitle">رفض طلب الانضمام</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="rejectReason">سبب الرفض <span class="text-danger">*</span></label>
                        <textarea name="rejectReason" id="rejectReason" class="form-control" rows="4" maxlength="500" required
                            placeholder="مثال: صورة السجل التجاري غير واضحة، يرجى إعادة التسجيل بصورة أوضح.">{{ old('rejectReason') }}</textarea>
                        <div class="form-text">يُرسل هذا السبب للشركة في الإشعار، فاكتبه بوضوح.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg"></i> تأكيد الرفض</button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@if ($isPending && $errors->has('rejectReason'))
    @push('my-java-script')
        <script>$(() => bootstrap.Modal.getOrCreateInstance(document.getElementById('rejectModal')).show());</script>
    @endpush
@endif
