{{-- Partial: Branding & PDF --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Branding & PDF</h2>
                <p class="text-sm text-gray-500 mt-1">PUT /branding • POST /pdf/preview</p>
            </div>
            <button 
                type="button"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
                :disabled="client.state.pdfPreviewLoading"
                @click="previewPdf()">
                <span x-show="!client.state.pdfPreviewLoading">Preview PDF</span>
                <span x-show="client.state.pdfPreviewLoading">Loading...</span>
            </button>
        </div>
    </div>
    <div class="p-6 space-y-6">
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
                <input class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.branding.org_name" placeholder="Alamat Komplek">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Alamat</span>
                <input class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.pdf.header.address">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Kontak</span>
                <input class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.pdf.header.contact">
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
            <span x-show="client.state.loadingSections['branding']">Saving...</span>
        </button>
    </div>
</div>

<div 
    x-show="client.state.sectionStatus['branding']?.message" 
    x-transition
    class="mt-4 bg-green-50 border border-green-200 rounded-lg p-4">
    <p class="text-sm text-green-800" x-text="client.state.sectionStatus['branding']?.message"></p>
</div>
