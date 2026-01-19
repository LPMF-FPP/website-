@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full pl-3 pr-4 py-2 text-left text-base font-semibold text-primary-700 bg-primary-50 rounded-md transition-colors duration-150 dark:text-primary-400 dark:bg-accent-800'
            : 'block w-full pl-3 pr-4 py-2 text-left text-base font-medium text-pd-body hover:text-primary-900 hover:bg-primary-50 rounded-md transition-colors duration-150 dark:hover:text-accent-100 dark:hover:bg-accent-800';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
