<div
    x-data="qmhPendukungPicker({ endpoint: '/api/quality/pendukung' })"
    x-init="init()"
    x-show="open"
    x-cloak
    x-trap.noscroll="open"
    class="fixed inset-0 z-pd-modal overflow-y-auto"
    role="dialog"
    aria-modal="true"
>
    <div class="flex min-h-dvh items-center justify-center px-4 py-8">
        <div class="fixed inset-0 bg-gray-900/50" @click="close()"></div>

        <div class="relative w-full max-w-3xl rounded-xl bg-white p-5 shadow-xl" x-transition>
            <div class="flex items-start justify-between gap-3 border-b border-gray-200 pb-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Pilih Dokumen Pendukung</h3>
                    <p class="mt-1 text-sm text-gray-600">Pilih dokumen untuk disisipkan sebagai link pada editor.</p>
                </div>
                <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="close()">Tutup</button>
            </div>

            <div class="mt-4 space-y-3">
                <input
                    type="text"
                    x-model.trim="query"
                    placeholder="Cari kode atau judul dokumen"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary-600 focus:ring-primary-600"
                >

                <template x-if="error">
                    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="error"></div>
                </template>

                <div class="max-h-96 overflow-auto rounded-lg border border-gray-200">
                    <template x-if="loading">
                        <div class="px-4 py-6 text-sm text-gray-500">Memuat dokumen pendukung...</div>
                    </template>

                    <template x-if="!loading && filteredItems().length === 0">
                        <div class="px-4 py-6 text-sm text-gray-500">Tidak ada dokumen yang cocok.</div>
                    </template>

                    <template x-if="!loading && filteredItems().length > 0">
                        <ul class="divide-y divide-gray-100">
                            <template x-for="item in filteredItems()" :key="item.id">
                                <li>
                                    <button
                                        type="button"
                                        @click="select(item)"
                                        class="flex w-full items-start justify-between px-4 py-3 text-left transition"
                                        :class="isSelected(item) ? 'bg-primary-50' : 'hover:bg-gray-50'"
                                    >
                                        <span>
                                            <span class="block text-sm font-medium text-gray-900" x-text="item.doc_code"></span>
                                            <span class="mt-0.5 block text-xs text-gray-600" x-text="item.title"></span>
                                        </span>
                                        <span class="ml-4 inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] text-gray-700">
                                            Klausul <span class="ml-1" x-text="item.clause"></span>
                                        </span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="close()">Batal</button>
                <button
                    type="button"
                    class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!selectedItem()"
                    @click="confirmSelection()"
                >
                    Sisipkan Link
                </button>
            </div>
        </div>
    </div>
</div>
