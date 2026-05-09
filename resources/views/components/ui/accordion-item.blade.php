@props(['title'])

<details {{ $attributes->merge(['class' => 'ui-accordion-item']) }}>
    <summary>{{ $title }}</summary>
    <div class="ui-accordion-content">{{ $slot }}</div>
</details>
