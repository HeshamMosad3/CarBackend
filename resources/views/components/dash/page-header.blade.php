@props(['title', 'subtitle' => null, 'crumbs' => []])
{{-- crumbs: ['label' => 'url-or-null', ...] — the home link is added automatically. --}}
<div class="page-header">
    <div class="min-w-0">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                @foreach ($crumbs as $label => $url)
                    @if ($url)
                        <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                    @else
                        <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
                    @endif
                @endforeach
            </ol>
        </nav>
        <h1>{{ $title }}</h1>
        @if ($subtitle)
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="page-actions">{{ $slot }}</div>
    @endif
</div>
