@props(['variant' => 'default', 'title' => null])

<div {{ $attributes->merge(['class' => 'ui-toast ui-toast-'.$variant]) }} role="status">
    @if ($title)
        <p class="ui-toast-title">{{ $title }}</p>
    @endif
    <div class="ui-toast-body">{{ $slot }}</div>
</div>
