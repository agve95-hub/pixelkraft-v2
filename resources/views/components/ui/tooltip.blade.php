@props(['text'])

<span {{ $attributes->merge(['class' => 'ui-tooltip', 'data-tooltip' => $text]) }}>
    {{ $slot }}
</span>
