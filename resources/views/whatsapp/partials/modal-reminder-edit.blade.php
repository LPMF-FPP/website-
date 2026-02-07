<div x-data="reminderEditor()"
     @open-reminder-modal.window="openModal($event.detail.reminder ?? null)"
     x-show="isOpen"
     class="fixed inset-0 z-50 overflow-y-auto"
     style="display: none;">

    <div x-show="isOpen"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-500/75"
         @click="closeModal"></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div x-show="isOpen"
             x-transition:enter="transform transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transform transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-3xl">

            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[80vh] overflow-y-auto">
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100" x-text="isEditMode ? 'Edit Reminder' : 'Tambah Reminder Baru'"></h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipe Reminder</label>
                            <select x-model="form.type" @change="onTypeChange" :disabled="isEditMode" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm disabled:opacity-60">
                                <template x-for="option in reminderTypes" :key="option.value">
                                    <option :value="option.value" x-text="option.label"></option>
                                </template>
                            </select>
                            <p x-show="isEditMode" class="mt-1 text-xs text-gray-500">Tipe reminder dikunci saat edit agar konfigurasi tetap konsisten.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Reminder</label>
                            <input type="text" x-model="form.name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" placeholder="Contoh: Countdown Surveillance 2026">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Deskripsi</label>
                        <textarea x-model="form.description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" placeholder="Keterangan singkat reminder"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Schedule Time</label>
                            <input type="time" x-model="form.schedule_time" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Schedule Days</label>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="day in weekDays" :key="day">
                                    <label class="inline-flex items-center gap-2 cursor-pointer bg-gray-50 dark:bg-gray-700/50 px-2.5 py-1 rounded-md border border-gray-200 dark:border-gray-600">
                                        <input type="checkbox" :value="day" x-model="form.schedule_days" class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                                        <span class="text-xs text-gray-700 dark:text-gray-300" x-text="day"></span>
                                    </label>
                                </template>
                            </div>
                            <div class="mt-2 flex gap-2 text-xs">
                                <button @click="form.schedule_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']" type="button" class="text-primary-600 hover:text-primary-800 underline">Weekdays</button>
                                <span class="text-gray-300">|</span>
                                <button @click="form.schedule_days = [...weekDays]" type="button" class="text-primary-600 hover:text-primary-800 underline">Everyday</button>
                            </div>
                        </div>
                    </div>

                    <div x-show="isCountdownType" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/40 p-4 space-y-4">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Countdown Settings</h4>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Event</label>
                                <input type="text" x-model="form.event_name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" placeholder="Contoh: Surveillance ISO 17025">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Target Date</label>
                                <input type="date" x-model="form.target_date" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Pilih Emoji (Profesional)</label>
                            <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2">
                                <template x-for="emoji in professionalEmojis" :key="emoji.value">
                                    <button type="button"
                                            @click="selectEmoji(emoji.value)"
                                            class="inline-flex items-center justify-center h-10 rounded-md border text-lg transition-colors"
                                            :class="form.event_emoji === emoji.value
                                                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/30'
                                                : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600'"
                                            :title="emoji.label"
                                            x-text="emoji.value"></button>
                                </template>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">Emoji terpilih: <span class="font-medium" x-text="form.event_emoji || 'Belum dipilih'"></span></p>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <h5 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Custom Milestones</h5>
                                <div class="flex items-center gap-3 text-xs">
                                    <button type="button" @click="addMilestone" class="text-primary-600 hover:text-primary-800 underline">+ Tambah Milestone</button>
                                    <button type="button" @click="resetMilestones" class="text-gray-600 hover:text-gray-800 underline">Reset Default</button>
                                </div>
                            </div>

                            <template x-for="(milestone, index) in form.milestones" :key="index">
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-4 sm:col-span-3">
                                        <label class="sr-only">Hari</label>
                                        <input type="number" min="0" x-model.number="milestone.days" @change="sortMilestones" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" placeholder="H-30">
                                    </div>
                                    <div class="col-span-7 sm:col-span-8">
                                        <label class="sr-only">Pesan</label>
                                        <input type="text" x-model="milestone.message" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" placeholder="Pesan milestone">
                                    </div>
                                    <div class="col-span-1 text-right">
                                        <button type="button" @click="removeMilestone(index)" class="text-red-500 hover:text-red-700">✕</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Message Template</label>
                        <x-magic-toolbar
                            target="form.message_template"
                            textarea-id="reminder-message-field"
                            :show-formatting="true"
                        />
                        <textarea id="reminder-message-field" x-model="form.message_template" rows="5" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm font-mono text-xs"></textarea>
                        <p class="mt-1 text-xs text-gray-500" x-show="isCountdownType">Variabel countdown: {event_emoji}, {event_name}, {target_date}, {days_remaining}, {milestone_message}</p>
                        <p class="mt-1 text-xs text-gray-500" x-show="!isCountdownType">Variabel umum: {date}, {time}, {temperature}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Recipients</label>
                        <template x-for="(recipient, index) in form.recipients" :key="index">
                            <div class="flex gap-2 mb-2 items-start">
                                <select x-model="recipient.type" class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                    <option value="group">Group</option>
                                    <option value="phone">Phone</option>
                                </select>

                                <div class="flex-1">
                                    <div x-show="recipient.type === 'group'">
                                        <select x-model="recipient.value" class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                            <option value="">Select Group</option>
                                            <template x-for="group in groups" :key="group.jid">
                                                <option :value="group.jid" x-text="group.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <input x-show="recipient.type === 'phone'" type="text" x-model="recipient.value" placeholder="628..." class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                </div>

                                <button @click="removeRecipient(index)" type="button" class="text-red-500 hover:text-red-700 p-1">✕</button>
                            </div>
                        </template>
                        <button @click="addRecipient" type="button" class="mt-2 text-sm text-primary-600 hover:text-primary-500">+ Add Recipient</button>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600" x-show="hasGroupRecipient">
                        <label class="inline-flex items-center gap-2">
                            <input id="mention_all" type="checkbox" x-model="form.mention_all" class="focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300 rounded">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Mention All Members</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-3">
                <button @click="save" type="button" :disabled="saving" class="inline-flex w-full justify-center rounded-md border border-transparent bg-primary-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 sm:w-auto sm:text-sm disabled:opacity-50">
                    <span x-show="!saving" x-text="isEditMode ? 'Save Changes' : 'Create Reminder'"></span>
                    <span x-show="saving">Saving...</span>
                </button>
                <button @click="closeModal" type="button" class="mt-3 sm:mt-0 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 sm:w-auto sm:text-sm">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function reminderEditor() {
            return {
                isOpen: false,
                isEditMode: false,
                saving: false,
                groups: [],
                weekDays: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                reminderTypes: [
                    { value: 'countdown', label: 'Countdown (General)' },
                    { value: 'iso_countdown', label: 'Countdown (Legacy ISO)' },
                    { value: 'temp_morning', label: 'Suhu Pagi' },
                    { value: 'temp_afternoon', label: 'Suhu Siang' },
                    { value: 'custom', label: 'Custom' },
                ],
                professionalEmojis: [
                    { value: '📋', label: 'Audit/Assessment' },
                    { value: '📅', label: 'Deadline' },
                    { value: '🎯', label: 'Target' },
                    { value: '⏰', label: 'Reminder' },
                    { value: '📜', label: 'Certification' },
                    { value: '📑', label: 'Document' },
                    { value: '✅', label: 'Checklist' },
                    { value: '🔬', label: 'Laboratory' },
                    { value: '🧪', label: 'Sample' },
                    { value: '⚗️', label: 'Chemistry' },
                    { value: '🌡️', label: 'Temperature' },
                    { value: '🔧', label: 'Maintenance' },
                    { value: '⚙️', label: 'Calibration' },
                    { value: '📚', label: 'Training' },
                    { value: '🎓', label: 'Certification Program' },
                    { value: '🔔', label: 'Notification' },
                ],
                form: {},

                get isCountdownType() {
                    return ['countdown', 'iso_countdown'].includes(this.form.type);
                },

                get hasGroupRecipient() {
                    return this.form.recipients.some((recipient) => recipient.type === 'group');
                },

                init() {
                    this.form = this.emptyForm();
                    this.fetchGroups();
                },

                emptyForm() {
                    return {
                        id: null,
                        type: 'countdown',
                        name: '',
                        description: '',
                        schedule_time: '07:00',
                        schedule_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                        target_date: '',
                        event_name: '',
                        event_emoji: '📋',
                        milestones: this.defaultMilestones(),
                        message_template: this.defaultTemplate('countdown'),
                        recipients: [{ type: 'group', value: '' }],
                        mention_all: false,
                    };
                },

                defaultMilestones() {
                    return [
                        { days: 100, message: 'Masih ada waktu panjang, persiapkan dengan santai tapi pasti.' },
                        { days: 60, message: 'Dua bulan lagi. Mulai cek kelengkapan dokumen dan rencana kerja.' },
                        { days: 30, message: 'Satu bulan tersisa. Pastikan semua poin persiapan sudah on-track.' },
                        { days: 14, message: 'Dua minggu lagi. Fokus ke detail final dan koordinasi tim.' },
                        { days: 7, message: 'Satu minggu terakhir. Final check sebelum hari H.' },
                        { days: 0, message: 'HARI INI. Jalankan agenda sesuai rencana dan tetap tenang.' },
                    ];
                },

                defaultTemplate(type) {
                    if (['countdown', 'iso_countdown'].includes(type)) {
                        return '{event_emoji} *{event_name}*\n\n📅 Target: {target_date}\n⏳ Sisa: *{days_remaining} hari*\n\n{milestone_message}';
                    }

                    if (type === 'temp_morning') {
                        return '🌡️ Pengingat suhu pagi. Mohon input suhu ruangan sekarang.';
                    }

                    if (type === 'temp_afternoon') {
                        return '🌡️ Pengingat suhu siang. Mohon input suhu ruangan sekarang.';
                    }

                    return 'Reminder: {date} {time}';
                },

                normalizeMilestones(rawMilestones) {
                    if (!rawMilestones || typeof rawMilestones !== 'object') {
                        return this.defaultMilestones();
                    }

                    const normalized = Object.entries(rawMilestones)
                        .map(([days, message]) => ({ days: Number(days), message: String(message || '') }))
                        .filter((item) => Number.isFinite(item.days) && item.days >= 0 && item.message.trim() !== '')
                        .sort((a, b) => b.days - a.days);

                    return normalized.length > 0 ? normalized : this.defaultMilestones();
                },

                onTypeChange() {
                    if (!this.form.message_template || this.form.message_template.trim() === '') {
                        this.form.message_template = this.defaultTemplate(this.form.type);
                    }

                    if (!this.isCountdownType) {
                        return;
                    }

                    if (!Array.isArray(this.form.milestones) || this.form.milestones.length === 0) {
                        this.form.milestones = this.defaultMilestones();
                    }
                },

                async fetchGroups() {
                    try {
                        const response = await fetch('{{ route("whatsapp.groups") }}');
                        const data = await response.json();
                        if (Array.isArray(data.groups)) {
                            this.groups = data.groups;
                        }
                    } catch (error) {
                        console.error('Error fetching groups', error);
                    }
                },

                openModal(reminder = null) {
                    this.isOpen = true;
                    this.isEditMode = !!reminder;

                    if (!reminder) {
                        this.form = this.emptyForm();
                        return;
                    }

                    const scheduleDays = Array.isArray(reminder.schedule_days)
                        ? reminder.schedule_days
                        : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

                    this.form = {
                        id: reminder.id,
                        type: reminder.type || 'custom',
                        name: reminder.name || '',
                        description: reminder.description || '',
                        schedule_time: (reminder.schedule_time || '07:00:00').substring(0, 5),
                        schedule_days: scheduleDays,
                        target_date: reminder.metadata?.target_date || '',
                        event_name: reminder.metadata?.event_name || reminder.name || '',
                        event_emoji: reminder.metadata?.event_emoji || '📋',
                        milestones: this.normalizeMilestones(reminder.metadata?.milestones),
                        message_template: reminder.message_template || this.defaultTemplate(reminder.type),
                        recipients: Array.isArray(reminder.recipients) && reminder.recipients.length > 0
                            ? reminder.recipients.map((recipient) => ({
                                type: recipient.recipient_type,
                                value: recipient.recipient_value,
                            }))
                            : [{ type: 'group', value: '' }],
                        mention_all: !!reminder.mention_all,
                    };
                },

                closeModal() {
                    this.isOpen = false;
                    this.saving = false;
                },

                addRecipient() {
                    this.form.recipients.push({ type: 'group', value: '' });
                },

                removeRecipient(index) {
                    this.form.recipients.splice(index, 1);
                },

                addMilestone() {
                    this.form.milestones.push({ days: 1, message: '' });
                    this.sortMilestones();
                },

                removeMilestone(index) {
                    this.form.milestones.splice(index, 1);
                },

                resetMilestones() {
                    this.form.milestones = this.defaultMilestones();
                },

                sortMilestones() {
                    this.form.milestones.sort((a, b) => Number(b.days || 0) - Number(a.days || 0));
                },

                selectEmoji(emoji) {
                    this.form.event_emoji = emoji;
                },

                buildPayload() {
                    const payload = {
                        type: this.form.type,
                        name: this.form.name,
                        description: this.form.description,
                        schedule_time: this.form.schedule_time,
                        schedule_days: this.form.schedule_days,
                        message_template: this.form.message_template,
                        recipients: this.form.recipients.filter((recipient) => recipient.value),
                        mention_all: this.form.mention_all,
                    };

                    if (this.isCountdownType) {
                        payload.target_date = this.form.target_date;
                        payload.event_name = this.form.event_name;
                        payload.event_emoji = this.form.event_emoji;
                        payload.milestones = this.form.milestones
                            .filter((milestone) => milestone.message && milestone.message.trim() !== '')
                            .map((milestone) => ({
                                days: Number(milestone.days || 0),
                                message: milestone.message,
                            }));
                    }

                    return payload;
                },

                async save() {
                    this.saving = true;

                    try {
                        const endpoint = this.isEditMode
                            ? `/whatsapp/reminders/${this.form.id}`
                            : '/whatsapp/reminders';

                        const method = this.isEditMode ? 'PUT' : 'POST';

                        const response = await fetch(endpoint, {
                            method,
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify(this.buildPayload()),
                        });

                        if (!response.ok) {
                            const data = await response.json();
                            const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
                            alert(firstError || data.message || 'Failed to save reminder');
                            return;
                        }

                        this.closeModal();
                        window.dispatchEvent(new CustomEvent('reminder-saved'));
                    } catch (error) {
                        console.error(error);
                        alert('Error saving reminder');
                    } finally {
                        this.saving = false;
                    }
                },
            };
        }
    </script>
</div>
