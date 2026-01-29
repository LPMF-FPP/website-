<div x-data="taskForm()"
     @open-task-modal.window="open($event.detail)"
     x-show="isOpen"
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title"
     role="dialog"
     aria-modal="true"
     style="display: none;">
    
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Backdrop -->
        <div x-show="isOpen"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity"
             @click="close"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal Panel -->
        <div x-show="isOpen"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">

            <form @submit.prevent="submit">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100" 
                            x-text="isEdit ? 'Edit Tugas' : 'Buat Tugas Baru'"></h3>
                        <button type="button" @click="close"
                                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <!-- Title -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Judul Tugas <span class="text-red-500">*</span>
                            </label>
                            <input type="text" x-model="form.title" required
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="Contoh: Siapkan sampel untuk pengujian">
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Deskripsi
                            </label>
                            <textarea x-model="form.description" rows="3"
                                      class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500"
                                      placeholder="Deskripsi detail tugas..."></textarea>
                        </div>

                        <!-- Assignee -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Ditugaskan Kepada <span class="text-red-500">*</span>
                            </label>
                            <select x-model="form.assigned_to" required
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Pilih staff...</option>
                                <template x-for="user in users" :key="user.id">
                                    <option :value="user.id" x-text="user.name"></option>
                                </template>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Priority -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Prioritas
                                </label>
                                <select x-model="form.priority"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500">
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>

                            <!-- Due Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Tenggat Waktu
                                </label>
                                <input type="date" x-model="form.due_at"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500">
                            </div>
                        </div>

                        <!-- WhatsApp Notification -->
                        <div class="flex items-center gap-3">
                            <input type="checkbox" id="task-notify" x-model="form.notify_whatsapp"
                                   class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                            <label for="task-notify" class="text-sm text-gray-700 dark:text-gray-300">
                                Kirim notifikasi WhatsApp ke staff
                            </label>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 flex items-center justify-end gap-3">
                    <button type="button" @click="close"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        Batal
                    </button>
                    <button type="submit" :disabled="saving"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2">
                        <span x-show="!saving" x-text="isEdit ? 'Simpan Perubahan' : 'Buat Tugas'"></span>
                        <span x-show="saving">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function taskForm() {
            return {
                isOpen: false,
                isEdit: false,
                saving: false,
                users: [],
                form: {
                    id: null,
                    title: '',
                    description: '',
                    assigned_to: '',
                    priority: 'normal',
                    due_at: '',
                    notify_whatsapp: true
                },

                open(detail) {
                    this.isOpen = true;
                    this.users = detail.users || [];
                    
                    if (detail.task) {
                        this.isEdit = true;
                        const task = detail.task;
                        this.form = {
                            id: task.id,
                            title: task.title,
                            description: task.description || '',
                            assigned_to: task.assigned_to,
                            priority: task.priority,
                            due_at: task.due_at ? task.due_at.split('T')[0] : '',
                            notify_whatsapp: task.notify_whatsapp
                        };
                    } else {
                        this.isEdit = false;
                        this.form = {
                            id: null,
                            title: '',
                            description: '',
                            assigned_to: '',
                            priority: 'normal',
                            due_at: '',
                            notify_whatsapp: true
                        };
                    }
                },

                close() {
                    this.isOpen = false;
                },

                async submit() {
                    this.saving = true;
                    try {
                        const url = this.isEdit 
                            ? `/whatsapp/tasks/${this.form.id}` 
                            : '{{ route("whatsapp.tasks.store") }}';
                        const method = this.isEdit ? 'PUT' : 'POST';

                        const res = await fetch(url, {
                            method: method,
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.form)
                        });

                        if (res.ok) {
                            this.close();
                            // Reload parent data
                            if (window.whatsappHub) {
                                // Since we can't easily access the parent instance directly, 
                                // we'll reload the page for now or dispatch a refresh event if we implemented listener
                                window.location.reload(); 
                            }
                        } else {
                            const data = await res.json();
                            alert(data.message || 'Terjadi kesalahan');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan saat menyimpan tugas');
                    } finally {
                        this.saving = false;
                    }
                }
            }
        }
    </script>
</div>
