<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Detail Pemusnahan"
            :breadcrumbs="[
                ['label' => 'Inventori', 'route' => 'inventory.dashboard'],
                ['label' => 'Pemusnahan Sampel', 'route' => 'inventory.disposal.index'],
                ['label' => $disposal->batch_number]
            ]"
        >
            <x-slot name="actions">
                <div class="flex gap-2">
                    <a href="{{ route('inventory.disposal.index', ['tab' => 'history']) }}"
                        class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50">
                        ← Kembali
                    </a>
                    <a href="{{ route('inventory.disposal.pdf', $disposal) }}"
                        class="inline-flex items-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700">
                        📄 Download PDF
                    </a>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        {{-- Success Message --}}
        @if(session('success'))
            <div class="rounded-md bg-green-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Disposal Info --}}
        <div class="card">
            <h3 class="text-lg font-semibold mb-4">Informasi Pemusnahan</h3>
            
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Nomor Batch</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $disposal->batch_number }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Tanggal Eksekusi</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $disposal->executed_at->format('d F Y, H:i') }} WIB</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Metode Pemusnahan</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $disposal->method->label() }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Jumlah Sampel</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $disposal->samples->count() }} sampel</dd>
                </div>
                <div class="md:col-span-2">
                    <dt class="text-sm font-medium text-gray-500">Saksi</dt>
                    <dd class="mt-2 space-y-2 text-sm text-gray-900">
                        @foreach($disposal->witness_entries_for_display as $witness)
                            <div class="rounded-md bg-gray-50 px-3 py-2">
                                <div class="font-medium">{{ $witness['name'] }}</div>
                                <div class="text-gray-600">{{ $witness['role'] }}</div>
                                @if(!empty($witness['identity']))
                                    <div class="text-xs text-gray-500">{{ $witness['identity'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Pelaksana</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $disposal->executed_by_name ?: ($disposal->executedBy?->display_name_with_title ?? $disposal->executedBy?->name ?? '-') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Kepala Farmapol</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $disposal->approver_name ?: '-' }}</dd>
                    @if($disposal->approver_role || $disposal->approver_identity)
                        <div class="text-xs text-gray-500">{{ trim(($disposal->approver_role ? $disposal->approver_role.' ' : '').($disposal->approver_identity ?: '')) }}</div>
                    @endif
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Dicatat oleh</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $disposal->createdBy?->name ?? '-' }}</dd>
                </div>
                @if($disposal->notes)
                <div class="md:col-span-2">
                    <dt class="text-sm font-medium text-gray-500">Catatan</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $disposal->notes }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Samples List --}}
        <div class="card">
            <h3 class="text-lg font-semibold mb-4">Daftar Sampel yang Dimusnahkan</h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">No</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kode Sampel</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">No. LHU</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">No. LP / Tgl</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Tersangka</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Jenis Bukti</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($disposal->samples as $index => $sample)
                        @php
                            $lhuProcess = $sample->testProcesses->where('stage', 'interpretation')->whereNotNull('completed_at')->first();
                            $lhuNumber = $lhuProcess?->metadata['lhu_number'] ?? '-';
                            $testRequest = $sample->testRequest;
                            $investigator = $testRequest?->investigator;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $index + 1 }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $sample->sample_code }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $lhuNumber }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $testRequest?->case_number ?? '-' }}
                                @if($testRequest?->case_date)
                                    <br><span class="text-xs text-gray-400">{{ $testRequest->case_date->format('d/m/Y') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $testRequest?->suspect_name ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $sample->short_description ?? $sample->sample_form }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        window.localStorage.removeItem('inventory.disposal.selected_sample_ids');
    </script>
    @endpush
</x-app-layout>
