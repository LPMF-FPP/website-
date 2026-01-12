<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Detail Pengujian"
            :breadcrumbs="[[ 'label' => 'Kaji Ulang Permintaan', 'href' => route('review.create') ], [ 'label' => 'Pengujian', 'href' => route('testing.index') ], [ 'label' => ($testRequest->receipt_number ?? $testRequest->request_number) ]]"
        />
    </x-slot>

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if(session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-success-700">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-danger-200 bg-danger-50 px-4 py-3 text-danger-700">
                <ul class="list-disc list-inside space-y-1 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $investigator = $testRequest->investigator;
            $unit = $investigator?->jurisdiction ?? $investigator?->institution;
            $receivedAt = $testRequest->received_at ?? $testRequest->created_at;
        @endphp

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="text-2xl font-semibold text-primary-900">
                    {{ $testRequest->receipt_number ?? $testRequest->request_number }}
                </div>
                <div class="mt-1 text-sm text-gray-600">
                    {{ $investigator?->full_name ?? $investigator?->name ?? '-' }}
                    @if($unit)
                        / {{ $unit }}
                    @endif
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    Diterima pada: {{ optional($receivedAt)->format('d F Y') ?? '-' }}
                </div>
            </div>
            <div class="flex gap-2">
                @if($readyForDelivery)
                    <form action="{{ route('testing.ready-for-delivery', $testRequest) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700">
                            <x-icon name="truck" size="sm" :decorative="true" />
                            Kirim ke Penyerahan
                        </button>
                    </form>
                @endif
                <a
                    href="{{ route('testing.processes.create', ['request_id' => $testRequest->id]) }}"
                    class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                    <x-icon name="plus" size="sm" :decorative="true" />
                    Tambah Proses
                </a>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                @foreach($stepper as $step)
                    <div class="flex flex-1 flex-col items-center">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full border-2 text-xs font-semibold
                            {{ $step['state'] === 'completed' ? 'border-primary-600 bg-primary-600 text-white' : ($step['state'] === 'active' ? 'border-primary-600 bg-primary-50 text-primary-700' : 'border-gray-200 text-gray-400') }}">
                            @if($step['state'] === 'completed')
                                <x-icon name="check" size="sm" :decorative="true" />
                            @else
                                <span>{{ $loop->iteration }}</span>
                            @endif
                        </div>
                        <div class="mt-2 text-xs font-semibold {{ in_array($step['state'], ['completed', 'active'], true) ? 'text-primary-700' : 'text-gray-400' }}">
                            {{ $step['label'] }}
                        </div>
                    </div>
                    @if(!$loop->last)
                        <div class="mx-2 h-0.5 flex-1 {{ $step['state'] === 'completed' ? 'bg-primary-500' : 'bg-gray-200' }}"></div>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm space-y-4">
            <form method="GET" action="{{ route('testing.show', $testRequest) }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="filter_stage" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Tahapan</label>
                    <select id="filter_stage" name="stage" class="mt-1 block w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua Tahapan</option>
                        @foreach($stageOptions as $stage)
                            <option value="{{ $stage->value }}" @selected(($filters['stage'] ?? '') === $stage->value)>{{ $stage->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter_short_description" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Deskripsi Singkat</label>
                    <select id="filter_short_description" name="short_description" class="mt-1 block w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua Deskripsi</option>
                        @forelse($shortDescriptions as $desc)
                            <option value="{{ $desc }}" @selected(($filters['short_description'] ?? '') === $desc)>{{ $desc }}</option>
                        @empty
                            <option disabled>Tidak ada deskripsi</option>
                        @endforelse
                    </select>
                </div>

                <div>
                    <label for="filter_status" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Status</label>
                    <select id="filter_status" name="status" class="mt-1 block w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua Status</option>
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-600 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-700">
                    Terapkan
                </button>
            </form>

            <div class="overflow-hidden rounded-lg border border-gray-100">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Sampel</th>
                            <th class="px-4 py-3 text-left">Deskripsi Singkat</th>
                            <th class="px-4 py-3 text-left">Tahapan</th>
                            <th class="px-4 py-3 text-left">Jadwal</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse($samples as $sample)
                            <tr class="hover:bg-gray-50/70">
                                <td class="px-4 py-3 font-semibold text-gray-900">
                                    {{ $sample->sample_code ?? '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $sample->short_description ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $sample->current_stage_label }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ optional($sample->current_schedule)->format('d M Y') ?? '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusColor = match ($sample->current_status_key) {
                                            'completed' => 'bg-green-100 text-green-700',
                                            'in_progress' => 'bg-yellow-100 text-yellow-700',
                                            default => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold {{ $statusColor }}">
                                        {{ $sample->current_status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex overflow-hidden rounded-md border border-gray-200 shadow-sm">
                                        @if($sample->current_process)
                                            <a
                                                href="{{ route('testing.processes.show', $sample->current_process) }}"
                                                class="inline-flex items-center px-3 py-2 text-sm font-semibold text-primary-700 hover:bg-primary-50">
                                                Lihat
                                            </a>
                                        @else
                                            <span class="inline-flex items-center px-3 py-2 text-sm font-semibold text-gray-400">
                                                Lihat
                                            </span>
                                        @endif
                                        <button type="button" class="inline-flex items-center border-l border-gray-200 px-2 text-gray-400 hover:bg-gray-50">
                                            <x-icon name="chevron-down" size="sm" :decorative="true" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">
                                    Belum ada sampel untuk resi ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(!$hasProcesses)
                <div class="rounded-lg border border-dashed border-gray-200 p-4 text-sm text-gray-500">
                    Belum ada proses untuk resi ini.
                </div>
            @endif

            <div>
                {{ $samples->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
