<nav x-data aria-label="Aksi inventori" class="grid grid-cols-2 gap-3">
    @if(auth()->user()?->hasPermission('inventori.receipt.create'))
        <a href="{{ route('inventory.transaction.receipt') }}" class="group flex min-h-24 flex-col items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white p-4 text-center text-gray-900 transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
            <svg aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M12 3v13.5M12 16.5l-4.5-4.5M12 16.5l4.5-4.5" /></svg>
            <span class="text-sm font-semibold">Terima stok</span>
            <span class="text-xs text-gray-600 dark:text-slate-300">Pilih item, lot, dan lokasi</span>
        </a>
    @endif

    @if(auth()->user()?->hasPermission('inventori.issue.create'))
        <a href="{{ route('inventory.transaction.issue') }}" class="group flex min-h-24 flex-col items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white p-4 text-center text-gray-900 transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
            <svg aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
            <span class="text-sm font-semibold">Pengeluaran Stok</span>
            <span class="text-xs text-gray-600 dark:text-slate-300">Tinjau saldo tersedia</span>
        </a>
    @endif

    @if(auth()->user()?->hasPermission('inventori.transfer.create'))
        <a href="{{ route('inventory.transaction.transfer') }}" class="group flex min-h-24 flex-col items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white p-4 text-center text-gray-900 transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
            <svg aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
            <span class="text-sm font-semibold">Transfer Stok</span>
            <span class="text-xs text-gray-600 dark:text-slate-300">Pilih lokasi asal dan tujuan</span>
        </a>
    @endif

    @if(auth()->user()?->hasPermission('inventori.material-disposal.create'))
        <a href="{{ route('inventory.transaction.dispose') }}" class="group flex min-h-24 flex-col items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white p-4 text-center text-gray-900 transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
            <svg aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" /></svg>
            <span class="text-sm font-semibold">Buang Stok</span>
            <span class="text-xs text-gray-700 dark:text-slate-300">Catat pembuangan material</span>
        </a>
    @endif

    @if(auth()->user()?->hasPermission('inventori.view'))
        <button type="button" @click="$dispatch('open-modal-fast-moving', { trigger: $el })" aria-haspopup="dialog" class="group flex min-h-24 flex-col items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white p-4 text-center text-gray-900 transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
            <svg aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5v-6m5 6v-11m5 11v-9m5 9v-14" /></svg>
            <span class="text-sm font-semibold">Pengeluaran Terbanyak</span>
            <span class="text-xs text-gray-700 dark:text-slate-300">Lihat 30 hari terakhir</span>
        </button>
    @endif

    @if(auth()->user()?->hasPermission('sample-disposal.view'))
        <a href="{{ route('inventory.disposal.index') }}" class="group flex min-h-24 flex-col items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white p-4 text-center text-gray-900 transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
            <svg aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" /></svg>
            <span class="text-sm font-semibold">Pemusnahan Sampel</span>
            <span class="text-xs text-gray-700 dark:text-slate-300">Tinjau sampel yang memenuhi syarat</span>
        </a>
    @endif
</nav>

@include('inventory.partials.fast-moving-modal')
