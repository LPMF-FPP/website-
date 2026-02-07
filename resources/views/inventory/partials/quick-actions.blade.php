<div x-data="{ activeTab: 'issue' }" class="bg-white rounded-lg shadow-sm p-6 mb-6">
    <div class="border-b border-gray-200 mb-4">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button 
                @click="activeTab = 'issue'"
                :class="{ 'border-primary-500 text-primary-600': activeTab === 'issue', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'issue' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
            >
                📤 Pengeluaran Cepat
            </button>
            <button 
                @click="activeTab = 'receipt'"
                :class="{ 'border-primary-500 text-primary-600': activeTab === 'receipt', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'receipt' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
            >
                📥 Penerimaan Cepat
            </button>
             <button 
                @click="activeTab = 'transfer'"
                :class="{ 'border-primary-500 text-primary-600': activeTab === 'transfer', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'transfer' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
            >
                🔄 Transfer Cepat
            </button>
        </nav>
    </div>

    <!-- Quick Issue Form -->
    <div x-show="activeTab === 'issue'" class="space-y-4">
        <p class="text-gray-500 text-sm">Catat pengeluaran barang dengan cepat.</p>
        <div class="flex gap-4">
            <div class="flex-1">
                 <x-text-input type="text" placeholder="Cari Item / Scan Barcode..." class="w-full" />
            </div>
            <x-primary-button>Proses</x-primary-button>
        </div>
    </div>
    
    <!-- Quick Receipt Form -->
    <div x-show="activeTab === 'receipt'" class="space-y-4" style="display: none;">
         <p class="text-gray-500 text-sm">Input penerimaan barang baru.</p>
         <div class="flex gap-4">
            <div class="flex-1">
                 <x-text-input type="text" placeholder="Cari Item..." class="w-full" />
            </div>
            <x-primary-button>Terima</x-primary-button>
        </div>
    </div>

    <!-- Quick Transfer Form -->
    <div x-show="activeTab === 'transfer'" class="space-y-4" style="display: none;">
         <p class="text-gray-500 text-sm">Pindahkan barang antar lokasi.</p>
         <div class="flex gap-4">
            <div class="flex-1">
                 <x-text-input type="text" placeholder="Cari Item..." class="w-full" />
            </div>
            <x-primary-button>Transfer</x-primary-button>
        </div>
    </div>
</div>
