@extends('dashboard.layouts.app')
@section('title', 'سجلات النظام')
@section('content')
    @php
        $lines = $logs === '' ? [] : preg_split('/\R/', rtrim($logs));
        $sizeLabel = $size >= 1048576 ? number_format($size / 1048576, 1) . ' MB' : number_format($size / 1024, 1) . ' KB';
    @endphp

    <x-dash.page-header title="سجلات النظام" :subtitle="'آخر 1000 سطر من laravel.log · حجم الملف ' . $sizeLabel . '. مفيدة للمطور عند تتبع الأخطاء.'"
        :crumbs="['سجلات النظام' => null]">
        @if ($exists && $size > 0)
            <a href="{{ route('dashboard.logs.download-logs') }}" class="btn btn-light"><i class="bi bi-download"></i> تنزيل الملف كاملاً</a>
            <form method="POST" action="{{ route('dashboard.logs.clear-logs') }}" data-confirm="سيتم مسح ملف السجلات بالكامل ولا يمكن استرجاعه."
                data-confirm-danger data-confirm-text="مسح السجلات">
                @csrf
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-eraser"></i> مسح السجلات</button>
            </form>
        @endif
    </x-dash.page-header>

    <div class="app-content">
        @if (count($lines) === 0)
            <div class="card">
                <x-dash.empty icon="bi-journal-check" title="السجلات فارغة" text="لم يُسجَّل أي شيء بعد." />
            </div>
        @else
            <div class="filter-bar mb-3">
                <div class="filter-chips" id="levelFilter">
                    <button type="button" class="filter-chip active" data-level="">كل الأسطر</button>
                    <button type="button" class="filter-chip" data-level="is-error"><i class="bi bi-circle-fill text-danger" style="font-size:.45rem"></i> الأخطاء</button>
                    <button type="button" class="filter-chip" data-level="is-warning"><i class="bi bi-circle-fill text-warning" style="font-size:.45rem"></i> التحذيرات</button>
                </div>
                <input type="search" id="logSearch" class="form-control ms-auto" style="max-width: 280px" placeholder="بحث في السجلات..." dir="auto">
            </div>
            <div class="log-viewer" id="logViewer">@foreach ($lines as $line)<div class="log-line {{ preg_match('/\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $line) ? 'is-error' : (preg_match('/\.WARNING:/', $line) ? 'is-warning' : (preg_match('/\.(INFO|NOTICE|DEBUG):/', $line) ? 'is-info' : '')) }}">{{ $line }}</div>@endforeach</div>
        @endif
    </div>
@endsection

@push('my-java-script')
    <script>
        $(function() {
            const viewer = document.getElementById('logViewer');
            if (!viewer) return;
            viewer.scrollTop = viewer.scrollHeight;

            const lines = Array.from(viewer.children);
            let level = '';

            function apply() {
                const q = document.getElementById('logSearch').value.trim().toLowerCase();
                lines.forEach(l => {
                    const show = (!level || l.classList.contains(level)) && (!q || l.textContent.toLowerCase().includes(q));
                    l.classList.toggle('is-hidden', !show);
                });
            }

            $('#levelFilter').on('click', '.filter-chip', function() {
                $(this).addClass('active').siblings().removeClass('active');
                level = this.dataset.level;
                apply();
                viewer.scrollTop = viewer.scrollHeight;
            });
            let timer;
            $('#logSearch').on('input', () => { clearTimeout(timer); timer = setTimeout(apply, 200); });
        });
    </script>
@endpush
