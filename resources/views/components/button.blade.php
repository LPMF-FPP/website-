{{-- Enhanced Button Component with Typography --}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'disabled' => false,
    'href' => null,
    'icon' => null,
    'iconPosition' => 'left',
    'loading' => false,
    'block' => false
])

@php
$classes = 'btn font-medium transition-all duration-200 ease-in-out';

// Variant classes with medical color palette
$classes .= match($variant) {
    'primary' => ' btn-primary text-white font-semibold tracking-wide',
    'secondary' => ' btn-secondary text-accent-900 font-medium tracking-normal',
    'outline' => ' btn-outline text-primary-600 font-medium tracking-normal',
    'success' => ' btn-success text-white font-semibold tracking-wide',
    'warning' => ' btn-warning text-white font-semibold tracking-wide',
    'danger' => ' btn-danger text-white font-semibold tracking-wide',
    'ghost' => ' btn-ghost text-primary-700 font-medium tracking-normal',
    default => ' btn-primary text-white font-semibold tracking-wide'
};

// Size classes with typography scaling
$classes .= match($size) {
    'xs' => ' btn-xs text-xs font-medium px-2 py-1',
    'sm' => ' btn-sm text-sm font-medium px-3 py-1.5',
    'lg' => ' btn-lg text-lg font-semibold px-6 py-3',
    'xl' => ' btn-xl text-xl font-bold px-8 py-4',
    default => ' text-base font-medium px-4 py-2'
};

// Block button
if ($block) {
    $classes .= ' w-full justify-center';
}

// Loading state
if ($loading) {
    $classes .= ' opacity-75 cursor-not-allowed';
}
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        <span class="inline-flex items-center gap-2">
            @if($icon && $iconPosition === 'left')
                @if($loading)
                    <x-icon name="loading" size="{{ $size === 'sm' ? 'xs' : ($size === 'lg' ? 'md' : 'sm') }}" spin />
                @else
                    <x-icon name="{{ $icon }}" size="{{ $size === 'sm' ? 'xs' : ($size === 'lg' ? 'md' : 'sm') }}" />
                @endif
            @endif

            <span class="font-display">{{ $slot }}</span>

            @if($icon && $iconPosition === 'right')
                @if($loading)
                    <x-icon name="loading" size="{{ $size === 'sm' ? 'xs' : ($size === 'lg' ? 'md' : 'sm') }}" spin />
                @else
                    <x-icon name="{{ $icon }}" size="{{ $size === 'sm' ? 'xs' : ($size === 'lg' ? 'md' : 'sm') }}" />
                @endif
            @endif
        </span>
    </a>
@else
    <button
        type="{{ $type }}"
        @if($disabled || $loading) disabled @endif
        {{ $attributes->merge(['class' => $classes]) }}
    >
        <span class="inline-flex items-center gap-2">
            @if($icon && $iconPosition === 'left')
                @if($loading)
                    <x-icon name="loading" size="{{ $size === 'sm' ? 'xs' : ($size === 'lg' ? 'md' : 'sm') }}" spin />
                @else
                    <x-icon name="{{ $icon }}" size="{{ $size === 'sm' ? 'xs' : ($size === 'lg' ? 'md' : 'sm') }}" />
                @endif
            @endif

            <span class="font-display">{{ $slot }}</span>

            @if($icon && $iconPosition === 'right')
                @if($loading)
                    <x-icon name="loading" size="{{ $size === 'sm' ? 'xs' : ($size === 'lg' ? 'md' : 'sm') }}" spin />
                @else
                    <x-icon name="{{ $icon }}" size="{{ $size === 'sm' ? 'xs' : ($size === 'lg' ? 'md' : 'sm') }}" />
                @endif
            @endif
        </span>
    </button>
@endif
