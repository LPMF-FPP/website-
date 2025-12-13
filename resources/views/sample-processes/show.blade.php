@php
    use Illuminate\Support\Facades\Storage;
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Detail Proses Pengujian"
            :breadcrumbs="[[ 'label' => 'Proses', 'href' => route('sample-processes.index') ], [ 'label' => 'Detail' ]]"
        />
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if(session('success'))
            <div class="rounded-lg border-2 border-green-300 bg-green-50 p-5 text-sm shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-green-900">Berhasil!</h3>
                        <p class="mt-1 text-green-800">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if(session('error') || $errors->any())
            <div class="rounded-lg border-2 border-red-300 bg-red-50 p-5 text-sm shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-red-900">Error!</h3>
                        @if(session('error'))
                            <p class="mt-1 text-red-800">{{ session('error') }}</p>
                        @endif
                        @if($errors->any())
                            <ul class="mt-2 list-disc list-inside space-y-1">
                                @foreach($errors->all() as $error)
                                    <li class="text-red-800">{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">

            <a href="{{ route('sample-processes.index') }}"

                class="inline-flex items-center text-sm font-semibold text-primary-700 hover:text-primary-800">&larr; Kembali ke daftar</a>

            <div class="flex flex-wrap items-center gap-2">

                @if(optional($sampleProcess->sample)->status === 'ready_for_delivery')

                          <a href="{{ route('delivery.show', $sampleProcess->sample->testRequest) }}"

                              class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-primary-500 hover:text-primary-700">Penyerahan</a>

                @endif

                @php
                    $stageVal = ($sampleProcess->stage instanceof \App\Enums\TestProcessStage ? $sampleProcess->stage->value : $sampleProcess->stage);
                @endphp
                @if($stageVal === 'preparation')
                    <div class="flex gap-2">
                        <a href="{{ route('sample-processes.generate-form', ['sample_process' => $sampleProcess->id, 'stage' => 'preparation']) }}"
                           target="_blank"
                           class="inline-flex items-center gap-2 rounded-md bg-primary-50 px-3 py-2 text-sm font-semibold text-primary-700 ring-1 ring-inset ring-primary-200 hover:bg-primary-100">
                            <x-icon name="document" class="h-4 w-4" aria-hidden="true" />
                            Lihat Formulir Preparasi
                        </a>
                        <a href="{{ route('sample-processes.generate-form', ['sample_process' => $sampleProcess->id, 'stage' => 'preparation']) }}?download=1"
                           class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download
                        </a>
                    </div>
                @elseif($stageVal === 'instrumentation')
                    <a href="{{ route('sample-processes.generate-form', ['sample_process' => $sampleProcess->id, 'stage' => 'instrumentation']) }}"
                       class="inline-flex items-center gap-2 rounded-md bg-primary-50 px-3 py-2 text-sm font-semibold text-primary-700 ring-1 ring-inset ring-primary-200 hover:bg-primary-100">
                        <x-icon name="document" class="h-4 w-4" aria-hidden="true" />
                        Generate Formulir Pengujian Instrumen
                    </a>
                @elseif($stageVal === 'interpretation')
                    <a href="{{ route('sample-processes.lab-report', $sampleProcess) }}" target="_blank"
                       class="inline-flex items-center gap-2 rounded-md bg-primary-50 px-3 py-2 text-sm font-semibold text-primary-700 ring-1 ring-inset ring-primary-200 hover:bg-primary-100">
                        <x-icon name="document" class="h-4 w-4" aria-hidden="true" />
                        Lihat Laporan Hasil Uji
                    </a>
                    <a href="{{ route('sample-processes.lab-report', ['sample_process' => $sampleProcess, 'download' => 1]) }}"
                       class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                        <x-icon name="download" class="h-4 w-4" aria-hidden="true" />
                        Download Laporan
                    </a>
                @endif

                <a href="{{ route('sample-processes.edit', ['sample_process' => $sampleProcess->id]) }}"
                   class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Ubah Proses</a>

            </div>

        </div>



        <div class="rounded-lg bg-white p-6 shadow-sm space-y-6">
            <x-page-section title="Sampel">
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ $sampleProcess->sample->sample_name }}</p>
                <p class="text-sm text-gray-500">Permintaan: {{ $sampleProcess->sample->testRequest?->request_number ?? '-' }}</p>
            </x-page-section>

            <x-page-section title="Informasi Proses">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tahapan</div>
                        <p class="mt-1 text-base text-gray-800">{{ $sampleProcess->stage_label }}</p>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pelaksana</div>
                        <p class="mt-1 text-base text-gray-800">
                            {{ $sampleProcess->analyst?->display_name_with_title ?? 'Belum ditentukan' }}
                        </p>
                        @if($sampleProcess->analyst)
                            <p class="text-sm text-gray-500">{{ $sampleProcess->analyst->rank }} {{ $sampleProcess->analyst->identification_number ? '(' . $sampleProcess->analyst->identification_number . ')' : '' }}</p>
                        @endif
                    </div>
                </div>
            </x-page-section>

            <x-page-section title="Waktu">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Mulai</div>
                        <p class="mt-1 text-base text-gray-800">{{ optional($sampleProcess->started_at)->format('d/m/Y H:i') ?? '-' }}</p>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Selesai</div>
                        <p class="mt-1 text-base text-gray-800">{{ optional($sampleProcess->completed_at)->format('d/m/Y H:i') ?? '-' }}</p>
                    </div>
                </div>
            </x-page-section>

            @if($interpretationDetails)
                <x-page-section title="Interpretasi Hasil">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Nomor Laporan Hasil Uji</div>
                    <div class="mt-1 mb-3 font-semibold text-gray-900">{{ $interpretationDetails['report_number'] }}</div>
                    @php
                        // Build unified rows: primary + multi
                        $rows = [];
                        $rows[] = [
                            'instrument' => $interpretationDetails['instrument'] ?? '-',
                            'result_raw' => $interpretationDetails['test_result_raw'] ?? null,
                            'result'     => $interpretationDetails['test_result'] ?? 'Belum ditentukan',
                            'detected'   => $interpretationDetails['detected_substance'] ?? '-',
                            'attachment_url' => $interpretationDetails['attachment_url'] ?? null,
                            'attachment_original' => $interpretationDetails['attachment_original'] ?? null,
                        ];
                        if (!empty($interpretationDetails['multi'])) {
                            foreach ($interpretationDetails['multi'] as $mi) {
                                $rows[] = [
                                    'instrument' => $mi['instrument'] ?? '-',
                                    'result_raw' => $mi['test_result_raw'] ?? null,
                                    'result'     => $mi['test_result'] ?? 'Belum ditentukan',
                                    'detected'   => $mi['detected_substance'] ?? '-',
                                    'attachment_url' => $mi['attachment_url'] ?? null,
                                    'attachment_original' => $mi['attachment_original'] ?? null,
                                ];
                            }
                        }
                    @endphp
                    <div class="overflow-hidden rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-left">Instrumen Pengujian</th>
                                    <th class="px-4 py-3 text-left">Hasil Uji</th>
                                    <th class="px-4 py-3 text-left">Zat Aktif Terdeteksi</th>
                                    <th class="px-4 py-3 text-left">Lampiran</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-gray-700">
                                @foreach($rows as $r)
                                    @php
                                        $badge = match ($r['result_raw']) {
                                            'positive' => 'inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700',
                                            'negative' => 'inline-flex items-center rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700',
                                            default => 'inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $r['instrument'] }}</td>
                                        <td class="px-4 py-3"><span class="{{ $badge }}">{{ $r['result'] }}</span></td>
                                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $r['detected'] }}</td>
                                        <td class="px-4 py-3">
                                            @if(!empty($r['attachment_url']))
                                                <a href="{{ $r['attachment_url'] }}" target="_blank" class="text-primary-700 hover:text-primary-800 underline">{{ $r['attachment_original'] ?? 'Lihat dokumen' }}</a>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Link ke Laporan Hasil Uji yang di-generate --}}
                    @if($interpretationDetails['report_exists'] && $interpretationDetails['report_document'])
                        @php
                            $doc = $interpretationDetails['report_document'];
                        @endphp
                        <div class="mt-4 rounded-md border border-primary-200 bg-primary-50 px-4 py-3">
                            <div class="flex items-center gap-3">
                                <x-icon name="document" class="h-5 w-5 text-primary-600" />
                                <div class="flex-1">
                                    <span class="font-semibold text-primary-900">Laporan Hasil Uji</span>
                                    <p class="text-xs text-primary-700 mt-1">
                                        Nomor: <span class="font-mono">{{ $interpretationDetails['report_number'] }}</span>
                                        @if($doc->created_at)
                                            &middot; Generated: {{ $doc->created_at->format('d/m/Y H:i') }}
                                        @endif
                                    </p>
                                </div>
                                <a href="{{ asset('storage/' . ltrim($doc->path, '/')) }}"
                                   target="_blank"
                                   class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition">
                                    <x-icon name="eye" class="h-4 w-4" />
                                    Lihat Laporan
                                </a>
                            </div>
                        </div>
                    @endif

                </x-page-section>
            @endif

            <div class="text-xs text-gray-500">
                Dibuat: {{ $sampleProcess->created_at->format('d/m/Y H:i') }} &middot; Diperbarui: {{ $sampleProcess->updated_at->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>
</x-app-layout>
