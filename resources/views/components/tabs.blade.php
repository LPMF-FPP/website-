@props([
    'items' => null, // array of ['label'=>..., 'href'=>..., 'active'=>bool]
    'variant' => 'underline', // underline|pill|segment
])

@php
    $navBase = 'flex gap-6 overflow-x-auto';
    $itemStyles = [
        'underline' => [
            'base' => 'whitespace-nowrap border-b-2 py-3 text-sm font-medium transition',
            'active' => 'border-primary-600 text-primary-700',
            'inactive' => 'border-transparent text-accent-600 hover:text-accent-800 hover:border-accent-300'
        ],
        'pill' => [
            'base' => 'whitespace-nowrap rounded-full px-4 py-2 text-sm font-medium transition',
            'active' => 'bg-primary-600 text-white shadow-sm',
            'inactive' => 'text-accent-600 hover:text-accent-800 hover:bg-accent-100'
        ],
        'segment' => [
            'wrapper' => 'inline-flex gap-1 rounded-lg bg-accent-100 p-1',
            'base' => 'whitespace-nowrap px-4 py-2 text-sm font-medium rounded-md transition',
            'active' => 'bg-white text-primary-700 shadow-sm',
            'inactive' => 'text-accent-600 hover:text-accent-800'
        ],
    ][$variant] ?? $itemStyles['underline'];
@endphp

<div {{ $attributes->merge(['class' => $variant === 'segment' ? $itemStyles['wrapper'] ?? '' : '']) }}>
    <nav class="{{ $variant !== 'segment' ? $navBase : 'flex gap-1' }}" aria-label="Tabs">
        @if($items)
            @foreach($items as $it)
                @php $active = $it['active'] ?? false; @endphp
                <a href="{{ $it['href'] }}" @class([
                    $itemStyles['base'],
                    $itemStyles['active'] => $active,
                    $itemStyles['inactive'] => !$active,
                ])>
                    {{ $it['label'] }}
                </a>
            @endforeach
        @else
            {{ $slot }}
        @endif
    </nav>
</div>
