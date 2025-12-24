
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Penomoran Otomatis</h2>
                <p class="text-sm text-gray-500 mt-1">PUT /api/settings/numbering  •  POST /api/settings/numbering/preview</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <p class="text-xs font-medium text-gray-600 mb-2">Penomoran Saat Ini</p>
                <p class="text-sm text-gray-500 mb-1">GET /api/settings/numbering/current</p>
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
                    <span x-show="!client.state.currentNumberingLoading">Refresh</span>
                    <span x-show="client.state.currentNumberingLoading">Loading...</span>
                </button>
            </div>
        </div>
    </div>

    <div class="p-6">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <template x-for="scope in ['sample_code', 'ba', 'lhu', 'ba_penyerahan', 'tracking']" :key="scope">
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3" x-text="labels[scope] || scope"></h3>
                    
                    
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Pattern</label>
                        <input 
                            type="text"
                            class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm font-mono" 
                            :class="client.state.scopeErrors[scope]?.pattern ? 'border-red-300 bg-red-50' : 'border-gray-300'"
                            x-model="client.state.form.numbering[scope].pattern"
                            :placeholder="getDefaultPattern(scope)">
                        <p x-show="client.state.scopeErrors[scope]?.pattern" class="text-xs text-red-600 mt-1" x-text="client.state.scopeErrors[scope]?.pattern"></p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Reset Period</label>
                            <select 
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                                :class="client.state.scopeErrors[scope]?.reset ? 'border-red-300 bg-red-50' : 'border-gray-300'"
                                x-model="client.state.form.numbering[scope].reset">
                                <option value="never">Never</option>
                                <option value="yearly">Yearly</option>
                                <option value="monthly">Monthly</option>
                                <option value="daily">Daily</option>
                            </select>
                            <p x-show="client.state.scopeErrors[scope]?.reset" class="text-xs text-red-600 mt-1" x-text="client.state.scopeErrors[scope]?.reset"></p>
                        </div>
                        
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Start From</label>
                            <input 
                                type="number" 
                                min="1"
                                class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
                                :class="client.state.scopeErrors[scope]?.start_from ? 'border-red-300 bg-red-50' : 'border-gray-300'"
                                x-model.number="client.state.form.numbering[scope].start_from">
                            <p x-show="client.state.scopeErrors[scope]?.start_from" class="text-xs text-red-600 mt-1" x-text="client.state.scopeErrors[scope]?.start_from"></p>
                        </div>
                    </div>
                    
                    
                    <div class="mt-3 bg-gray-50 rounded-lg p-2 border border-gray-200">
                        <p class="text-xs text-gray-600 mb-1">Preview:</p>
                        <p class="text-sm font-mono font-semibold" 
                           :class="client.state.numberingPreview?.[scope] === null ? 'text-red-600' : 'text-gray-900'" 
                           x-text="getPreviewText(scope)"></p>
                    </div>
                    
                    
                    <div class="mt-3 flex gap-2">
                        <button 
                            type="button"
                            class="flex-1 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="client.state.previewLoading?.[scope] || false"
                            @click.prevent="testPreview(scope)">
                            <span x-show="!client.state.previewLoading?.[scope]">Test Preview</span>
                            <span x-show="client.state.previewLoading?.[scope]">Testing…</span>
                        </button>
                        
                        <button 
                            type="button"
                            class="flex-1 px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="client.state.scopeLoading[scope]"
                            @click.prevent="client.saveNumberingScope(scope)">
                            <span x-show="!client.state.scopeLoading[scope]">Simpan</span>
                            <span x-show="client.state.scopeLoading[scope]">Saving…</span>
                        </button>
                    </div>
                    
                    
                    <div 
                        x-show="client.state.scopeStatus[scope]?.message" 
                        x-transition
                        class="mt-2 p-2 rounded-lg text-xs"
                        :class="client.state.scopeStatus[scope]?.intentClass?.includes('red') ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200'">
                        <p :class="client.state.scopeStatus[scope]?.intentClass || 'text-gray-800'" x-text="client.state.scopeStatus[scope]?.message"></p>
                    </div>
                </div>
            </template>
        </div>

        
        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-xs text-blue-800">
                <strong>Tip:</strong> Gunakan tombol "Simpan" pada setiap kartu untuk menyimpan konfigurasi per jenis dokumen. 
                Ini memungkinkan penyimpanan parsial tanpa harus mengisi semua field sekaligus.
            </p>
        </div>
    </div>
</div>
<?php /**PATH /home/lpmf-dev/website-/resources/views/settings/partials/numbering.blade.php ENDPATH**/ ?>