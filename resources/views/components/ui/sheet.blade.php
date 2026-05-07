@props(['side' => 'right', 'title' => null])

<aside {{ $attributes->merge(['class' => 'pk-ui-sheet pk-ui-sheet-'.$side]) }}>
    @if ($title)
        <h2 class="pk-ui-dialog-title">{{ $title }}</h2>
    @endif
    {{ $slot }}
</aside>
