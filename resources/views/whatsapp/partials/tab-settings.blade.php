<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">Settings</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Konfigurasi WhatsApp Hub: Quick Test, Templates, GOWA, Whitelist Admin, dan Alerts.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="initSettingsTab()"
                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-650 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors shadow-sm"
                    >
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Sub Tabs -->
            <div class="mt-4 border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Settings tabs">
                    <template x-for="tab in settingsSubTabs" :key="tab.id">
                        <button
                            type="button"
                            @click="activeSettingsTab = tab.id"
                            :class="activeSettingsTab === tab.id
                                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                            class="whitespace-nowrap py-2.5 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span class="text-base" x-text="tab.icon"></span>
                                <span x-text="tab.label"></span>
                            </span>
                        </button>
                    </template>
                </nav>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <!-- Quick Test -->
            <div x-show="activeSettingsTab === 'quick-test'" x-transition.opacity>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Test Message -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-900/30">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Send Test Message</h4>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Quick sanity check untuk device & koneksi.</p>
                            </div>
                        </div>

                        <form @submit.prevent="sendTestMessage" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone Number (contoh: 62812...)</label>
                                <input
                                    type="text"
                                    x-model="testMessage.phone"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Message</label>
                                <textarea
                                    x-model="testMessage.message"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                ></textarea>
                            </div>
                            <button
                                type="submit"
                                :disabled="sendingTest"
                                class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 transition-all duration-200"
                            >
                                <span x-show="!sendingTest">Send Test</span>
                                <span x-show="sendingTest">Sending...</span>
                            </button>
                        </form>
                    </div>

                    <!-- Connected Devices -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-900/30">
                        <div class="flex justify-between items-center mb-3">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Connected Devices</h4>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Status device yang terhubung ke GOWA.</p>
                            </div>
                            <button
                                type="button"
                                @click="fetchDevices"
                                :disabled="loadingDevices"
                                class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-650 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 transition-colors shadow-sm"
                            >
                                <span x-show="!loadingDevices">Refresh</span>
                                <span x-show="loadingDevices">Loading...</span>
                            </button>
                        </div>

                        <div x-show="loadingDevices" class="text-center py-4 text-gray-500 text-sm">Loading devices...</div>

                        <div x-show="!loadingDevices" class="bg-white dark:bg-gray-800 rounded-lg p-3 max-h-60 overflow-y-auto border border-gray-200 dark:border-gray-700">
                            <template x-if="devices.length === 0">
                                <p class="text-xs text-gray-500 text-center">No devices found.</p>
                            </template>
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                <template x-for="device in devices" :key="device.id">
                                    <li class="py-2 flex justify-between items-center">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100"
                                               x-text="device.display_name || device.name || device.jid || device.id || 'Unknown Device'"></p>
                                            <p class="text-xs text-gray-500 font-mono" x-text="device.jid || device.id"></p>
                                        </div>
                                        <span class="px-2 py-0.5 text-xs rounded-full"
                                              :class="device.state === 'logged_in'
                                                  ? 'bg-green-100 text-green-800'
                                                  : 'bg-yellow-100 text-yellow-800'"
                                              x-text="device.state === 'logged_in' ? 'Connected' : (device.state || 'Unknown')"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Templates -->
            <div x-show="activeSettingsTab === 'templates'" x-transition.opacity>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-900/30">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Template Pesan</h4>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Edit satu template sekaligus untuk mengurangi scroll.</p>
                        </div>
                        <button
                            type="button"
                            @click="loadTemplates"
                            :disabled="loadingTemplates"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-650 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 transition-colors shadow-sm"
                        >
                            <span x-show="!loadingTemplates">Refresh</span>
                            <span x-show="loadingTemplates">Loading...</span>
                        </button>
                    </div>

                    <div x-show="loadingTemplates" class="text-center py-6 text-gray-500 text-sm">Loading templates...</div>

                    <div x-show="!loadingTemplates" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                                <select
                                    x-model="activeCategory"
                                    @change="onTemplateCategoryChanged()"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                >
                                    <template x-for="(label, cat) in categories" :key="cat">
                                        <option :value="cat" x-text="label"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Template</label>
                                <select
                                    x-model="activeTemplateKey"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                >
                                    <template x-for="key in Object.keys(templates?.[activeCategory] || {})" :key="key">
                                        <option :value="key" x-text="labels?.[activeCategory]?.[key] || key"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <template x-if="!activeTemplateKey">
                            <div class="text-sm text-gray-500">Tidak ada template untuk kategori ini.</div>
                        </template>

                        <template x-if="activeTemplateKey">
                            <div>
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-2">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="labels?.[activeCategory]?.[activeTemplateKey] || activeTemplateKey"></p>
                                        <p class="text-xs text-gray-500" x-text="activeCategory"></p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <x-button @click="previewTemplate(activeCategory, activeTemplateKey)" variant="outline" size="xs">Preview</x-button>
                                        <x-button @click="resetTemplate(activeCategory, activeTemplateKey)" variant="warning" size="xs">Reset</x-button>
                                        <x-button @click="saveTemplate(activeCategory, activeTemplateKey)" variant="primary" size="xs">Simpan</x-button>
                                    </div>
                                </div>

                                <!-- Placeholders -->
                                <div class="mb-2 flex flex-wrap gap-1" x-show="(placeholders?.[activeCategory]?.[activeTemplateKey] || []).length > 0">
                                    <span class="text-xs text-gray-500 mr-1">Placeholders:</span>
                                    <template x-for="ph in (placeholders?.[activeCategory]?.[activeTemplateKey] || [])" :key="ph">
                                        <button
                                            type="button"
                                            @click="insertPlaceholder(activeCategory, activeTemplateKey, ph)"
                                            class="text-xs bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-1.5 py-0.5 rounded hover:bg-blue-100 dark:hover:bg-blue-900/50"
                                            x-text="'{' + ph + '}'"
                                        ></button>
                                    </template>
                                </div>

                                <textarea
                                    id="template_editor_textarea"
                                    x-model="templates[activeCategory][activeTemplateKey]"
                                    rows="8"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
                                ></textarea>

                                <div class="mt-2">
                                    <div
                                        class="text-xs"
                                        :class="statusMessages[`${activeCategory}_${activeTemplateKey}`]?.success ? 'text-green-600' : 'text-red-600'"
                                        x-text="statusMessages[`${activeCategory}_${activeTemplateKey}`]?.message"
                                    ></div>

                                    <div x-show="previews[`${activeCategory}_${activeTemplateKey}`]" class="mt-2 bg-white dark:bg-gray-800 p-3 rounded border border-gray-200 dark:border-gray-700 text-xs font-mono relative">
                                        <button type="button" @click="delete previews[`${activeCategory}_${activeTemplateKey}`]" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600">×</button>
                                        <div class="whitespace-pre-wrap" x-text="previews[`${activeCategory}_${activeTemplateKey}`]"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- GOWA -->
            <div x-show="activeSettingsTab === 'gowa'" x-transition.opacity>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-900/30">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">GOWA Configuration</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Set sekali, jarang berubah. Wajib benar agar WhatsApp Hub berjalan.</p>

                    <form @submit.prevent="saveSettings" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Base URL</label>
                                <input type="url" x-model="settingsForm.base_url" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Device ID</label>
                                <input type="text" x-model="settingsForm.device_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Basic Auth User (Optional)</label>
                                <input type="text" x-model="settingsForm.basic_user" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Basic Auth Password (Optional)</label>
                                <input type="password" x-model="settingsForm.basic_pass" placeholder="Leave blank to keep unchanged" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>
                        </div>

                        <div class="pt-1">
                            <x-button type="submit" variant="primary" block>
                                Save Configuration
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- AI -->
            <div x-show="activeSettingsTab === 'ai'" x-transition.opacity>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-900/30 space-y-5">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">AI Configuration</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Atur provider/model AI untuk fitur compose pesan WhatsApp, lalu jalankan test respons.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Provider</label>
                            <select x-model="settingsForm.ai_provider" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                <option value="openai">OpenAI</option>
                                <option value="openrouter">OpenRouter</option>
                                <option value="deepseek">DeepSeek</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Model</label>
                            <input type="text" x-model="settingsForm.ai_model" list="ai-model-options" placeholder="gpt-5.3-codex"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            <datalist id="ai-model-options">
                                <option value="gpt-5-codex"></option>
                                <option value="gpt-5.1"></option>
                                <option value="gpt-5.2"></option>
                                <option value="gpt-5.3-codex"></option>
                                <option value="gpt-5-codex-mini"></option>
                                <option value="gpt-5.1-codex"></option>
                                <option value="gpt-5.1-codex-mini"></option>
                                <option value="gpt-5.1-codex-max"></option>
                                <option value="gpt-5.2-codex"></option>
                                <option value="gpt-5.3-codex-spark"></option>
                            </datalist>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Bisa pilih dari list atau isi manual sesuai provider AI.</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Base URL API</label>
                            <input type="url" x-model="settingsForm.ai_base_url" placeholder="https://api.openai.com/v1"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">API Key</label>
                            <input type="password" x-model="settingsForm.ai_api_key" placeholder="Kosongkan jika tidak ingin mengubah"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="settingsForm.ai_api_key_configured">API key sudah tersimpan. Isi field jika ingin ganti.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <x-button type="button" variant="primary" @click="saveSettings">Simpan Konfigurasi AI</x-button>
                    </div>

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">AI Test</h5>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Rekomendasi: simpan dulu konfigurasi AI, lalu jalankan test.</p>

                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Prompt Test</label>
                                <textarea x-model="aiTest.prompt" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"></textarea>
                            </div>

                            <x-button type="button" variant="success" @click="sendAiTest" :disabled="sendingAiTest" block>
                                <span x-show="!sendingAiTest">Jalankan AI Test</span>
                                <span x-show="sendingAiTest">Menjalankan...</span>
                            </x-button>

                            <div x-show="aiTest.error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="aiTest.error"></div>

                            <div x-show="aiTest.result" class="rounded-md border border-green-200 bg-green-50 px-3 py-2">
                                <p class="text-xs font-semibold text-green-700 mb-1">Hasil AI</p>
                                <pre class="whitespace-pre-wrap text-sm text-green-900" x-text="aiTest.result"></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Whitelist -->
            <div x-show="activeSettingsTab === 'whitelist'" x-transition.opacity>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-900/30">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Admin Whitelist</h4>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Daftar admin yang menerima alert dan boleh menjalankan command tertentu.</p>
                        </div>
                        <button
                            type="button"
                            @click="fetchWhitelist"
                            :disabled="loadingWhitelist"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-650 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 transition-colors shadow-sm"
                        >
                            <span x-show="!loadingWhitelist">Refresh</span>
                            <span x-show="loadingWhitelist">Loading...</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Add Form -->
                        <div class="lg:col-span-1">
                            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Tambah Admin</h5>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Format: 08xx atau 62xx.</p>

                                <div class="mt-3 space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                                        <input
                                            type="text"
                                            x-model="whitelistForm.phone"
                                            placeholder="0812... / 62812..."
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name (optional)</label>
                                        <input
                                            type="text"
                                            x-model="whitelistForm.name"
                                            placeholder="Nama admin"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                                        >
                                    </div>

                                    <button
                                        type="button"
                                        @click="addWhitelist"
                                        :disabled="addingWhitelist"
                                        class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 transition-colors"
                                    >
                                        <span x-show="!addingWhitelist">Tambah</span>
                                        <span x-show="addingWhitelist">Menyimpan...</span>
                                    </button>

                                    <div x-show="whitelistError" class="text-sm text-red-600" x-text="whitelistError"></div>
                                    <div x-show="whitelistSuccess" class="text-sm text-green-600" x-text="whitelistSuccess"></div>
                                </div>
                            </div>
                        </div>

                        <!-- List -->
                        <div class="lg:col-span-2">
                            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Daftar Penerima</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Alert dikirim ke whitelist + super admin fallback.</p>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-900/30">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Name</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Phone</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Added By</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Created</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            <!-- Super Admin Row -->
                                            <template x-if="whitelistData?.super_admin">
                                                <tr class="bg-green-50 dark:bg-green-900/10">
                                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">Super Admin</td>
                                                    <td class="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-200" x-text="whitelistData.super_admin.phone_number"></td>
                                                    <td class="px-4 py-3 text-sm text-gray-500">System</td>
                                                    <td class="px-4 py-3 text-sm text-gray-500">-</td>
                                                    <td class="px-4 py-3 text-right">
                                                        <span class="text-xs text-gray-500">Protected</span>
                                                    </td>
                                                </tr>
                                            </template>

                                            <template x-if="!whitelistData?.whitelist || whitelistData.whitelist.length === 0">
                                                <tr>
                                                    <td colspan="5" class="px-4 py-6 text-sm text-gray-500 text-center">Belum ada admin tambahan di whitelist.</td>
                                                </tr>
                                            </template>

                                            <template x-for="item in (whitelistData?.whitelist || [])" :key="item.id">
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100" x-text="item.name || '-' "></td>
                                                    <td class="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-200" x-text="item.phone_number"></td>
                                                    <td class="px-4 py-3 text-sm text-gray-500" x-text="item.added_by || '-' "></td>
                                                    <td class="px-4 py-3 text-sm text-gray-500" x-text="item.created_at_human || '-' "></td>
                                                    <td class="px-4 py-3 text-right">
                                                        <button
                                                            type="button"
                                                            @click="removeWhitelist(item)"
                                                            class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-md border border-red-300 text-red-700 bg-white hover:bg-red-50"
                                                        >
                                                            Remove
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
            </div>

            <!-- Alerts -->
            <div x-show="activeSettingsTab === 'alerts'" x-transition.opacity>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-900/30">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Inventory Alerts</h4>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Atur threshold expiry. Penerima: whitelist + super admin fallback.</p>
                        </div>
                        <button
                            type="button"
                            @click="activeSettingsTab = 'whitelist'"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 text-xs font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-650 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors shadow-sm"
                        >
                            Kelola Whitelist
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Expiry Alert Threshold (days)</label>
                            <input
                                type="number"
                                min="1"
                                max="365"
                                x-model.number="settingsForm.inventory_alert_expiry_days"
                                class="mt-1 block w-40 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                            >
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Alert dikirim untuk lot yang akan kadaluarsa dalam X hari ke depan.</p>
                        </div>

                        <div class="flex items-end">
                            <x-button type="button" variant="primary" block @click="saveSettings">
                                Save Settings
                            </x-button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
