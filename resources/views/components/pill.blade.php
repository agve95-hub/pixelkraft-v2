@props(['color' => 'blue'])

@php
    $palette = match ($color) {
        'green' => 'pill-green',
        'yellow' => 'pill-yellow',
        'red' => 'pill-red',
        default => 'pill-blue',
    };
@endphp

<span {{ $attributes->class(['pill ' . $palette]) }}>
    {{ $slot }}
</span>
