@props(['value' => 0, 'max' => 100])

@php
    $percent = max(0, min(100, ((float) $value / max(1, (float) $max)) * 100));
@endphp

<div {{ $attributes->merge(['class' => 'pk-ui-progress']) }} role="progressbar" aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="{{ $max }}">
    <span style="width: {{ $percent }}%"></span>
</div>
