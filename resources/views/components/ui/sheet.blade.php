@props(['side' => 'right', 'title' => null])

<aside {{ $attributes->merge(['class' => 'ui-sheet ui-sheet-'.$side]) }}>
    @if ($title)
        <h2 class="ui-dialog-title">{{ $title }}</h2>
    @endif
    {{ $slot }}
</aside>
