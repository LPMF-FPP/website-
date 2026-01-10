
{{-- Remaining Label Section --}}
                <div class="px-6 py-5 text-sm text-gray-700 space-y-4">
                    <x-page-section title="Label Sisa Sampel">
                        <div x-data="remainingLabelApp()" x-init="init()">
                            
                            {{-- Form --}}
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                <h4 class="font-semibold text-yellow-900 mb-2">Buat Label Sisa Baru</h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Pilih Sampel (Evidence Unit)</label>
                                        <select x-model="form.evidence_unit_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 sm:text-sm">
                                            <option value="">-- Pilih Sampel --</option>
                                            @foreach($request->evidenceUnits as $eu)
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

                            {{-- List --}}
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-2">Daftar Label Sisa</h4>
                                <template x-if="remainingUnits.length === 0">
                                    <p class="text-sm text-gray-500 italic">Belum ada label sisa yang dibuat.</p>
                                </template>
                                
                                <ul class="divide-y divide-gray-200">
                                    <template x-for="unit in remainingUnits" :key="unit.id">
                                        <li class="py-3 flex justify-between items-center">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900" x-text="unit.remaining_code"></p>
                                                <p class="text-xs text-gray-500">
                                                    Qty: <span x-text="unit.qty_remaining"></span> <span x-text="unit.uom"></span>
                                                    | Dibuat: <span x-text="new Date(unit.created_at).toLocaleDateString()"></span>
                                                </p>
                                            </div>
                                            <div>
                                                <a :href="`/labels/remaining/${unit.id}/single`" target="_blank" class="text-yellow-600 hover:text-yellow-900 text-xs font-medium mr-2">Cetak (Single)</a>
                                                <button type="button" @click="deleteLabel(unit.id)" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                                            </div>
                                        </li>
                                    </template>
                                </ul>

                                <div class="mt-4 pt-4 border-t border-gray-200" x-show="remainingUnits.length > 0">
                                    <a href="{{ route('labels.remaining.sheet', $request->id) }}" target="_blank" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                        Cetak Semua (Sheet A4)
                                    </a>
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
                                        @foreach($request->evidenceUnits as $eu)
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
                                            
                                            if(data.id) {
                                                // Add to list
                                                this.remainingUnits.unshift(data);
                                                // Reset form partially
                                                this.form.qty_remaining = '';
                                                this.form.uom = '';
                                                alert('Label sisa berhasil dibuat: ' + data.remaining_code);
                                            } else {
                                                alert('Gagal: ' + (data.message || 'Unknown error'));
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
                                                    
                                                    if(data.success) {
                                                        // Remove from list
                                                        this.remainingUnits = this.remainingUnits.filter(u => u.id !== id);
                                                        alert('Label berhasil dihapus.');
                                                    } else {
                                                        alert('Gagal menghapus: ' + data.message);
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
