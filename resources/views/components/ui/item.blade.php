@props(['icon' => null, 'title' => null, 'meta' => null])

<div {{ $attributes->merge(['class' => 'ui-item']) }}>
    @if ($icon)
        <span class="ui-item-icon"><flux:icon :name="$icon" /></span>
    @endif
    <div class="ui-item-content">
        @if ($title)
            <p class="ui-item-title">{{ $title }}</p>
        @endif
        <div class="ui-item-body">{{ $slot }}</div>
        @if ($meta)
            <p class="ui-item-meta">{{ $meta }}</p>
        @endif
    </div>
</div>
