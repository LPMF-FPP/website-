{{-- Partial: Branding & PDF --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Branding & PDF</h2>
                <p class="text-sm text-gray-500 mt-1">Konfigurasi identitas laboratorium dan tampilan PDF.</p>
            </div>
            <button 
                type="button"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
                :disabled="client.state.pdfPreviewLoading"
                @click="previewPdf()">
                <span x-show="!client.state.pdfPreviewLoading">Pratinjau PDF</span>
                <span x-show="client.state.pdfPreviewLoading">Memuat...</span>
            </button>
        </div>
    </div>
    <div class="p-6 space-y-6">
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <div class="text-sm text-amber-800">
                    <p class="font-medium">Teks kop surat dikelola terpusat dari pengaturan ini</p>
                    <p class="mt-1 text-amber-700">
                        Perubahan nama instansi, nama laboratorium, alamat, telepon, email, dan website
                        akan diterapkan ke dokumen berkop tanpa mengubah desainnya.
                        Untuk mengubah layout atau tampilan visual dokumen, gunakan
                        <a
                            href="{{ route('settings.blade-templates') }}"
                            class="font-medium underline hover:text-amber-900"
                        >
                            Template Dokumen
                        </a>.
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
            <p class="text-sm text-orange-700 font-medium">Preview tampil di panel / tab baru.</p>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Kode Lab</span>
                <input class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.branding.lab_code">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Nama Instansi</span>
                <input class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.branding.org_name" placeholder="Nama instansi laboratorium">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Nama Laboratorium</span>
                <input class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.branding.lab_name" placeholder="Nama laboratorium pada kop surat">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Alamat</span>
                <input class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.branding.address">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Telepon</span>
                <input class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.branding.phone">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Email</span>
                <input type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.branding.email">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Website</span>
                <input class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.branding.website">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Preset Watermark</span>
                <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.pdf.header.watermark">
                    <option value="none">Tidak ada</option>
                    <option value="diagonal">Diagonal</option>
                    <option value="center">Tengah</option>
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Footer</span>
                <input class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.pdf.footer.text">
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" class="rounded border-gray-300" x-model="client.state.form.pdf.qr.enabled">
                <span>Tampilkan QR pada PDF</span>
            </label>
        </div>
    </div>
    <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 flex items-center justify-end gap-3">
        <button 
            type="button"
            class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
            :disabled="client.state.loadingSections['branding']"
            @click="client.saveSection('branding')">
            <span x-show="!client.state.loadingSections['branding']">Simpan</span>
            <span x-show="client.state.loadingSections['branding']">Menyimpan...</span>
        </button>
    </div>
</div>

<div 
    x-show="client.state.sectionStatus['branding']?.message" 
    x-transition
    role="status"
    class="mt-4 bg-green-50 border border-green-200 rounded-lg p-4">
    <p class="text-sm text-green-800" x-text="client.state.sectionStatus['branding']?.message"></p>
</div>
