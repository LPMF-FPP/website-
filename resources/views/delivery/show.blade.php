<x-app-layout>
    <x-slot name="header">
        <x-page-header
            :title="'Detail Penyerahan · ' . $request->receipt_number"
            :breadcrumbs="[]"
        />
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
        {{-- Back Button --}}
        <a href="{{ route('delivery.index') }}"
            class="inline-flex items-center text-sm font-semibold text-primary-700 transition hover:text-primary-800">
            &larr; Kembali ke daftar penyerahan
        </a>

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 shadow-sm">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-sm">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        @if($request->status === 'ready_for_delivery' && ! $delivery->hasBeenCollected() && auth()->user()?->hasAnyPermission(['penyerahan.edit', 'penyerahan.create']))
            <section class="rounded-xl border border-amber-300 bg-amber-50 p-5" aria-labelledby="reopen-sample-heading">
                <h2 id="reopen-sample-heading" class="text-base font-semibold text-amber-950">Perlu menambahkan sampel?</h2>
                <p class="mt-1 text-sm text-amber-900">Hasil belum tercatat diambil. Anda dapat membuka kembali siklus pengujian. Handover lama akan tetap tersimpan sebagai riwayat dan tidak dapat dipakai untuk siklus baru.</p>
                <a href="{{ route('delivery.reopen-additional-sample.create', $request) }}" class="mt-3 inline-flex min-h-11 items-center justify-center rounded-md bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-700">
                    Buka Kembali untuk Sampel Tambahan
                </a>
            </section>
        @elseif($request->status === 'completed' && ! $delivery->hasBeenCollected() && auth()->user()?->hasAnyPermission(['penyerahan.edit', 'penyerahan.create']))
            <section class="rounded-xl border border-amber-300 bg-amber-50 p-5" aria-labelledby="completed-sample-path-heading">
                <h2 id="completed-sample-path-heading" class="text-base font-semibold text-amber-950">Status pengambilan hasil</h2>
                <p class="mt-1 text-sm text-amber-900">Permintaan sudah ditandai selesai, tetapi belum ada catatan hasil diambil. Pilih alur sesuai kondisi fisik hasil.</p>
                <div class="mt-3 flex flex-wrap gap-3">
                    <a href="{{ route('delivery.reopen-additional-sample.create', $request) }}" class="inline-flex min-h-11 items-center justify-center rounded-md bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-700">Belum Diambil — Buka Kembali</a>
                    @if(auth()->user()?->hasPermission('penyerahan.create'))
                        <a href="{{ route('requests.supplemental-samples.create', $request) }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-800 hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">Sudah Diambil — Buat Suplemen</a>
                    @endif
                </div>
            </section>
        @elseif($delivery->hasBeenCollected() && auth()->user()?->hasPermission('penyerahan.create'))
            <section class="rounded-xl border border-blue-200 bg-blue-50 p-5" aria-labelledby="supplement-sample-heading">
                <h2 id="supplement-sample-heading" class="text-base font-semibold text-blue-950">Sampel tambahan setelah hasil diambil</h2>
                <p class="mt-1 text-sm text-blue-900">Buat permintaan suplemen tertaut dengan siklus dan dokumen tersendiri. Permintaan awal tetap selesai tanpa diubah.</p>
                <a href="{{ route('requests.supplemental-samples.create', $request) }}" class="mt-3 inline-flex min-h-11 items-center justify-center rounded-md bg-primary-700 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700">
                    Buat Suplemen Tertaut
                </a>
            </section>
        @endif

        @if($handoverHistory->isNotEmpty())
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="handover-history-heading">
                <h2 id="handover-history-heading" class="text-base font-semibold text-gray-900">Riwayat Siklus Penyerahan</h2>
                <ul class="mt-3 space-y-3">
                    @foreach($handoverHistory as $cycle)
                        <li class="rounded-md border border-gray-200 p-3 text-sm">
                            <p class="font-semibold text-gray-800">Siklus {{ $cycle['cycle'] }} disupersede</p>
                            <p class="mt-1 text-gray-600">Sampel {{ $cycle['sample_code'] }} · Dibuka kembali {{ $cycle['reopened_at']?->format('d M Y H:i') }} oleh {{ $cycle['reopened_by'] }} · {{ $cycle['reason'] }}</p>
                            @foreach($cycle['documents'] as $document)
                                <a href="{{ route('delivery.handover.archive', [$delivery, $document]) }}" class="mt-2 inline-flex min-h-11 items-center gap-2 rounded-md border border-gray-300 px-3 py-2 font-medium text-primary-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                                    Buka BA siklus {{ $cycle['cycle'] }} · {{ $document->original_filename }}
                                </a>
                            @endforeach
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($request->supplementalRequests->isNotEmpty())
            <section class="rounded-xl border border-blue-200 bg-white p-5 shadow-sm" aria-labelledby="supplemental-requests-heading">
                <h2 id="supplemental-requests-heading" class="text-base font-semibold text-gray-900">Permintaan Suplemen Tertaut</h2>
                <ul class="mt-3 space-y-2">
                    @foreach($request->supplementalRequests as $supplement)
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-gray-200 p-3 text-sm">
                            <span><strong>{{ $supplement->receipt_number }}</strong> · {{ $supplement->supplement_reason }}</span>
                            <a href="{{ route('requests.show', $supplement) }}" class="inline-flex min-h-11 items-center rounded-md px-3 py-2 font-semibold text-primary-700 hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">Buka suplemen</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($request->parentTestRequest)
            <section class="rounded-xl border border-blue-200 bg-blue-50 p-5" aria-labelledby="supplement-parent-heading">
                <h2 id="supplement-parent-heading" class="text-base font-semibold text-blue-950">Suplemen tertaut ke permintaan awal</h2>
                <p class="mt-1 text-sm text-blue-900">{{ $request->supplement_reason }}</p>
                <a href="{{ route('requests.show', $request->parentTestRequest) }}" class="mt-3 inline-flex min-h-11 items-center rounded-md px-3 py-2 text-sm font-semibold text-primary-700 hover:bg-blue-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">Lihat resi awal {{ $request->parentTestRequest->receipt_number }}</a>
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Left Column: Stepper (2/3 width) --}}
            <div class="lg:col-span-2 space-y-6">
                
                {{-- Stepper Card --}}
                <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5 border-l-4 border-teal-500">
                    <div class="border-b border-gray-100 bg-gray-50/50 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold leading-6 text-gray-900">Langkah Penyerahan</h2>
                            @php
                                $completedCount = collect($stepper)->where('completed', true)->count();
                                $totalSteps = count($stepper);
                            @endphp
                            @if($completedCount < $totalSteps)
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 animate-pulse">
                                    {{ $completedCount }} dari {{ $totalSteps }} selesai
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-700/10">
                                    <svg class="mr-1 h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    Semua Selesai
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="px-6 py-6">
                        <nav aria-label="Progress">
                            <ol role="list" class="overflow-hidden">
                                
                                {{-- STEP 1: Berita Acara --}}
                                <li class="relative pb-10">
                                    <div class="absolute left-4 top-4 -ml-px h-full w-0.5 {{ ($stepper[2]['completed'] ?? false) ? 'bg-green-500' : (($stepper[1]['completed'] ?? false) ? 'bg-gradient-to-b from-green-500 to-gray-200' : 'bg-gray-200') }}" aria-hidden="true"></div>
                                    <div class="relative flex items-start group">
                                        <span class="flex h-9 items-center">
                                            @if($stepper[1]['completed'])
                                                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-green-600 group-hover:bg-green-800">
                                                    <svg class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                            @else
                                                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full border-2 border-blue-600 bg-white">
                                                    <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                                </span>
                                            @endif
                                        </span>
                                        <div class="ml-4 flex min-w-0 flex-1 flex-col">
                                            <span class="text-sm font-medium {{ $stepper[1]['completed'] ? 'text-gray-900' : 'text-blue-600' }}">Berita Acara Penyerahan</span>
                                            <div class="text-sm text-gray-500 mt-1">
                                                @if($stepper[1]['completed'])
                                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                        <span>Dokumen tersedia. <span class="text-amber-600 font-medium">⚠️ Cetak 2 rangkap (Arsip & Penyidik).</span></span>
                                                        <div class="flex items-center gap-2">
                                                            <a href="{{ route('delivery.handover.view', $delivery) }}" target="_blank" class="inline-flex items-center rounded bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Buka</a>
                                                            <a href="{{ route('delivery.handover.download', $delivery) }}" class="inline-flex items-center rounded bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Unduh</a>
                                                            <form method="POST" action="{{ route('delivery.handover.generate', $delivery) }}" class="inline-block">
                                                                @csrf
                                                                <button type="submit" class="text-xs text-gray-400 hover:text-gray-600 underline ml-1">Regenerate</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="flex items-center justify-between">
                                                        <span>Dokumen belum dibuat.</span>
                                                        <form method="POST" action="{{ route('delivery.handover.generate', $delivery) }}">
                                                            @csrf
                                                            <button type="submit" class="inline-flex items-center rounded bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">Generate Dokumen</button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </li>

                                {{-- STEP 2: Label Sisa --}}
                                <li class="relative pb-10">
                                    <div class="absolute left-4 top-4 -ml-px h-full w-0.5 {{ ($stepper[3]['completed'] ?? false) ? 'bg-green-500' : (($stepper[2]['completed'] ?? false) ? 'bg-gradient-to-b from-green-500 to-gray-200' : 'bg-gray-200') }}" aria-hidden="true"></div>
                                    <div class="relative flex items-start group">
                                        <span class="flex h-9 items-center">
                                            @if($stepper[2]['completed'])
                                                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-green-600 group-hover:bg-green-800">
                                                    <svg class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                                </span>
                                            @elseif($stepper[2]['locked'])
                                                 <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 border-2 border-gray-300">
                                                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                                </span>
                                            @else
                                                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full border-2 border-blue-600 bg-white">
                                                    <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                                </span>
                                            @endif
                                        </span>
                                        <div class="ml-4 flex min-w-0 flex-1 flex-col">
                                            <span class="text-sm font-medium {{ $stepper[2]['locked'] ? 'text-gray-500' : ($stepper[2]['completed'] ? 'text-gray-900' : 'text-blue-600') }}">Label Sisa Sampel</span>
                                            <div class="text-sm text-gray-500 mt-1">
                                                @if($stepper[2]['locked'])
                                                    Selesaikan langkah sebelumnya terlebih dahulu.
                                                @elseif(!($stepper[2]['required'] ?? true))
                                                    <div class="rounded-xl border border-emerald-200 bg-gradient-to-r from-emerald-50 via-white to-teal-50 px-4 py-3 text-sm text-emerald-800 shadow-sm">
                                                        <div class="flex items-start gap-3">
                                                            <div class="mt-0.5 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                </svg>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <p class="font-semibold">Tidak ada sisa sampel yang perlu dilabeli.</p>
                                                                <p class="mt-1 text-emerald-700/90">Semua sampel pada penyerahan ini habis diuji atau tidak memiliki sisa positif, sehingga langkah ini ditandai selesai otomatis dan proses dapat dilanjutkan.</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="mb-3 flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-800">
                                                        <div>
                                                            <p class="font-semibold">Label sisa diperlukan.</p>
                                                            <p class="mt-0.5 text-amber-700/90">{{ $stepper[2]['remaining_sample_count'] ?? 0 }} sampel masih memiliki sisa positif dan perlu dilabeli sebelum penyerahan dilanjutkan.</p>
                                                        </div>
                                                        @if(($stepper[2]['count'] ?? 0) > 0)
                                                            <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200">
                                                                {{ $stepper[2]['count'] }} label dibuat
                                                            </span>
                                                        @endif
                                                    </div>

                                                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 mt-2">
                                                        @include('partials.remaining-label-section')
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </li>

                                {{-- STEP 3: Notifikasi WhatsApp --}}
                                <li class="relative pb-10">
                                    <div class="absolute left-4 top-4 -ml-px h-full w-0.5 {{ ($stepper[4]['completed'] ?? false) ? 'bg-green-500' : (($stepper[3]['completed'] ?? false) ? 'bg-gradient-to-b from-green-500 to-gray-200' : 'bg-gray-200') }}" aria-hidden="true"></div>
                                    <div class="relative flex items-start group">
                                        <span class="flex h-9 items-center">
                                            @if($stepper[3]['completed'])
                                                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-green-600 group-hover:bg-green-800">
                                                    <svg class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                                </span>
                                            @elseif($stepper[3]['locked'])
                                                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 border-2 border-gray-300">
                                                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                                </span>
                                            @else
                                                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full border-2 border-blue-600 bg-white">
                                                    <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                                </span>
                                            @endif
                                        </span>
                                        <div class="ml-4 flex min-w-0 flex-1 flex-col">
                                            <span class="text-sm font-medium {{ $stepper[3]['locked'] ? 'text-gray-500' : ($stepper[3]['completed'] ? 'text-gray-900' : 'text-blue-600') }}">Notifikasi WhatsApp</span>
                                            <div class="text-sm text-gray-500 mt-1">
                                                @if($stepper[3]['locked'])
                                                    Selesaikan langkah sebelumnya terlebih dahulu.
                                                @else
                                                    <div class="flex items-center justify-between gap-4">
                                                        <div class="flex-1">
                                                            <div class="mb-1">Penerima: {{ $request->investigator->name ?? '-' }} ({{ $request->investigator->phone ?? '-' }})</div>
                                                            @if($lastNotification)
                                                                <div class="flex items-center gap-2">
                                                                    @php
                                                                        $badgeColors = [
                                                                            'queued' => 'bg-gray-100 text-gray-700',
                                                                            'sent' => 'bg-blue-100 text-blue-700',
                                                                            'delivered' => 'bg-green-100 text-green-700',
                                                                            'read' => 'bg-green-100 text-green-700',
                                                                            'failed' => 'bg-red-100 text-red-700',
                                                                        ];
                                                                        $badgeColor = $badgeColors[$lastNotification->status] ?? 'bg-gray-100 text-gray-700';
                                                                    @endphp
                                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeColor }}">
                                                                        {{ ucfirst($lastNotification->status) }}
                                                                    </span>
                                                                    <span class="text-xs text-gray-400">{{ $lastNotification->updated_at->diffForHumans() }}</span>
                                                                </div>
                                                            @else
                                                                <div class="text-gray-400 text-xs italic">Belum pernah dikirim.</div>
                                                            @endif
                                                        </div>
                                                        <form action="{{ route('delivery.send-notification', $request) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" 
                                                                class="inline-flex items-center rounded px-3 py-2 text-sm font-semibold shadow-sm transition {{ $lastNotification ? 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' : 'bg-blue-600 text-white hover:bg-blue-500' }}">
                                                                {{ $lastNotification ? 'Kirim Ulang' : 'Kirim Notifikasi' }}
                                                            </button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </li>

                                {{-- STEP 4: Survei --}}
                                <li class="relative pb-10">
                                    <div class="absolute left-4 top-4 -ml-px h-full w-0.5 {{ ($stepper[5]['completed'] ?? false) ? 'bg-green-500' : (($stepper[4]['completed'] ?? false) ? 'bg-gradient-to-b from-green-500 to-gray-200' : 'bg-gray-200') }}" aria-hidden="true"></div>
                                    <div class="relative flex items-start group">
                                        <span class="flex h-9 items-center">
                                            @if($stepper[4]['completed'])
                                                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-green-600 group-hover:bg-green-800">
                                                    <svg class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                                </span>
                                            @elseif($stepper[4]['locked'])
                                                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 border-2 border-gray-300">
                                                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                                </span>
                                            @else
                                                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full border-2 border-blue-600 bg-white">
                                                    <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                                </span>
                                            @endif
                                        </span>
                                        <div class="ml-4 flex min-w-0 flex-1 flex-col">
                                            <span class="text-sm font-medium {{ $stepper[4]['locked'] ? 'text-gray-500' : ($stepper[4]['completed'] ? 'text-gray-900' : 'text-blue-600') }}">Survei Kepuasan</span>
                                            <div class="text-sm text-gray-500 mt-1">
                                                @if($stepper[4]['locked'])
                                                    Selesaikan langkah sebelumnya terlebih dahulu.
                                                @else
                                                    <div class="flex items-center justify-between">
                                                        <span>
                                                            @if($stepper[4]['completed'])
                                                                Survei telah diisi oleh {{ $request->customerSurvey->respondent_name ?? 'Responden' }}.
                                                            @else
                                                                Wajib diisi sebelum penyerahan selesai.
                                                            @endif
                                                        </span>
                                                        <a href="{{ route('delivery.survey', $request) }}" 
                                                           class="inline-flex items-center rounded px-3 py-2 text-sm font-semibold shadow-sm transition {{ $stepper[4]['completed'] ? 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' : 'bg-blue-600 text-white hover:bg-blue-500' }}">
                                                            {{ $stepper[4]['completed'] ? 'Lihat Survei' : 'Isi Survei' }}
                                                        </a>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </li>

                                {{-- STEP 5: Selesai --}}
                                <li class="relative">
                                    <div class="relative flex items-start group">
                                        <span class="flex h-9 items-center">
                                            @if($stepper[5]['completed'])
                                                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-green-600 group-hover:bg-green-800">
                                                    <svg class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                                </span>
                                            @elseif($stepper[5]['locked'])
                                                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 border-2 border-gray-300">
                                                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                                </span>
                                            @else
                                                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full border-2 border-blue-600 bg-white">
                                                    <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                                </span>
                                            @endif
                                        </span>
                                        <div class="ml-4 flex min-w-0 flex-1 flex-col">
                                            <span class="text-sm font-medium {{ $stepper[5]['locked'] ? 'text-gray-500' : ($stepper[5]['completed'] ? 'text-gray-900' : 'text-blue-600') }}">Selesaikan Penyerahan</span>
                                            <div class="text-sm text-gray-500 mt-1">
                                                @if($stepper[5]['completed'])
                                                    <span class="text-green-700 font-medium">Penyerahan Selesai.</span>
                                                @elseif($stepper[5]['locked'])
                                                    Selesaikan semua langkah di atas terlebih dahulu.
                                                @else
                                                    <div class="flex items-center justify-between">
                                                        <span>Klik tombol untuk menandai selesai.</span>
                                                        <form method="POST" action="{{ route('delivery.complete', $request) }}" x-data>
                                                            @csrf
                                                            <label class="mb-2 flex items-start gap-2 text-xs text-gray-700" for="collection_confirmation">
                                                                <input id="collection_confirmation" type="checkbox" name="collection_confirmation" value="1" required class="mt-0.5 min-h-5 min-w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                                                <span>Saya mengonfirmasi hasil fisik sudah diambil oleh penerima.</span>
                                                            </label>
                                                            <button type="button"
                                                                @click.prevent="showConfirmDialog({
                                                                    type: 'info',
                                                                    title: 'Konfirmasi Penyerahan Selesai',
                                                                    message: 'Tandai penyerahan selesai dan catat bahwa hasil fisik sudah diambil?<br><br>Status akan berubah menjadi Selesai.',
                                                                    confirmButtonText: 'Ya, Selesaikan',
                                                                    onConfirm: () => $el.closest('form')?.requestSubmit()
                                                                })"
                                                                class="inline-flex items-center rounded bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                                                                Tandai Selesai
                                                            </button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </li>

                            </ol>
                        </nav>

                        @if($stepper[5]['completed'] ?? false)
                            <div class="mt-6 rounded-xl border border-green-200 bg-gradient-to-r from-green-50 via-emerald-50 to-teal-50 p-6">
                                <div class="flex items-center gap-4">
                                    <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-green-400 to-emerald-500 shadow-lg">
                                        <span class="text-3xl" aria-hidden="true">🎉</span>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-bold text-green-800">Penyerahan Berhasil!</h3>
                                        <p class="mt-0.5 text-sm text-green-700">Semua langkah telah diselesaikan dengan sukses.</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Detail Sampel (Collapsible) --}}
                <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5" x-data="{ open: false }">
                    <button @click="open = !open" class="flex w-full items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Detail Sampel ({{ $request->samples->count() }} sampel)</h2>
                            <p class="mt-1 text-xs text-gray-500 truncate max-w-xl">
                                {{ $request->samples->pluck('sample_code')->join(', ') }}
                            </p>
                        </div>
                        <svg class="h-5 w-5 text-gray-400 transform transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    
                    <div x-show="open" x-collapse style="display: none;">
                        <div class="border-t border-gray-100 divide-y divide-gray-100">
                            @foreach($request->samples as $sample)
                                <div class="px-6 py-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="text-sm font-medium text-gray-900">{{ $sample->sample_code }}</div>
                                        @php
                                            $sampleStatus = is_object($sample->status) ? $sample->status->value : $sample->status;
                                            $badges = [
                                                'ready_for_delivery' => 'bg-teal-50 text-teal-700 ring-teal-600/20',
                                                'completed' => 'bg-green-50 text-green-700 ring-green-600/20',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $badges[$sampleStatus] ?? 'bg-gray-50 text-gray-600 ring-gray-500/10' }}">
                                            {{ ucfirst(str_replace('_', ' ', $sampleStatus)) }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600">{{ $sample->short_description ?? $sample->sample_description }}</p>

                                    @php
                                        $process = $sample->testProcesses->firstWhere('stage', \App\Enums\TestProcessStage::INTERPRETATION);
                                        $lhuNumber = data_get($process?->metadata ?? [], 'lhu_number')
                                            ?? data_get($process?->metadata ?? [], 'report_number');
                                    @endphp

                                    @if($process && $lhuNumber)
                                        <div class="mt-2 flex items-center justify-between gap-3">
                                            <div class="flex min-w-0 items-center gap-2">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5l5 5v11a2 2 0 01-2 2z" />
                                                </svg>
                                                <span class="min-w-0 truncate font-mono text-xs text-gray-600">{{ $lhuNumber }}</span>
                                            </div>
                                            <a href="{{ route('testing.processes.lab-report', $process) }}" target="_blank" rel="noopener noreferrer"
                                                class="shrink-0 rounded px-2 py-1 text-xs font-medium text-primary-600 hover:text-primary-700 hover:underline">
                                                Buka PDF
                                                <span class="sr-only">Laporan Hasil Uji {{ $lhuNumber }}</span>
                                            </a>
                                        </div>
                                    @endif

                                    {{-- Rekonsiliasi Sampel --}}
                                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-gray-500 bg-gray-50 rounded p-2">
                                        <div>
                                            <span class="block text-gray-400 text-[10px] uppercase">Jumlah Diserahkan</span>
                                            <span class="font-medium text-gray-700">{{ $sample->delivered_quantity_display ?? '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="block text-gray-400 text-[10px] uppercase">Digunakan untuk Pengujian</span>
                                            <span class="font-medium text-gray-700">{{ $sample->testing_quantity_display ?? '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="block text-gray-400 text-[10px] uppercase">Sisa Diserahkan</span>
                                            <span class="font-medium text-gray-700">{{ $sample->leftover_quantity_display ?? '-' }}</span>
                                        </div>
                                    </div>

                                    @if(auth()->user()?->hasAnyPermission(['penyerahan.edit', 'penyerahan.create']))
                                        @if(($request->status ?? null) !== 'ready_for_delivery')
                                            <p class="mt-3 rounded-md border border-gray-200 bg-gray-50 p-3 text-[11px] text-gray-600">Jumlah sisa sampel sudah final dan tidak dapat diedit pada status ini.</p>
                                        @elseif(($sample->remaining_units_count ?? 1) > 1)
                                            <p class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-[11px] text-amber-800">Sampel ini memiliki beberapa label sisa. Perbarui jumlah dari menu label agar setiap label tetap akurat.</p>
                                        @else
                                        <form method="POST" action="{{ route('delivery.remaining-quantities.update', $request) }}" class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3">
                                            @csrf
                                            @method('PATCH')
                                            <label for="remaining-qty-{{ $sample->id }}" class="block text-xs font-semibold text-amber-900">Edit jumlah sisa sampel</label>
                                            <div class="mt-1 flex items-center gap-2">
                                                <input
                                                    id="remaining-qty-{{ $sample->id }}"
                                                    name="samples[{{ $sample->id }}][qty_remaining]"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    value="{{ old('samples.'.$sample->id.'.qty_remaining', $sample->leftover_quantity_value) }}"
                                                    class="w-28 rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600"
                                                >
                                                <span class="text-xs font-medium text-amber-900">{{ $sample->remaining_unit?->uom ?: ($sample->unit ?? $sample->quantity_unit ?? '') }}</span>
                                                <button type="submit" class="rounded-md bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">Simpan</button>
                                            </div>
                                            <p class="mt-1 text-[11px] text-amber-800">Generate ulang Berita Acara setelah mengubah jumlah sisa.</p>
                                        </form>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right Column: Ringkasan --}}
            <div class="lg:col-span-1 space-y-6">
                {{-- Surat Pengantar Section --}}
                <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5 p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Surat Pengantar</h3>
                    
                    @if($delivery->has_surat_pengantar)
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Nomor Surat:</span>
                                <span class="font-medium text-gray-900">{{ $delivery->surat_pengantar_number }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Tanggal:</span>
                                <span class="font-medium text-gray-900">{{ $delivery->surat_pengantar_date?->format('d F Y') }}</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <button 
                                type="button"
                                onclick="document.getElementById('sp-form').classList.toggle('hidden')"
                                class="text-sm text-primary-600 hover:text-primary-800 font-medium"
                            >
                                Ubah Data SP
                            </button>
                        </div>
                    @else
                        <div class="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg mb-4">
                            <span class="text-amber-500">⚠️</span>
                            <p class="text-sm text-amber-700">Belum ada data Surat Pengantar</p>
                        </div>
                    @endif

                    <form 
                        id="sp-form"
                        method="POST" 
                        action="{{ route('delivery.update-surat-pengantar', $delivery) }}"
                        class="{{ $delivery->has_surat_pengantar ? 'hidden' : '' }} mt-4 space-y-4"
                    >
                        @csrf
                        @method('PATCH')
                        
                        <div>
                            <label for="surat_pengantar_number" class="block text-sm font-medium text-gray-700 mb-1">
                                Nomor Surat Pengantar
                            </label>
                            <input 
                                type="text" 
                                name="surat_pengantar_number" 
                                id="surat_pengantar_number"
                                value="{{ old('surat_pengantar_number', $delivery->surat_pengantar_number) }}"
                                placeholder="B/123/I/2026"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500"
                                required
                            >
                        </div>
                        
                        <div>
                            <label for="surat_pengantar_date" class="block text-sm font-medium text-gray-700 mb-1">
                                Tanggal Surat Pengantar
                            </label>
                            <input 
                                type="date" 
                                name="surat_pengantar_date" 
                                id="surat_pengantar_date"
                                value="{{ old('surat_pengantar_date', $delivery->surat_pengantar_date?->format('Y-m-d')) }}"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500"
                                required
                            >
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <button 
                                type="submit" 
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700"
                            >
                                Simpan
                            </button>
                            @if($delivery->has_surat_pengantar)
                                <button 
                                    type="button"
                                    onclick="document.getElementById('sp-form').classList.add('hidden')"
                                    class="text-sm text-gray-600 hover:text-gray-800"
                                >
                                    Batal
                                </button>
                            @endif
                        </div>
                    </form>
                </div>

                <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5 p-6 sticky top-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                        Ringkasan Permintaan
                    </h3>
                    
                    <div class="space-y-4 text-sm">
                        @php
                            $statusBadges = [
                                'submitted' => 'bg-blue-50 text-blue-700 ring-blue-700/10',
                                'in_testing' => 'bg-yellow-50 text-yellow-800 ring-yellow-600/20',
                                'analysis' => 'bg-orange-50 text-orange-800 ring-orange-600/20',
                                'ready_for_delivery' => 'bg-teal-50 text-teal-700 ring-teal-600/20',
                                'completed' => 'bg-green-50 text-green-700 ring-green-600/20',
                            ];

                            $st = is_object($request->status) ? $request->status->value : $request->status;
                            $statusIndicators = [
                                'submitted' => ['tile' => 'bg-blue-100', 'icon' => '📝', 'label' => 'Diajukan'],
                                'in_testing' => ['tile' => 'bg-yellow-100', 'icon' => '🔬', 'label' => 'Dalam Pengujian'],
                                'analysis' => ['tile' => 'bg-orange-100', 'icon' => '🧪', 'label' => 'Analisis'],
                                'ready_for_delivery' => ['tile' => 'bg-teal-100', 'icon' => '📦', 'label' => 'Siap Diserahkan'],
                                'completed' => ['tile' => 'bg-emerald-100', 'icon' => '✅', 'label' => 'Selesai'],
                            ];

                            $indicator = $statusIndicators[$st] ?? ['tile' => 'bg-gray-100', 'icon' => '📋', 'label' => ucfirst(str_replace('_', ' ', $st))];
                        @endphp

                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $indicator['tile'] }} shadow-inner">
                                    <span class="text-xl" aria-hidden="true">{{ $indicator['icon'] }}</span>
                                </div>
                                @if($st === 'completed')
                                    <div class="absolute -right-1 -top-1">
                                        <div class="absolute h-3.5 w-3.5 rounded-full bg-emerald-500 opacity-30 animate-ping"></div>
                                        <div class="relative h-3.5 w-3.5 rounded-full bg-emerald-500 ring-2 ring-white"></div>
                                    </div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Status</div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-gray-900">{{ $indicator['label'] }}</span>
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $statusBadges[$st] ?? 'bg-gray-50 text-gray-600 ring-gray-500/10' }}">
                                        {{ ucfirst(str_replace('_', ' ', $st)) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Penyidik</div>
                                @if(auth()->user()?->hasPermission('investigators.edit'))
                                    @if($request->status === 'ready_for_delivery' && $request->investigator)
                                        <a href="{{ route('delivery.investigator.edit', $request) }}"
                                            class="inline-flex items-center rounded-md bg-white px-2 py-1 text-xs font-semibold text-primary-700 shadow-sm ring-1 ring-inset ring-primary-200 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                            Edit
                                        </a>
                                    @endif
                                @endif
                            </div>
                            <div class="font-medium text-gray-900 mt-0.5">{{ $request->investigator->name ?? '-' }}</div>
                            <div class="text-gray-500 text-xs">{{ $request->investigator->rank ?? '' }} &middot; {{ $request->investigator->jurisdiction ?? '' }}</div>
                        </div>

                        <div>
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Tersangka</div>
                            @if($request->display_suspect_collection->isNotEmpty())
                                <ol class="mt-1 space-y-1 text-sm text-gray-900">
                                    @foreach($request->display_suspect_collection as $suspect)
                                        <li class="flex items-start gap-2">
                                            <span class="mt-0.5 text-xs font-semibold text-gray-500">{{ $loop->iteration }}.</span>
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $suspect->name }}</div>
                                                @if($suspect->gender || $suspect->age)
                                                    <div class="text-xs text-gray-500">
                                                        {{ $suspect->gender === 'male' ? 'L' : ($suspect->gender === 'female' ? 'P' : '-') }}
                                                        @if($suspect->age)
                                                            , {{ $suspect->age }} th
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ol>
                            @else
                                <div class="font-medium text-gray-900 mt-0.5">-</div>
                            @endif
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                            <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Dokumen Permintaan</div>
                            @php
                                $requestLetterDate = $request->letter_date ?? $request->created_at;
                            @endphp

                            <div class="space-y-3">
                                <div>
                                    <div class="text-[11px] uppercase tracking-wide text-slate-400">Nomor Surat Permintaan</div>
                                    <div class="mt-0.5 font-medium text-slate-900">{{ $request->case_number ?? '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-[11px] uppercase tracking-wide text-slate-400">Tanggal Surat Permintaan</div>
                                    <div class="mt-0.5 text-slate-900">{{ $requestLetterDate?->locale('id')->translatedFormat('d F Y') ?? '-' }}</div>
                                </div>

                                @if($request->has_expert_witness_request || $request->expert_witness_letter_number || $request->expert_witness_letter_date)
                                    <div class="border-t border-slate-200 pt-3">
                                        <div class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Surat Saksi Ahli</div>
                                        <div class="space-y-2">
                                            <div>
                                                <div class="text-[11px] uppercase tracking-wide text-slate-400">Nomor Surat Saksi Ahli</div>
                                                <div class="mt-0.5 font-medium text-slate-900">{{ $request->expert_witness_letter_number ?? '-' }}</div>
                                            </div>
                                            <div>
                                                <div class="text-[11px] uppercase tracking-wide text-slate-400">Tanggal Surat Saksi Ahli</div>
                                                <div class="mt-0.5 text-slate-900">{{ $request->expert_witness_letter_date?->locale('id')->translatedFormat('d F Y') ?? '-' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div>
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Tanggal Selesai</div>
                            <div class="text-gray-900 mt-0.5">{{ $request->completed_at ? $request->completed_at->format('d M Y H:i') : '-' }}</div>
                        </div>

                        @if($request->case_description)
                            <div class="pt-4 border-t border-gray-100">
                                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Catatan Kasus</div>
                                <p class="text-gray-600 italic">{{ $request->case_description }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
