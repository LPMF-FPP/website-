{{-- Partial: Lokalisasi & Retensi --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Lokalisasi & Retensi</h2>
        <p class="text-sm text-gray-500 mt-1">PUT /localization-retention</p>
    </div>
    <div class="p-6 space-y-4">
        <div class="text-sm text-gray-700 bg-gray-50 rounded-lg p-3 border border-gray-200">
            <span>Timezone: </span>
            <span class="font-semibold" x-text="client.state.form.locale.timezone || 'Asia/Jakarta'"></span>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Zona Waktu</span>
                <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.locale.timezone" @change="updateNowPreview()">
                    <template x-for="tz in timezones" :key="tz">
                        <option :value="tz" x-text="tz"></option>
                    </template>
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Format Tanggal</span>
                <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.locale.date_format" @change="updateNowPreview()">
                    <template x-for="fmt in dateFormats" :key="fmt">
                        <option :value="fmt" x-text="fmt"></option>
                    </template>
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Format Angka</span>
                <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.locale.number_format">
                    <template x-for="fmt in numberFormats" :key="fmt.value">
                        <option :value="fmt.value" x-text="fmt.label"></option>
                    </template>
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Bahasa</span>
                <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.locale.language">
                    <template x-for="lang in languages" :key="lang.value">
                        <option :value="lang.value" x-text="lang.label"></option>
                    </template>
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Driver Penyimpanan</span>
                <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-gray-100" x-model="client.state.form.retention.storage_driver" disabled>
                    <template x-for="drv in storageDrivers" :key="drv">
                        <option :value="drv" x-text="drv"></option>
                    </template>
                </select>
                <span class="text-xs text-gray-500 mt-1 block">Fixed: public (tidak dapat diubah)</span>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Folder Path</span>
                <input class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 text-sm" 
                       :value="storagePathInfo || 'storage/app/public/investigators/{investigator}/{request}/'"
                       disabled readonly>
                <span class="text-xs text-gray-500 mt-1 block">Path otomatis berdasarkan penyidik dan nomor permohonan</span>
            </label>
        </div>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
        <div>
            <p class="text-sm" 
               :class="client.state.sectionStatus['localization']?.intentClass" 
               x-text="client.state.sectionStatus['localization']?.message" 
               x-show="client.state.sectionStatus['localization']?.message"></p>
            <p class="text-xs text-red-600" 
               x-text="client.state.sectionErrors['localization']" 
               x-show="client.state.sectionErrors['localization']"></p>
        </div>
        <button 
            type="button"
            class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
            :disabled="client.state.loadingSections['localization']"
            @click="client.saveSection('localization')">
            <span x-show="!client.state.loadingSections['localization']">Simpan</span>
            <span x-show="client.state.loadingSections['localization']">Menyimpan...</span>
        </button>
    </div>
</div>
