@props([
    'variant' => 'default',
    'dot' => false,
])

<span {{ $attributes->merge(['class' => 'ui-badge ui-badge-'.$variant.($dot ? ' ui-badge-dot' : '')]) }}>
    {{ $slot }}
</span>
