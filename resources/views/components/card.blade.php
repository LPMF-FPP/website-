{{-- Enhanced Card Component with Typography --}}
@props([
    'title' => null,
    'subtitle' => null,
    'elevated' => false,
    'image' => null,
    'imageAlt' => '',
    'imagePosition' => 'top',
    'interactive' => false,
    'bordered' => true,
    'padding' => 'normal'
])

@php
$classes = 'card group';

if ($elevated) {
    $classes .= ' card-elevated shadow-lg hover:shadow-xl';
}

if ($interactive) {
    $classes .= ' cursor-pointer transition-all duration-200 hover:-translate-y-1 hover:shadow-lg';
}

if (!$bordered) {
    $classes .= ' border-0 shadow-none';
}

$classes .= match($padding) {
    'none' => ' p-0',
    'small' => ' p-4',
    'large' => ' p-8',
    default => ' p-6'
};
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    @if($image && $imagePosition === 'top')
        <div class="card-image-top overflow-hidden rounded-t-lg">
            <img
                src="{{ $image }}"
                alt="{{ $imageAlt }}"
                class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105"
                loading="lazy"
            />
        </div>
    @endif

    @if($title || $subtitle || isset($header))
        <div class="card-header {{ $image && $imagePosition === 'top' ? 'pt-6' : '' }}">
            @if(isset($header))
                {{ $header }}
            @else
                @if($title)
                    <h3 class="typography-title text-primary-900 mb-2 font-display font-semibold tracking-tight">
                        {{ $title }}
                    </h3>
                @endif
                @if($subtitle)
                    <p class="typography-subtitle text-primary-700 font-body leading-relaxed">
                        {{ $subtitle }}
                    </p>
                @endif
            @endif
        </div>
    @endif

    @if($image && $imagePosition === 'side')
        <div class="flex gap-6">
            <div class="flex-shrink-0">
                <img
                    src="{{ $image }}"
                    alt="{{ $imageAlt }}"
                    class="w-24 h-24 object-cover rounded-lg"
                    loading="lazy"
                />
            </div>
            <div class="flex-1 card-body">
                {{ $slot }}
            </div>
        </div>
    @else
        <div class="card-body {{ ($title || $subtitle || isset($header)) ? 'pt-4' : '' }}">
            <div class="typography-body font-body text-primary-800 leading-relaxed">
                {{ $slot }}
            </div>
        </div>
    @endif

    @if(isset($footer))
        <div class="card-footer border-t border-primary-100 pt-4 mt-6">
            <div class="flex items-center justify-between">
                {{ $footer }}
            </div>
        </div>
    @endif

    @if($image && $imagePosition === 'bottom')
        <div class="card-image-bottom overflow-hidden rounded-b-lg mt-6">
            <img
                src="{{ $image }}"
                alt="{{ $imageAlt }}"
                class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105"
                loading="lazy"
            />
        </div>
    @endif
</div>
