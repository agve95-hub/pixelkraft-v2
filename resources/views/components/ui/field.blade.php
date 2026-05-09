@props([
    'label' => null,
    'for' => null,
    'hint' => null,
    'error' => null,
])

<div {{ $attributes->merge(['class' => 'ui-field']) }}>
    @if ($label)
        <x-ui.label :for="$for">{{ $label }}</x-ui.label>
    @endif

    {{ $slot }}

    @if ($error)
        <p class="ui-field-error">{{ $error }}</p>
    @elseif ($hint)
        <p class="ui-field-hint">{{ $hint }}</p>
    @endif
</div>
