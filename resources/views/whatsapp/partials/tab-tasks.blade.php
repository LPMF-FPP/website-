<div x-data="{
    filterOwnership: 'my_tasks',
    filterStatus: 'active',

    loadTasks() {
        this.loadTabData('tasks', {
            filter: this.filterOwnership,
            status: this.filterStatus,
        });
    },
    
    refresh() {
        this.loadTasks();
    },

    async updateStatus(task, newStatus) {
        if (task && task.source_module === 'qmh' && task.source_ref_type === 'qmh_document_revision') {
            alert('Task workflow QMH hanya dapat diproses lewat command WhatsApp /qmh.');
            return;
        }

        try {
            const res = await fetch(`/whatsapp/tasks/${task.id}/status`, {
                method: 'PATCH',
                headers: { 
                    'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ status: newStatus })
            });
            if (res.ok) {
                this.refresh();
            } else {
                const data = await res.json();
                alert(data.message || 'Failed to update status');
            }
        } catch(e) { console.error(e); alert('Error updating status'); }
    },

    async deleteTask(taskId) {
        if (!confirm('Apakah Anda yakin ingin menghapus tugas ini?')) return;
        try {
            const res = await fetch(`/whatsapp/tasks/${taskId}`, {
                method: 'DELETE',
                headers: { 
                    'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                    'Accept': 'application/json' 
                }
            });
            if (res.ok) {
                this.refresh();
            } else {
                alert('Failed to delete task');
            }
        } catch(e) { console.error(e); alert('Error deleting task'); }
    }
}">
    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100" x-text="tasksData?.stats?.pending || 0"></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Menunggu</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100" x-text="tasksData?.stats?.in_progress || 0"></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Dikerjakan</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100" x-text="tasksData?.stats?.overdue || 0"></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Terlambat</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100" x-text="tasksData?.stats?.completed_today || 0"></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Selesai Hari Ini</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Actions -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 mb-6">
        <div class="p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <!-- Filter by ownership -->
                <select x-model="filterOwnership"
                        @change="loadTasks()"
                        class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500">
                    <option value="my_tasks">Tugas Saya</option>
                    <option value="assigned_by_me">Ditugaskan Saya</option>
                    <option value="all">Semua Tugas</option>
                </select>

                <!-- Filter by status -->
                <select x-model="filterStatus"
                        @change="loadTasks()"
                        class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500">
                    <option value="active">Aktif</option>
                    <option value="pending">Menunggu</option>
                    <option value="in_progress">Dikerjakan</option>
                    <option value="completed">Selesai</option>
                    <option value="all">Semua Status</option>
                </select>
            </div>

            <button @click="$dispatch('open-task-modal', { users: tasksData?.users || [] })"
                    type="button"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Buat Tugas
            </button>
        </div>
    </div>

    <!-- Task List -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <template x-if="!tasksData?.tasks?.data || tasksData.tasks.data.length === 0">
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">Tidak ada tugas</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada tugas yang perlu dikerjakan.</p>
                </div>
            </template>
            
            <template x-for="task in tasksData?.tasks?.data || []" :key="task.id">
                <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3 flex-1">
                            <!-- Status Checkbox -->
                            <div class="flex-shrink-0 pt-1">
                                <button type="button" 
                                        @click="updateStatus(task, task.status === 'completed' ? 'in_progress' : 'completed')"
                                        :disabled="task.source_module === 'qmh' && task.source_ref_type === 'qmh_document_revision'"
                                        class="w-5 h-5 rounded border flex items-center justify-center transition-colors focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                                        :class="task.status === 'completed' 
                                            ? 'bg-green-500 border-green-500 text-white' 
                                            : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 hover:border-green-500'"
                                        :title="task.source_module === 'qmh' && task.source_ref_type === 'qmh_document_revision'
                                            ? 'Gunakan command /qmh untuk memproses task workflow QMH'
                                            : 'Update status task'">
                                    <svg x-show="task.status === 'completed'" class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100" 
                                        :class="{'line-through text-gray-500': task.status === 'completed'}"
                                        x-text="task.title"></h4>
                                    
                                    <!-- Priority Badge -->
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                          :class="{
                                              'bg-red-100 text-red-800': task.priority === 'urgent',
                                              'bg-orange-100 text-orange-800': task.priority === 'high',
                                              'bg-blue-100 text-blue-800': task.priority === 'normal',
                                              'bg-gray-100 text-gray-800': task.priority === 'low'
                                          }">
                                        <span x-text="task.priority.charAt(0).toUpperCase() + task.priority.slice(1)"></span>
                                    </span>
                                </div>

                                <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2 mb-2" x-text="task.description"></p>

                                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center gap-1" title="Assigned To">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        <span x-text="task.assignee ? task.assignee.name : 'Unassigned'"></span>
                                    </div>

                                    <div class="flex items-center gap-1" title="Due Date">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <span x-text="task.due_at ? new Date(task.due_at).toLocaleDateString() : 'No Deadline'"></span>
                                    </div>

                                    <!-- Status Badge -->
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700"
                                          :class="{
                                              'bg-amber-100 text-amber-800': task.status === 'pending',
                                              'bg-blue-100 text-blue-800': task.status === 'in_progress',
                                              'bg-green-100 text-green-800': task.status === 'completed'
                                          }">
                                        <span x-text="task.status.replace('_', ' ').charAt(0).toUpperCase() + task.status.replace('_', ' ').slice(1)"></span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <button @click="$dispatch('open-task-modal', { task: task, users: tasksData?.users || [] })"
                                    class="p-1 text-gray-400 hover:text-blue-600 rounded transition-colors"
                                    title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                </svg>
                            </button>
                            <button @click="deleteTask(task.id)"
                                    class="p-1 text-gray-400 hover:text-red-600 rounded transition-colors"
                                    title="Hapus">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
