@props(['active' => false, 'href' => null])

@php
    $class = 'pk-ui-tab'.($active ? ' is-active' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>{{ $slot }}</a>
@else
    <button type="button" {{ $attributes->merge(['class' => $class]) }}>{{ $slot }}</button>
@endif
