@props(['icon' => 'bi-inbox', 'title' => 'لا توجد بيانات', 'text' => null])
<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <div class="empty-icon"><i class="bi {{ $icon }}"></i></div>
    <h3>{{ $title }}</h3>
    @if ($text)
        <p>{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
