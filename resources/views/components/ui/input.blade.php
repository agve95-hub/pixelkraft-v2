@props(['type' => 'text'])

<input type="{{ $type }}" {{ $attributes->merge(['class' => 'ui-input']) }}>
