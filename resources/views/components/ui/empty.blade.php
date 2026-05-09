@props([
    'icon' => 'inbox',
    'title' => 'Nothing here yet',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'ui-empty']) }}>
    <span class="ui-empty-icon"><flux:icon :name="$icon" /></span>
    <div>
        <p class="ui-empty-title">{{ $title }}</p>
        @if ($description)
            <p class="ui-empty-description">{{ $description }}</p>
        @endif
    </div>
    @if (trim((string) $slot) !== '')
        <div class="ui-empty-actions">{{ $slot }}</div>
    @endif
</div>
