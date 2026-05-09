@props(['label' => null])

<label class="ui-check {{ $attributes->get('class') }}">
    <input type="checkbox" {{ $attributes->except('class') }}>
    <span class="ui-check-box"></span>
    @if ($label)
        <span>{{ $label }}</span>
    @else
        <span>{{ $slot }}</span>
    @endif
</label>
