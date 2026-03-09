@php
    $labelRequest = $testRequestModel ?? $request ?? null;
@endphp

@if(! $labelRequest)
    <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
        Data permintaan tidak tersedia untuk menampilkan label sisa.
    </div>
@else
                <div class="px-6 py-5 text-sm text-gray-700 space-y-4">
                    <x-page-section title="Label Sisa Sampel">
                        <div x-data="remainingLabelApp()" x-init="init()">
                            
                            {{-- Auto-generated info banner --}}
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                                <div class="flex items-start gap-3">
                                    <svg class="h-5 w-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div>
                                        <h4 class="font-semibold text-green-900">Label Sisa Otomatis</h4>
                                        <p class="mt-1 text-sm text-green-700">
                                            Label sisa sampel dibuat otomatis berdasarkan data "Sisa Sampel" dari setiap sampel yang memiliki sisa > 0.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- List --}}
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-2">Daftar Label Sisa</h4>
                                <template x-if="remainingUnits.length === 0">
                                    <p class="text-sm text-gray-500 italic">Belum ada label sisa yang dibuat. Sampel mungkin belum memiliki data sisa atau sisa = 0.</p>
                                </template>
                                
                                <div class="space-y-3">
                                    <template x-for="unit in remainingUnits" :key="unit.id">
                                        <div class="group relative bg-white border border-gray-200 rounded-xl p-4 hover:border-primary-300 hover:shadow-md transition-all duration-200">
                                            <div class="flex items-center justify-between gap-4">
                                                {{-- Left: Label Info --}}
                                                <div class="flex items-center gap-3 min-w-0">
                                                    <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-amber-100 to-yellow-200 rounded-lg flex items-center justify-center">
                                                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                                        </svg>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-semibold text-gray-900 truncate" x-text="unit.remaining_code"></p>
                                                        <p class="text-xs text-gray-500 flex items-center gap-2 mt-0.5">
                                                            <span class="inline-flex items-center gap-1">
                                                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                                                </svg>
                                                                <span x-text="unit.qty_remaining"></span> <span x-text="unit.uom"></span>
                                                            </span>
                                                            <span class="text-gray-300">|</span>
                                                            <span class="inline-flex items-center gap-1">
                                                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                                </svg>
                                                                <span x-text="new Date(unit.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })"></span>
                                                            </span>
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                {{-- Right: Action Buttons --}}
                                                <div class="flex items-center gap-2 flex-shrink-0">
                                                    <a :href="`/labels/remaining/${unit.id}/single`" 
                                                       target="_blank" 
                                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 hover:border-amber-300 transition-colors duration-150">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                                        </svg>
                                                        Cetak
                                                    </a>
                                                    <button type="button" 
                                                            @click="deleteLabel(unit.id)" 
                                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-gray-200 rounded-lg hover:bg-red-50 hover:border-red-200 hover:text-red-700 transition-colors duration-150">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                        Hapus
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                {{-- Print All Button --}}
                                <div class="mt-5 pt-5 border-t border-gray-100" x-show="remainingUnits.length > 0">
                                    <a href="{{ route('labels.remaining.sheet', $labelRequest->id) }}" 
                                       target="_blank" 
                                       class="group inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-teal-600 to-teal-700 rounded-xl shadow-sm hover:from-teal-700 hover:to-teal-800 hover:shadow-md transition-all duration-200">
                                        <svg class="w-5 h-5 group-hover:scale-110 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                        </svg>
                                        Cetak Semua Label
                                        <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-teal-700 bg-white rounded-full" x-text="remainingUnits.length"></span>
                                    </a>
                                    <p class="mt-2 text-xs text-gray-500">Format: Sheet A4 dengan multiple label per halaman</p>
                                </div>
                            </div>

                            {{-- Collapsible Manual Form (hidden by default) --}}
                            <div x-data="{ showManualForm: false }" class="mt-4 pt-4 border-t border-gray-200">
                                <button 
                                    @click="showManualForm = !showManualForm" 
                                    class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                                    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-90': showManualForm }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    Buat Label Manual (Opsional)
                                </button>
                                
                                <div x-show="showManualForm" x-collapse class="mt-3">
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                        <h4 class="font-semibold text-yellow-900 mb-2">Buat Label Sisa Baru</h4>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">Pilih Sampel (Evidence Unit)</label>
                                                <select x-model="form.evidence_unit_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 sm:text-sm">
                                                    <option value="">-- Pilih Sampel --</option>
                                                    @foreach($labelRequest->evidenceUnits as $eu)
                                                        <option value="{{ $eu->id }}">
                                                            {{ $eu->sample_code }} ({{ Str::limit($eu->sample_desc ?? 'No description', 30) }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Qty Sisa</label>
                                                    <input type="number" step="0.01" x-model="form.qty_remaining" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 sm:text-sm" placeholder="Contoh: 0.5">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700 mb-1">Satuan</label>
                                                    <input type="text" x-model="form.uom" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 sm:text-sm" placeholder="g, ml, pcs">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="flex justify-end">
                                            <button 
                                                @click="createLabel" 
                                                :disabled="loading || !isValid" 
                                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 disabled:opacity-50">
                                                <span x-show="loading" class="mr-2">
                                                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </span>
                                                Buat Label Sisa
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                            function remainingLabelApp() {
                                return {
                                    form: {
                                        evidence_unit_id: '',
                                        qty_remaining: '',
                                        uom: '',
                                        seal_status: 'disegel', // default
                                        handover_doc_no: ''
                                    },
                                    remainingUnits: [],
                                    loading: false,
                                    
                                    get isValid() {
                                        return this.form.evidence_unit_id && this.form.qty_remaining && this.form.uom;
                                    },

                                    init() {
                                        // Helper to flatten units from backend
                                        const initialUnits = [];
                                        @foreach($labelRequest->evidenceUnits as $eu)
                                            @foreach($eu->remainingUnits as $ru)
                                                initialUnits.push(@json($ru));
                                            @endforeach
                                        @endforeach
                                        
                                        // Sort by ID desc
                                        this.remainingUnits = initialUnits.sort((a,b) => b.id - a.id);
                                    },

                                    async createLabel() {
                                        if(!this.isValid) return;
                                        
                                        this.loading = true;
                                        
                                        try {
                                            const res = await fetch('/labels/remaining-units', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                    'Accept': 'application/json'
                                                },
                                                body: JSON.stringify(this.form)
                                            });
                                            
                                            const data = await res.json();
                                            const created = data?.data ?? data;

                                            if (res.ok && data?.success && created?.id) {
                                                // Add to list
                                                this.remainingUnits.unshift(created);
                                                // Reset form partially
                                                this.form.qty_remaining = '';
                                                this.form.uom = '';
                                                alert('Label sisa berhasil dibuat: ' + (created.remaining_code || '-'));
                                            } else {
                                                const message = data?.message || data?.errors?.evidence_unit_id?.[0] || 'Unknown error';
                                                alert('Gagal: ' + message);
                                            }
                                        } catch(e) {
                                            console.error(e);
                                            alert('Terjadi kesalahan jaringan');
                                        } finally {
                                            this.loading = false;
                                        }
                                    },

                                    async deleteLabel(id) {
                                        showConfirmDialog({
                                            type: 'danger',
                                            title: 'Hapus Label',
                                            message: 'Anda yakin ingin menghapus label ini?',
                                            confirmButtonText: 'Ya, Hapus',
                                            onConfirm: async () => {
                                                try {
                                                    const res = await fetch(`/labels/remaining-units/${id}`, {
                                                        method: 'DELETE',
                                                        headers: {
                                                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                            'Accept': 'application/json'
                                                        }
                                                    });
                                                    
                                                    const data = await res.json();
                                                    
                                                    if (res.ok && data?.success) {
                                                        // Remove from list
                                                        this.remainingUnits = this.remainingUnits.filter(u => u.id !== id);
                                                        alert('Label berhasil dihapus.');
                                                    } else {
                                                        alert('Gagal menghapus: ' + (data?.message || 'Unknown error'));
                                                    }
                                                } catch(e) {
                                                    console.error(e);
                                                    alert('Terjadi kesalahan jaringan');
                                                }
                                            }
                                        });
                                    }
                                }
                            }
                        </script>
                    </x-page-section>
                </div>
@endif