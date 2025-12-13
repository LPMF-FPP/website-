@props([
    // items: [['label' => 'Dashboard', 'href' => route('dashboard')], ['label' => 'Permintaan']]
    'items' => [],
    'class' => ''
])

@php
    $classes = 'w-full';
    if (!empty($class)) { $classes .= ' ' . $class; }
@endphp

<nav class="mb-4" aria-label="Breadcrumb">
    <ol class="flex items-center space-x-2 text-sm text-accent-600 {{ $classes }}">
        <li>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1 hover:text-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 rounded">
                <x-icon name="home" size="sm" color="muted" :decorative="true" />
                <span>Dashboard</span>
            </a>
        </li>
        @foreach($items as $item)
            <li class="select-none" aria-hidden="true">
                <x-icon name="chevron-right" size="sm" color="muted" :decorative="true" />
            </li>
            <li>
                @if(!empty($item['href']))
                    <a href="{{ $item['href'] }}" class="inline-flex items-center gap-1 hover:text-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 rounded">
                        <span>{{ $item['label'] }}</span>
                    </a>
                @else
                    <span class="text-primary-800 font-medium" aria-current="page">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
 </nav>
