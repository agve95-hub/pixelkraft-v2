@props(['variant' => 'default', 'title' => null])

<div {{ $attributes->merge(['class' => 'pk-ui-toast pk-ui-toast-'.$variant]) }} role="status">
    @if ($title)
        <p class="pk-ui-toast-title">{{ $title }}</p>
    @endif
    <div class="pk-ui-toast-body">{{ $slot }}</div>
</div>
