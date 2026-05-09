@props(['variant' => 'default', 'icon' => null, 'title' => null])

<div {{ $attributes->merge(['class' => 'ui-alert ui-alert-'.$variant]) }} role="alert">
    @if ($icon)
        <flux:icon :name="$icon" variant="mini" class="ui-alert-icon" />
    @endif
    <div>
        @if ($title)
            <p class="ui-alert-title">{{ $title }}</p>
        @endif
        <div class="ui-alert-body">{{ $slot }}</div>
    </div>
</div>
