@props(['active'])

@php
$classes = 'mobile-nav-link '.(($active ?? false) ? 'mobile-nav-link-active' : 'mobile-nav-link-inactive');
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
