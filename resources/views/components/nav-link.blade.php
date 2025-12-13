@props(['active'])

@php
$classes = 'nav-link '.(($active ?? false) ? 'nav-link-active' : 'nav-link-inactive');
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
