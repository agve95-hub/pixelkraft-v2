@props([
    'label' => null,
    'for' => null,
    'hint' => null,
    'error' => null,
])

<div {{ $attributes->merge(['class' => 'pk-ui-field']) }}>
    @if ($label)
        <x-ui.label :for="$for">{{ $label }}</x-ui.label>
    @endif

    {{ $slot }}

    @if ($error)
        <p class="pk-ui-field-error">{{ $error }}</p>
    @elseif ($hint)
        <p class="pk-ui-field-hint">{{ $hint }}</p>
    @endif
</div>
