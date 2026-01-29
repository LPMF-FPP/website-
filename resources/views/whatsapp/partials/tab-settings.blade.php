<div x-data="{
    form: {
        base_url: '',
        basic_user: '',
        basic_pass: '',
        device_id: '',
        enabled: false,
        enabled_milestones: []
    },
    devices: [],
    testMessage: { phone: '', message: '' },
    loadingDevices: false,
    sendingTest: false,
    
    // Templates
    templates: {},
    categories: {},
    labels: {},
    placeholders: {},
    activeCategory: 'milestone',
    loadingTemplates: false,
    previews: {},
    statusMessages: {},
    
    init() {
        if (this.settingsData) {
            this.form = { ...this.form, ...this.settingsData };
        }
        this.$watch('settingsData', val => {
            if (val) this.form = { ...this.form, ...val };
        });
        
        // Load templates on init
        this.loadTemplates();
        this.fetchDevices();
    },

    async saveSettings() {
        try {
            const res = await fetch('{{ route("whatsapp.settings.save") }}', {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(this.form)
            });
            if (res.ok) {
                alert('Settings saved successfully');
            } else {
                alert('Failed to save settings');
            }
        } catch(e) { console.error(e); alert('Error saving settings'); }
    },

    async fetchDevices() {
        this.loadingDevices = true;
        try {
            const res = await fetch('{{ route("whatsapp.settings.devices") }}');
            const data = await res.json();
            if (data.success) {
                this.devices = data.devices;
            } else {
                // Ignore error on init if just checking
                if (this.devices.length === 0) console.warn('Failed to fetch devices', data.error);
            }
        } catch(e) { console.error(e); }
        finally { this.loadingDevices = false; }
    },

    async sendTest() {
        if (!this.testMessage.phone || !this.testMessage.message) {
            alert('Phone and message are required');
            return;
        }
        this.sendingTest = true;
        try {
            const res = await fetch('{{ route("whatsapp.settings.test-message") }}', {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(this.testMessage)
            });
            const data = await res.json();
            if (res.ok && data.success) {
                alert('Message sent! ID: ' + data.message_id);
                this.testMessage.message = '';
            } else {
                alert('Failed to send: ' + (data.error || 'Unknown error'));
            }
        } catch(e) { console.error(e); alert('Error sending test message'); }
        finally { this.sendingTest = false; }
    },

    // Templates Logic
    async loadTemplates() {
        this.loadingTemplates = true;
        try {
            const res = await fetch('{{ route("whatsapp.settings.templates") }}');
            const data = await res.json();
            this.templates = data.templates;
            this.categories = data.categories;
            this.labels = data.labels;
            this.placeholders = data.placeholders;
        } catch(e) { console.error(e); alert('Error loading templates'); }
        finally { this.loadingTemplates = false; }
    },

    async saveTemplate(category, key) {
        try {
            const res = await fetch('{{ route("whatsapp.settings.templates.save") }}', {
                method: 'PUT',
                headers: { 
                    'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    templates: {
                        [category]: {
                            [key]: this.templates[category][key]
                        }
                    }
                })
            });
            if (res.ok) {
                this.statusMessages[`${category}_${key}`] = { success: true, message: 'Saved!' };
                setTimeout(() => delete this.statusMessages[`${category}_${key}`], 3000);
            } else {
                this.statusMessages[`${category}_${key}`] = { success: false, message: 'Failed to save' };
            }
        } catch(e) { console.error(e); alert('Error saving template'); }
    },

    async resetTemplate(category, key) {
        if (!confirm('Reset template to default?')) return;
        try {
            const res = await fetch('{{ route("whatsapp.settings.templates.reset") }}', {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ category, key })
            });
            const data = await res.json();
            if (res.ok) {
                this.templates[category][key] = data.template;
                this.statusMessages[`${category}_${key}`] = { success: true, message: 'Reset!' };
                setTimeout(() => delete this.statusMessages[`${category}_${key}`], 3000);
            }
        } catch(e) { console.error(e); alert('Error resetting template'); }
    },

    async previewTemplate(category, key) {
        try {
            const res = await fetch('{{ route("whatsapp.settings.templates.preview") }}', {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ 
                    category, 
                    key,
                    template: this.templates[category][key]
                })
            });
            const data = await res.json();
            if (res.ok) {
                this.previews[`${category}_${key}`] = data.preview;
            }
        } catch(e) { console.error(e); alert('Error previewing template'); }
    },
    
    insertPlaceholder(category, key, placeholder) {
        const textarea = document.getElementById(`textarea_${category}_${key}`);
        if (textarea) {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = this.templates[category][key];
            const before = text.substring(0, start);
            const after = text.substring(end, text.length);
            this.templates[category][key] = before + '{' + placeholder + '}' + after;
            
            // Restore focus and selection
            this.$nextTick(() => {
                textarea.focus();
                textarea.setSelectionRange(start + placeholder.length + 2, start + placeholder.length + 2);
            });
        }
    }
}">
    <div class="space-y-8">
        
        <!-- Section 1: GOWA Configuration -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100 mb-4">GOWA Configuration</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <form @submit.prevent="saveSettings" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Base URL</label>
                        <input type="url" x-model="form.base_url" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Basic Auth User (Optional)</label>
                        <input type="text" x-model="form.basic_user" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Basic Auth Password (Optional)</label>
                        <input type="password" x-model="form.basic_pass" placeholder="Leave blank to keep unchanged" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Device ID</label>
                        <input type="text" x-model="form.device_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            Save Configuration
                        </button>
                    </div>
                </form>

                <!-- Devices List -->
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Connected Devices</h4>
                        <button @click="fetchDevices" class="text-xs text-primary-600 hover:text-primary-500">Refresh</button>
                    </div>
                    
                    <div x-show="loadingDevices" class="text-center py-4 text-gray-500 text-sm">Loading devices...</div>
                    
                    <div x-show="!loadingDevices" class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 max-h-60 overflow-y-auto">
                        <template x-if="devices.length === 0">
                            <p class="text-xs text-gray-500 text-center">No devices found.</p>
                        </template>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            <template x-for="device in devices" :key="device.id">
                                <li class="py-2 flex justify-between items-center">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="device.name || 'Unnamed'"></p>
                                        <p class="text-xs text-gray-500" x-text="device.device_id"></p>
                                    </div>
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800">Connected</span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Template Editor -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">Template Pesan</h3>
                <button @click="loadTemplates" class="text-sm text-primary-600 hover:text-primary-500">Refresh Templates</button>
            </div>

            <div x-show="loadingTemplates" class="text-center py-8 text-gray-500">Loading templates...</div>

            <div x-show="!loadingTemplates">
                <!-- Category Tabs -->
                <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                    <nav class="-mb-px flex space-x-4 overflow-x-auto">
                        <template x-for="(label, cat) in categories" :key="cat">
                            <button @click="activeCategory = cat"
                                    :class="activeCategory === cat ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors"
                                    x-text="label">
                            </button>
                        </template>
                    </nav>
                </div>

                <!-- Templates List -->
                <div class="space-y-6">
                    <template x-if="templates[activeCategory]">
                        <template x-for="(template, key) in templates[activeCategory]" :key="key">
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-900/30">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium text-gray-900 dark:text-gray-100" 
                                           x-text="labels[activeCategory]?.[key] || key"></label>
                                    <div class="flex gap-2">
                                        <button @click="previewTemplate(activeCategory, key)" class="text-xs px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">Preview</button>
                                        <button @click="resetTemplate(activeCategory, key)" class="text-xs px-2 py-1 bg-orange-100 text-orange-700 rounded hover:bg-orange-200 transition-colors">Reset</button>
                                        <button @click="saveTemplate(activeCategory, key)" class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors">Simpan</button>
                                    </div>
                                </div>

                                <!-- Placeholders -->
                                <div class="mb-2 flex flex-wrap gap-1" x-show="placeholders[activeCategory]?.[key]?.length > 0">
                                    <span class="text-xs text-gray-500 mr-1">Placeholders:</span>
                                    <template x-for="ph in placeholders[activeCategory]?.[key]" :key="ph">
                                        <button @click="insertPlaceholder(activeCategory, key, ph)" 
                                                class="text-xs bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-1.5 py-0.5 rounded cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-900/50"
                                                x-text="'{' + ph + '}'"></button>
                                    </template>
                                </div>

                                <textarea :id="`textarea_${activeCategory}_${key}`"
                                          x-model="templates[activeCategory][key]"
                                          rows="4"
                                          class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"></textarea>

                                <!-- Preview & Status -->
                                <div class="mt-2 flex justify-between items-start">
                                    <div class="text-xs" :class="statusMessages[`${activeCategory}_${key}`]?.success ? 'text-green-600' : 'text-red-600'" 
                                         x-text="statusMessages[`${activeCategory}_${key}`]?.message"></div>
                                    
                                    <div x-show="previews[`${activeCategory}_${key}`]" class="bg-gray-100 dark:bg-gray-800 p-2 rounded text-xs font-mono w-full mt-2 relative">
                                        <button @click="delete previews[`${activeCategory}_${key}`]" class="absolute top-1 right-2 text-gray-400 hover:text-gray-600">×</button>
                                        <div class="whitespace-pre-wrap" x-text="previews[`${activeCategory}_${key}`]"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </template>
                    <template x-if="!templates[activeCategory] || Object.keys(templates[activeCategory]).length === 0">
                        <div class="text-center text-gray-500 py-4">Tidak ada template di kategori ini.</div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Section 3: Test Message -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100 mb-4">Send Test Message</h3>
            <form @submit.prevent="sendTest" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone Number (e.g. 62812...)</label>
                    <input type="text" x-model="testMessage.phone" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Message</label>
                    <textarea x-model="testMessage.message" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"></textarea>
                </div>
                <button type="submit" :disabled="sendingTest" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50">
                    <span x-show="!sendingTest">Send Test</span>
                    <span x-show="sendingTest">Sending...</span>
                </button>
            </form>
        </div>

    </div>
</div>
