<div x-data="reminderEditor()"
     @open-reminder-modal.window="openModal($event.detail.reminder)"
     x-show="isOpen"
     class="fixed inset-0 z-50 overflow-y-auto"
     style="display: none;">
    
    <!-- Backdrop -->
    <div x-show="isOpen" 
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
         @click="closeModal"></div>

    <!-- Modal Panel -->
    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div x-show="isOpen"
             x-transition:enter="transform transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transform transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
            
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100" id="modal-title">Edit Reminder</h3>
                        
                        <div class="mt-4 space-y-4">
                            <!-- Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                                <input type="text" x-model="form.name" disabled class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 dark:bg-gray-700 dark:border-gray-600 shadow-sm sm:text-sm">
                            </div>

                            <!-- Schedule Time -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Schedule Time</label>
                                <input type="time" x-model="form.schedule_time" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>

                            <!-- Schedule Days -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Schedule Days</label>
                                <div class="flex flex-wrap gap-3">
                                    <template x-for="day in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']">
                                        <label class="flex items-center space-x-2 cursor-pointer bg-gray-50 dark:bg-gray-700/50 px-3 py-1.5 rounded-md border border-gray-200 dark:border-gray-600 hover:bg-gray-100 transition-colors">
                                            <input type="checkbox" :value="day" x-model="form.schedule_days" class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                                            <span class="text-sm text-gray-700 dark:text-gray-300" x-text="day"></span>
                                        </label>
                                    </template>
                                </div>
                                <div class="mt-2 flex gap-2 text-xs">
                                    <button @click="form.schedule_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']" type="button" class="text-primary-600 hover:text-primary-800 underline">Weekdays Only</button>
                                    <span class="text-gray-300">|</span>
                                    <button @click="form.schedule_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" type="button" class="text-primary-600 hover:text-primary-800 underline">Everyday</button>
                                </div>
                            </div>

                            <!-- Target Date (ISO Only) -->
                            <div x-show="form.type === 'iso_countdown'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Target Date</label>
                                <input type="date" x-model="form.target_date" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                            </div>

                            <!-- Message Template -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Message Template</label>
                                <textarea x-model="form.message_template" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm font-mono text-xs"></textarea>
                                <p class="mt-1 text-xs text-gray-500">Available variables: {days_remaining}, {target_date}, {date}, {time}, {temperature}</p>
                            </div>

                            <!-- Recipients -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Recipients</label>
                                <template x-for="(recipient, index) in form.recipients" :key="index">
                                    <div class="flex gap-2 mb-2 items-start">
                                        <select x-model="recipient.type" class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                            <option value="group">Group</option>
                                            <option value="phone">Phone</option>
                                        </select>
                                        
                                        <div class="flex-1">
                                            <!-- Group Select -->
                                            <div x-show="recipient.type === 'group'" class="relative">
                                                <select x-model="recipient.value" @change="checkGroupSize(index)" class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                                    <option value="">Select Group</option>
                                                    <template x-for="group in groups" :key="group.jid">
                                                        <option :value="group.jid" x-text="group.name"></option>
                                                    </template>
                                                </select>
                                            </div>
                                            <!-- Phone Input -->
                                            <input x-show="recipient.type === 'phone'" type="text" x-model="recipient.value" placeholder="628..." class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                        </div>

                                        <button @click="removeRecipient(index)" type="button" class="text-red-500 hover:text-red-700 p-1">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    </div>
                                </template>
                                <button @click="addRecipient" type="button" class="mt-2 text-sm text-primary-600 hover:text-primary-500">+ Add Recipient</button>
                            </div>

                            <!-- Smart Mention Toggle -->
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600" x-show="hasGroupRecipient">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="flex items-center h-5">
                                            <input id="mention_all" type="checkbox" x-model="form.mention_all" class="focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300 rounded">
                                        </div>
                                        <div class="ml-2 text-sm">
                                            <label for="mention_all" class="font-medium text-gray-700 dark:text-gray-300">Mention All Members</label>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Tag everyone in the group(s)</p>
                                        </div>
                                    </div>
                                    <!-- Preview badge -->
                                    <div x-show="form.mention_all" class="flex items-center gap-1 text-xs text-amber-600 font-medium bg-amber-50 dark:bg-amber-900/20 px-2 py-1 rounded">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        <span x-text="'Potentially annoying feature'"></span>
                                    </div>
                                </div>
                                <div x-show="form.mention_all && groupSize > 50" class="mt-2 text-xs text-red-600 bg-red-50 dark:bg-red-900/20 p-2 rounded">
                                    ⚠️ Warning: Large group detected (>50 members). Proceed with caution.
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                <button @click="save" type="button" :disabled="saving" class="inline-flex w-full justify-center rounded-md border border-transparent bg-primary-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                    <span x-show="!saving">Save Changes</span>
                    <span x-show="saving">Saving...</span>
                </button>
                <button @click="closeModal" type="button" class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function reminderEditor() {
            return {
                isOpen: false,
                saving: false,
                groups: [],
                groupSize: 0,
                form: {
                    id: null,
                    name: '',
                    type: '',
                    schedule_time: '',
                    schedule_days: [],
                    target_date: '',
                    message_template: '',
                    recipients: [],
                    mention_all: false
                },

                get hasGroupRecipient() {
                    return this.form.recipients.some(r => r.type === 'group');
                },

                async init() {
                    this.fetchGroups();
                },

                async fetchGroups() {
                    try {
                        const res = await fetch('{{ route("whatsapp.groups") }}');
                        const data = await res.json();
                        if (data.groups) {
                            this.groups = data.groups;
                        }
                    } catch (e) { console.error('Error fetching groups', e); }
                },

                openModal(reminder) {
                    this.isOpen = true;
                    // Ensure schedule_days is an array. If string (legacy or default not working), default to weekdays.
                    let days = reminder.schedule_days;
                    if (!Array.isArray(days)) {
                         // Fallback if not array
                         days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
                    }

                    this.form = {
                        id: reminder.id,
                        name: reminder.name,
                        type: reminder.type,
                        schedule_time: reminder.schedule_time.substring(0, 5),
                        schedule_days: days,
                        target_date: reminder.metadata?.target_date || '',
                        message_template: reminder.message_template,
                        recipients: reminder.recipients ? reminder.recipients.map(r => ({
                            type: r.recipient_type,
                            value: r.recipient_value
                        })) : [],
                        mention_all: !!reminder.mention_all
                    };
                    
                    // Check initial groups
                    this.form.recipients.forEach((r, i) => {
                        if (r.type === 'group') this.checkGroupSize(i);
                    });
                },

                closeModal() {
                    this.isOpen = false;
                },

                addRecipient() {
                    this.form.recipients.push({ type: 'group', value: '' });
                },

                removeRecipient(index) {
                    this.form.recipients.splice(index, 1);
                },

                async checkGroupSize(index) {
                    // Logic to check group size if available
                    // For now, we don't have exact counts, so we just set a flag if it's a known large group or just placeholder
                    // In a real scenario, we would fetch /whatsapp/groups/{jid}/participants
                    // const recipient = this.form.recipients[index];
                    // if (recipient.type === 'group' && recipient.value) {
                    //    // fetch count
                    // }
                    // Placeholder logic:
                    this.groupSize = 0; // Reset
                },

                async save() {
                    this.saving = true;
                    try {
                        const res = await fetch(`/whatsapp/reminders/${this.form.id}`, {
                            method: 'PUT',
                            headers: { 
                                'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.form)
                        });
                        
                        if (res.ok) {
                            this.closeModal();
                            // Refresh parent
                            if (window.whatsappHub) {
                                // We need to access the parent Alpine component. 
                                // Since we can't easily access parent scope directly if not nested cleanly in data,
                                // we can dispatch a custom event on window/document
                                window.location.reload(); // Simplest way for now or implement event listener in parent
                            } else {
                                window.location.reload();
                            }
                        } else {
                            const data = await res.json();
                            alert('Failed to save: ' + (data.message || 'Unknown error'));
                        }
                    } catch(e) { console.error(e); alert('Error saving reminder'); }
                    finally { this.saving = false; }
                }
            }
        }
    </script>
</div>
