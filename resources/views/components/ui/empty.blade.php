@props([
    'icon' => 'inbox',
    'title' => 'Nothing here yet',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'pk-ui-empty']) }}>
    <span class="pk-ui-empty-icon"><flux:icon :name="$icon" /></span>
    <div>
        <p class="pk-ui-empty-title">{{ $title }}</p>
        @if ($description)
            <p class="pk-ui-empty-description">{{ $description }}</p>
        @endif
    </div>
    @if (trim((string) $slot) !== '')
        <div class="pk-ui-empty-actions">{{ $slot }}</div>
    @endif
</div>
