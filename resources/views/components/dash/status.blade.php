@props(['type', 'value'])
@php [$label, $tone] = \App\Support\Dashboard\StatusPresenter::get($type, $value); @endphp
<span {{ $attributes->merge(['class' => "status-pill tone-$tone"]) }}>{{ $label }}</span>
