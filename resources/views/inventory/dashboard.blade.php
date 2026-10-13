<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Dashboard Inventori"
            class="inventory-page-header"
            :breadcrumbs="[['label' => 'Inventori']]"
        />
    </x-slot>

    <div class="space-y-6">

        <!-- Global Search / Barcode -->
        @if(auth()->user()?->hasPermission('inventori.view'))
            @include('inventory.partials.global-search')
        @endif
        
        <!-- Stats Cards -->
        <div x-data class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="card p-4 flex flex-col justify-between h-full bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-slate-800 dark:border-slate-700">
                <div class="text-xs text-gray-700 font-medium uppercase tracking-wider dark:text-slate-300">Total Item</div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-slate-100">{{ $stats['total_items'] }}</div>
            </div>
            <div class="card p-4 flex flex-col justify-between h-full bg-white rounded-lg shadow-sm border border-gray-200 dark:bg-slate-800 dark:border-slate-700">
                <div class="text-xs text-gray-700 font-medium uppercase tracking-wider dark:text-slate-300">Lot Aktif</div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-slate-100">{{ $stats['total_lots'] }}</div>
            </div>
            <!-- Interactive Card: Low Stock -->
            <button type="button" aria-controls="inventory-alerts" aria-label="Lihat stok rendah, {{ $stats['low_stock'] }} item"
                @click="$dispatch('inventory-select-alert-tab', 'low_stock'); document.getElementById('inventory-alerts')?.scrollIntoView({ block: 'center' })"
                class="card p-4 flex flex-col justify-between h-full bg-white rounded-lg shadow-sm border border-l-4 border-gray-200 text-left transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:bg-slate-800 dark:border-slate-700 dark:hover:bg-slate-700 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900 {{ $stats['low_stock'] > 0 ? 'border-l-red-700 dark:border-l-red-300' : '' }}"
            >
                <div class="text-xs text-gray-700 font-medium uppercase tracking-wider dark:text-slate-300">Stok Rendah</div>
                <div class="mt-2 text-3xl font-bold {{ $stats['low_stock'] > 0 ? 'text-red-700 dark:text-red-300' : 'text-gray-900 dark:text-slate-100' }}">
                    {{ $stats['low_stock'] }}
                </div>
            </button>
            <!-- Interactive Card: Expired -->
            <button type="button" aria-controls="inventory-alerts" aria-label="Lihat lot kedaluwarsa, {{ $stats['expired'] }} lot"
                @click="$dispatch('inventory-select-alert-tab', 'expired'); document.getElementById('inventory-alerts')?.scrollIntoView({ block: 'center' })"
                class="card p-4 flex flex-col justify-between h-full bg-white rounded-lg shadow-sm border border-l-4 border-gray-200 text-left transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:bg-slate-800 dark:border-slate-700 dark:hover:bg-slate-700 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900 {{ $stats['expired'] > 0 ? 'border-l-red-700 dark:border-l-red-300' : '' }}"
            >
                <div class="text-xs text-gray-700 font-medium uppercase tracking-wider dark:text-slate-300">Lot Kedaluwarsa</div>
                <div class="mt-2 text-3xl font-bold {{ $stats['expired'] > 0 ? 'text-red-700 dark:text-red-300' : 'text-gray-900 dark:text-slate-100' }}">
                    {{ $stats['expired'] }}
                </div>
            </button>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- LEFT COLUMN (Main Content - 2/3) -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Alerts Widget (Tabs) -->
                @include('inventory.partials.alerts-widget')

                <!-- Disposal Widget -->
                @if(auth()->user()?->hasPermission('sample-disposal.view'))
                    @include('inventory.partials.disposal-widget')
                @endif

            </div>

            <!-- RIGHT COLUMN (Sidebar - 1/3) -->
            <div class="space-y-6">
                
                <!-- Quick Actions (Moved to Sidebar) -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 dark:bg-slate-800 dark:border-slate-700">
                    <h3 class="font-semibold text-gray-900 mb-3 dark:text-slate-100">Aksi cepat</h3>
                    @include('inventory.partials.quick-actions')
                </div>

                <!-- Top Movers -->
                @include('inventory.partials.top-movers')

            </div>
        </div>

        <!-- Bottom Section: Overview -->
        <div class="mt-6">
            @include('inventory.partials.overview-widget')
        </div>

    </div>
</x-app-layout>
