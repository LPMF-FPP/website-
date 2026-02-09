<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Dashboard Inventori"
            :breadcrumbs="[['label' => 'Inventori']]"
        >
            <x-slot name="actions">
                <div class="flex flex-wrap gap-2">
                    <x-button variant="outline" size="sm" href="{{ route('inventory.items.index') }}">Master Item</x-button>
                    <x-button variant="outline" size="sm" href="{{ route('inventory.stock-card') }}">Kartu Stok</x-button>
                    <x-button variant="outline" size="sm" href="{{ route('inventory.transaction.stocktake') }}" class="!border-amber-500 !text-amber-700 hover:!bg-amber-50">Stok Opname</x-button>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6" x-data="{ tab: 'low_stock' }">

        <!-- Global Search / Barcode -->
        @include('inventory.partials.global-search')
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="card p-4 flex flex-col justify-between h-full bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="text-xs text-gray-500 font-medium uppercase tracking-wider">Total Item</div>
                <div class="mt-2 text-3xl font-bold text-gray-900">{{ $stats['total_items'] }}</div>
            </div>
            <div class="card p-4 flex flex-col justify-between h-full bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="text-xs text-gray-500 font-medium uppercase tracking-wider">Lot Aktif</div>
                <div class="mt-2 text-3xl font-bold text-gray-900">{{ $stats['total_lots'] }}</div>
            </div>
            <!-- Interactive Card: Low Stock -->
            <div 
                @click="tab = 'low_stock'; $el.scrollIntoView({behavior: 'smooth', block: 'center'})"
                class="card p-4 flex flex-col justify-between h-full bg-white rounded-lg shadow-sm border-l-4 cursor-pointer transition-transform hover:-translate-y-1 hover:shadow-md {{ $stats['low_stock'] > 0 ? 'border-red-500' : 'border-gray-200' }}"
            >
                <div class="text-xs text-gray-500 font-medium uppercase tracking-wider">Stok Rendah</div>
                <div class="mt-2 text-3xl font-bold {{ $stats['low_stock'] > 0 ? 'text-red-600' : 'text-gray-900' }}">
                    {{ $stats['low_stock'] }}
                </div>
            </div>
            <!-- Interactive Card: Expired -->
            <div 
                @click="tab = 'expired'; $el.scrollIntoView({behavior: 'smooth', block: 'center'})"
                class="card p-4 flex flex-col justify-between h-full bg-white rounded-lg shadow-sm border-l-4 cursor-pointer transition-transform hover:-translate-y-1 hover:shadow-md {{ $stats['expired'] > 0 ? 'border-red-500' : 'border-gray-200' }}"
            >
                <div class="text-xs text-gray-500 font-medium uppercase tracking-wider">Lot Kadaluarsa</div>
                <div class="mt-2 text-3xl font-bold {{ $stats['expired'] > 0 ? 'text-red-600' : 'text-gray-900' }}">
                    {{ $stats['expired'] }}
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- LEFT COLUMN (Main Content - 2/3) -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Alerts Widget (Tabs) -->
                @include('inventory.partials.alerts-widget')

                <!-- Disposal Widget -->
                @include('inventory.partials.disposal-widget')

            </div>

            <!-- RIGHT COLUMN (Sidebar - 1/3) -->
            <div class="space-y-6">
                
                <!-- Quick Actions (Moved to Sidebar) -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <h3 class="font-semibold text-gray-900 mb-3">⚡ Aksi Cepat</h3>
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

        @if($lowStockItems->isEmpty() && $nearExpiry30->isEmpty() && $expiredLots->isEmpty())
        <div class="text-center py-12 bg-gray-50 rounded-lg border border-dashed border-gray-300">
            <div class="text-4xl mb-2">🎉</div>
            <h3 class="text-lg font-medium text-gray-900">Semua Stok Sehat</h3>
            <p class="text-gray-500 text-sm">Tidak ada item kritis yang perlu perhatian Anda.</p>
        </div>
        @endif

    </div>
</x-app-layout>