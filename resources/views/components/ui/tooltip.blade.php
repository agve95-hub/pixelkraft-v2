@props(['text'])

<span {{ $attributes->merge(['class' => 'pk-ui-tooltip', 'data-tooltip' => $text]) }}>
    {{ $slot }}
</span>
