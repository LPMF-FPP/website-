@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-3 py-2 text-sm font-semibold rounded-md bg-primary-50 text-primary-700 transition-colors duration-150 dark:bg-accent-800 dark:text-primary-400'
            : 'inline-flex items-center px-3 py-2 text-sm font-medium text-pd-body rounded-md hover:text-primary-900 hover:bg-primary-50 transition-colors duration-150 dark:hover:text-accent-100 dark:hover:bg-accent-800';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
