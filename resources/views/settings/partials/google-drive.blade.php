{{-- Partial: Google Drive --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-slate-50 to-blue-50">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Google Drive</h2>
                <p class="text-sm text-gray-600 mt-1">Atur folder tujuan dan pola nama folder untuk dokumen permintaan yang tersinkron ke Drive.</p>
            </div>
            <span class="rounded-full border border-blue-200 bg-white px-3 py-1 text-xs font-semibold text-blue-700">Drive API v3</span>
        </div>
    </div>

    <div class="p-6 space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block md:col-span-2">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Folder ID Utama Google Drive</span>
                <input
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-mono"
                    placeholder="Contoh: 1aBcD_folderIdDariUrlGoogleDrive"
                    x-model="client.state.form.google_drive.folder_id"
                >
                <span class="text-xs text-gray-500 mt-1 block">Kosongkan untuk membuat folder utama otomatis berdasarkan nama di bawah. Folder ID diambil dari URL <code class="font-mono">drive/folders/{folder_id}</code>.</span>
            </label>

            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Nama Folder Utama</span>
                <input
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                    x-model="client.state.form.google_drive.uploads_folder_name"
                >
            </label>

            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Mode Nama Folder Permintaan</span>
                <select
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                    x-model="client.state.form.google_drive.request_folder_mode"
                >
                    <option value="request_number">Nomor permintaan saja</option>
                    <option value="request_number_suspect">Nomor permintaan + nama tersangka</option>
                    <option value="suspect_request_number">Nama tersangka + nomor permintaan</option>
                    <option value="month_suspect">Per bulan / no resi + tersangka</option>
                </select>
            </label>

            <label class="block md:col-span-2">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Akun Uploader Google Drive Terpusat</span>
                <select
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                    x-model="client.state.form.google_drive.uploader_user_id"
                >
                    <option value="">Gunakan akun Google Drive user yang sedang login</option>
                    <template x-for="user in client.state.options?.google_drive_users || []" :key="user.id">
                        <option :value="String(user.id)" x-text="user.label"></option>
                    </template>
                </select>
                <span class="text-xs text-gray-500 mt-1 block">Jika dipilih, sinkronisasi Drive akan memakai akun ini saat user aktif belum menghubungkan Google Drive. Pilihan hanya menampilkan user yang sudah connect Google Drive.</span>
            </label>
        </div>

        <label class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <input
                type="checkbox"
                class="mt-1 rounded border-amber-300 text-blue-600 focus:ring-blue-500"
                x-model="client.state.form.google_drive.use_suspect_name"
            >
            <span class="text-sm text-amber-900">
                <span class="font-semibold block">Gunakan nama tersangka pada folder</span>
                <span class="mt-1 block text-amber-800">Struktur default mengikuti kesepakatan: bulan lalu folder no resi - nama tersangka.</span>
            </span>
        </label>

        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
            <h3 class="text-sm font-semibold text-gray-800">Preview Struktur</h3>
            <pre class="mt-3 overflow-x-auto rounded-md bg-slate-950 p-4 text-xs text-slate-100"><code x-text="`${client.state.form.google_drive.folder_id ? '[Folder ID utama]' : (client.state.form.google_drive.uploads_folder_name || 'LPMF LIMS Uploads')}/\n  ${client.state.form.google_drive.request_folder_mode === 'month_suspect' ? '2026-04/' : ''}${client.state.form.google_drive.use_suspect_name ? (client.state.form.google_drive.request_folder_mode === 'month_suspect' ? 'RESI-2026-0001 - BUDI SANTOSO/' : (client.state.form.google_drive.request_folder_mode === 'suspect_request_number' ? 'BUDI SANTOSO - REQ-2026-0001/' : (client.state.form.google_drive.request_folder_mode === 'request_number_suspect' ? 'REQ-2026-0001 - BUDI SANTOSO/' : 'REQ-2026-0001/'))) : 'REQ-2026-0001/'}\n    Permintaan/\n      Berita Acara Penerimaan - Budi Santoso.pdf\n      Foto Sampel - Budi Santoso.jpg\n      Surat Permintaan - Budi Santoso.pdf\n      Label Sampel - Budi Santoso.pdf\n    Pengujian/\n      LHU - LS001I2026.pdf\n      Lampiran Pengujian - LS001I2026.pdf\n    Penyerahan/\n      Label Sisa Sampel - Budi Santoso.pdf\n      Berita Acara Penyerahan - Budi Santoso.pdf`"></code></pre>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
        <div>
            <p class="text-sm" role="status"
               :class="client.state.sectionStatus['google_drive']?.intentClass"
               x-text="client.state.sectionStatus['google_drive']?.message"
               x-show="client.state.sectionStatus['google_drive']?.message"></p>
            <p class="text-xs text-red-600" role="alert"
               x-text="client.state.sectionErrors['google_drive']"
               x-show="client.state.sectionErrors['google_drive']"></p>
        </div>
        <button
            type="button"
            class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
            :disabled="client.state.loadingSections['google_drive']"
            @click="client.saveSection('google_drive')">
            <span x-show="!client.state.loadingSections['google_drive']">Simpan Google Drive</span>
            <span x-show="client.state.loadingSections['google_drive']">Menyimpan...</span>
        </button>
    </div>
</div>
