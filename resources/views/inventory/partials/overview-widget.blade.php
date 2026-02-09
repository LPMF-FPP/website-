<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-gray-900">Ringkasan Inventori</h3>
        <select class="text-sm border-gray-300 rounded-md shadow-sm focus:border-primary-500 focus:ring-primary-500">
            <option>Bulan Ini</option>
            <option>Bulan Lalu</option>
            <option>Tahun Ini</option>
        </select>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Value Summary -->
        <div class="p-4 bg-gray-50 rounded-lg border border-gray-100">
            <div class="text-sm text-gray-500 font-medium mb-1">Total Nilai Aset</div>
            <div class="text-2xl font-bold text-gray-900">Rp 1.2M</div>
            <div class="text-xs text-emerald-600 flex items-center mt-2">
                <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                +2.5% dari bulan lalu
            </div>
        </div>

        <!-- Turnover -->
        <div class="p-4 bg-gray-50 rounded-lg border border-gray-100">
            <div class="text-sm text-gray-500 font-medium mb-1">Perputaran Stok</div>
            <div class="text-2xl font-bold text-gray-900">4.2x</div>
            <div class="text-xs text-gray-500 mt-2">Rata-rata per tahun</div>
        </div>

        <!-- Accuracy -->
        <div class="p-4 bg-gray-50 rounded-lg border border-gray-100">
            <div class="text-sm text-gray-500 font-medium mb-1">Akurasi Stok</div>
            <div class="text-2xl font-bold text-gray-900">98.5%</div>
            <div class="text-xs text-emerald-600 flex items-center mt-2">
                <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Audit Terakhir: Hari ini
            </div>
        </div>
    </div>

    <!-- Chart Placeholder -->
    <div class="mt-6 h-64 bg-gray-50 rounded border border-dashed border-gray-200 flex flex-col items-center justify-center text-gray-400">
        <svg class="w-12 h-12 mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
        </svg>
        <span class="text-sm font-medium">Grafik Pergerakan Stok</span>
        <span class="text-xs text-gray-400 mt-1">(Data belum tersedia)</span>
    </div>
</div>
