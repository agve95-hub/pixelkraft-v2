@props(['variant' => 'default', 'icon' => null, 'title' => null])

<div {{ $attributes->merge(['class' => 'pk-ui-alert pk-ui-alert-'.$variant]) }} role="alert">
    @if ($icon)
        <flux:icon :name="$icon" variant="mini" class="pk-ui-alert-icon" />
    @endif
    <div>
        @if ($title)
            <p class="pk-ui-alert-title">{{ $title }}</p>
        @endif
        <div class="pk-ui-alert-body">{{ $slot }}</div>
    </div>
</div>
