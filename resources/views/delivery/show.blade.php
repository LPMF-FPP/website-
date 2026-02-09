<x-app-layout>
    <x-slot name="header">
        <x-page-header
            :title="'Detail Penyerahan · ' . $request->receipt_number"
            :breadcrumbs="[[ 'label' => 'Penyerahan', 'href' => route('delivery.index') ], [ 'label' => 'Detail' ]]"
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
                                                @else
                                                    {{-- Include partial --}}
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
                                                            <button type="button"
                                                                @click.prevent="showConfirmDialog({
                                                                    type: 'info',
                                                                    title: 'Konfirmasi Penyerahan Selesai',
                                                                    message: 'Tandai penyerahan sebagai selesai?\\n\\nStatus akan berubah menjadi Selesai.',
                                                                    confirmButtonText: 'Ya, Selesaikan',
                                                                    onConfirm: () => $el.closest('form').submit()
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

                                    {{-- Qty Info --}}
                                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-gray-500 bg-gray-50 rounded p-2">
                                        <div>
                                            <span class="block text-gray-400 text-[10px] uppercase">Diserahkan</span>
                                            <span class="font-medium text-gray-700">{{ $sample->delivered_quantity_display ?? '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="block text-gray-400 text-[10px] uppercase">Diuji</span>
                                            <span class="font-medium text-gray-700">{{ $sample->testing_quantity_display ?? '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="block text-gray-400 text-[10px] uppercase">Sisa</span>
                                            <span class="font-medium text-gray-700">{{ $sample->leftover_quantity_display ?? '-' }}</span>
                                        </div>
                                    </div>
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
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Penyidik</div>
                            <div class="font-medium text-gray-900 mt-0.5">{{ $request->investigator->name ?? '-' }}</div>
                            <div class="text-gray-500 text-xs">{{ $request->investigator->rank ?? '' }} &middot; {{ $request->investigator->jurisdiction ?? '' }}</div>
                        </div>

                        <div>
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Tersangka</div>
                            <div class="font-medium text-gray-900 mt-0.5">{{ $request->suspect_name ?? '-' }}</div>
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
