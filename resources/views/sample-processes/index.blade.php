<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Proses Pengujian Sampel"
            :breadcrumbs="[[ 'label' => 'Pengujian', 'href' => route('samples.test.create') ], [ 'label' => 'Proses' ]]"
        />
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6" x-data="listFetcher()" x-init="init()">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" action="{{ route('sample-processes.index') }}" class="flex flex-wrap items-end gap-3" @submit.prevent="handleFilterSubmit($event)">
                <div>
                    <label for="filter_stage" class="block text-xs font-medium text-gray-600 uppercase tracking-wide">Tahapan</label>
                    <select id="filter_stage" name="stage"
                        class="mt-1 block w-48 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua Tahapan</option>
                        @foreach($stages as $stage)
                            <option value="{{ $stage->value }}" @selected(($filters['stage'] ?? '') === $stage->value)>{{ $stage->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter_sample_name" class="block text-xs font-medium text-gray-600 uppercase tracking-wide">
                        Nama Sampel
                        <span class="text-gray-400 normal-case">(pilih dari yang tersedia)</span>
                    </label>
                    <select id="filter_sample_name" name="sample_name"
                        class="mt-1 block w-56 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua Nama</option>
                        @forelse($sampleNames as $name)
                            <option value="{{ $name }}" @selected(($filters['sample_name'] ?? '') === $name)>{{ $name }}</option>
                        @empty
                            <option disabled>Tidak ada nama sampel</option>
                        @endforelse
                    </select>
                </div>

                <div>
                    <label for="filter_request_number" class="block text-xs font-medium text-gray-600 uppercase tracking-wide">
                        Nomor Permintaan
                    </label>
                    <select id="filter_request_number" name="request_number"
                        class="mt-1 block w-56 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua Nomor</option>
                        @forelse($requestNumbers as $no)
                            <option value="{{ $no }}" @selected(($filters['request_number'] ?? '') === $no)>{{ $no }}</option>
                        @empty
                            <option disabled>Tidak ada nomor permintaan</option>
                        @endforelse
                    </select>
                </div>

                <!-- Uses centralized listFetcher from app.js -->

                <button type="submit"
                    class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-600 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-700">
                    Terapkan
                </button>
            </form>

            <div class="flex flex-col items-start sm:items-end gap-2">
                <a href="{{ route('sample-processes.create') }}"
                    class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                    Tambah Proses
                </a>

                @php
                    $readyOptions = [];
                    foreach ($processes as $p) {
                        if (in_array($p->sample_id, $samplesReadyForDelivery ?? [])) {
                            $label = ($p->sample->sample_name ?? 'Sampel') . ' (' . ($p->sample->testRequest?->request_number ?? '-') . ')';
                            $readyOptions[$p->sample_id] = $label;
                        }
                    }
                @endphp
                @if(!empty($readyOptions))
                    <form id="readyForm" method="POST" action="" onsubmit="return confirm('Kirim sampel ini ke Penyerahan?')" class="flex items-center gap-2">
                        @csrf
                        <select id="readySampleSelect" class="block w-64 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">Pilih sampel siap diserahkan…</option>
                            @foreach($readyOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" id="readySubmit" disabled class="inline-flex items-center rounded-md bg-secondary-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition enabled:hover:bg-secondary-600 disabled:opacity-50">
                            Siapkan Penyerahan
                        </button>
                    </form>
                    <script>
                        (function(){
                            const sel = document.getElementById('readySampleSelect');
                            const btn = document.getElementById('readySubmit');
                            const frm = document.getElementById('readyForm');
                            const base = '{{ url('samples') }}';
                            sel?.addEventListener('change', () => {
                                if (sel.value) {
                                    frm.action = base + '/' + sel.value + '/ready-for-delivery';
                                    btn.disabled = false;
                                } else {
                                    frm.action = '';
                                    btn.disabled = true;
                                }
                            });
                        })();
                    </script>
                @endif
            </div>
        </div>

        <!-- Skeleton while loading -->
        <div x-show="loading" class="mt-2">
            <x-skeleton-table :columns="6" :rows="8" />
        </div>

        <!-- List container (table + pagination) -->
        <div x-show="!loading" x-ref="listContainer">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Sampel</th>
                            <th class="px-4 py-3 text-left">Tahapan</th>
                            <th class="px-4 py-3 text-left">Pelaksana</th>
                            <th class="px-4 py-3 text-left">Jadwal</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse($processes as $process)
                            @php
                                $isReadyForDelivery = in_array($process->sample_id, $samplesReadyForDelivery);
                            @endphp
                            <tr class="hover:bg-gray-50/60 {{ $isReadyForDelivery ? 'bg-blue-50/30' : '' }}">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">
                                        {{ $process->sample->sample_name }}
                                        @if($isReadyForDelivery)
                                            <span class="ml-2 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">Siap Diserahkan</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">Permintaan: {{ $process->sample->testRequest?->request_number ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $process->stage_label }}</td>
                                <td class="px-4 py-3">
                                    {{ $process->analyst?->display_name_with_title ?? 'Belum ditentukan' }}
                                    <div class="text-xs text-gray-500">
                                        {{ $process->analyst?->rank }} {{ $process->analyst?->identification_number ? '(' . $process->analyst->identification_number . ')' : '' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div>Mulai: {{ optional($process->started_at)->format('d/m/Y H:i') ?? '-' }}</div>
                                    <div>Selesai: {{ optional($process->completed_at)->format('d/m/Y H:i') ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($process->completed_at)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">Selesai</span>
                                    @elseif($process->started_at)
                                        <span class="inline-flex items-center rounded-full bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-700">Berjalan</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">Belum dimulai</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('sample-processes.show', ['sample_process' => $process->id]) }}" class="text-sm font-semibold text-primary-700 hover:text-primary-800">Detail</a>
                                        <a href="{{ route('sample-processes.edit', ['sample_process' => $process->id]) }}" class="text-sm font-semibold text-gray-600 hover:text-gray-800">Ubah</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                    <div class="space-y-2">
                                        <p>Belum ada data proses pengujian.</p>
                                        <p class="text-sm">
                                            <a href="{{ route('samples.test.create') }}" class="text-primary-700 hover:text-primary-800 underline">
                                                Input data pengujian sampel terlebih dahulu →
                                            </a>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $processes->links() }}
            </div>
        </div>

        <!-- Uses centralized listFetcher from app.js -->
    </div>
</x-app-layout>
