{{-- Partial: Penomoran Otomatis --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Penomoran Otomatis</h2>
                <p class="text-sm text-gray-500 mt-1">Konfigurasi pola penomoran otomatis untuk setiap jenis dokumen.</p>
            </div>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <p class="text-xs font-medium text-gray-600 mb-2">Penomoran Saat Ini</p>
                    <div class="space-y-1 mt-3">
                    <div class="flex justify-between gap-4">
                        <span class="text-xs text-gray-600">Sample:</span>
                        <span class="text-xs font-mono font-semibold text-gray-900" x-text="client.state.currentNumbering.sample_code || 'SMP-2025-0128'">SMP-2025-0128</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-xs text-gray-600">BA Penerimaan:</span>
                        <span class="text-xs font-mono font-semibold text-gray-900" x-text="client.state.currentNumbering.ba || 'BA-2025-0042'">BA-2025-0042</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-xs text-gray-600">LHU:</span>
                        <span class="text-xs font-mono font-semibold text-gray-900" x-text="client.state.currentNumbering.lhu || 'LHU-2025-0099'">LHU-2025-0099</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-xs text-gray-600">BA Penyerahan:</span>
                        <span class="text-xs font-mono font-semibold text-gray-900" x-text="client.state.currentNumbering.ba_penyerahan || 'BAP-2025-0050'">BAP-2025-0050</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-xs text-gray-600">Resi Tracking:</span>
                        <span class="text-xs font-mono font-semibold text-gray-900" x-text="client.state.currentNumbering.tracking || 'RESI-20251219/00123'">RESI-20251219/00123</span>
                    </div>
                </div>
                <button 
                    type="button" 
                    class="mt-3 w-full px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    :disabled="client.state.currentNumberingLoading" 
                    @click="client.fetchCurrentNumbering()">
                    <span x-show="!client.state.currentNumberingLoading">Perbarui</span>
                    <span x-show="client.state.currentNumberingLoading">Memuat...</span>
                </button>
            </div>
        </div>
    </div>

    <div class="p-6">
        {{-- All Number Types Configuration --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <template x-for="scope in ['sample_code', 'ba', 'lhu', 'ba_penyerahan', 'tracking']" :key="scope">
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3" x-text="labels[scope] || scope"></h3>
                    
                    {{-- Pattern Input --}}
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Pola</label>
                        <input 
                            type="text"
                            class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm font-mono" 
                            :class="client.state.scopeErrors[scope]?.pattern ? 'border-red-300 bg-red-50' : 'border-gray-300'"
                            x-model="client.state.form.numbering[scope].pattern"
                            :placeholder="getDefaultPattern(scope)">
                        <p x-show="client.state.scopeErrors[scope]?.pattern" class="text-xs text-red-600 mt-1" x-text="client.state.scopeErrors[scope]?.pattern"></p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        {{-- Reset Period --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Periode Reset</label>
                            <select 
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                                :class="client.state.scopeErrors[scope]?.reset ? 'border-red-300 bg-red-50' : 'border-gray-300'"
                                x-model="client.state.form.numbering[scope].reset">
                                <option value="never">Tidak Pernah</option>
                                <option value="yearly">Tahunan</option>
                                <option value="monthly">Bulanan</option>
                                <option value="daily">Harian</option>
                            </select>
                            <p x-show="client.state.scopeErrors[scope]?.reset" class="text-xs text-red-600 mt-1" x-text="client.state.scopeErrors[scope]?.reset"></p>
  </div>

                        {{-- Start From --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Mulai Dari</label>
                            <input 
                                type="number" 
                                min="1"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                                :class="client.state.scopeErrors[scope]?.start_from ? 'border-red-300 bg-red-50' : 'border-gray-300'"
                                x-model.number="client.state.form.numbering[scope].start_from">
                            <p x-show="client.state.scopeErrors[scope]?.start_from" class="text-xs text-red-600 mt-1" x-text="client.state.scopeErrors[scope]?.start_from"></p>
                        </div>
                    </div>
                    
                    {{-- Preview --}}
                    <div class="mt-3 bg-gray-50 rounded-lg p-2 border border-gray-200">
                        <p class="text-xs text-gray-600 mb-1">Pratinjau:</p>
                        <p class="text-sm font-mono font-semibold" 
                           :class="client.state.numberingPreview?.[scope] === null ? 'text-red-600' : 'text-gray-900'" 
                           x-text="getPreviewText(scope)"></p>
                    </div>
                    
                    {{-- Action Buttons --}}
                    <div class="mt-3 flex gap-2">
                        <button 
                            type="button"
                            class="flex-1 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="client.state.previewLoading?.[scope] || false"
                            @click.prevent="testPreview(scope)">
                            <span x-show="!client.state.previewLoading?.[scope]">Uji Pratinjau</span>
                            <span x-show="client.state.previewLoading?.[scope]">Menguji...</span>
                        </button>
                        
                        <button 
                            type="button"
                            class="flex-1 px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="client.state.scopeLoading[scope]"
                            @click.prevent="client.saveNumberingScope(scope)">
                            <span x-show="!client.state.scopeLoading[scope]">Simpan</span>
                            <span x-show="client.state.scopeLoading[scope]">Menyimpan...</span>
                        </button>
                    </div>
                    
                    {{-- Status Message --}}
                    <div 
                        x-show="client.state.scopeStatus[scope]?.message" 
                        x-transition
                        role="status"
                        class="mt-2 p-2 rounded-lg text-xs"
                        :class="client.state.scopeStatus[scope]?.intentClass?.includes('red') ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200'">
                        <p :class="client.state.scopeStatus[scope]?.intentClass || 'text-gray-800'" x-text="client.state.scopeStatus[scope]?.message"></p>
                    </div>
                </div>
            </template>
        </div>

        {{-- Info Text --}}
        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-xs text-blue-800">
                <strong>Tip:</strong> Gunakan tombol "Simpan" pada setiap kartu untuk menyimpan konfigurasi per jenis dokumen. 
                Ini memungkinkan penyimpanan parsial tanpa harus mengisi semua field sekaligus.
            </p>
        </div>
    </div>
</div>

{{-- Collapsible Repair Section --}}
<div class="mt-6" x-data="{ repairExpanded: false }">
    <button
        @click="repairExpanded = !repairExpanded"
        type="button"
        class="w-full flex items-center justify-between px-6 py-4 bg-white rounded-lg shadow-sm border border-gray-200 hover:bg-gray-50 transition-colors"
    >
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <div class="text-left">
                <span class="text-sm font-semibold text-gray-900">Perbaikan & Sinkronisasi Nomor</span>
                <p class="text-xs text-gray-500">Alat lanjutan untuk mendeteksi dan memperbaiki masalah penomoran</p>
            </div>
        </div>
        <svg
            class="w-5 h-5 text-gray-400 transition-transform"
            :class="repairExpanded ? 'rotate-180' : ''"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>
    <div x-show="repairExpanded" x-collapse x-cloak>
        @include('settings.partials.numbering-repair')
    </div>
</div>
