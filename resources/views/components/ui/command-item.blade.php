@props(['icon' => null, 'active' => false])

<div {{ $attributes->merge(['class' => 'ui-command-item'.($active ? ' is-active' : '')]) }}>
    @if ($icon)
        <flux:icon :name="$icon" variant="mini" />
    @endif
    <span>{{ $slot }}</span>
</div>
