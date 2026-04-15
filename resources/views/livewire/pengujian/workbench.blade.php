<div class="space-y-6" wire:loading.class="opacity-90">
    <!-- Top Toolbar -->
    <div class="flex flex-col sm:flex-row gap-4 flex-wrap items-center justify-between bg-white px-5 py-4 rounded-lg shadow-sm ring-1 ring-gray-100">
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <div class="p-2 bg-primary-50 rounded-lg">
                <x-icon name="beaker" size="sm" class="text-primary-600" :decorative="true" />
            </div>
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-800">Daftar Pengujian</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    Menampilkan <span class="font-semibold text-primary-700">{{ $requests->firstItem() ?? 0 }}-{{ $requests->lastItem() ?? 0 }}</span> dari total <span class="font-semibold text-primary-700">{{ $requests->total() }}</span> Resi
                </p>
            </div>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
            <div class="relative w-full sm:w-48">
                <select
                    id="scope"
                    wire:model.live="scope"
                    class="block w-full rounded-md border-0 py-2 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-primary-600 sm:text-sm sm:leading-6 bg-gray-50/50"
                    aria-label="Pilih jenis pencarian"
                >
                    <option value="all">Cari Semua</option>
                    <option value="receipt_number">Nomor Resi</option>
                    <option value="request_number">Nomor Permintaan</option>
                    <option value="investigator">Penyidik/Unit</option>
                </select>
            </div>

            <div class="relative w-full sm:w-64">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <x-icon name="magnifying-glass" size="xs" class="text-gray-400" />
                </div>
                <input
                    type="text"
                    id="q"
                    wire:model.live.debounce.300ms="q"
                    placeholder="Ketik kata kunci (contoh: nomor BA/resi)..."
                    class="block w-full rounded-md border-0 py-2 pl-9 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6"
                />
            </div>
        </div>
    </div>

    <!-- Summary Operational -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="bg-white px-4 py-3 rounded-lg border-l-4 border-gray-300 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Menunggu Proses</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ $summaryStats['submitted'] ?? 0 }}</p>
        </div>
        <div class="bg-white px-4 py-3 rounded-lg border-l-4 border-yellow-400 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Sedang Diuji</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ $summaryStats['in_testing'] ?? 0 }}</p>
        </div>
        <div class="bg-white px-4 py-3 rounded-lg border-l-4 border-primary-500 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Aktif</p>
            <p class="text-xl font-bold text-primary-700 mt-1">{{ $requests->total() }}</p>
        </div>
    </div>

    <!-- Loading State for Grid -->
    <div wire:loading.flex wire:target="q,scope" class="items-center justify-center gap-2 rounded-lg border border-dashed border-gray-200 p-12 text-sm text-gray-500 bg-white shadow-sm ring-1 ring-gray-100">
        <x-icon name="loading" size="sm" spin :decorative="true" class="text-primary-500" />
        <span class="font-medium animate-pulse">Menyiapkan daftar resi...</span>
    </div>

    <!-- Table/List View -->
    <div wire:loading.remove wire:target="q,scope" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm ring-1 ring-black/5">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold text-gray-900 uppercase tracking-wide">
                            <button
                                type="button"
                                wire:click="sortBy('receipt_number')"
                                class="inline-flex items-center gap-2 rounded-sm text-left transition hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                            >
                                <span>No. Resi &amp; Tgl Terima</span>
                                <span class="inline-flex h-5 min-w-5 items-center justify-center rounded bg-gray-200 px-1 text-[10px] font-bold text-gray-700">
                                    @if($sortField === 'receipt_number')
                                        {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                                    @else
                                        ↕
                                    @endif
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold text-gray-900 uppercase tracking-wide">Penyidik / Unit</th>
                        <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold text-gray-900 uppercase tracking-wide">Sampel</th>
                        <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold text-gray-900 uppercase tracking-wide">Status</th>
                        <th scope="col" class="px-4 py-3.5 text-right text-xs font-semibold text-gray-900 uppercase tracking-wide">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
        @forelse($requests as $request)
            @php
                $investigator = $request->investigator;
                $unit = $investigator?->jurisdiction ?? $investigator?->institution;
                $receivedAt = $request->received_at ?? $request->created_at;

                $requestStatus = $request->status ?? 'submitted';
                $statusColors = match ($requestStatus) {
                    'in_testing' => ['bg' => 'bg-yellow-50', 'text' => 'text-yellow-700', 'ring' => 'ring-yellow-200', 'icon' => 'play', 'label' => 'Sedang Diuji'],
                    'submitted' => ['bg' => 'bg-gray-50', 'text' => 'text-gray-700', 'ring' => 'ring-gray-200', 'icon' => 'folder-open', 'label' => 'Menunggu Proses'],
                    default => ['bg' => 'bg-primary-50', 'text' => 'text-primary-700', 'ring' => 'ring-primary-200', 'icon' => 'check-circle', 'label' => 'Selesai'],
                };
            @endphp
            
            <tr class="hover:bg-primary-50/50 transition-colors group" wire:key="request-row-{{ $request->id }}">
                <td class="whitespace-nowrap px-4 py-3">
                    <div class="flex flex-col items-start">
                        <button
                            type="button"
                            wire:click="selectRequest({{ $request->id }})"
                            class="font-bold text-left text-gray-900 group-hover:text-primary-700 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 rounded-sm"
                        >{{ $request->receipt_number ?? $request->request_number }}</button>
                        <span class="text-xs text-gray-500 mt-0.5">{{ optional($receivedAt)->format('d M Y') ?? '-' }}</span>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-900">{{ $investigator?->full_name ?? $investigator?->name ?? '-' }}</span>
                        @if($unit)
                            <span class="text-xs text-gray-500 line-clamp-1">{{ $unit }}</span>
                        @endif
                    </div>
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">
                        <x-icon name="beaker" size="sm" :decorative="true" class="text-gray-400" />
                        {{ $request->samples_count }}
                    </span>
                </td>
                <td class="whitespace-nowrap px-4 py-3">
                    <span class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold {{ $statusColors['bg'] }} {{ $statusColors['text'] }} ring-1 ring-inset {{ $statusColors['ring'] }}">
                         @if(isset($statusColors['icon']))
                             <x-icon name="{{ $statusColors['icon'] }}" size="sm" :decorative="true" />
                         @endif
                        {{ $statusColors['label'] ?? str($requestStatus)->replace('_', ' ')->title() }}
                    </span>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    <a href="{{ route('testing.show', $request) }}" wire:navigate class="inline-flex items-center justify-center gap-1.5 rounded-md bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 transition-colors">
                        <x-icon name="arrow-top-right-on-square" size="sm" :decorative="true" />
                        Buka
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-4 py-16 text-center text-sm text-gray-500 border-2 border-dashed border-gray-200 bg-gray-50/50">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 mb-3">
                        <x-icon name="magnifying-glass" class="h-6 w-6 text-gray-400" />
                    </div>
                    <span class="font-semibold text-gray-900 block">Tidak ada data pengujian</span>
                    Belum ada resi yang cocok dengan kriteria pencarian Anda.
                </td>
            </tr>
        @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6" wire:loading.remove wire:target="q,scope">
        {{ $requests->links() }}
    </div>

    <!-- Slide-Over Drawer for Details -->
    <div x-data="{
            open: false,
            init() {
                this.$watch('$wire.selectedRequestId', (val) => {
                    this.open = val !== null;
                    if (val !== null) {
                        this.$nextTick(() => this.$refs.drawerCloseButton?.focus());
                    }
                });
                if ($wire.selectedRequestId !== null) {
                    this.open = true;
                    this.$nextTick(() => this.$refs.drawerCloseButton?.focus());
                }
            },
            closeDrawer() {
                this.open = false;
                setTimeout(() => { $wire.closeRequest(); }, 300);
            }
        }"
        @keydown.escape.window="closeDrawer()"
        class="relative z-[9999]"
        aria-labelledby="slide-over-title"
        role="dialog"
        aria-modal="true"
        x-show="open"
        x-trap.inert.noscroll="open"
        x-cloak
    >
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity"
            x-show="open"
            x-transition:enter="ease-in-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in-out duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="closeDrawer()"
        ></div>

        <!-- Panel Wrapper -->
        <div class="fixed inset-0 overflow-hidden">
            <div class="absolute inset-0 overflow-hidden">
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10 sm:pl-16">
                    <!-- Slide-Over Panel -->
                    <div class="pointer-events-auto w-screen max-w-2xl transform transition ease-in-out duration-300 sm:duration-400"
                        x-show="open"
                        x-transition:enter-start="translate-x-full"
                        x-transition:enter-end="translate-x-0"
                        x-transition:leave-start="translate-x-0"
                        x-transition:leave-end="translate-x-full"
                    >
                        <div class="flex h-full flex-col bg-gray-50 shadow-2xl overflow-hidden">
                            
                            <!-- Drawer Header (Sticky) -->
                            <div class="bg-white border-b border-gray-200 px-6 py-4 sm:px-8 shrink-0 z-20 shadow-sm relative">
                                
                                <!-- Loading overlay mapping -->
                                <div wire:loading.flex wire:target="selectRequest" class="absolute inset-0 z-30 bg-white/80 backdrop-blur-sm items-center justify-center">
                                    <div class="flex items-center gap-3 px-4 py-2 bg-white rounded-full shadow-md text-primary-600 font-medium">
                                        <x-icon name="loading" size="sm" spin />
                                        <span>Memuat rincian resi...</span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between">
                                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-3 hover:text-primary-700 transition" id="slide-over-title">
                                        <x-icon name="document-duplicate" class="text-primary-600" />
                                        @if($selectedRequest)
                                            <a href="{{ route('testing.show', $selectedRequest) }}" class="underline decoration-transparent hover:decoration-primary-300 underline-offset-4">
                                                {{ $selectedRequest->receipt_number ?? $selectedRequest->request_number }}
                                            </a>
                                        @else
                                            Memuat...
                                        @endif
                                    </h2>
                                    
                                    <div class="ml-3 flex h-7 items-center gap-4">
                                        @if($selectedRequest && $readyForDelivery)
                                            <form action="{{ route('testing.ready-for-delivery', $selectedRequest) }}" method="POST" class="hidden sm:block">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 transition">
                                                    <x-icon name="truck" size="sm" :decorative="true" />
                                                    Kirim ke Penyerahan
                                                </button>
                                            </form>
                                        @endif
                                        <button type="button" x-ref="drawerCloseButton" @click="closeDrawer()" class="rounded-md bg-white text-gray-400 hover:text-gray-500 hover:bg-gray-100 p-1.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 transition">
                                            <span class="sr-only">Tutup panel</span>
                                            <x-icon name="x-mark" class="h-6 w-6" />
                                        </button>
                                    </div>
                                </div>
                                
                                @if($selectedRequest)
                                    @php
                                        $drawerInvestigator = $selectedRequest->investigator;
                                        $drawerUnit = $drawerInvestigator?->jurisdiction ?? $drawerInvestigator?->institution;
                                        $drawerReceivedAt = $selectedRequest->received_at ?? $selectedRequest->created_at;
                                        $drawerStatus = $selectedRequest->status ?? 'submitted';
                                        $drawerStatusLabel = match ($drawerStatus) {
                                            'in_testing' => 'Sedang Diuji',
                                            'submitted' => 'Menunggu Proses',
                                            'ready_for_delivery' => 'Siap Diserahkan',
                                            'completed' => 'Selesai',
                                            default => str($drawerStatus)->replace('_', ' ')->title(),
                                        };
                                    @endphp
                                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-6 text-sm text-gray-600">
                                        <div>
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Penyidik & Unit</span>
                                            <p class="font-medium text-gray-900">{{ $drawerInvestigator?->full_name ?? $drawerInvestigator?->name ?? '-' }}</p>
                                            <p class="text-xs">{{ $drawerUnit ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Tanggal Terima</span>
                                            <p class="font-medium text-gray-900">{{ optional($drawerReceivedAt)->format('d F Y') ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">Status Pengujian</span>
                                            <span class="inline-flex mt-0.5 items-center rounded-md bg-white border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-800 shadow-sm">
                                                {{ $drawerStatusLabel }}
                                            </span>
                                        </div>
                                    </div>

                                     <!-- Mobile actions row -->
                                     @if($readyForDelivery)
                                        <div class="mt-4 block sm:hidden">
                                            <form action="{{ route('testing.ready-for-delivery', $selectedRequest) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="w-full inline-flex justify-center items-center gap-2 rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 transition">
                                                    <x-icon name="truck" size="sm" :decorative="true" />
                                                    Kirim ke Penyerahan
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                @endif
                            </div>

                            <!-- Drawer Body (Scrollable) -->
                            <div class="flex-1 overflow-y-auto w-full relative">
                                @if($detailError)
                                    <div class="m-6 rounded-md border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-700">
                                        <div class="flex items-center gap-3">
                                            <x-icon name="exclamation-triangle" class="text-danger-500" />
                                            {{ $detailError }}
                                        </div>
                                    </div>
                                @endif

                                @if($selectedRequest && $samples)
                                    <div class="px-6 py-6 sm:px-8 space-y-6">
                                        
                                        <!-- Header Table -->
                                        <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                                            <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                                                <x-icon name="document" size="sm" class="text-primary-500" />
                                                Daftar Sampel
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                                    {{ $samplesTotal }} total
                                                </span>
                                            </h3>
                                            <a href="{{ route('testing.show', $selectedRequest) }}" class="text-sm font-semibold text-primary-600 hover:text-primary-800">Buka Workspace Penuh &rarr;</a>
                                        </div>

                                        <!-- The Samples Table -->
                                        @if($samples->isNotEmpty())
                                            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white ring-1 ring-black/5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
                                                <div class="overflow-x-auto">
                                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                                        <thead class="bg-gray-50">
                                                            <tr>
                                                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold text-gray-900 uppercase tracking-wide">Kode</th>
                                                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold text-gray-900 uppercase tracking-wide">Deskripsi Singkat</th>
                                                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold text-gray-900 uppercase tracking-wide">Tahap Aktif</th>
                                                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold text-gray-900 uppercase tracking-wide">Status</th>
                                                                <th scope="col" class="px-4 py-3.5 text-right text-xs font-semibold text-gray-900 uppercase tracking-wide">Aksi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-100 bg-white">
                                                            @foreach($samples as $sample)
                                                                <tr class="hover:bg-primary-50/30 transition-colors group">
                                                                    <td class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900">{{ $sample->sample_code ?? '-' }}</td>
                                                                    <td class="px-4 py-3 text-gray-600">{{ $sample->short_description ?? '—' }}</td>
                                                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                                                        <div class="flex items-center gap-1.5">
                                                                            <span class="w-1.5 h-1.5 rounded-full {{ $sample->current_status_key === 'completed' ? 'bg-green-500' : ($sample->current_status_key === 'in_progress' ? 'bg-yellow-500 animate-pulse' : 'bg-gray-300') }}"></span>
                                                                            {{ $sample->current_stage_label ?? '—' }}
                                                                        </div>
                                                                    </td>
                                                                    <td class="whitespace-nowrap px-4 py-3">
                                                                        @php
                                                                            $statusColor = match ($sample->current_status_key ?? null) {
                                                                                'completed' => 'bg-green-50 text-green-700 ring-green-600/20',
                                                                                'in_progress' => 'bg-yellow-50 text-yellow-800 ring-yellow-600/20',
                                                                                default => 'bg-gray-50 text-gray-600 ring-gray-500/10',
                                                                            };
                                                                        @endphp
                                                                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $statusColor }}">
                                                                            {{ $sample->current_status_label ?? '—' }}
                                                                        </span>
                                                                    </td>
                                                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                                                        @if($sample->current_process)
                                                                            <div class="flex items-center gap-2">
                                                                                <button type="button"
                                                                                    wire:click="$dispatch('open-workstation', { processId: {{ $sample->current_process->id }} })"
                                                                                    class="rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 flex items-center gap-1">
                                                                                    <x-icon name="beaker" class="w-4 h-4 text-gray-500" />
                                                                                    Kerjakan
                                                                                </button>
                                                                            </div>
                                                                        @else
                                                                            <span class="text-xs text-gray-400">&mdash;</span>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                                @if($samplesTotal > 10)
                                                    <div class="border-t border-gray-200 bg-gray-50 px-4 py-3 flex items-center justify-between sm:px-6">
                                                        <p class="text-xs text-gray-600">Terbatas 10 sampel pertama. Buka workspace penuh untuk melihat semua.</p>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center text-sm text-gray-500">
                                                <x-icon name="beaker" class="mx-auto h-8 w-8 text-gray-300 mb-3" />
                                                <p>Belum ada data sampel terdaftar di resi ini.</p>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Workstation Livewire Slide-Over -->
    <div x-data="{
            wsOpen: false,
            init() {
                Livewire.on('workstation-opened', () => {
                    this.wsOpen = true;
                    this.$nextTick(() => this.$refs.workstationPanel?.focus());
                });
                Livewire.on('workstation-closed', () => {
                    this.wsOpen = false;
                });
                Livewire.on('sample-process-updated', () => {
                    $wire.$refresh();
                });
            },
            closeWorkstation() {
                this.wsOpen = false;
            }
        }"
        @keydown.escape.window="if(wsOpen) { closeWorkstation(); }"
        class="relative z-[10000]"
        aria-labelledby="slide-over-workstation"
        role="dialog"
        aria-modal="true"
        x-show="wsOpen"
        x-trap.inert.noscroll="wsOpen"
        x-cloak
    >
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity"
            x-show="wsOpen"
            x-transition:enter="ease-in-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in-out duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="closeWorkstation()"
        ></div>

        <!-- Panel Wrapper -->
        <div class="fixed inset-0 overflow-hidden">
            <div class="absolute inset-0 overflow-hidden">
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10 sm:pl-16">
                    <div class="pointer-events-auto w-full lg:max-w-4xl transform transition ease-in-out duration-300 sm:duration-400"
                        x-show="wsOpen"
                        x-transition:enter-start="translate-x-full"
                        x-transition:enter-end="translate-x-0"
                        x-transition:leave-start="translate-x-0"
                        x-transition:leave-end="translate-x-full"
                    >
                        <div class="flex h-full flex-col bg-white shadow-2xl overflow-hidden">
                            <div x-ref="workstationPanel" tabindex="-1" class="flex h-full flex-col bg-white shadow-2xl overflow-hidden">
                                <livewire:pengujian.process-workstation />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
