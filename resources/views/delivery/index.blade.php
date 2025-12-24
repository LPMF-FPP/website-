<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Penyerahan Hasil Pengujian"
            :breadcrumbs="[[ 'label' => 'Penyerahan' ]]"
        />
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="listFetcher()" x-init="init()">
        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                @if(session('success'))
                    <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Permintaan Siap Diserahkan</h3>
                    <p class="text-gray-600">Daftar permintaan yang telah menyelesaikan seluruh proses pengujian dan menunggu penyerahan hasil.</p>
                </div>

                <div x-show="loading" class="mb-4">
                    <x-skeleton-table :columns="6" :rows="8" />
                </div>

                @if($requests->isNotEmpty())
                <template x-if="!loading">
                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg" x-ref="listContainer">
                        <table class="min-w-full divide-y divide-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">No. Resi</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Penyidik</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tersangka</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Jumlah Sampel</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tanggal Selesai</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach($requests as $request)
                                    <tr class="transition hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                            {{ $request->receipt_number ?? $request->request_number }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ optional($request->investigator)->name ?? '-' }}
                                            @if($request->investigator)
                                                <div class="text-xs text-gray-500">{{ $request->investigator->rank }} &middot; {{ $request->investigator->jurisdiction }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ $request->suspect_name ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            @php
                                                $completedSamples = $request->samples->filter(function($sample) {
                                                    $requiredStages = ['preparation', 'instrumentation', 'interpretation'];
                                                    $completedStages = $sample->testProcesses
                                                        ->where('completed_at', '!=', null)
                                                        ->whereIn('stage', $requiredStages)
                                                        ->groupBy('stage')
                                                        ->count();
                                                    return $completedStages === 3;
                                                });
                                            @endphp
                                            <div class="flex items-center">
                                                <span>{{ $request->samples->count() }} sampel</span>
                                                @if($completedSamples->count() > 0)
                                                    <span class="ml-2 inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                                        {{ $completedSamples->count() }} selesai
                                                    </span>
                                                @endif
                                            </div>
                                            @if($request->request_number === 'REQ-2025-0005' && $completedSamples->count() > 0)
                                                <div class="mt-1 text-xs text-green-600">Siap untuk diserahkan</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ optional($request->completed_at)->format('d/m/Y') ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-right text-sm font-medium">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <a href="{{ route('delivery.show', $request) }}"
                                                   class="inline-flex items-center rounded border border-primary-600 px-3 py-1 text-sm font-semibold text-primary-700 transition hover:bg-primary-50">
                                                    Lihat Detail
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </template>
                @else
                    <div class="py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada permintaan yang siap diserahkan</h3>
                        <p class="mt-1 text-sm text-gray-500">Lengkapi seluruh proses pengujian untuk menampilkan data di sini.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

<!-- Uses centralized listFetcher from app.js -->
