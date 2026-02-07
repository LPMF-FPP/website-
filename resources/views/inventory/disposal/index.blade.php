<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Pemusnahan Sisa Sampel"
            :breadcrumbs="[['label' => 'Inventori', 'route' => 'inventory.dashboard'], ['label' => 'Pemusnahan Sampel']]"
        >
            <x-slot name="actions">
                <div class="flex gap-2">
                    <a href="{{ route('inventory.dashboard') }}"
                        class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50">
                        ← Kembali
                    </a>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6" x-data="disposalIndex()">
        {{-- Tab Navigation --}}
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <a href="{{ route('inventory.disposal.index', ['tab' => 'eligible']) }}"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $tab === 'eligible' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Siap Musnah
                    @if($eligibleSamples->total() > 0)
                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            {{ $eligibleSamples->total() }}
                        </span>
                    @endif
                </a>
                <a href="{{ route('inventory.disposal.index', ['tab' => 'history']) }}"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $tab === 'history' ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Riwayat Pemusnahan
                </a>
            </nav>
        </div>

        {{-- Tab: Eligible Samples --}}
        @if($tab === 'eligible')
        <div class="card">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Sampel Siap Musnah</h3>
                <button 
                    type="button"
                    x-show="selectedIds.length > 0"
                    @click="processSelected"
                    class="inline-flex items-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700">
                    <span x-text="`Proses Pemusnahan (${selectedIds.length})`"></span>
                </button>
            </div>

            @if($eligibleSamples->isEmpty())
                <div class="text-center py-12 text-gray-500">
                    <div class="text-4xl mb-4">✅</div>
                    <p>Tidak ada sampel yang siap dimusnahkan.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">
                                    <input type="checkbox" 
                                        @change="toggleAll($event.target.checked)"
                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kode Sampel</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">No. LHU</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Tersangka</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Jenis</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Umur (hari)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($eligibleSamples as $sample)
                            @php
                                $lhuProcess = $sample->testProcesses->where('stage', 'interpretation')->whereNotNull('completed_at')->first();
                                $lhuNumber = $lhuProcess?->metadata['lhu_number'] ?? '-';
                                $completedAt = $lhuProcess?->completed_at;
                                $age = $completedAt ? now()->diffInDays($completedAt) : '-';
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <input type="checkbox" 
                                        value="{{ $sample->id }}"
                                        x-model="selectedIds"
                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ $sample->sample_code }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $lhuNumber }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $sample->testRequest?->investigator?->suspect_name ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $sample->short_description ?? $sample->sample_form }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 text-right">
                                    {{ $age }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $eligibleSamples->withQueryString()->links() }}
                </div>
            @endif
        </div>
        @endif

        {{-- Tab: History --}}
        @if($tab === 'history')
        <div class="card">
            <h3 class="text-lg font-semibold mb-4">Riwayat Pemusnahan</h3>

            @if($disposals->isEmpty())
                <div class="text-center py-12 text-gray-500">
                    <div class="text-4xl mb-4">📋</div>
                    <p>Belum ada riwayat pemusnahan.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Batch No</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Tanggal</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600">Jumlah</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Metode</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Pelaksana</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($disposals as $disposal)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    <a href="{{ route('inventory.disposal.show', $disposal) }}" class="text-primary-600 hover:underline">
                                        {{ $disposal->batch_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $disposal->executed_at->format('d M Y H:i') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 text-center">
                                    {{ $disposal->samples->count() }} sampel
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $disposal->method->label() }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $disposal->executedBy?->name ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('inventory.disposal.pdf', $disposal) }}" 
                                        class="text-red-600 hover:text-red-800 text-sm font-medium">
                                        📄 PDF
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $disposals->withQueryString()->links() }}
                </div>
            @endif
        </div>
        @endif
    </div>

    @push('scripts')
    <script>
        function disposalIndex() {
            return {
                selectedIds: [],
                toggleAll(checked) {
                    if (checked) {
                        this.selectedIds = @json($eligibleSamples->pluck('id')->map(fn($id) => (string) $id)->toArray());
                    } else {
                        this.selectedIds = [];
                    }
                },
                processSelected() {
                    if (this.selectedIds.length === 0) {
                        alert('Pilih minimal 1 sampel untuk dimusnahkan.');
                        return;
                    }
                    window.location.href = '{{ route('inventory.disposal.create') }}?sample_ids=' + this.selectedIds.join(',');
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
