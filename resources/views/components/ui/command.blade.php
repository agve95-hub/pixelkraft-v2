@props(['placeholder' => 'Search...'])

<div {{ $attributes->merge(['class' => 'pk-ui-command']) }}>
    <div class="pk-ui-command-input">
        <flux:icon name="magnifying-glass" variant="mini" />
        <input type="search" placeholder="{{ $placeholder }}">
    </div>
    <div class="pk-ui-command-list">
        {{ $slot }}
    </div>
</div>
