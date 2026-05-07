@props(['type' => 'text'])

<input type="{{ $type }}" {{ $attributes->merge(['class' => 'pk-ui-input']) }}>
