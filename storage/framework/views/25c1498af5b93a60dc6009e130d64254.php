
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
                <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.retention.storage_driver">
                    <template x-for="drv in storageDrivers" :key="drv">
                        <option :value="drv" x-text="drv"></option>
                    </template>
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Folder Path</span>
                <input class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.retention.storage_folder_path" placeholder="/lims/storage">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Purge Days</span>
                <input type="number" min="30" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model.number="client.state.form.retention.purge_after_days" placeholder="90">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700 mb-1 block">Export Pattern</span>
                <input class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" x-model="client.state.form.retention.export_filename_pattern" placeholder="export-{YYYY}-{MM}.zip">
            </label>
        </div>
    </div>
    <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 flex items-center justify-end gap-3">
        <button 
            type="button"
            class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
            :disabled="client.state.loadingSections['localization']"
            @click="client.saveSection('localization')">
            <span x-show="!client.state.loadingSections['localization']">Simpan</span>
            <span x-show="client.state.loadingSections['localization']">Saving...</span>
        </button>
    </div>
</div>

<div 
    x-show="client.state.sectionStatus['localization']?.message" 
    x-transition
    class="mt-4 bg-green-50 border border-green-200 rounded-lg p-4">
    <p class="text-sm text-green-800" x-text="client.state.sectionStatus['localization']?.message"></p>
</div>
<?php /**PATH /home/lpmf-dev/website-/resources/views/settings/partials/localization-retention.blade.php ENDPATH**/ ?>