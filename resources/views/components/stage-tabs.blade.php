{{-- Deprecated: Prefer <x-tabs :items="[...]" variant="underline" /> --}}
{{-- This legacy component kept for backward compatibility with request stage navigation. --}}

<div class="border-b border-gray-200">
    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
        <a href="{{ $baseUrl }}"
           @class([
               'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium',
               'border-primary-600 text-primary-700' => !$currentStage,
               'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => $currentStage
           ])>
            Semua Tahap
        </a>
        @foreach($stages as $stage)
            <a href="{{ $baseUrl }}?stage={{ $stage->value }}"
               @class([
                   'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium',
                   'border-primary-600 text-primary-700' => $currentStage?->value === $stage->value,
                   'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => $currentStage?->value !== $stage->value
               ])>
                {{ $stage->label() }}
            </a>
        @endforeach
    </nav>
</div>
