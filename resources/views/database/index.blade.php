@php
    use Illuminate\Support\Facades\URL;
    use Illuminate\Support\Str;

    $hasActiveFilters = collect([
        'q' => $filters['q'] ?? null,
        'status' => $filters['status'] ?? null,
        'tipe' => $filters['tipe'] ?? null,
        'date_from' => $filters['date_from'] ?? null,
        'date_to' => $filters['date_to'] ?? null,
        'operator_tanggal' => $filters['operator_tanggal'] ?? null,
    ])->filter()->isNotEmpty();

    $query = $filters['q'] ?? '';
    $docLabels = $docLabels ?? [];
    $docFilterKey = $docFilterKey ?? null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Basis Data Permintaan"
            :breadcrumbs="[['label' => 'Database']]"
            description="Akses penuh permintaan dan sampel laboratorium, lengkap dengan pencarian lanjutan dan statistik per penyidik."
        />
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white border border-primary-100 rounded-xl p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-primary-500 font-semibold">Total Permintaan</p>
                <p class="mt-2 text-3xl font-bold text-primary-900">{{ number_format($aggregates['totalRequests']) }}</p>
            </div>
            <div class="bg-white border border-primary-100 rounded-xl p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-primary-500 font-semibold">Total Sampel</p>
                <p class="mt-2 text-3xl font-bold text-primary-900">{{ number_format($aggregates['totalSamples']) }}</p>
            </div>
            <div class="bg-white border border-primary-100 rounded-xl p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-primary-500 font-semibold">Selesai (Generate)</p>
                <p class="mt-2 text-3xl font-bold text-primary-900">{{ number_format($aggregates['completed']) }}</p>
            </div>
            <div class="bg-white border border-primary-100 rounded-xl p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-primary-500 font-semibold">Terakhir Diperbarui</p>
                <p class="mt-2 text-base font-semibold text-primary-900">
                    {{ $aggregates['latestUpdate']?->timezone(config('app.timezone'))->format('d M Y H:i') ?? 'N/A' }}
                </p>
            </div>
        </div>

        <div class="bg-white border border-primary-100 rounded-2xl shadow-sm">
            <div class="p-6 border-b border-primary-100">
                <h2 class="text-lg font-semibold text-primary-900">Filter &amp; Pencarian</h2>
                <p class="mt-1 text-sm text-primary-600">
                    Gunakan kata kunci beserta operator: <code>status:</code>, <code>tipe:</code>, <code>tanggal:</code>, <code>dokumen:</code>.
                    Contoh: <span class="font-mono text-xs bg-primary-50 px-2 py-1 rounded-md">dokumen:lhu status:completed 2025-09</span>
                </p>
            </div>
            <form method="GET" action="{{ route('database.index') }}" class="p-6 space-y-4" autocomplete="off" x-data="databaseSearch('{{ $query }}')">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div class="md:col-span-3 relative" id="search-wrap" @click.away="closeSuggestions">
                        <label class="block text-sm font-medium text-primary-700 dark:text-gray-300 mb-2">Pencarian</label>
                        
                        <!-- Google-Style Search Input -->
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-5 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            
                            <input
                                id="search-input"
                                name="q"
                                x-model="query"
                                @input.debounce.300ms="fetchSuggestions"
                                @focus="onFocus"
                                @keydown.down.prevent="highlightNext"
                                @keydown.up.prevent="highlightPrev"
                                @keydown.enter.prevent="selectHighlighted"
                                @keydown.escape="closeSuggestions"
                                placeholder="Cari permintaan, penyidik, tersangka... (gunakan operator status: tipe: tanggal: dokumen:)"
                                autocomplete="off"
                                class="block w-full pl-13 pr-14 py-3.5
                                       bg-white dark:bg-gray-800 
                                       border-2 border-gray-200 dark:border-gray-700 
                                       rounded-full 
                                       text-gray-900 dark:text-gray-100 
                                       placeholder-gray-400 dark:placeholder-gray-500
                                       focus:ring-4 focus:ring-primary-100 dark:focus:ring-primary-900/30 
                                       focus:border-primary-400 dark:focus:border-primary-500
                                       transition-all duration-200
                                       shadow-sm hover:shadow-md focus:shadow-xl"
                            >

                            <!-- Clear Button -->
                            <button
                                x-show="query.length > 0"
                                @click="clearQuery"
                                type="button"
                                class="absolute inset-y-0 right-12 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition"
                                x-transition
                            >
                                <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                </svg>
                            </button>

                            <!-- Loading Spinner -->
                            <div 
                                x-show="loading" 
                                class="absolute inset-y-0 right-4 flex items-center"
                                x-transition
                            >
                                <svg class="animate-spin h-5 w-5 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- Helper Text -->
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 flex items-start gap-1.5">
                            <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                            </svg>
                            <span>Gunakan operator: <code class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-primary-600 dark:text-primary-400 font-mono text-xs">status:</code> <code class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-primary-600 dark:text-primary-400 font-mono text-xs">tipe:</code> <code class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-primary-600 dark:text-primary-400 font-mono text-xs">tanggal:</code> <code class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-primary-600 dark:text-primary-400 font-mono text-xs">dokumen:</code></span>
                        </p>

                        <!-- Google-Style Dropdown Suggestions -->
                        <div
                            x-show="showSuggestions && suggestions.length > 0"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 translate-y-1"
                            class="absolute z-50 mt-3 w-full 
                                   bg-white dark:bg-gray-800 
                                   rounded-2xl 
                                   shadow-2xl 
                                   ring-1 ring-black/5 dark:ring-white/10
                                   overflow-hidden"
                            style="max-height: 70vh; overflow-y: auto;"
                        >
                            <div class="p-2">
                                <template x-for="(item, index) in suggestions" :key="index">
                                    <button
                                        type="button"
                                        @click="selectSuggestion(item)"
                                        @mouseover="highlightedIndex = index"
                                        :class="{
                                            'bg-primary-50 dark:bg-primary-900/20': highlightedIndex === index,
                                            'hover:bg-gray-50 dark:hover:bg-gray-700/50': highlightedIndex !== index
                                        }"
                                        class="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-colors text-left"
                                    >
                                        <!-- Icon based on type -->
                                        <div class="shrink-0 w-10 h-10 rounded-lg flex items-center justify-center"
                                             :class="{
                                                 'bg-blue-100 dark:bg-blue-900/30': item.type === 'operator',
                                                 'bg-purple-100 dark:bg-purple-900/30': item.type === 'penyidik',
                                                 'bg-green-100 dark:bg-green-900/30': item.type === 'request',
                                                 'bg-orange-100 dark:bg-orange-900/30': item.type === 'tersangka',
                                                 'bg-pink-100 dark:bg-pink-900/30': item.type === 'perkara',
                                                 'bg-teal-100 dark:bg-teal-900/30': item.type === 'dokumen'
                                             }">
                                            <!-- Operator Icon -->
                                            <template x-if="item.type === 'operator'">
                                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                                                </svg>
                                            </template>
                                            <!-- User Icon -->
                                            <template x-if="item.type === 'penyidik'">
                                                <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z" />
                                                </svg>
                                            </template>
                                            <!-- Document Icon -->
                                            <template x-if="item.type === 'request'">
                                                <svg class="w-5 h-5 text-green-600 dark:text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                                </svg>
                                            </template>
                                            <!-- User Shield Icon for Suspect -->
                                            <template x-if="item.type === 'tersangka'">
                                                <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-.257-.257A6 6 0 1118 8zm-1.5 0a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM10 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                                </svg>
                                            </template>
                                            <!-- Folder Icon -->
                                            <template x-if="item.type === 'perkara'">
                                                <svg class="w-5 h-5 text-pink-600 dark:text-pink-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                                </svg>
                                            </template>
                                            <!-- Document Stack Icon -->
                                            <template x-if="item.type === 'dokumen'">
                                                <svg class="w-5 h-5 text-teal-600 dark:text-teal-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                                                </svg>
                                            </template>
                                        </div>

                                        <!-- Label -->
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="item.label"></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 capitalize" x-text="item.type"></div>
                                        </div>

                                        <!-- Type Badge -->
                                        <div class="shrink-0">
                                            <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium"
                                                  :class="{
                                                      'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': item.type === 'operator',
                                                      'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400': item.type === 'penyidik',
                                                      'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': item.type === 'request',
                                                      'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400': item.type === 'tersangka',
                                                      'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400': item.type === 'perkara',
                                                      'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400': item.type === 'dokumen'
                                                  }"
                                                  x-text="item.insert">
                                            </span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-primary-700">Status</label>
                        <select name="status" class="mt-1 block w-full rounded-lg border-primary-200 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                            <option value="">Semua</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-primary-700">Tipe</label>
                        <select name="tipe" class="mt-1 block w-full rounded-lg border-primary-200 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                            <option value="">Semua</option>
                            @foreach($typeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['tipe'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-primary-700">Tanggal Dari</label>
                        <input
                            type="date"
                            id="date_from"
                            name="date_from"
                            value="{{ $filters['date_from'] ?? '' }}"
                            class="mt-1 block w-full rounded-lg border-primary-200 focus:border-primary-500 focus:ring-primary-500 shadow-sm"
                        >
                    </div>
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-primary-700">Tanggal Sampai</label>
                        <input
                            type="date"
                            id="date_to"
                            name="date_to"
                            value="{{ $filters['date_to'] ?? '' }}"
                            class="mt-1 block w-full rounded-lg border-primary-200 focus:border-primary-500 focus:ring-primary-500 shadow-sm"
                        >
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 justify-end">
                    <a href="{{ route('database.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-primary-200 text-primary-700 hover:bg-primary-50 transition">
                        Bersihkan
                    </a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-primary-600 text-white hover:bg-primary-700 transition">
                        Terapkan Filter
                    </button>
                </div>
            </form>
        </div>

        @if($aggregates['statusBreakdown']->isNotEmpty())
            <div class="bg-white border border-primary-100 rounded-2xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-primary-900 mb-4">Distribusi Status</h3>
                <div class="flex flex-wrap gap-3">
                    @foreach($aggregates['statusBreakdown'] as $status => $count)
                        <div class="px-4 py-2 rounded-lg bg-primary-50 border border-primary-200 text-sm text-primary-800">
                            <span class="uppercase font-medium">{{ $status }}</span>
                            <span class="ml-2 text-primary-600">({{ $count }})</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="space-y-6">
            @forelse($groups as $investigatorName => $items)
                @php
                    $stat = $statsPerGroup[$investigatorName] ?? null;
                    $openByDefault = $hasActiveFilters || $loop->first;
                @endphp
                <details class="bg-white border border-primary-100 rounded-2xl shadow-sm overflow-hidden" {{ $openByDefault ? 'open' : '' }}>
                    <summary class="cursor-pointer select-none px-6 py-4 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <span class="text-lg font-semibold text-primary-900">{{ $investigatorName }}</span>
                            <span class="ml-3 text-sm text-primary-600">
                                Permintaan: {{ $items->count() }} | Sampel: {{ number_format($items->sum('samples_count')) }}
                            </span>
                        </div>
                        @if($stat)
                            <div class="flex flex-wrap gap-3 text-xs text-primary-600">
                                <span class="px-3 py-1 rounded-full border border-primary-200 bg-primary-50">Input: {{ $stat['input'] }}</span>
                                <span class="px-3 py-1 rounded-full border border-primary-200 bg-primary-50">Generate: {{ $stat['generate'] }}</span>
                                <span class="px-3 py-1 rounded-full border border-primary-200 bg-primary-50">Completed: {{ $stat['completed'] }}</span>
                                <span class="px-3 py-1 rounded-full border border-primary-200 bg-primary-50">
                                    Rentang: {{ $stat['minDate'] ?? '' }} &ndash; {{ $stat['maxDate'] ?? '' }}
                                </span>
                                <span class="px-3 py-1 rounded-full border border-primary-200 bg-primary-50">
                                    Rata lama proses: {{ $stat['avgDays'] !== null ? $stat['avgDays'].' hari' : '' }}
                                </span>
                                <span class="px-3 py-1 rounded-full border border-primary-200 bg-primary-50">
                                    Metode populer: {{ $stat['topTest'] ?? '' }}
                                </span>
                            </div>
                        @endif
                    </summary>

                    <div class="px-6 pb-6">
                        <div class="overflow-x-auto border border-primary-100 rounded-xl">
                            <table class="min-w-full divide-y divide-primary-100">
                                <thead class="bg-primary-25">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-primary-600 uppercase tracking-wide">Tanggal</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-primary-600 uppercase tracking-wide">Nomor</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-primary-600 uppercase tracking-wide">Suspect / Kasus</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-primary-600 uppercase tracking-wide">Sampel</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-primary-600 uppercase tracking-wide">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-primary-600 uppercase tracking-wide">Catatan</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-primary-600 uppercase tracking-wide">Dokumen</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-primary-50 bg-white">
                                    @foreach($items as $row)
                                        @php
                                            $dateDisplay = ($row->submitted_at ?? $row->created_at)
                                                ?->timezone(config('app.timezone'))->format('d M Y') ?? '';
                                            $samplesPreview = $row->samples->pluck('sample_name')->filter()->take(3);
                                            $sampleLabel = $samplesPreview->implode(', ');
                                            if ($row->samples_count > 3) {
                                                $sampleLabel .= ' +' . ($row->samples_count - 3) . ' lainnya';
                                            }
                                            $statusBadgeClasses = match ($row->status) {
                                                'submitted' => 'bg-yellow-100 text-yellow-800',
                                                'verified' => 'bg-blue-100 text-blue-800',
                                                'received' => 'bg-indigo-100 text-indigo-800',
                                                'in_testing' => 'bg-sky-100 text-sky-800',
                                                'analysis' => 'bg-teal-100 text-teal-800',
                                                'quality_check' => 'bg-amber-100 text-amber-800',
                                                'ready_for_delivery' => 'bg-purple-100 text-purple-800',
                                                'completed' => 'bg-green-100 text-green-800',
                                                default => 'bg-gray-100 text-gray-700',
                                            };
                                            $notes = $row->case_description ?? '';
                                        @endphp
                                        <tr class="hover:bg-primary-25/60 transition">
                                            <td class="px-4 py-3 text-sm text-primary-800">{{ $dateDisplay }}</td>
                                            <td class="px-4 py-3 text-sm font-mono text-primary-900">{{ $row->request_number }}</td>
                                            <td class="px-4 py-3 text-sm text-primary-800">
                                                <div class="font-semibold">{{ $row->suspect_name }}</div>
                                                <div class="text-xs text-primary-600">
                                                    Kasus: {{ $row->case_number ?? '' }}
                                                    @if($row->incident_location)
                                                        | {{ Str::limit($row->incident_location, 60) }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-primary-800">
                                                <div>{{ $sampleLabel ?: 'Tidak ada sampel' }}</div>
                                                <div class="text-xs text-primary-500">
                                                    {{ $row->samples_count }} sampel terhubung
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusBadgeClasses }}">
                                                    {{ Str::title(str_replace('_', ' ', $row->status)) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-primary-800">
                                                {{ Str::limit($notes, 120) ?: 'Tidak ada catatan' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-primary-800">
                                                @php
                                                    $docs = $row->documents ?? collect();
                                                    if ($docFilterKey) {
                                                        $docs = $docs->where('document_type', $docFilterKey);
                                                    }
                                                    
                                                    $bundleParams = ['testRequest' => $row->id];
                                                    if ($docFilterKey) {
                                                        $bundleParams['category'] = $docFilterKey;
                                                    }
                                                    $bundleUrl = route('database.request.bundle', $bundleParams);
                                                @endphp
                                                @if($docs->isEmpty())
                                                    <span class="text-primary-400">Tidak ada</span>
                                                @else
                                                    <div class="flex flex-wrap gap-2 items-center">
                                                        <a href="{{ $bundleUrl }}"
                                                           class="inline-flex items-center px-2 py-1 text-xs rounded-md bg-gray-100 hover:bg-gray-200"
                                                           title="Unduh semua dokumen{{ $docFilterKey ? ' (terfilter)' : '' }}">
                                                            Unduh Semua
                                                        </a>
                                                        @foreach($docs as $doc)
                                                            @php
                                                                $category = $doc->document_type ?? 'dokumen';
                                                                $labelIndo = $docLabels[$category] ?? Str::headline($category);
                                                                $isGenerated = isset($doc->is_generated) && $doc->is_generated;
                                                                
                                                                // For generated documents, use separate route
                                                                if ($isGenerated) {
                                                                    $downloadUrl = URL::signedRoute('database.docs.download.generated', [
                                                                        'generated' => 1,
                                                                        'file_path' => $doc->file_path,
                                                                        'filename' => $doc->original_filename ?? basename($doc->file_path),
                                                                    ]);
                                                                    $previewUrl = URL::signedRoute('database.docs.preview.generated', [
                                                                        'generated' => 1,
                                                                        'file_path' => $doc->file_path,
                                                                        'filename' => $doc->original_filename ?? basename($doc->file_path),
                                                                        'mime_type' => $doc->mime_type ?? 'application/octet-stream',
                                                                    ]);
                                                                } else {
                                                                    $downloadUrl = URL::signedRoute('database.docs.download', ['doc' => $doc->id]);
                                                                    $previewUrl = URL::signedRoute('database.docs.preview', ['doc' => $doc->id]);
                                                                }
                                                                
                                                                $isImage = $doc->mime_type ? Str::startsWith($doc->mime_type, 'image/') : false;
                                                                $filename = $doc->original_filename ?? basename($doc->file_path);
                                                                $docToken = 'dokumen:' . $category;
                                                                $docFilterUrl = route('database.index', array_merge(request()->except('page'), [
                                                                    'q' => trim(($query ? $query . ' ' : '') . $docToken),
                                                                ]));
                                                            @endphp

                                                            <a href="{{ $docFilterUrl }}"
                                                               class="inline-flex items-center px-2 py-1 text-xs rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 hover:bg-emerald-100"
                                                               title="Filter berdasarkan {{ $labelIndo }}">
                                                                {{ Str::ucfirst($labelIndo) }}
                                                            </a>

                                                            @if($isImage)
                                                                <button type="button"
                                                                        class="underline text-xs text-gray-600 hover:text-emerald-700"
                                                                        data-preview="{{ $previewUrl }}"
                                                                        data-title="{{ Str::ucfirst($labelIndo) }} - {{ $filename }}">
                                                                    Preview
                                                                </button>
                                                            @endif
                                                            <a href="{{ $downloadUrl }}"
                                                               class="underline text-xs text-emerald-700 hover:text-emerald-900"
                                                               title="Unduh {{ $filename }}">
                                                                Unduh
                                                            </a>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            @empty
                <div class="bg-white border border-primary-100 rounded-2xl shadow-sm p-12 text-center text-primary-600">
                    @if($hasActiveFilters)
                        Tidak ada data cocok untuk pencarian ini.
                    @else
                        Belum ada permintaan yang tercatat. Tambahkan melalui modul Permintaan.
                    @endif
                </div>
            @endforelse
        </div>

        @if($results->hasPages())
            <div class="bg-white border border-primary-100 rounded-2xl shadow-sm p-6">
                {{ $results->appends(request()->except('page'))->links() }}
            </div>
        @endif

    </div>

    <div id="database-img-modal" class="hidden fixed inset-0 z-40 bg-black/60">
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 border-b border-primary-100">
                    <h3 id="database-img-title" class="text-sm font-semibold text-primary-700"></h3>
                    <button id="database-img-close" type="button" class="px-2 py-1 text-sm rounded-md hover:bg-primary-50 transition">
                        Tutup
                    </button>
                </div>
                <div class="p-3 bg-primary-25">
                    <img id="database-img-preview" src="" alt="Preview dokumen" class="max-h-[70vh] mx-auto object-contain rounded-lg shadow-inner bg-white" />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('databaseSearch', (initialQuery = '') => ({
        query: initialQuery,
        suggestions: [],
        showSuggestions: false,
        loading: false,
        highlightedIndex: -1,
        abortController: null,

        async fetchSuggestions() {
            const trimmed = this.query.trim();
            
            if (!trimmed) {
                this.suggestions = [];
                this.showSuggestions = false;
                return;
            }

            // Cancel previous request
            if (this.abortController) {
                this.abortController.abort();
            }

            this.abortController = new AbortController();
            this.loading = true;

            try {
                const url = new URL("{{ route('database.suggest') }}", window.location.origin);
                url.searchParams.set('q', this.query);
                
                const response = await fetch(url.toString(), {
                    signal: this.abortController.signal,
                    headers: { 'Accept': 'application/json' }
                });

                if (!response.ok) throw new Error('Network error');

                const data = await response.json();
                this.suggestions = data.items || [];
                this.showSuggestions = this.suggestions.length > 0;
                this.highlightedIndex = -1;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Autocomplete error:', error);
                    this.suggestions = [];
                }
            } finally {
                this.loading = false;
            }
        },

        selectSuggestion(item) {
            // Smart token insertion
            const raw = this.query.trim();
            const parts = raw ? raw.split(/\s+/) : [];
            
            // Remove last partial token
            if (parts.length) {
                parts.pop();
            }
            
            // Add selected token
            const next = (parts.length ? parts.join(' ') + ' ' : '') + item.insert + ' ';
            this.query = next.replace(/\s{2,}/g, ' ');
            
            this.closeSuggestions();
            
            // Focus back on input
            this.$nextTick(() => {
                document.getElementById('search-input')?.focus();
            });
        },

        selectHighlighted() {
            if (this.highlightedIndex >= 0 && this.highlightedIndex < this.suggestions.length) {
                this.selectSuggestion(this.suggestions[this.highlightedIndex]);
            } else {
                // Submit form if no suggestion highlighted
                this.$el.closest('form')?.submit();
            }
        },

        highlightNext() {
            if (this.suggestions.length === 0) return;
            this.highlightedIndex = Math.min(this.highlightedIndex + 1, this.suggestions.length - 1);
        },

        highlightPrev() {
            if (this.suggestions.length === 0) return;
            this.highlightedIndex = Math.max(this.highlightedIndex - 1, -1);
        },

        onFocus() {
            if (this.query.trim() && this.suggestions.length > 0) {
                this.showSuggestions = true;
            } else if (this.query.trim()) {
                this.fetchSuggestions();
            }
        },

        closeSuggestions() {
            this.showSuggestions = false;
            this.highlightedIndex = -1;
        },

        clearQuery() {
            this.query = '';
            this.suggestions = [];
            this.showSuggestions = false;
            this.highlightedIndex = -1;
            document.getElementById('search-input')?.focus();
        }
    }));
});

(function(){
  const modal = document.getElementById('database-img-modal');
  const preview = document.getElementById('database-img-preview');
  const title = document.getElementById('database-img-title');
  const closeBtn = document.getElementById('database-img-close');

  if (!modal || !preview || !title || !closeBtn) {
    return;
  }

  const closeModal = () => {
    modal.classList.add('hidden');
    preview.src = '';
    title.textContent = '';
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('button[data-preview]');
    if (!trigger) return;
    preview.src = trigger.dataset.preview;
    title.textContent = trigger.dataset.title || 'Preview dokumen';
    modal.classList.remove('hidden');
  });

  closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeModal();
    }
  });
})();
</script>
@endpush









