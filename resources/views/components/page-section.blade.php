@props([
    'title' => '',
    'description' => null,
    'class' => ''
])

<section class="{{ $class }}">
    @if($title || isset($actions))
        <div class="mb-3 flex items-start justify-between gap-4">
            @if($title)
                <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
            @endif
            @if (isset($actions))
                <div class="shrink-0">{{ $actions }}</div>
            @endif
        </div>
    @endif
    @if($description)
        <p class="mb-3 text-sm text-gray-600">{{ $description }}</p>
    @endif
    {{ $slot }}
</section>
