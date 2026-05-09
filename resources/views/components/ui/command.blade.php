@props(['placeholder' => 'Search...'])

<div {{ $attributes->merge(['class' => 'ui-command']) }}>
    <div class="ui-command-input">
        <flux:icon name="magnifying-glass" variant="mini" />
        <input type="search" placeholder="{{ $placeholder }}">
    </div>
    <div class="ui-command-list">
        {{ $slot }}
    </div>
</div>
