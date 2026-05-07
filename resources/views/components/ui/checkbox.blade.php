@props(['label' => null])

<label class="pk-ui-check {{ $attributes->get('class') }}">
    <input type="checkbox" {{ $attributes->except('class') }}>
    <span class="pk-ui-check-box"></span>
    @if ($label)
        <span>{{ $label }}</span>
    @else
        <span>{{ $slot }}</span>
    @endif
</label>
