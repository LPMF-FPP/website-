<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="WhatsApp Hub"
            :breadcrumbs="[['label' => 'WhatsApp Hub']]"
        >
            <x-slot name="actions">
                <div class="flex items-center gap-2" x-data="whatsappHeader({ initialConnected: @js($initialConnectionStatus ?? false) })" x-init="start()">
                    <!-- Connection Status Indicator -->
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <div class="w-2.5 h-2.5 rounded-full" :class="indicatorClass"></div>
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300" x-text="connectionLabel"></span>
                    </div>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-6"
         x-data="whatsappHub()"
         x-init="init()">

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <template x-for="tab in tabs" :key="tab.id">
                    <button
                        @click="activeTab = tab.id; loadTabData(tab.id)"
                        :class="activeTab === tab.id
                            ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
                    >
                        <div class="flex items-center gap-2">
                            <span x-html="tab.icon"></span>
                            <span x-text="tab.label"></span>
                        </div>
                    </button>
                </template>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="min-h-[400px]">
            <!-- Loading State -->
            <div x-show="loading" class="flex justify-center items-center py-12">
                <svg class="animate-spin h-8 w-8 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            <!-- Content Panels -->
            <div x-show="!loading" x-transition.opacity>
                <!-- Overview Tab -->
                <div x-show="activeTab === 'overview'">
                    @include('whatsapp.partials.tab-overview')
                </div>

                <!-- Tasks Tab -->
                <div x-show="activeTab === 'tasks'">
                    @include('whatsapp.partials.tab-tasks')
                </div>

                <!-- Broadcasts Tab -->
                <div x-show="activeTab === 'broadcasts'">
                    @include('whatsapp.partials.tab-broadcasts')
                </div>

                <!-- Reminders Tab -->
                <div x-show="activeTab === 'reminders'">
                    @include('whatsapp.partials.tab-reminders')
                </div>

                <!-- Logs Tab -->
                <div x-show="activeTab === 'logs'">
                    @include('whatsapp.partials.tab-logs')
                </div>

                <!-- Inventory Alerts Tab -->
                <div x-show="activeTab === 'inventory_alerts'">
                    @include('whatsapp.partials.tab-inventory-alerts')
                </div>

                <!-- Settings Tab -->
                <div x-show="activeTab === 'settings'">
                    @include('whatsapp.partials.tab-settings')
                </div>
            </div>
        </div>

        <!-- Modals -->
        @include('whatsapp.partials.modal-broadcast-form')
        @include('whatsapp.partials.modal-reminder-edit')
        @include('whatsapp.partials.modal-fetch-groups')
        @include('whatsapp.partials.modal-task-form')

    </div>

    @push('scripts')
    <script>
        function whatsappHeader(config = {}) {
            return {
                connected: Boolean(config.initialConnected),
                connectionLabel: Boolean(config.initialConnected) ? 'Connected' : 'Disconnected',
                indicatorClass: Boolean(config.initialConnected) ? 'bg-green-500' : 'bg-red-500',
                failedChecks: 0,
                lastSuccessAt: Boolean(config.initialConnected) ? Date.now() : null,
                start() {
                    this.checkConnection();
                    // Poll connection status every 30s
                    setInterval(() => this.checkConnection(), 30000);
                },
                async checkConnection() {
                    try {
                        const res = await fetch('{{ route("whatsapp.connection") }}', {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        if (!res.ok) {
                            throw new Error(`Connection status request failed (${res.status})`);
                        }

                        const data = await res.json();
                        const connected = Boolean(data.connected ?? data.is_connected ?? false);
                        const reachable = Boolean(data.reachable ?? false);

                        this.connected = connected || reachable;
                        this.failedChecks = 0;
                        this.lastSuccessAt = Date.now();

                        if (this.connected) {
                            this.connectionLabel = 'Connected';
                            this.indicatorClass = 'bg-green-500';
                        } else {
                            this.connectionLabel = 'Disconnected';
                            this.indicatorClass = 'bg-red-500';
                        }
                    } catch (e) {
                        this.failedChecks += 1;

                        const hasRecentSuccess = this.lastSuccessAt && (Date.now() - this.lastSuccessAt) < 5 * 60 * 1000;

                        if (hasRecentSuccess) {
                            this.connected = true;
                            this.connectionLabel = 'Connected (syncing...)';
                            this.indicatorClass = 'bg-amber-500';
                            console.warn('WhatsApp connection check failed, preserving recent connected state', e);

                            return;
                        }

                        if (this.failedChecks >= 6) {
                            this.connected = false;
                            this.connectionLabel = 'Disconnected';
                            this.indicatorClass = 'bg-red-500';
                        } else if (this.failedChecks >= 2) {
                            this.connected = true;
                            this.connectionLabel = 'Connected (unstable)';
                            this.indicatorClass = 'bg-amber-500';
                        }

                        console.warn('Failed to refresh WhatsApp connection status', e);
                    }
                }
            }
        }

        function whatsappHub() {
            return {
                activeTab: 'overview',
                loading: false,
                tabs: [
                    { id: 'overview', label: 'Overview', icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>' },
                    { id: 'tasks', label: 'Tugas', icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>' },
                    { id: 'broadcasts', label: 'Broadcasts', icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>' },
                    { id: 'reminders', label: 'Reminders', icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' },
                    { id: 'logs', label: 'Logs', icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>' },
                    { id: 'inventory_alerts', label: 'Inventory Alerts', icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6m16 0H4"></path></svg>' },
                    { id: 'settings', label: 'Settings', icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>' }
                ],
                
                // Data for tabs
                overviewData: { stats: { sent_today: 0, pending_tasks: 0, scheduled: 0, failed_today: 0 }, recent_activity: [] },
                tasksData: { tasks: { data: [] }, stats: {}, users: [] },
                broadcastsData: { broadcasts: { data: [] }, statuses: {} },
                remindersData: { reminders: [] },
                logsData: { logs: { data: [] } },
                inventoryAlertsData: { expiry_days: 30, low_stock: [], expiring: [], recipients: [], history: { data: [] } },
                settingsData: null,

                // Inventory Alerts Tab State
                savingInventoryAlertRecipientIds: {},

                // Logs Tab State
                expandedBatch: null,
                batchDetails: {},
                loadingDetails: false,

                // Settings Tab State
                activeSettingsTab: 'quick-test',
                settingsSubTabs: [
                    { id: 'quick-test', label: 'Quick Test', icon: '⚡' },
                    { id: 'templates', label: 'Templates', icon: '📝' },
                    { id: 'gowa', label: 'GOWA', icon: '⚙️' },
                    { id: 'ai', label: 'AI', icon: '🤖' },
                    { id: 'whitelist', label: 'Whitelist', icon: '👥' },
                    { id: 'alerts', label: 'Alerts', icon: '🔔' },
                ],
                settingsInitialized: false,

                settingsForm: {
                    base_url: '',
                    basic_user: '',
                    basic_pass: '',
                    device_id: '',
                    enabled: false,
                    enabled_milestones: [],
                    inventory_alert_expiry_days: 30,
                    ai_provider: 'openai',
                    ai_base_url: '',
                    ai_model: '',
                    ai_api_key: '',
                    ai_api_key_configured: false
                },
                devices: [],
                testMessage: { phone: '', message: '' },
                loadingDevices: false,
                sendingTest: false,
                sendingAiTest: false,
                aiTest: {
                    prompt: 'Buatkan pesan WhatsApp singkat dan profesional untuk notifikasi laporan statistik periodik sudah terbit.',
                    result: '',
                    error: ''
                },

                // Whitelist Manager State
                whitelistData: { whitelist: [], super_admin: null },
                whitelistForm: { phone: '', name: '' },
                loadingWhitelist: false,
                addingWhitelist: false,
                whitelistError: '',
                whitelistSuccess: '',

                // Template Editor State
                templates: {},
                categories: {},
                labels: {},
                placeholders: {},
                activeCategory: 'milestone',
                activeTemplateKey: null,
                loadingTemplates: false,
                previews: {},
                statusMessages: {},

                formatDays(days) {
                    if (!Array.isArray(days) || days.length === 0) return 'Daily'; // Fallback
                    const allDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    if (days.length === 7) return 'Everyday';
                    
                    const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
                    const isWeekdays = days.length === 5 && days.every(d => weekdays.includes(d));
                    if (isWeekdays) return 'Weekdays';

                    const weekends = ['Sat', 'Sun'];
                    const isWeekends = days.length === 2 && days.every(d => weekends.includes(d));
                    if (isWeekends) return 'Weekends';

                    return days.join(', ');
                },

                init() {
                    const urlParams = new URLSearchParams(window.location.search);
                    const tab = urlParams.get('tab');
                    if (tab && this.tabs.find(t => t.id === tab)) {
                        this.activeTab = tab;
                    }

                    window.addEventListener('reminder-saved', () => {
                        this.loadTabData('reminders');
                    });

                    this.loadTabData(this.activeTab);

                    // Initialize settings form when settingsData is loaded
                    this.$watch('settingsData', (val) => {
                        if (val) {
                            this.settingsForm = { ...this.settingsForm, ...val };
                        }
                    });
                },

                openSettingsSubtab(subtabId) {
                    this.activeTab = 'settings';
                    this.activeSettingsTab = subtabId;
                    this.loadTabData('settings');
                },

                async loadTabData(tab, params = {}) {
                    this.loading = true;
                    // Update URL without reload
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tab);
                    window.history.pushState({}, '', url);

                    try {
                        const endpointByTab = {
                            overview: '{{ route("whatsapp.overview") }}',
                            tasks: '{{ route("whatsapp.tasks.index") }}',
                            broadcasts: '{{ route("whatsapp.broadcasts.index") }}',
                            reminders: '{{ route("whatsapp.reminders.index") }}',
                            logs: '{{ route("whatsapp.logs") }}',
                            inventory_alerts: '{{ route("whatsapp.inventory-alerts") }}',
                            settings: '{{ route("whatsapp.settings.index") }}',
                        };

                        const endpoint = endpointByTab[tab];
                        if (!endpoint) {
                            throw new Error(`Unknown tab: ${tab}`);
                        }

                        const query = new URLSearchParams();
                        if (tab === 'tasks') {
                            const filter = typeof params.filter === 'string' && params.filter !== ''
                                ? params.filter
                                : 'my_tasks';
                            const status = typeof params.status === 'string' && params.status !== ''
                                ? params.status
                                : 'active';

                            query.set('filter', filter);
                            query.set('status', status);
                        }

                        const requestUrl = query.toString() !== '' ? `${endpoint}?${query.toString()}` : endpoint;

                        const response = await fetch(requestUrl, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        if (!response.ok) {
                            throw new Error(`Failed to load tab ${tab} (${response.status})`);
                        }

                        const payload = await response.json();

                        switch(tab) {
                            case 'overview':
                                this.overviewData = payload;
                                break;
                            case 'tasks':
                                this.tasksData = payload;
                                break;
                            case 'broadcasts':
                                this.broadcastsData = payload;
                                break;
                            case 'reminders':
                                this.remindersData = payload;
                                break;
                            case 'logs':
                                this.logsData = payload;
                                break;
                            case 'inventory_alerts':
                                this.inventoryAlertsData = payload;
                                break;
                            case 'settings':
                                this.settingsData = payload;
                                this.initSettingsTab();
                                break;
                        }
                    } catch (error) {
                        console.error('Error loading tab data:', error);
                        alert('Gagal memuat data. Silakan coba lagi.');
                    } finally {
                        this.loading = false;
                    }
                },

                // --- Inventory Alerts Functions ---
                async setInventoryAlertRecipient(recipient, enabled) {
                    if (!recipient || !recipient.id) return;
                    if (recipient.is_super_admin) return;
                    if (this.savingInventoryAlertRecipientIds?.[recipient.id]) return;

                    const prev = !!recipient.receive_inventory_alerts;
                    recipient.receive_inventory_alerts = !!enabled;

                    this.savingInventoryAlertRecipientIds = {
                        ...(this.savingInventoryAlertRecipientIds || {}),
                        [recipient.id]: true,
                    };

                    try {
                        const url = '{{ route("whatsapp.settings.whitelist.inventory-alert", ["whitelist" => "__ID__"]) }}'.replace('__ID__', recipient.id);
                        const res = await fetch(url, {
                            method: 'PATCH',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ receive_inventory_alerts: !!enabled }),
                        });

                        const data = await res.json().catch(() => null);

                        if (!res.ok) {
                            recipient.receive_inventory_alerts = prev;
                            alert(data?.message || 'Gagal menyimpan penerima inventory alert.');
                            return;
                        }

                        if (data?.item && typeof data.item.receive_inventory_alerts !== 'undefined') {
                            recipient.receive_inventory_alerts = !!data.item.receive_inventory_alerts;
                        }
                    } catch (e) {
                        recipient.receive_inventory_alerts = prev;
                        console.error(e);
                        alert('Error menyimpan penerima inventory alert.');
                    } finally {
                        const next = { ...(this.savingInventoryAlertRecipientIds || {}) };
                        delete next[recipient.id];
                        this.savingInventoryAlertRecipientIds = next;
                    }
                },

                // --- Reminders Functions ---
                async toggleReminder(id) {
                    // Optimistic UI update
                    const reminder = this.remindersData.reminders.find(r => r.id === id);
                    if (reminder) reminder.is_enabled = !reminder.is_enabled;
                    
                    try {
                        const res = await fetch(`/whatsapp/reminders/${id}/toggle`, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                        });
                        if (!res.ok) {
                            // Revert on failure
                            if (reminder) reminder.is_enabled = !reminder.is_enabled;
                            alert('Failed to toggle reminder');
                        }
                    } catch(e) { 
                        if (reminder) reminder.is_enabled = !reminder.is_enabled;
                        console.error(e); 
                    }
                },
    
                async triggerReminder(id) {
                    if (!confirm('Run this reminder immediately?')) return;
                    try {
                        const res = await fetch(`/whatsapp/reminders/${id}/trigger`, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                        });
                        if (res.ok) {
                            alert('Reminder triggered (queued)');
                        } else {
                            alert('Failed to trigger reminder');
                        }
                    } catch(e) { console.error(e); }
                },

                async deleteReminder(id) {
                    if (!confirm('Hapus reminder ini? Tindakan ini tidak bisa dibatalkan.')) return;

                    try {
                        const res = await fetch(`/whatsapp/reminders/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        });

                        if (!res.ok) {
                            const data = await res.json();
                            alert(data.message || 'Failed to delete reminder');
                            return;
                        }

                        this.remindersData.reminders = this.remindersData.reminders.filter(r => r.id !== id);
                    } catch (e) {
                        console.error(e);
                        alert('Error deleting reminder');
                    }
                },

                // --- Logs Functions ---
                async toggleBatch(id) {
                    if (this.expandedBatch === id) {
                        this.expandedBatch = null;
                        return;
                    }
                    this.expandedBatch = id;
                    if (!this.batchDetails[id]) {
                        this.loadingDetails = true;
                        try {
                            const res = await fetch(`/whatsapp/logs/${id}`);
                            const data = await res.json();
                            this.batchDetails[id] = data.messages.data;
                        } catch (e) { console.error(e); }
                        finally { this.loadingDetails = false; }
                    }
                },

                // --- Settings Functions ---
                initSettingsTab() {
                    if (this.settingsData) {
                        this.settingsForm = { ...this.settingsForm, ...this.settingsData };
                    }

                    if (!this.settingsInitialized) {
                        this.settingsInitialized = true;
                        this.$watch('activeSettingsTab', (tabId) => {
                            if (this.activeTab !== 'settings') return;
                            if (tabId === 'quick-test') this.fetchDevices();
                            if (tabId === 'templates') this.loadTemplates();
                            if (tabId === 'whitelist') this.fetchWhitelist();
                        });
                    }

                    // Prefetch the default/high-frequency panels
                    if (this.activeSettingsTab === 'quick-test') {
                        this.fetchDevices();
                    }
                    if (this.activeSettingsTab === 'templates') {
                        this.loadTemplates();
                    }
                    if (this.activeSettingsTab === 'whitelist') {
                        this.fetchWhitelist();
                    }
                },

                async saveSettings() {
                    try {
                        const payload = {
                            base_url: this.settingsForm.base_url,
                            basic_user: this.settingsForm.basic_user,
                            basic_pass: this.settingsForm.basic_pass,
                            device_id: this.settingsForm.device_id,
                            inventory_alert_expiry_days: this.settingsForm.inventory_alert_expiry_days,
                            ai_provider: this.settingsForm.ai_provider,
                            ai_base_url: this.settingsForm.ai_base_url,
                            ai_model: this.settingsForm.ai_model,
                            ai_api_key: this.settingsForm.ai_api_key
                        };

                        const res = await fetch('{{ route("whatsapp.settings.save") }}', {
                            method: 'POST',
                            headers: { 
                                'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });
                        
                        const data = await res.json();
                        
                        if (res.ok) {
                            alert('Settings saved successfully');
                            this.settingsForm.ai_api_key = '';
                            this.settingsForm.ai_api_key_configured = true;
                        } else {
                            alert('Failed to save settings: ' + (data.message || 'Unknown error'));
                        }
                    } catch(e) { console.error(e); alert('Error saving settings'); }
                },

                async sendAiTest() {
                    if (!this.aiTest.prompt || this.aiTest.prompt.trim() === '') {
                        this.aiTest.error = 'Prompt test wajib diisi.';
                        this.aiTest.result = '';
                        return;
                    }

                    this.sendingAiTest = true;
                    this.aiTest.error = '';
                    this.aiTest.result = '';

                    try {
                        const res = await fetch('{{ route("whatsapp.settings.test-ai") }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                prompt: this.aiTest.prompt
                            })
                        });

                        const data = await res.json();

                        if (!res.ok || !data.success) {
                            this.aiTest.error = data.message || data.error || 'AI test gagal dijalankan.';
                            return;
                        }

                        this.aiTest.result = data.result || '(Respons kosong)';
                    } catch (e) {
                        console.error(e);
                        this.aiTest.error = 'Error saat menjalankan AI test.';
                    } finally {
                        this.sendingAiTest = false;
                    }
                },

                async fetchDevices() {
                    this.loadingDevices = true;
                    try {
                        const res = await fetch('{{ route("whatsapp.settings.devices") }}');
                        const data = await res.json();
                        
                        if (data.success) {
                            this.devices = data.devices || [];
                        } else {
                            console.warn('Failed to fetch devices', data.error);
                        }
                    } catch(e) { 
                        console.error(e); 
                        alert('Error connecting to device service');
                    }
                    finally { this.loadingDevices = false; }
                },

                async sendTestMessage() {
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

                // Template Functions
                async loadTemplates() {
                    this.loadingTemplates = true;
                    try {
                        const res = await fetch('{{ route("whatsapp.settings.templates") }}');
                        const data = await res.json();
                        this.templates = data.templates;
                        this.categories = data.categories;
                        this.labels = data.labels;
                        this.placeholders = data.placeholders;

                        // Default selection
                        const availableCategories = Object.keys(this.categories || {});
                        if (!availableCategories.includes(this.activeCategory)) {
                            this.activeCategory = availableCategories.includes('milestone') ? 'milestone' : (availableCategories[0] || 'milestone');
                        }
                        this.onTemplateCategoryChanged();
                    } catch(e) { console.error(e); alert('Error loading templates'); }
                    finally { this.loadingTemplates = false; }
                },

                onTemplateCategoryChanged() {
                    const keys = Object.keys(this.templates?.[this.activeCategory] || {});
                    if (this.activeTemplateKey && keys.includes(this.activeTemplateKey)) {
                        return;
                    }
                    this.activeTemplateKey = keys[0] || null;
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
                    const textarea = document.getElementById('template_editor_textarea');
                    if (textarea) {
                        const start = textarea.selectionStart;
                        const end = textarea.selectionEnd;
                        const text = this.templates[category][key];
                        const before = text.substring(0, start);
                        const after = text.substring(end, text.length);
                        this.templates[category][key] = before + '{' + placeholder + '}' + after;
                        
                        this.$nextTick(() => {
                            textarea.focus();
                            textarea.setSelectionRange(start + placeholder.length + 2, start + placeholder.length + 2);
                        });
                    }
                },

                // Whitelist Manager
                async fetchWhitelist() {
                    this.loadingWhitelist = true;
                    this.whitelistError = '';
                    this.whitelistSuccess = '';
                    try {
                        const res = await fetch('{{ route("whatsapp.settings.whitelist.index") }}');
                        const data = await res.json();
                        if (!res.ok) {
                            this.whitelistError = data.message || 'Gagal memuat whitelist.';
                            return;
                        }
                        this.whitelistData = data;
                    } catch (e) {
                        console.error(e);
                        this.whitelistError = 'Error memuat whitelist.';
                    } finally {
                        this.loadingWhitelist = false;
                    }
                },

                async addWhitelist() {
                    if (!this.whitelistForm.phone) {
                        this.whitelistError = 'Phone wajib diisi.';
                        return;
                    }

                    this.addingWhitelist = true;
                    this.whitelistError = '';
                    this.whitelistSuccess = '';

                    try {
                        const res = await fetch('{{ route("whatsapp.settings.whitelist.store") }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                phone: this.whitelistForm.phone,
                                name: this.whitelistForm.name
                            })
                        });

                        const data = await res.json();

                        if (!res.ok) {
                            this.whitelistError = data.message || 'Gagal menambahkan admin.';
                            return;
                        }

                        this.whitelistSuccess = data.message || 'Admin berhasil ditambahkan.';
                        this.whitelistForm.phone = '';
                        this.whitelistForm.name = '';

                        // Update list locally if possible, otherwise refetch
                        if (data.item) {
                            this.whitelistData.whitelist = [data.item, ...(this.whitelistData.whitelist || [])];
                        } else {
                            await this.fetchWhitelist();
                        }
                    } catch (e) {
                        console.error(e);
                        this.whitelistError = 'Error menambahkan admin.';
                    } finally {
                        this.addingWhitelist = false;
                    }
                },

                async removeWhitelist(item) {
                    if (!item || !item.id) return;
                    if (!confirm('Hapus admin dari whitelist?')) return;

                    this.whitelistError = '';
                    this.whitelistSuccess = '';

                    try {
                        const res = await fetch(`/whatsapp/settings/whitelist/${item.id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        });

                        const data = await res.json();

                        if (!res.ok) {
                            this.whitelistError = data.message || 'Gagal menghapus admin.';
                            return;
                        }

                        this.whitelistSuccess = data.message || 'Admin berhasil dihapus.';
                        this.whitelistData.whitelist = (this.whitelistData.whitelist || []).filter(w => w.id !== item.id);
                    } catch (e) {
                        console.error(e);
                        this.whitelistError = 'Error menghapus admin.';
                    }
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
