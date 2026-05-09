@props(['align' => 'start'])

<div {{ $attributes->merge(['class' => 'ui-button-group ui-button-group-'.$align]) }}>
    {{ $slot }}
</div>
