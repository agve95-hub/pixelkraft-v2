@props(['color' => 'blue'])

@php
    $palette = match ($color) {
        'green' => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/25',
        'yellow' => 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/25',
        'red' => 'bg-red-500/15 text-red-300 ring-1 ring-red-500/25',
        default => 'bg-sky-500/15 text-sky-300 ring-1 ring-sky-500/25',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ' . $palette]) }}>
    {{ $slot }}
</span>
