@props([
    'title' => 'Data belum tersedia',
    'description' => 'Belum ada data yang dapat ditampilkan.',
    'actionHref' => null,
    'actionText' => null,
    'icon' => 'document',
    'size' => 'md', // sm|md|lg
])

@php
    $sizes = [
        'sm' => ['icon' => 'h-10 w-10', 'title' => 'text-base', 'desc' => 'text-xs', 'pad' => 'py-8 px-4'],
        'md' => ['icon' => 'h-12 w-12', 'title' => 'text-lg', 'desc' => 'text-sm', 'pad' => 'py-12 px-4'],
        'lg' => ['icon' => 'h-16 w-16', 'title' => 'text-xl', 'desc' => 'text-base', 'pad' => 'py-16 px-6'],
    ];
    $cfg = $sizes[$size] ?? $sizes['md'];
@endphp

<div class="text-center {{ $cfg['pad'] }}">
    <div class="mx-auto mb-4 inline-flex items-center justify-center rounded-full bg-primary-50 {{ $cfg['icon'] }}">
        <x-icon :name="$icon" size="md" color="primary" :decorative="true" />
    </div>
    <h3 class="font-semibold text-primary-900 {{ $cfg['title'] }}">{{ $title }}</h3>
    <p class="mt-1 text-accent-600 {{ $cfg['desc'] }}">{{ $description }}</p>
    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
        @if($actionHref && $actionText)
            <a href="{{ $actionHref }}" class="btn btn-primary">
                {{ $actionText }}
            </a>
        @endif
        @isset($actions)
            {{ $actions }}
        @endisset
    </div>
</div>
