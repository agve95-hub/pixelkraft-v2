@props(['for' => null])

<label @if($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'pk-ui-label']) }}>
    {{ $slot }}
</label>
