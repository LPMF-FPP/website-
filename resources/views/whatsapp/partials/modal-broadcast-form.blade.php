<div x-data="broadcastForm()"
     @open-broadcast-modal.window="open($event.detail.broadcast)"
     class="relative z-50" 
     aria-labelledby="slide-over-title" 
     role="dialog" 
     aria-modal="true"
     x-show="isOpen"
     style="display: none;">
    
    <!-- Background backdrop -->
    <div x-show="isOpen"
         x-transition:enter="ease-in-out duration-500"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in-out duration-500"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
         @click="close"></div>

    <div class="fixed inset-0 overflow-hidden">
        <div class="absolute inset-0 overflow-hidden">
            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div x-show="isOpen"
                     x-transition:enter="transform transition ease-in-out duration-500 sm:duration-700"
                     x-transition:enter-start="translate-x-full"
                     x-transition:enter-end="translate-x-0"
                     x-transition:leave="transform transition ease-in-out duration-500 sm:duration-700"
                     x-transition:leave-start="translate-x-0"
                     x-transition:leave-end="translate-x-full"
                     class="pointer-events-auto w-screen max-w-md">
                    
                    <div class="flex h-full flex-col overflow-y-scroll bg-white dark:bg-gray-800 shadow-xl">
                        <div class="bg-primary-700 px-4 py-6 sm:px-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-medium text-white" id="slide-over-title" x-text="isEdit ? 'Edit Broadcast' : 'New Broadcast'"></h2>
                                <div class="ml-3 flex h-7 items-center">
                                    <button @click="close" type="button" class="rounded-md bg-primary-700 text-primary-200 hover:text-white focus:outline-none focus:ring-2 focus:ring-white">
                                        <span class="sr-only">Close panel</span>
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-1">
                                <p class="text-sm text-primary-300">Fill in the details to send a broadcast message.</p>
                            </div>
                        </div>
                        
                        <div class="relative flex-1 px-4 py-6 sm:px-6">
                            <form @submit.prevent="save" class="space-y-6">
                                <!-- Title -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">Title</label>
                                    <input type="text" x-model="form.title" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                </div>

                                <!-- Message -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">Message</label>
                                    <x-magic-toolbar 
                                        target="form.message" 
                                        textarea-id="broadcast-message-field"
                                        :show-formatting="true"
                                    />
                                    <textarea id="broadcast-message-field" x-model="form.message" rows="6" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"></textarea>
                                </div>

                                <!-- Target Type -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">Target Audience</label>
                                    <select x-model="form.target_type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                        <option value="investigators">Investigators</option>
                                        <option value="users">Staff / Users</option>
                                        <!-- Custom/Group logic could be added here later -->
                                    </select>
                                </div>

                                <!-- Schedule (Optional) -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">Schedule (Optional)</label>
                                    <input type="datetime-local" x-model="form.scheduled_at" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                    <p class="mt-1 text-xs text-gray-500">Leave blank to save as draft (or send immediately later).</p>
                                </div>

                                <!-- Preview Recipient Count -->
                                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-md p-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Estimated Recipients:</span>
                                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100" x-text="estimatedRecipients + ' people'"></span>
                                    </div>
                                    <button type="button" @click="calculateRecipients" class="mt-2 text-xs text-primary-600 hover:text-primary-500">Refresh Count</button>
                                </div>

                                <!-- Buttons -->
                                <div class="flex gap-3 pt-4">
                                    <button type="submit" :disabled="saving" class="flex-1 inline-flex justify-center rounded-md border border-transparent bg-primary-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50">
                                        <span x-show="!saving" x-text="isEdit ? 'Update Broadcast' : 'Create Broadcast'"></span>
                                        <span x-show="saving">Saving...</span>
                                    </button>
                                    <button type="button" @click="close" class="flex-1 inline-flex justify-center rounded-md border border-gray-300 bg-white dark:bg-gray-700 py-2 px-4 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function broadcastForm() {
            return {
                isOpen: false,
                isEdit: false,
                saving: false,
                estimatedRecipients: 0,
                form: {
                    id: null,
                    title: '',
                    message: '',
                    target_type: 'investigators',
                    scheduled_at: '',
                    target_filters: {},
                    recipient_ids: []
                },
                
                open(broadcast = null) {
                    this.isOpen = true;
                    if (broadcast) {
                        this.isEdit = true;
                        this.form = {
                            id: broadcast.id,
                            title: broadcast.title,
                            message: broadcast.message,
                            target_type: broadcast.target_type,
                            scheduled_at: broadcast.scheduled_at ? broadcast.scheduled_at.replace(' ', 'T') : '',
                            target_filters: broadcast.target_filters || {},
                            recipient_ids: broadcast.recipient_ids || []
                        };
                        this.estimatedRecipients = broadcast.total_recipients || 0;
                    } else {
                        this.isEdit = false;
                        this.form = {
                            id: null,
                            title: '',
                            message: '',
                            target_type: 'investigators',
                            scheduled_at: '',
                            target_filters: {},
                            recipient_ids: []
                        };
                        this.calculateRecipients();
                    }
                },
                
                close() {
                    this.isOpen = false;
                },

                async calculateRecipients() {
                    try {
                        const res = await fetch('{{ route("whatsapp.broadcasts.preview-recipients") }}', {
                            method: 'POST',
                            headers: { 
                                'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                target_type: this.form.target_type,
                                target_filters: this.form.target_filters,
                                recipient_ids: this.form.recipient_ids
                            })
                        });
                        const data = await res.json();
                        this.estimatedRecipients = data.count;
                    } catch(e) { console.error(e); }
                },
                
                async save() {
                    this.saving = true;
                    const url = this.isEdit 
                        ? `/whatsapp/broadcasts/${this.form.id}`
                        : '{{ route("whatsapp.broadcasts.store") }}';
                    const method = this.isEdit ? 'PUT' : 'POST';
                    
                    try {
                        const res = await fetch(url, {
                            method: method,
                            headers: { 
                                'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.form)
                        });
                        
                        if (res.ok) {
                            this.close();
                            window.location.reload();
                        } else {
                            const data = await res.json();
                            alert('Failed to save: ' + (data.message || 'Unknown error'));
                        }
                    } catch(e) { console.error(e); alert('Error saving broadcast'); }
                    finally { this.saving = false; }
                }
            }
        }
    </script>
</div>
