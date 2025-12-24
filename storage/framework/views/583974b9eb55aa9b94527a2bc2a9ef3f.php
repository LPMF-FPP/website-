
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200 flex flex-col gap-2">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Manajemen Dokumen</h2>
                <p class="text-sm text-gray-500 mt-1">Daftar seluruh file di storage <code class="font-mono text-xs text-gray-600">storage/app/public</code> untuk dipreview, diunduh, atau dihapus.</p>
            </div>
            <div class="flex items-center text-sm" x-show="client.state.sectionStatus.documents.message">
                <span :class="client.state.sectionStatus.documents.intentClass" x-text="client.state.sectionStatus.documents.message"></span>
            </div>
            <div class="flex items-center text-sm text-red-600" x-show="client.state.sectionErrors.documents">
                <span x-text="client.state.sectionErrors.documents"></span>
            </div>
        </div>

        <div class="p-6 space-y-6">
            
            <form @submit.prevent="client.fetchDocuments({ page: 1 })" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Cari Dokumen</label>
                        <input 
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            placeholder="Nama file, investigator, #request"
                            x-model="client.state.documentsFilters.query">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Nomor Permintaan</label>
                        <input 
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            placeholder="REQ-2025-0012"
                            x-model="client.state.documentsFilters.request_number">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tipe Dokumen</label>
                        <select 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            x-model="client.state.documentsFilters.type">
                            <option value="">Semua tipe</option>
                            <template x-for="option in documentTypes" :key="option.value">
                                <option :value="option.value" x-text="option.label"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Sumber Dokumen</label>
                        <select 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            x-model="client.state.documentsFilters.source">
                            <template x-for="option in documentSources" :key="option.value || 'all'">
                                <option :value="option.value" x-text="option.label || 'Semua sumber'"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Per halaman</label>
                        <select 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            x-model.number="client.state.documentsFilters.per_page">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button 
                        type="submit"
                        class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="client.state.documentsLoading">
                        <span x-show="!client.state.documentsLoading">Cari Dokumen</span>
                        <span x-show="client.state.documentsLoading">Memuat...</span>
                    </button>
                    <button 
                        type="button"
                        class="px-6 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        @click="client.resetDocumentsFilters(); client.fetchDocuments({ page: 1 });">
                        Reset Filter
                    </button>
                    <span class="text-xs text-gray-500 self-center">
                        Gunakan filter untuk mempersempit hingga request tertentu.
                    </span>
                </div>
            </form>

            
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                
                <div x-show="client.hasSelectedDocuments" class="p-3 bg-blue-50 border-b border-blue-200 flex items-center gap-3">
                    <div class="flex items-center gap-2 text-sm text-blue-900">
                        <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span x-text="`${client.state.selectedDocuments.length} dokumen dipilih`"></span>
                    </div>
                    <button 
                        type="button"
                        class="ml-auto px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                        @click="client.bulkDeleteDocuments()"
                        :disabled="client.state.bulkDeleteLoading">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span x-show="!client.state.bulkDeleteLoading">Hapus Terpilih</span>
                        <span x-show="client.state.bulkDeleteLoading">Menghapus...</span>
                    </button>
                    <button 
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                        @click="client.state.selectedDocuments = []">
                        Batal
                    </button>
                </div>

                <div class="p-4 flex flex-wrap items-center gap-3 border-b border-gray-200 bg-gray-50">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
                        </svg>
                        <span x-text="`Menampilkan ${client.state.documents.length} dari ${client.state.documentsPagination.total} dokumen`"></span>
                    </div>
                    <div class="ml-auto flex items-center gap-2 text-xs text-gray-500">
                        <span x-text="`Halaman ${client.state.documentsPagination.current_page} / ${client.state.documentsPagination.last_page}`"></span>
                        <button 
                            type="button"
                            class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-white transition disabled:opacity-50 text-xs"
                            @click="client.changeDocumentsPage(client.state.documentsPagination.current_page - 1)"
                            :disabled="client.state.documentsPagination.current_page <= 1 || client.state.documentsLoading">
                            Prev
                        </button>
                        <button 
                            type="button"
                            class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-white transition disabled:opacity-50 text-xs"
                            @click="client.changeDocumentsPage(client.state.documentsPagination.current_page + 1)"
                            :disabled="client.state.documentsPagination.current_page >= client.state.documentsPagination.last_page || client.state.documentsLoading">
                            Next
                        </button>
                    </div>
                </div>

                <div x-show="client.state.documentsLoading" class="p-6 text-center text-sm text-gray-500">
                    <span class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle>
                            <path class="opacity-75" stroke-width="4" d="M4 12a8 8 0 018-8" stroke-linecap="round"></path>
                        </svg>
                        Memuat data dokumen...
                    </span>
                </div>

                <div x-show="!client.state.documentsLoading && client.state.documents.length === 0" class="p-6 text-center text-sm text-gray-500">
                    Tidak ada dokumen yang cocok dengan filter saat ini.
                </div>

                <div x-show="!client.state.documentsLoading && client.state.documents.length > 0" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left">
                                    <input 
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 cursor-pointer"
                                        :checked="client.allDocumentsSelected"
                                        @change="client.toggleAllDocuments()"
                                        title="Pilih semua">
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Dokumen</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Tipe</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Request</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Sumber</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Ukuran</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Diubah</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="entry in client.state.documents" :key="entry.path">
                                <tr :class="client.isDocumentSelected(entry.path) ? 'bg-blue-50' : ''">
                                    <td class="px-4 py-3">
                                        <input 
                                            type="checkbox"
                                            class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 cursor-pointer"
                                            :checked="client.isDocumentSelected(entry.path)"
                                            @change="client.toggleDocumentSelection(entry.path)">
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-sm font-medium text-gray-900" x-text="entry.name"></p>
                                        <p class="text-xs text-gray-500 break-all" x-text="entry.path"></p>
                                        <p class="text-xs text-gray-500" x-text="entry.document?.investigator?.name || '-'"></p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <span x-text="entry.type_label || formatDocumentTypeValue(entry.type)"></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        <div class="flex flex-col">
                                            <span class="font-medium" x-text="entry.document?.request_number || '-'"></span>
                                            <span class="text-xs text-gray-500" x-text="entry.document?.request_id ? `#${entry.document?.request_id}` : ''"></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        <span x-text="formatDocumentSourceLabel(entry.source)"></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        <span x-text="entry.size_label || formatDocumentSizeLabel(entry.size)"></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        <span x-text="formatDocumentTimestamp(entry.last_modified)"></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 space-y-2">
                                        <div class="flex flex-wrap gap-2">
                                            <template x-if="entry.preview_url">
                                                <a 
                                                    :href="entry.preview_url"
                                                    target="_blank"
                                                    class="inline-flex items-center px-3 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50 transition">
                                                    Preview
                                                </a>
                                            </template>
                                            <template x-if="entry.download_url">
                                                <a 
                                                    :href="entry.download_url"
                                                    target="_blank"
                                                    class="inline-flex items-center px-3 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded hover:bg-blue-100 transition">
                                                    Unduh
                                                </a>
                                            </template>
                                        </div>
                                        <button 
                                            type="button"
                                            class="inline-flex items-center px-3 py-1 text-xs font-medium text-white rounded transition"
                                            :class="entry.can_delete ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-300 cursor-not-allowed'"
                                            :disabled="!entry.can_delete || client.isDocumentDeleting(entry.path)"
                                            @click="client.deleteDocumentEntry(entry)">
                                            <span x-show="!client.isDocumentDeleting(entry.path)">Hapus</span>
                       	                <span x-show="client.isDocumentDeleting(entry.path)">Menghapus...</span>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /**PATH /home/lpmf-dev/website-/resources/views/settings/partials/documents.blade.php ENDPATH**/ ?>