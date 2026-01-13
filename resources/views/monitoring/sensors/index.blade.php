<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Monitoring Suhu & Kelembaban"
            :breadcrumbs="[['label' => 'Monitoring', 'url' => route('monitoring.sensors.index')]]"
        >
        </x-page-header>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Active Alerts Section -->
        @if($activeAlerts->isNotEmpty())
        <div class="rounded-lg bg-red-50 p-4 border border-red-200">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Peringatan Aktif ({{ $activeAlerts->count() }})</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <ul role="list" class="list-disc pl-5 space-y-1">
                            @foreach($activeAlerts as $alert)
                                <li>
                                    <span class="font-bold">{{ $alert->sensor->name }}</span>: 
                                    {{ $alert->type }} ({{ $alert->value }}°C) - 
                                    <span class="text-xs">{{ $alert->created_at->diffForHumans() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Sensors Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($sensors as $sensor)
            <div class="bg-white overflow-hidden shadow-sm rounded-lg border {{ $sensor->alerts->isNotEmpty() ? 'border-red-300 ring-2 ring-red-100' : 'border-gray-200' }}">
                <div class="p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ $sensor->name }}</h3>
                            <p class="text-sm text-gray-500">{{ $sensor->location ?? 'No Location' }}</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sensor->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $sensor->is_active ? 'Aktif' : 'Non-Aktif' }}
                        </span>
                    </div>

                    <div class="flex items-baseline mb-2">
                        <span class="text-4xl font-bold {{ $sensor->alerts->isNotEmpty() ? 'text-red-600' : 'text-gray-900' }}">
                            {{ $sensor->last_value ?? '--' }}
                        </span>
                        <span class="text-xl text-gray-500 ml-1">°C</span>
                    </div>

                    <div class="text-sm text-gray-500 mb-4">
                        Terakhir update: {{ $sensor->last_reading_at ? $sensor->last_reading_at->diffForHumans() : 'Belum ada data' }}
                    </div>

                    <div class="border-t border-gray-100 pt-4 mt-4">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="block text-gray-500">Min</span>
                                <span class="font-medium">{{ $sensor->min_threshold ?? '-' }}°C</span>
                            </div>
                            <div>
                                <span class="block text-gray-500">Max</span>
                                <span class="font-medium">{{ $sensor->max_threshold ?? '-' }}°C</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if($sensors->isEmpty())
        <div class="text-center py-12 bg-white rounded-lg border border-dashed border-gray-300">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada sensor</h3>
            <p class="mt-1 text-sm text-gray-500">Silakan tambahkan sensor monitoring terlebih dahulu.</p>
        </div>
        @endif
    </div>
</x-app-layout>
