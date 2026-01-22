<!-- Create/Edit Task Modal -->
<div x-show="showModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title"
     role="dialog"
     aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Backdrop -->
        <div x-show="showModal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity"
             @click="showModal = false"></div>

        <!-- Centering trick -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal Panel -->
        <div x-show="showModal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative inline-block align-bottom surface-sem rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">

            <form @submit.prevent="submitForm()">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-pd-text" id="modal-title"
                            x-text="isEditing ? 'Edit Tugas' : 'Buat Tugas Baru'"></h3>
                        <button type="button" @click="showModal = false"
                                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <!-- Title -->
                        <div>
                            <label for="task-title" class="block text-sm font-medium text-pd-text mb-1">
                                Judul Tugas <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="task-title"
                                   x-model="form.title"
                                   required
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="Contoh: Siapkan sampel untuk pengujian">
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="task-description" class="block text-sm font-medium text-pd-text mb-1">
                                Deskripsi
                            </label>
                            <textarea id="task-description"
                                      x-model="form.description"
                                      rows="3"
                                      class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500"
                                      placeholder="Deskripsi detail tugas..."></textarea>
                        </div>

                        <!-- Assignee -->
                        <div>
                            <label for="task-assignee" class="block text-sm font-medium text-pd-text mb-1">
                                Ditugaskan Kepada <span class="text-red-500">*</span>
                            </label>
                            <select id="task-assignee"
                                    x-model="form.assigned_to"
                                    required
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Pilih staff...</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ ucfirst($user->role) }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Priority -->
                            <div>
                                <label for="task-priority" class="block text-sm font-medium text-pd-text mb-1">
                                    Prioritas
                                </label>
                                <select id="task-priority"
                                        x-model="form.priority"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500">
                                    @foreach($priorities as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Due Date -->
                            <div>
                                <label for="task-due" class="block text-sm font-medium text-pd-text mb-1">
                                    Tenggat Waktu
                                </label>
                                <input type="date"
                                       id="task-due"
                                       x-model="form.due_at"
                                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500">
                            </div>
                        </div>

                        <!-- WhatsApp Notification -->
                        <div class="flex items-center gap-3">
                            <input type="checkbox"
                                   id="task-notify"
                                   x-model="form.notify_whatsapp"
                                   class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                            <label for="task-notify" class="text-sm text-pd-text">
                                Kirim notifikasi WhatsApp ke staff
                            </label>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 flex items-center justify-end gap-3">
                    <button type="button"
                            @click="showModal = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            :disabled="loading"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2">
                        <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="isEditing ? 'Simpan Perubahan' : 'Buat Tugas'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
