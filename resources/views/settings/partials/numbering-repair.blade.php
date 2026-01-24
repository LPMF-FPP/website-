{{-- Partial: Perbaikan & Sinkronisasi Nomor --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6" x-data="numberingRepair()">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Perbaikan & Sinkronisasi Nomor</h2>
        <p class="text-sm text-gray-500 mt-1">Deteksi dan perbaiki masalah penomoran dokumen</p>
    </div>

    {{-- Panduan Singkat --}}
    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 m-6 mb-0">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Panduan Penggunaan</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Pilih Scope:</strong> Pilih jenis dokumen yang ingin diperiksa (misal: BA Penyerahan).</li>
                        <li><strong>Scan Masalah:</strong> Sistem akan mencari nomor ganda atau nomor yang terlewat (gap).</li>
                        <li><strong>Sync Counter:</strong> Jika counter database tidak sesuai dengan dokumen fisik terakhir, gunakan "Sync Tertinggi".</li>
                        <li><strong>Cari Dokumen:</strong> Gunakan kolom pencarian untuk menemukan dokumen spesifik dan mengedit nomornya jika perlu.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="p-6">
        {{-- Scope Selector --}}
        <div class="flex items-center gap-4 mb-6">
                <div class="flex-1">
                    <label for="scope-select" class="block text-sm font-medium text-gray-700 mb-1">Pilih Scope</label>
                    <select 
                        id="scope-select"
                        x-model="selectedScope" 
                        @change="onScopeChange()"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                    <option value="">-- Pilih Scope --</option>
                    <template x-for="(label, key) in scopeLabels" :key="key">
                        <option :value="key" x-text="label"></option>
                    </template>
                </select>
            </div>
            <div class="pt-6">
                <button 
                    type="button"
                    @click="scanScope()"
                    :disabled="!selectedScope || loading"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!loading">Scan Masalah</span>
                    <span x-show="loading">Scanning...</span>
                </button>
            </div>
        </div>

        {{-- Search Document Section --}}
        <template x-if="selectedScope">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200 mb-6">
                <h3 class="text-sm font-semibold text-blue-900 mb-3">Cari & Edit <span x-text="getEntityLabel('number')"></span></h3>
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <label for="search-query-input" class="block text-xs text-blue-700 mb-1">Cari berdasarkan nomor</label>
                        <input 
                            type="text"
                            id="search-query-input"
                            x-model="searchQuery"
                            @keyup.enter="searchDocuments()"
                            :placeholder="'Ketik ' + getEntityLabel('number').toLowerCase() + '...'"
                            class="w-full px-3 py-2 border border-blue-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm bg-white">
                        <p class="mt-1 text-xs text-blue-600">Tips: Pencarian mendukung format garis miring (/) atau strip (-).</p>
                    </div>
                    <button 
                        type="button"
                        @click="searchDocuments()"
                        :disabled="!searchQuery || searching"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                        <span x-show="!searching">Cari</span>
                        <span x-show="searching">Mencari...</span>
                    </button>
                </div>

                {{-- Search Results --}}
                <template x-if="searchResults.length > 0">
                    <div class="mt-4">
                        <p class="text-xs text-blue-700 mb-2">Ditemukan <span x-text="searchResults.length"></span> dokumen:</p>
                        <div class="bg-white rounded-lg border border-blue-200 divide-y divide-blue-100 max-h-64 overflow-y-auto">
                            <template x-for="doc in searchResults" :key="doc.entity_id">
                                <div class="flex items-center justify-between p-3 hover:bg-blue-50">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-mono font-medium text-gray-900 truncate" x-text="doc.current_number"></p>
                                        <p class="text-xs text-gray-500 truncate" x-text="doc.entity_name + ' - ' + doc.created_at"></p>
                                    </div>
                                    <button 
                                        type="button"
                                        @click="openEditModalFromSearch(doc)"
                                        class="ml-3 px-3 py-1.5 text-xs font-medium text-white bg-amber-500 rounded hover:bg-amber-600">
                                        Edit Nomor
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- No Results --}}
                <template x-if="searchPerformed && searchResults.length === 0">
                    <div class="mt-4 text-sm text-blue-700">
                        Tidak ditemukan dokumen dengan nomor tersebut.
                    </div>
                </template>
            </div>
        </template>

        {{-- Counter Status --}}
        <template x-if="counterStatus">
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 mb-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Status Counter</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div>
                        <p class="text-xs text-gray-500">Scope</p>
                        <p class="text-sm font-medium" x-text="scopeLabels[counterStatus.scope]"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Periode (Bucket)</p>
                        <p class="text-sm font-mono" x-text="counterStatus.bucket"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Posisi Counter (DB)</p>
                        <p class="text-sm font-bold text-blue-600" x-text="counterStatus.current_counter"></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Total <span x-text="getEntityLabel('singular')"></span></p>
                        <p class="text-sm font-medium" x-text="counterStatus.total_documents"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="bg-white rounded-lg p-3 border">
                        <p class="text-xs text-gray-500 mb-1">Dari <span x-text="getEntityLabel('singular')"></span> Tertinggi (Real)</p>
                        <p class="text-lg font-bold" x-text="counterStatus.from_max"></p>
                        <p class="text-xs text-gray-400" x-text="counterStatus.max_document ? 'MAX: ' + counterStatus.max_document : 'Tidak ada ' + getEntityLabel('singular').toLowerCase()"></p>
                    </div>
                    <div class="bg-white rounded-lg p-3 border">
                        <p class="text-xs text-gray-500 mb-1">Dari Jumlah <span x-text="getEntityLabel('singular')"></span></p>
                        <p class="text-lg font-bold" x-text="counterStatus.from_count"></p>
                        <p class="text-xs text-gray-400" x-text="'COUNT: ' + counterStatus.total_documents + ' ' + getEntityLabel('singular').toLowerCase()"></p>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-wrap gap-2">
                    <button 
                        type="button"
                        @click="showResetModal = true"
                        class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Reset Manual
                    </button>
                    <button 
                        type="button"
                        @click="syncCounter('max')"
                        :disabled="syncing"
                        class="px-3 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50">
                        Sync Tertinggi
                    </button>
                    <button 
                        type="button"
                        @click="syncCounter('count')"
                        :disabled="syncing"
                        class="px-3 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50">
                        Sync Jumlah
                    </button>
                </div>
            </div>
        </template>

        {{-- Problems Table --}}
        <template x-if="problems.length > 0">
            <div class="border border-gray-200 rounded-lg overflow-hidden mb-6">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900">
                        <span x-text="getEntityLabel('singular')"></span> Bermasalah (<span x-text="problems.length"></span> ditemukan)
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Nomor</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Tanggal</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Entitas</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Masalah</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Saran</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <template x-for="problem in problems" :key="problem.entity_id || problem.gap_position">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-mono text-sm" x-text="problem.current_number || '-'"></td>
                                    <td class="px-4 py-3 text-sm" x-text="problem.created_at || '-'"></td>
                                    <td class="px-4 py-3 text-sm" x-text="problem.entity_name || '-'"></td>
                                    <td class="px-4 py-3">
                                        <span 
                                            class="inline-flex px-2 py-1 text-xs font-medium rounded-full"
                                            :class="problem.type === 'duplicate' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'"
                                            x-text="problem.type === 'duplicate' ? 'Duplikat' : 'Gap: ' + problem.gap_position">
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500" x-text="problem.suggested_number || (problem.is_first ? 'Pertahankan' : problem.missing_number || '-')"></td>
                                    <td class="px-4 py-3">
                                        <button 
                                            x-show="problem.entity_id"
                                            type="button"
                                            @click="openEditModal(problem)"
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        {{-- No Problems Message --}}
        <template x-if="scanned && problems.length === 0">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-green-800">Tidak ada masalah penomoran ditemukan untuk scope ini.</p>
            </div>
        </template>

        {{-- Document List Section --}}
        <template x-if="selectedScope">
            <div class="border border-gray-200 rounded-lg overflow-hidden mb-6">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-sm font-semibold text-gray-900">
                        Daftar <span x-text="getEntityLabel('singular')"></span> Berurutan
                        <span x-show="documentList.length > 0" class="text-gray-500 font-normal">
                            (<span x-text="documentListMeta.total"></span> <span x-text="getEntityLabel('singular').toLowerCase()"></span>)
                        </span>
                    </h3>
                    <button 
                        type="button"
                        @click="fetchDocumentList(1)"
                        :disabled="loadingList"
                        class="text-sm text-blue-600 hover:text-blue-800 disabled:opacity-50">
                        <span x-show="!loadingList">Muat Daftar</span>
                        <span x-show="loadingList">Memuat...</span>
                    </button>
                </div>
                
                <template x-if="documentList.length > 0">
                    <div>
                        <div class="overflow-x-auto max-h-96 overflow-y-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-16">#</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500" x-text="getEntityLabel('number')"></th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Entitas</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Tanggal</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-24">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-20">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <template x-for="(doc, index) in documentList" :key="doc.entity_id">
                                        <tr :class="doc.has_issue ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-gray-50'">
                                            <td class="px-3 py-2 text-sm text-gray-500" x-text="doc.sequence_number || '-'"></td>
                                            <td class="px-3 py-2 font-mono text-sm font-medium" x-text="doc.current_number"></td>
                                            <td class="px-3 py-2 text-sm text-gray-700 truncate max-w-[200px]" x-text="doc.entity_name"></td>
                                            <td class="px-3 py-2 text-sm text-gray-500" x-text="doc.created_at"></td>
                                            <td class="px-3 py-2">
                                                <template x-if="doc.has_issue">
                                                    <div class="flex flex-wrap gap-1">
                                                        <template x-for="issue in doc.issues" :key="issue">
                                                            <span 
                                                                class="inline-flex px-1.5 py-0.5 text-xs font-medium rounded"
                                                                :class="issue === 'duplicate' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'"
                                                                x-text="issue === 'duplicate' ? 'Duplikat' : 'Gap'">
                                                            </span>
                                                        </template>
                                                    </div>
                                                </template>
                                                <template x-if="!doc.has_issue">
                                                    <span class="text-green-600 text-xs">OK</span>
                                                </template>
                                            </td>
                                            <td class="px-3 py-2">
                                                <button 
                                                    type="button"
                                                    @click="openEditModalFromSearch(doc)"
                                                    class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                                    Edit
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        
                        {{-- Pagination --}}
                        <div class="bg-gray-50 px-4 py-3 border-t border-gray-200 flex justify-between items-center">
                            <span class="text-xs text-gray-500">
                                Halaman <span x-text="documentListMeta.current_page"></span> dari <span x-text="documentListMeta.last_page"></span>
                            </span>
                            <div class="flex gap-2">
                                <button 
                                    type="button"
                                    @click="fetchDocumentList(documentListMeta.current_page - 1)"
                                    :disabled="documentListMeta.current_page <= 1 || loadingList"
                                    class="px-3 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Sebelumnya
                                </button>
                                <button 
                                    type="button"
                                    @click="fetchDocumentList(documentListMeta.current_page + 1)"
                                    :disabled="!documentListMeta.has_more || loadingList"
                                    class="px-3 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Selanjutnya
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
                
                <template x-if="documentList.length === 0 && !loadingList">
                    <div class="px-4 py-8 text-center text-sm text-gray-500">
                        Klik "Muat Daftar" untuk melihat urutan dokumen
                    </div>
                </template>
            </div>
        </template>

        {{-- Change Logs --}}
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-sm font-semibold text-gray-900">History Perubahan Terakhir</h3>
                <button 
                    type="button"
                    @click="fetchChangeLogs()"
                    class="text-sm text-blue-600 hover:text-blue-800">
                    Refresh
                </button>
            </div>
            <div class="max-h-64 overflow-y-auto">
                <template x-if="changeLogs.length > 0">
                    <div class="divide-y divide-gray-200">
                        <template x-for="log in changeLogs" :key="log.id">
                            <div class="px-4 py-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="text-sm font-medium text-gray-900" x-text="log.user"></span>
                                        <span class="text-sm text-gray-500" x-text="log.action_label"></span>
                                        <span class="text-sm text-gray-500" x-text="log.scope_label"></span>
                                    </div>
                                    <span class="text-xs text-gray-400" x-text="log.created_at"></span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    <span x-text="log.old_value"></span> → <span class="font-medium" x-text="log.new_value"></span>
                                </p>
                                <p class="text-xs text-gray-400 mt-1" x-text="'Alasan: ' + log.reason"></p>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="changeLogs.length === 0">
                    <div class="px-4 py-8 text-center text-sm text-gray-500">
                        Belum ada perubahan tercatat
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Reset Modal --}}
    <div x-show="showResetModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" aria-labelledby="reset-modal-title" @keydown.escape.window="showResetModal = false">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black/50" @click="showResetModal = false"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6" x-trap.noscroll.inert="showResetModal">
                <h3 id="reset-modal-title" class="text-lg font-semibold text-gray-900 mb-4">Reset Counter Manual</h3>
                
                <div class="mb-4">
                    <label for="reset-value-input" class="block text-sm font-medium text-gray-700 mb-1">Nilai Counter Baru</label>
                    <input 
                        type="number" 
                        id="reset-value-input"
                        x-model.number="resetValue"
                        min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label for="reset-reason-input" class="block text-sm font-medium text-gray-700 mb-1">Alasan Perubahan *</label>
                    <textarea 
                        id="reset-reason-input"
                        x-model="resetReason"
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="Jelaskan alasan reset counter..."></textarea>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                    <p class="text-xs text-yellow-800">
                        <strong>Perhatian:</strong> Reset counter dapat menyebabkan duplikasi nomor jika nilai baru lebih kecil dari nomor tertinggi yang sudah terbit.
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <button 
                        type="button"
                        @click="showResetModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Batal
                    </button>
                    <button 
                        type="button"
                        @click="resetCounter()"
                        :disabled="!resetReason || resetValue < 0"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                        Reset Counter
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" aria-labelledby="edit-modal-title" @keydown.escape.window="showEditModal = false">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black/50" @click="showEditModal = false"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6" x-trap.noscroll.inert="showEditModal">
                <h3 id="edit-modal-title" class="text-lg font-semibold text-gray-900 mb-4">Edit <span x-text="getEntityLabel('number')"></span></h3>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-500">Entitas: <span class="font-medium" x-text="editingProblem?.entity_name"></span></p>
                    <p class="text-sm text-gray-500">Tanggal: <span class="font-medium" x-text="editingProblem?.created_at"></span></p>
                </div>

                <div class="mb-4">
                    <label for="current-number-display" class="block text-sm font-medium text-gray-700 mb-1">Nomor Saat Ini</label>
                    <input 
                        type="text" 
                        id="current-number-display"
                        :value="editingProblem?.current_number"
                        disabled
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500 font-mono">
                </div>

                <div class="mb-4">
                    <label for="edit-new-number-input" class="block text-sm font-medium text-gray-700 mb-1">Nomor Baru</label>
                    <input 
                        type="text" 
                        id="edit-new-number-input"
                        x-model="editNewNumber"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono">
                    <p x-show="editingProblem?.suggested_number" class="text-xs text-gray-500 mt-1">
                        Saran: <span x-text="editingProblem?.suggested_number"></span>
                    </p>
                </div>

                <div class="mb-4">
                    <label for="edit-reason-input" class="block text-sm font-medium text-gray-700 mb-1">Alasan Perubahan *</label>
                    <textarea 
                        id="edit-reason-input"
                        x-model="editReason"
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="Jelaskan alasan perubahan nomor..."></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button 
                        type="button"
                        @click="showEditModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Batal
                    </button>
                    <button 
                        type="button"
                        @click="saveEdit()"
                        :disabled="!editNewNumber || !editReason || saving"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                        <span x-show="!saving">Simpan Perubahan</span>
                        <span x-show="saving">Menyimpan...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Sync Reason Modal --}}
    <div x-show="showSyncModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" aria-labelledby="sync-modal-title" @keydown.escape.window="showSyncModal = false">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black/50" @click="showSyncModal = false"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6" x-trap.noscroll.inert="showSyncModal">
                <h3 id="sync-modal-title" class="text-lg font-semibold text-gray-900 mb-4">Konfirmasi Sinkronisasi</h3>
                
                <p class="text-sm text-gray-500 mb-4">
                    Counter akan diubah dari <strong x-text="counterStatus?.current_counter"></strong> 
                    ke <strong x-text="syncMethod === 'max' ? counterStatus?.from_max : counterStatus?.from_count"></strong>
                </p>

                <div class="mb-4">
                    <label for="sync-reason-input" class="block text-sm font-medium text-gray-700 mb-1">Alasan Sinkronisasi *</label>
                    <textarea 
                        id="sync-reason-input"
                        x-model="syncReason"
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="Jelaskan alasan sinkronisasi..."></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button 
                        type="button"
                        @click="showSyncModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Batal
                    </button>
                    <button 
                        type="button"
                        @click="confirmSync()"
                        :disabled="!syncReason || syncing"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50">
                        <span x-show="!syncing">Sinkronkan</span>
                        <span x-show="syncing">Menyinkronkan...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function numberingRepair() {
    return {
        selectedScope: '',
        loading: false,
        scanned: false,
        syncing: false,
        saving: false,
        counterStatus: null,
        problems: [],
        changeLogs: [],
        
        // Search state
        searchQuery: '',
        searchResults: [],
        searchPerformed: false,
        searching: false,
        
        // Document list state
        documentList: [],
        documentListMeta: { current_page: 1, last_page: 1, total: 0, has_more: false },
        loadingList: false,
        
        // Request abort controller
        abortController: null,
        
        // Modals
        showResetModal: false,
        showEditModal: false,
        showSyncModal: false,
        
        // Reset form
        resetValue: 0,
        resetReason: '',
        
        // Edit form
        editingProblem: null,
        editNewNumber: '',
        editReason: '',
        
        // Sync form
        syncMethod: '',
        syncReason: '',
        
        scopeLabels: {
            'ba': 'BA Penerimaan',
            'sample_code': 'Kode Sampel',
            'lhu': 'Laporan Hasil Uji',
            'ba_penyerahan': 'BA Penyerahan',
            'tracking': 'Nomor Resi',
        },

        // Scope-aware entity labels for UI text
        scopeEntityLabels: {
            'sample_code': { singular: 'Sampel', plural: 'Sampel', number: 'Kode Sampel' },
            'ba': { singular: 'BA', plural: 'BA', number: 'Nomor BA' },
            'lhu': { singular: 'LHU', plural: 'LHU', number: 'Nomor LHU' },
            'tracking': { singular: 'Resi', plural: 'Resi', number: 'Nomor Resi' },
            'ba_penyerahan': { singular: 'Dokumen', plural: 'Dokumen', number: 'Nomor Dokumen' },
        },

        getEntityLabel(type = 'singular') {
            return this.scopeEntityLabels[this.selectedScope]?.[type] || 'Dokumen';
        },

        init() {
            this.fetchChangeLogs();
        },

        handleError(data) {
            if (data.errors) {
                let message = '';
                for (const key in data.errors) {
                    message += data.errors[key].join('\n') + '\n';
                }
                alert(message);
            } else if (data.message) {
                alert(data.message);
            } else if (data.error) {
                alert(data.error);
            } else {
                alert('Terjadi kesalahan yang tidak diketahui');
            }
        },

        async searchDocuments() {
            if (!this.selectedScope || !this.searchQuery.trim()) return;
            
            this.searching = true;
            this.searchPerformed = false;
            
            try {
                const response = await fetch(`/api/settings/numbering/repair/${this.selectedScope}/search?q=${encodeURIComponent(this.searchQuery)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                    credentials: 'same-origin',
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    this.searchResults = data.results || [];
                    this.searchPerformed = true;
                } else {
                    this.handleError(data);
                    this.searchResults = [];
                }
            } catch (error) {
                console.error('Search error:', error);
                alert('Gagal melakukan pencarian');
                this.searchResults = [];
            } finally {
                this.searching = false;
            }
        },

        openEditModalFromSearch(doc) {
            this.editingProblem = {
                entity_id: doc.entity_id,
                entity_type: doc.entity_type,
                current_number: doc.current_number,
                entity_name: doc.entity_name,
                created_at: doc.created_at,
                suggested_number: null,
            };
            this.editNewNumber = doc.current_number;
            this.editReason = '';
            this.showEditModal = true;
        },

        async scanScope() {
            if (!this.selectedScope) return;
            
            // Abort any pending request
            if (this.abortController) {
                this.abortController.abort();
            }
            this.abortController = new AbortController();
            
            // Capture the scope at the time of the request
            const scopeToScan = this.selectedScope;
            
            // Reset state for new scope
            this.loading = true;
            this.scanned = false;
            this.counterStatus = null;
            this.problems = [];
            
            try {
                const response = await fetch(`/api/settings/numbering/repair/${scopeToScan}/scan`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                    credentials: 'same-origin',
                    signal: this.abortController.signal,
                });
                
                // Check if scope changed during request
                if (this.selectedScope !== scopeToScan) {
                    console.log('Scope changed during request, discarding result');
                    return;
                }
                
                const data = await response.json();
                
                if (response.ok) {
                    this.counterStatus = data.counter_status;
                    this.problems = data.problems;
                    this.scanned = true;
                } else {
                    this.handleError(data);
                }
            } catch (error) {
                // Ignore abort errors
                if (error.name === 'AbortError') {
                    console.log('Request aborted for scope change');
                    return;
                }
                console.error('Scan error:', error);
                alert('Gagal melakukan scan');
            } finally {
                // Only reset loading if this is still the active request
                if (this.selectedScope === scopeToScan) {
                    this.loading = false;
                }
                this.abortController = null;
            }
        },

        onScopeChange() {
            // Abort any pending request when scope changes
            if (this.abortController) {
                this.abortController.abort();
                this.abortController = null;
            }
            
            // Reset state when scope changes
            this.loading = false;
            this.scanned = false;
            this.counterStatus = null;
            this.problems = [];
            
            // Reset search state
            this.searchQuery = '';
            this.searchResults = [];
            this.searchPerformed = false;
            
            // Reset document list state
            this.documentList = [];
            this.documentListMeta = { current_page: 1, last_page: 1, total: 0, has_more: false };
            
            // Don't auto-scan, let user click the button
            // This prevents race conditions
        },

        async fetchDocumentList(page = 1) {
            if (!this.selectedScope) return;
            
            this.loadingList = true;
            
            try {
                const response = await fetch(`/api/settings/numbering/repair/${this.selectedScope}/list?page=${page}&per_page=50`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                    credentials: 'same-origin',
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    this.documentList = data.documents || [];
                    this.documentListMeta = data.meta || { current_page: 1, last_page: 1, total: 0, has_more: false };
                } else {
                    this.handleError(data);
                }
            } catch (error) {
                console.error('Fetch document list error:', error);
                alert('Gagal memuat daftar dokumen');
            } finally {
                this.loadingList = false;
            }
        },

        async fetchChangeLogs() {
            try {
                const url = this.selectedScope 
                    ? `/api/settings/numbering/repair/change-logs?scope=${this.selectedScope}`
                    : '/api/settings/numbering/repair/change-logs';
                    
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                    credentials: 'same-origin',
                });
                
                const data = await response.json();
                this.changeLogs = data.logs || [];
            } catch (error) {
                console.error('Fetch logs error:', error);
            }
        },

        syncCounter(method) {
            this.syncMethod = method;
            this.syncReason = '';
            this.showSyncModal = true;
        },

        async confirmSync() {
            if (!this.syncReason) return;
            
            this.syncing = true;
            
            try {
                const response = await fetch(`/api/settings/numbering/repair/${this.selectedScope}/sync`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        method: this.syncMethod,
                        reason: this.syncReason,
                    }),
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    this.showSyncModal = false;
                    this.scanScope();
                    this.fetchChangeLogs();
                    alert('Counter berhasil disinkronkan');
                } else {
                    this.handleError(data);
                }
            } catch (error) {
                console.error('Sync error:', error);
                alert('Gagal melakukan sinkronisasi');
            } finally {
                this.syncing = false;
            }
        },

        async resetCounter() {
            if (!this.resetReason || this.resetValue < 0) return;
            
            try {
                const response = await fetch(`/api/settings/numbering/repair/${this.selectedScope}/reset`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        new_value: this.resetValue,
                        reason: this.resetReason,
                    }),
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    this.showResetModal = false;
                    this.resetValue = 0;
                    this.resetReason = '';
                    this.scanScope();
                    this.fetchChangeLogs();
                    alert('Counter berhasil direset');
                } else {
                    this.handleError(data);
                }
            } catch (error) {
                console.error('Reset error:', error);
                alert('Gagal melakukan reset');
            }
        },

        openEditModal(problem) {
            this.editingProblem = problem;
            this.editNewNumber = problem.suggested_number || problem.current_number;
            this.editReason = '';
            this.showEditModal = true;
        },

        async saveEdit() {
            if (!this.editNewNumber || !this.editReason) return;
            
            this.saving = true;
            
            try {
                const response = await fetch(`/api/settings/numbering/repair/${this.selectedScope}/${this.editingProblem.entity_id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        new_number: this.editNewNumber,
                        reason: this.editReason,
                    }),
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    this.showEditModal = false;
                    
                    // Refresh search results if we came from search
                    if (this.searchQuery && this.searchPerformed) {
                        this.searchDocuments();
                    }
                    
                    // Refresh scan results if we have them
                    if (this.scanned) {
                        this.scanScope();
                    }
                    
                    // Refresh document list if loaded
                    if (this.documentList.length > 0) {
                        this.fetchDocumentList(this.documentListMeta.current_page);
                    }
                    
                    this.fetchChangeLogs();
                    alert('Nomor berhasil diperbarui');
                } else {
                    this.handleError(data);
                }
            } catch (error) {
                console.error('Save error:', error);
                alert('Gagal menyimpan perubahan');
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
