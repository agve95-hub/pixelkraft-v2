@props(['title' => null, 'description' => null])

<div {{ $attributes->merge(['class' => 'pk-ui-dialog', 'role' => 'dialog', 'aria-modal' => 'true']) }}>
    @if ($title || $description)
        <div class="pk-ui-dialog-header">
            @if ($title)
                <h2 class="pk-ui-dialog-title">{{ $title }}</h2>
            @endif
            @if ($description)
                <p class="pk-ui-dialog-description">{{ $description }}</p>
            @endif
        </div>
    @endif
    <div class="pk-ui-dialog-body">{{ $slot }}</div>
</div>
