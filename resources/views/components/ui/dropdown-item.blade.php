@props(['href' => null, 'icon' => null, 'destructive' => false])

@php
    $class = 'ui-menu-item'.($destructive ? ' is-destructive' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class, 'role' => 'menuitem']) }}>
        @if ($icon)
            <flux:icon :name="$icon" variant="mini" />
        @endif
        <span>{{ $slot }}</span>
    </a>
@else
    <button type="button" {{ $attributes->merge(['class' => $class, 'role' => 'menuitem']) }}>
        @if ($icon)
            <flux:icon :name="$icon" variant="mini" />
        @endif
        <span>{{ $slot }}</span>
    </button>
@endif
