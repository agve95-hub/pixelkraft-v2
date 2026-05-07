@props([
    'variant' => 'default',
    'dot' => false,
])

<span {{ $attributes->merge(['class' => 'pk-ui-badge pk-ui-badge-'.$variant.($dot ? ' pk-ui-badge-dot' : '')]) }}>
    {{ $slot }}
</span>
