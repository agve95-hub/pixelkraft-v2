@props(['title' => null, 'description' => null])

<div {{ $attributes->merge(['class' => 'ui-dialog', 'role' => 'dialog', 'aria-modal' => 'true']) }}>
    @if ($title || $description)
        <div class="ui-dialog-header">
            @if ($title)
                <h2 class="ui-dialog-title">{{ $title }}</h2>
            @endif
            @if ($description)
                <p class="ui-dialog-description">{{ $description }}</p>
            @endif
        </div>
    @endif
    <div class="ui-dialog-body">{{ $slot }}</div>
</div>
