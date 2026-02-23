@props(['active', 'icon' => null])

@php
$classes = ($active ?? false)
            ? 'group inline-flex shrink-0 min-h-[44px] items-center gap-2 px-3 py-2 text-sm font-semibold leading-none whitespace-nowrap rounded-md bg-primary-50 text-primary-800 ring-1 ring-primary-200 transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 xl:gap-1.5 xl:px-2.5 xl:text-xs 2xl:gap-2 2xl:px-3 2xl:text-sm dark:bg-accent-800 dark:text-primary-400'
            : 'group inline-flex shrink-0 min-h-[44px] items-center gap-2 px-3 py-2 text-sm font-medium leading-none whitespace-nowrap text-pd-body rounded-md hover:text-primary-900 hover:bg-primary-50 transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 xl:gap-1.5 xl:px-2.5 xl:text-xs 2xl:gap-2 2xl:px-3 2xl:text-sm dark:hover:text-accent-100 dark:hover:bg-accent-800';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if($active ?? false) aria-current="page" @endif>
    @if($icon)
        <x-icon
            :name="$icon"
            size="sm"
            class="shrink-0 transition-colors duration-200 group-hover:text-primary-600 dark:group-hover:text-primary-400"
            :color="($active ?? false) ? 'primary' : 'muted'"
            decorative
        />
    @endif
    <span class="truncate">{{ $slot }}</span>
</a>
