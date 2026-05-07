@props(['label' => null])

<label class="pk-ui-switch {{ $attributes->get('class') }}">
    <input type="checkbox" {{ $attributes->except('class') }}>
    <span class="pk-ui-switch-track"><span class="pk-ui-switch-thumb"></span></span>
    @if ($label)
        <span>{{ $label }}</span>
    @else
        <span>{{ $slot }}</span>
    @endif
</label>
