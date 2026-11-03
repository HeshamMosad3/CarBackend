@extends('dashboard.layouts.app')
@section('title', 'إيميلات الإشعارات')
@section('content')
    <x-dash.page-header title="إيميلات الإشعارات" subtitle="هذه العناوين تستلم بريداً عند كل طلب شحن جديد يحتاج متابعة الإدارة."
        :crumbs="['الإعدادات' => null, 'إيميلات الإشعارات' => null]" />

    <div class="app-content">
        <div class="row g-3">
            <div class="col-lg-4 order-lg-2">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="bi bi-plus-circle icon"></i> إضافة بريد</div>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('dashboard.settings.notification-emails.store') }}" method="POST">
                            @csrf
                            <label class="form-label" for="email">البريد الإلكتروني</label>
                            <input type="email" name="email" id="email" class="form-control ltr text-start mb-3 @error('email') is-invalid @enderror"
                                value="{{ old('email') }}" placeholder="name@example.com" required>
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> إضافة</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8 order-lg-1">
                <div class="card table-card">
                    <div class="card-header">
                        <div class="card-title"><i class="bi bi-envelope-at icon"></i> العناوين المسجلة <span class="text-muted fw-normal">({{ $emails->count() }})</span></div>
                    </div>
                    @if ($emails->isEmpty())
                        <x-dash.empty icon="bi-envelope-exclamation" title="لا توجد عناوين مسجلة"
                            text="لن تصل إشعارات الشحن بالبريد لأحد. أضف بريداً واحداً على الأقل." />
                    @else
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>البريد الإلكتروني</th>
                                        <th>تاريخ الإضافة</th>
                                        <th class="text-end"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($emails as $email)
                                        <tr>
                                            <td>
                                                <div class="entity">
                                                    <span class="avatar avatar-sm"><i class="bi bi-envelope"></i></span>
                                                    <span class="fw-semibold ltr">{{ $email->email }}</span>
                                                </div>
                                            </td>
                                            <td class="text-muted"><span class="ltr">{{ $email->created_at->format('Y-m-d') }}</span></td>
                                            <td class="text-end">
                                                <form action="{{ route('dashboard.settings.notification-emails.destroy', $email->id) }}" method="POST"
                                                    data-confirm="لن يستلم {{ $email->email }} إشعارات الشحن بعد الآن." data-confirm-danger data-confirm-text="حذف">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-icon danger" data-bs-toggle="tooltip" title="حذف">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
