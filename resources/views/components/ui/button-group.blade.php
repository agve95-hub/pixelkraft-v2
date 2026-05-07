@props(['align' => 'start'])

<div {{ $attributes->merge(['class' => 'pk-ui-button-group pk-ui-button-group-'.$align]) }}>
    {{ $slot }}
</div>
