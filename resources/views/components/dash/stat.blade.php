@props(['label', 'value', 'icon', 'tone' => 'primary', 'hint' => null, 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif class="card stat-card">
    <div class="card-body">
        <span class="stat-icon tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
        <div class="min-w-0">
            <div class="stat-label">{{ $label }}</div>
            <div class="stat-value">{{ is_numeric($value) ? number_format($value) : $value }}</div>
            @if ($hint)
                <div class="stat-hint">{{ $hint }}</div>
            @endif
        </div>
    </div>
</{{ $tag }}>
