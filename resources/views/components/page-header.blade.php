@props([
    'title' => '',
    'breadcrumbs' => [], // e.g., [['label' => 'Permintaan', 'href' => route('requests.index')], ['label' => 'Detail']]
    'class' => ''
])

<div class="{{ $class }}">
    <x-breadcrumbs :items="$breadcrumbs" />
    <div class="flex items-start justify-between gap-4">
        <h2 class="font-semibold text-xl text-primary-900 leading-tight">{{ $title }}</h2>
        @if (isset($actions))
            <div class="shrink-0">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>
