@props(['padding' => 'default'])

<section {{ $attributes->merge(['class' => 'ui-card ui-card-'.$padding]) }}>
    {{ $slot }}
</section>
