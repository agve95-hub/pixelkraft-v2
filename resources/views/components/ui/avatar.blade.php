@props(['name' => null, 'src' => null])

@php
    $initials = collect(explode(' ', (string) $name))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') ?: 'P';
@endphp

<span {{ $attributes->merge(['class' => 'pk-ui-avatar']) }}>
    @if ($src)
        <img src="{{ $src }}" alt="{{ $name ?? 'Avatar' }}">
    @else
        <span>{{ mb_strtoupper($initials) }}</span>
    @endif
</span>
