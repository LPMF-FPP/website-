{{-- Partial: Dokumen & Lampiran (untuk detail permintaan) --}}
<div class="bg-white shadow-sm sm:rounded-lg" x-data="requestDocumentsAlpine" x-init="init()" id="request-documents">
    <div class="border-b border-primary-100 px-6 py-4">
        <nav class="flex items-center gap-6 text-sm font-semibold text-primary-500" role="tablist">
            <button type="button" class="pb-1 border-b-2 border-primary-600 text-primary-900 focus:outline-none">Dokumen &amp; Lampiran</button>
            <button type="button" class="pb-1 text-primary-300 cursor-default" disabled>Preview</button>
        </nav>
    </div>
    <div class="p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-primary-600">Upload penyidik dan dokumen hasil generate otomatis tampil di sini.</p>
            <button type="button" class="btn-secondary text-sm disabled:opacity-60 disabled:cursor-not-allowed" :disabled="documentsClient.state.loading" @click="documentsClient.fetchDocuments()">
                <span x-show="!documentsClient.state.loading">Refresh</span>
                <span x-show="documentsClient.state.loading">Memuat...</span>
            </button>
        </div>
        <p class="text-sm text-red-600" x-text="documentsClient.state.error" x-show="documentsClient.state.error"></p>
        <div class="grid lg:grid-cols-2 gap-6">
            <div class="border border-gray-200 rounded-xl overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wide">Nama</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wide">Tipe</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase tracking-wide">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" x-show="documentsClient.state.documents.length">
                        <template x-for="doc in documentsClient.state.documents" :key="doc.id">
                            <tr :class="documentsClient.state.selectedDocument && documentsClient.state.selectedDocument.id === doc.id ? 'bg-primary-25/60' : 'bg-white'">
                                <td class="px-4 py-2 font-medium text-gray-900" x-text="doc.name"></td>
                                <td class="px-4 py-2 text-gray-600" x-text="documentsClient.documentType(doc)"></td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-3">
                                        <button type="button" class="text-primary-600 hover:underline text-xs font-semibold" @click="documentsClient.selectDocument(doc)">Lihat</button>
                                        <button type="button" class="text-red-600 hover:underline text-xs font-semibold disabled:opacity-50" :disabled="documentsClient.isDeleting(doc.id)" @click="documentsClient.deleteDocument(doc)">
                                            <span x-show="!documentsClient.isDeleting(doc.id)">Hapus</span>
                                            <span x-show="documentsClient.isDeleting(doc.id)">Menghapus...</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p class="text-sm text-gray-500 px-4 py-6" x-show="!documentsClient.state.documents.length && !documentsClient.state.loading">Belum ada dokumen untuk permintaan ini.</p>
                <p class="text-sm text-primary-500 px-4 py-6" x-show="documentsClient.state.loading">Memuat daftar dokumen...</p>
            </div>
            <div class="border border-gray-200 rounded-xl p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-primary-900">Preview Dokumen</h4>
                    <button type="button" class="text-xs text-primary-600 hover:underline" x-show="documentsClient.state.selectedDocument" @click="documentsClient.openDocument(documentsClient.state.selectedDocument)">Buka Tab Baru</button>
                </div>
                <div class="min-h-[320px] flex items-center justify-center bg-gray-50 rounded-lg" x-show="documentsClient.state.loading">
                    <p class="text-sm text-primary-500">Menyiapkan preview...</p>
                </div>
                <div class="min-h-[320px]" x-show="!documentsClient.state.loading">
                    <template x-if="documentsClient.state.previewUrl && documentsClient.documentIsPdf(documentsClient.state.selectedDocument)">
                        <iframe :src="documentsClient.state.previewUrl" class="w-full h-80 border border-gray-200 rounded-lg" title="Preview Dokumen"></iframe>
                    </template>
                    <template x-if="documentsClient.state.previewUrl && !documentsClient.documentIsPdf(documentsClient.state.selectedDocument) && documentsClient.documentIsImage(documentsClient.state.selectedDocument)">
                        <img :src="documentsClient.state.previewUrl" alt="Preview" class="w-full rounded-lg border border-gray-200 max-h-80 object-contain">
                    </template>
                    <template x-if="documentsClient.state.previewUrl && !documentsClient.documentIsPdf(documentsClient.state.selectedDocument) && !documentsClient.documentIsImage(documentsClient.state.selectedDocument)">
                        <div class="text-sm text-primary-600">
                            <p>Preview tidak tersedia untuk tipe ini.</p>
                            <button type="button" class="btn-secondary mt-3" @click="documentsClient.openDocument(documentsClient.state.selectedDocument)">Unduh / Lihat</button>
                        </div>
                    </template>
                    <p class="text-sm text-primary-500" x-show="!documentsClient.state.previewUrl && !documentsClient.state.loading">Pilih dokumen untuk melihat preview.</p>
                </div>
            </div>
        </div>
    </div>
</div>
