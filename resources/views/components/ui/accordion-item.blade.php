@props(['title'])

<details {{ $attributes->merge(['class' => 'pk-ui-accordion-item']) }}>
    <summary>{{ $title }}</summary>
    <div class="pk-ui-accordion-content">{{ $slot }}</div>
</details>
