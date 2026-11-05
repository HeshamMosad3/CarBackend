@php
    $tones = [
        'danger' => ['#b42318', '#fef3f2'],
        'warning' => ['#b54708', '#fffaeb'],
        'primary' => ['#2f5bea', '#eef2ff'],
        'info' => ['#0b7285', '#e3fafc'],
    ];
    [$accent, $soft] = $tones[$tone ?? 'warning'] ?? $tones['warning'];
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<body style="margin:0;padding:24px;background:#f4f6fa;font-family:Tahoma,Arial,sans-serif;direction:rtl;text-align:right;color:#1d2433">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e6e9f0;border-radius:14px;overflow:hidden">
        <div style="background:{{ $accent }};color:#ffffff;padding:14px 22px;font-size:13px;font-weight:bold">
            {{ $typeLabel ?? 'تنبيه من لوحة التحكم' }}
        </div>
        <div style="padding:22px">
            <h2 style="margin:0 0 10px;font-size:19px;color:#1d2433">{{ $title }}</h2>
            @if (!empty($body))
                <p style="margin:0 0 18px;font-size:15px;line-height:1.8;color:#3b4252">{{ $body }}</p>
            @endif
            @if (!empty($url))
                <a href="{{ $url }}"
                    style="display:inline-block;background:{{ $accent }};color:#ffffff;text-decoration:none;padding:11px 22px;border-radius:9px;font-weight:bold;font-size:14px">
                    {{ $buttonLabel ?? 'فتح في لوحة التحكم' }}
                </a>
            @endif
        </div>
        <div style="background:{{ $soft }};padding:12px 22px;font-size:12px;color:#6b7486">
            وصلك هذا البريد لأن عنوانك مسجّل لاستلام تنبيهات {{ config('app.name') }}.
            يمكن تغيير ما يصلك من
            <a href="{{ url('/dashboard/settings/notification-emails') }}" style="color:{{ $accent }}">صفحة التنبيهات</a>.
        </div>
    </div>
</body>

</html>
