<div class="space-y-6">
    {{-- Document Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        {{-- Loading State --}}
        <template x-if="loading">
            <div class="col-span-full py-8 text-center text-gray-500">
                <svg class="animate-spin mx-auto h-8 w-8 text-blue-500 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Memuat dokumen...
            </div>
        </template>

        {{-- Empty State --}}
        <template x-if="!loading && documents.length === 0">
            <div class="col-span-full py-8 text-center text-gray-500 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="mt-2">Belum ada dokumen yang diunggah.</p>
            </div>
        </template>

        {{-- Document Cards --}}
        <template x-for="doc in documents" :key="doc.id">
            <div class="relative group bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden flex flex-col">
                {{-- File Preview/Icon --}}
                <div class="h-32 bg-gray-100 flex items-center justify-center border-b border-gray-100 relative overflow-hidden group-hover:bg-gray-50">
                    <template x-if="canRenderImage(doc)">
                        <img
                            :src="imagePreviewUrl(doc)"
                            @error="markImageError(doc)"
                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                            alt="Preview dokumen"
                        >
                    </template>
                    <template x-if="!canRenderImage(doc)">
                        <div class="text-center">
                            <template x-if="documentIsPdf(doc)">
                                <svg class="w-12 h-12 text-red-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                                </svg>
                            </template>
                            <template x-if="!documentIsPdf(doc)">
                                <svg class="w-12 h-12 text-gray-400 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                                </svg>
                            </template>
                            <span class="text-xs font-semibold text-gray-500 mt-1 uppercase" x-text="doc.extension || 'FILE'"></span>
                        </div>
                    </template>
                    
                    {{-- Overlay Actions --}}
                    <div class="absolute inset-0 bg-black/40 flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        <button @click="openDocument(doc)" class="p-2 bg-white rounded-full text-gray-700 hover:text-blue-600 hover:shadow-lg transition-all" title="Lihat">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                        <a :href="doc.download_url" class="p-2 bg-white rounded-full text-gray-700 hover:text-green-600 hover:shadow-lg transition-all" title="Download" download>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                        </a>
                        <button @click="deleteDocument(doc)" class="p-2 bg-white rounded-full text-gray-700 hover:text-red-600 hover:shadow-lg transition-all" title="Hapus">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Card Info --}}
                <div class="p-3 flex-1 flex flex-col justify-between bg-white">
                    <div>
                        <p class="text-sm font-medium text-gray-900 truncate" :title="doc.name" x-text="doc.name"></p>
                        <p class="text-xs text-gray-500 mt-1 truncate" x-text="documentType(doc)"></p>
                    </div>
                    <div class="mt-2 text-xs text-gray-400 flex justify-between items-center">
                        <span x-text="doc.formatted_size || ''"></span>
                        <span x-text="doc.created_at_human || ''"></span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Manual Upload / Generate Section can be added here if needed --}}
</div>
