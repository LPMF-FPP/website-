{{-- Partial: Notifikasi & Security --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Notifikasi & Security</h2>
        <p class="text-sm text-gray-500 mt-1">Konfigurasi notifikasi dan manajemen role</p>
    </div>
    <div class="p-6 space-y-6">
        <div class="space-y-4">
            {{-- WhatsApp Notification --}}
            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    Konfigurasi WhatsApp
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 mb-3">
                            <input type="checkbox" class="rounded border-gray-300" 
                                   x-model="client.state.form.notifications.whatsapp.enabled">
                            <span>Aktifkan Notifikasi WhatsApp</span>
                        </label>
                    </div>

                    <div x-show="client.state.form.notifications.whatsapp.enabled" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">GOWA Service URL</label>
                            <input type="url" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                   x-model="client.state.form.notifications.whatsapp.base_url"
                                   placeholder="http://localhost:3000">
                            <p class="text-xs text-gray-500 mt-1">URL untuk go-whatsapp-web-multidevice service</p>
                        </div>

                        <div class="grid md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Basic Auth User (Optional)</label>
                                <input type="text" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                       x-model="client.state.form.notifications.whatsapp.basic_user"
                                       placeholder="admin">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Basic Auth Password (Optional)</label>
                                <input type="password" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                       x-model="client.state.form.notifications.whatsapp.basic_pass"
                                       placeholder="••••••••">
                            </div>
                        </div>

                        <div>
                            <button type="button"
                                    class="text-sm px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg flex items-center gap-2 transition-colors border border-gray-300"
                                    :disabled="client.state.gowaDevices?.loading"
                                    @click="client.checkGowaConnection()">
                                <svg class="w-4 h-4" :class="client.state.gowaDevices?.loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span x-text="client.state.gowaDevices?.loading ? 'Memeriksa Koneksi...' : 'Cek Koneksi & Load Devices'"></span>
                            </button>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Device ID</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono"
                                    x-model="client.state.form.notifications.whatsapp.device_id"
                                    :disabled="client.state.gowaDevices?.loading">
                                <option value="">-- Pilih Device --</option>
                                <template x-for="device in (client.state.gowaDevices?.list || [])" :key="device.id">
                                    <option :value="device.id" x-text="`${device.display_name} (${device.state}) - ${device.jid}`"></option>
                                </template>
                            </select>
                            <p class="text-xs mt-1" :class="client.state.gowaDevices?.error ? 'text-red-500' : 'text-gray-500'">
                                <span x-show="client.state.gowaDevices?.error" x-text="'Error: ' + client.state.gowaDevices?.error"></span>
                                <span x-show="!client.state.gowaDevices?.error && (client.state.gowaDevices?.list || []).length === 0">Klik tombol "Cek Koneksi" untuk memuat daftar device.</span>
                                <span x-show="!client.state.gowaDevices?.error && (client.state.gowaDevices?.list || []).length > 0" x-text="`${(client.state.gowaDevices?.list || []).length} device ditemukan.`"></span>
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-2">Milestone Aktif</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <template x-for="milestone in whatsappMilestones" :key="milestone.key">
                                    <label class="inline-flex items-center gap-2 text-xs text-gray-700 bg-white px-3 py-2 rounded border border-gray-200">
                                        <input type="checkbox" 
                                               class="rounded border-gray-300"
                                               :value="milestone.key"
                                               x-model="client.state.form.notifications.whatsapp.enabled_milestones">
                                        <span x-text="milestone.label"></span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        {{-- Template Editor Tabs --}}
                        <div class="border-t border-gray-200 pt-4 mt-4">
                            <div class="flex items-center justify-between mb-3">
                                <label class="block text-sm font-medium text-gray-700">Template Pesan WhatsApp</label>
                                <button type="button"
                                        class="text-xs px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg flex items-center gap-1 transition-colors border border-gray-300"
                                        :disabled="client.state.templateEditor?.loading"
                                        @click="loadAllTemplates()">
                                    <svg class="w-3 h-3" :class="client.state.templateEditor?.loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    <span>Refresh</span>
                                </button>
                            </div>

                            {{-- Category Tabs --}}
                            <div class="flex flex-wrap gap-1 mb-4 border-b border-gray-200">
                                <template x-for="(label, cat) in (client.state.templateEditor?.categories || {})" :key="cat">
                                    <button type="button"
                                            class="px-3 py-2 text-xs font-medium rounded-t-lg transition-colors border-b-2 -mb-px"
                                            :class="client.state.templateEditor?.activeCategory === cat 
                                                ? 'bg-blue-50 text-blue-700 border-blue-500' 
                                                : 'text-gray-600 hover:bg-gray-100 border-transparent'"
                                            @click="client.state.templateEditor.activeCategory = cat"
                                            x-text="label">
                                    </button>
                                </template>
                            </div>

                            {{-- Template List for Active Category --}}
                            <div class="space-y-3" x-show="client.state.templateEditor?.activeCategory">
                                <p class="text-xs text-gray-500">
                                    Gunakan placeholder dalam kurung kurawal seperti <code class="bg-gray-100 px-1 py-0.5 rounded text-gray-700">{placeholder}</code> untuk data dinamis.
                                </p>

                                <template x-for="(template, key) in (client.state.templateEditor?.templates?.[client.state.templateEditor?.activeCategory] || {})" :key="key">
                                    <div class="border border-gray-200 rounded-lg p-3 bg-white">
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="block text-xs font-medium text-gray-700" 
                                                   x-text="client.state.templateEditor?.labels?.[client.state.templateEditor?.activeCategory]?.[key] || key"></label>
                                            <div class="flex gap-1">
                                                <button type="button"
                                                        class="px-2 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 transition-colors"
                                                        @click="previewTemplate(client.state.templateEditor?.activeCategory, key)"
                                                        title="Preview template">
                                                    👁️ Preview
                                                </button>
                                                <button type="button"
                                                        class="px-2 py-1 text-xs font-medium text-orange-600 bg-orange-50 rounded hover:bg-orange-100 transition-colors"
                                                        @click="resetTemplate(client.state.templateEditor?.activeCategory, key)"
                                                        title="Reset ke default">
                                                    🔄 Reset
                                                </button>
                                                <button type="button"
                                                        class="px-2 py-1 text-xs font-medium text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition-colors"
                                                        @click="saveTemplate(client.state.templateEditor?.activeCategory, key)"
                                                        title="Simpan template ini">
                                                    💾 Simpan
                                                </button>
                                                <button type="button"
                                                        class="px-2 py-1 text-xs font-medium text-green-600 bg-green-50 rounded hover:bg-green-100 transition-colors disabled:opacity-50"
                                                        :disabled="client.state.templateEditor?.sending?.[client.state.templateEditor?.activeCategory + '_' + key] === true"
                                                        @click="sendTemplateTest(client.state.templateEditor?.activeCategory, key)"
                                                        title="Kirim test ke nomor WhatsApp">
                                                    <span x-show="client.state.templateEditor?.sending?.[client.state.templateEditor?.activeCategory + '_' + key] !== true">📤 Kirim</span>
                                                    <span x-show="client.state.templateEditor?.sending?.[client.state.templateEditor?.activeCategory + '_' + key] === true">⏳</span>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        {{-- Placeholder Reference --}}
                                        <div class="mb-2" x-show="(client.state.templateEditor?.placeholders?.[client.state.templateEditor?.activeCategory]?.[key] || []).length > 0">
                                            <div class="flex flex-wrap gap-1">
                                                <span class="text-xs text-gray-500">Placeholder:</span>
                                                <template x-for="ph in (client.state.templateEditor?.placeholders?.[client.state.templateEditor?.activeCategory]?.[key] || [])" :key="ph">
                                                    <code class="text-xs bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded cursor-pointer hover:bg-blue-100"
                                                          @click="$refs['textarea_' + client.state.templateEditor?.activeCategory + '_' + key]?.focus(); document.execCommand('insertText', false, '{' + ph + '}')"
                                                          x-text="'{' + ph + '}'"></code>
                                                </template>
                                            </div>
                                        </div>

                                        <textarea 
                                            :x-ref="'textarea_' + client.state.templateEditor?.activeCategory + '_' + key"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            rows="4"
                                            x-model="client.state.templateEditor.templates[client.state.templateEditor?.activeCategory][key]"
                                            placeholder="Masukkan template pesan..."></textarea>
                                        
                                        {{-- Preview Output --}}
                                        <div x-show="client.state.templateEditor?.previews?.[client.state.templateEditor?.activeCategory + '_' + key]" 
                                             class="mt-2 p-3 bg-gray-100 rounded-lg">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-xs font-medium text-gray-600">Preview:</span>
                                                <button type="button" 
                                                        class="text-xs text-gray-500 hover:text-gray-700"
                                                        @click="delete client.state.templateEditor.previews[client.state.templateEditor?.activeCategory + '_' + key]">
                                                    ✕ Tutup
                                                </button>
                                            </div>
                                            <pre class="text-xs text-gray-800 whitespace-pre-wrap font-mono" 
                                                 x-text="client.state.templateEditor?.previews?.[client.state.templateEditor?.activeCategory + '_' + key]"></pre>
                                        </div>

                                        {{-- Status Message --}}
                                        <div x-show="client.state.templateEditor?.status?.[client.state.templateEditor?.activeCategory + '_' + key]" 
                                             class="mt-2 text-xs"
                                             :class="client.state.templateEditor?.status?.[client.state.templateEditor?.activeCategory + '_' + key]?.success ? 'text-green-600' : 'text-red-600'"
                                             x-text="client.state.templateEditor?.status?.[client.state.templateEditor?.activeCategory + '_' + key]?.message"></div>
                                    </div>
                                </template>

                                {{-- Empty State --}}
                                <div x-show="Object.keys(client.state.templateEditor?.templates?.[client.state.templateEditor?.activeCategory] || {}).length === 0"
                                     class="text-center py-8 text-gray-500 text-sm">
                                    <p>Tidak ada template untuk kategori ini.</p>
                                    <button type="button" 
                                            class="mt-2 text-blue-600 hover:underline"
                                        @click="loadAllTemplates()">
                                        Muat template
                                    </button>
                                </div>
                            </div>

                            {{-- Initial State --}}
                            <div x-show="!client.state.templateEditor?.activeCategory" class="text-center py-8 text-gray-500 text-sm">
                                <p>Klik tombol "Refresh" untuk memuat template.</p>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-gray-200">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Test WhatsApp</label>
                            <p class="text-xs text-gray-500 mb-2">Masukkan nomor untuk test semua milestone</p>
                            <div class="flex gap-2">
                                <input type="text" 
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                       x-model="client.state.notificationsTest.whatsapp.target"
                                       placeholder="08123456789 atau +6285956592404">
                                <button type="button"
                                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50"
                                        :disabled="client.state.notificationsTest.whatsapp.loading"
                                        @click="client.testNotification('whatsapp')">
                                    <span x-show="!client.state.notificationsTest.whatsapp.loading">Test General</span>
                                    <span x-show="client.state.notificationsTest.whatsapp.loading">Sending...</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-800">
                        <strong>Persyaratan:</strong>
                        <ul class="list-disc ml-4 mt-1 space-y-1">
                            <li>Install <a href="https://github.com/aldinokemal/go-whatsapp-web-multidevice" target="_blank" class="underline">go-whatsapp-web-multidevice</a></li>
                            <li>Scan QR code untuk autentikasi WhatsApp</li>
                            <li>Pastikan service berjalan di URL yang dikonfigurasi</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer with Save Button --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
        <div>
            <p class="text-sm" 
               :class="client.state.sectionStatus['notifications']?.intentClass" 
               x-text="client.state.sectionStatus['notifications']?.message" 
               x-show="client.state.sectionStatus['notifications']?.message"></p>
            <p class="text-xs text-red-600" 
               x-text="client.state.sectionErrors['notifications']" 
               x-show="client.state.sectionErrors['notifications']"></p>
        </div>
        <button type="button"
                class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                :disabled="client.state.loadingSections['notifications']"
                @click="client.saveSection('notifications')">
            <span x-show="!client.state.loadingSections['notifications']">Simpan Pengaturan</span>
            <span x-show="client.state.loadingSections['notifications']">Menyimpan...</span>
        </button>
    </div>
</div>
