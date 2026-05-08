@props([
    'variant' => 'default',
    'size' => 'default',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'iconEnd' => null,
    'disabled' => false,
])

@php
    $classes = $attributes->get('class');
    $base = "pk-ui-button pk-ui-button-{$variant} pk-ui-button-{$size}";
@endphp

@if ($href && $disabled)
    <span {{ $attributes->except(['class', 'href', 'target'])->merge(['class' => trim($base.' '.$classes), 'role' => 'button', 'aria-disabled' => 'true', 'tabindex' => '-1']) }}>
        @if ($icon)
            <flux:icon :name="$icon" variant="mini" class="pk-ui-button-icon" />
        @endif
        <span>{{ $slot }}</span>
        @if ($iconEnd)
            <flux:icon :name="$iconEnd" variant="mini" class="pk-ui-button-icon" />
        @endif
    </span>
@elseif ($href)
    <a href="{{ $href }}" {{ $attributes->except('class')->merge(['class' => trim($base.' '.$classes), 'role' => 'button', 'aria-disabled' => $disabled ? 'true' : null]) }}>
        @if ($icon)
            <flux:icon :name="$icon" variant="mini" class="pk-ui-button-icon" />
        @endif
        <span>{{ $slot }}</span>
        @if ($iconEnd)
            <flux:icon :name="$iconEnd" variant="mini" class="pk-ui-button-icon" />
        @endif
    </a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->except('class')->merge(['class' => trim($base.' '.$classes)]) }}>
        @if ($icon)
            <flux:icon :name="$icon" variant="mini" class="pk-ui-button-icon" />
        @endif
        <span>{{ $slot }}</span>
        @if ($iconEnd)
            <flux:icon :name="$iconEnd" variant="mini" class="pk-ui-button-icon" />
        @endif
    </button>
@endif
