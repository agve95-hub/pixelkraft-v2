@props(['icon' => null, 'title' => null, 'meta' => null])

<div {{ $attributes->merge(['class' => 'pk-ui-item']) }}>
    @if ($icon)
        <span class="pk-ui-item-icon"><flux:icon :name="$icon" /></span>
    @endif
    <div class="pk-ui-item-content">
        @if ($title)
            <p class="pk-ui-item-title">{{ $title }}</p>
        @endif
        <div class="pk-ui-item-body">{{ $slot }}</div>
        @if ($meta)
            <p class="pk-ui-item-meta">{{ $meta }}</p>
        @endif
    </div>
</div>
