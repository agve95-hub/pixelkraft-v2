@props(['padding' => 'default'])

<section {{ $attributes->merge(['class' => 'pk-ui-card pk-ui-card-'.$padding]) }}>
    {{ $slot }}
</section>
