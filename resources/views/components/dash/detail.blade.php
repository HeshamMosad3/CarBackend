@props(['label'])
{{-- One row of a <dl class="detail-list">. An empty slot renders a dash. The slot is already
     escaped by Blade; it is trimmed so "white-space: pre-line" values don't pick up indentation. --}}
@php $value = trim((string) $slot); @endphp
<div class="detail-row">
    <dt>{{ $label }}</dt>
    <dd {{ $attributes }}>@if ($value === '')<span class="text-muted fw-normal">—</span>@else{!! $value !!}@endif</dd>
</div>
