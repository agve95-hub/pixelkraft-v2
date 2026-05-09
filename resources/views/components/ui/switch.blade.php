@props(['label' => null])

<label class="ui-switch {{ $attributes->get('class') }}">
    <input type="checkbox" {{ $attributes->except('class') }}>
    <span class="ui-switch-track"><span class="ui-switch-thumb"></span></span>
    @if ($label)
        <span>{{ $label }}</span>
    @else
        <span>{{ $slot }}</span>
    @endif
</label>
