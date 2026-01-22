<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Manajemen Tugas"
            :breadcrumbs="[['label' => 'Tugas']]"
        />
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-6"
         x-data="taskManager()"
         x-init="init()">

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="surface-sem rounded-lg p-4 border border-sem">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-pd-text">{{ $stats['pending'] }}</p>
                        <p class="text-sm text-pd-text-muted">Menunggu</p>
                    </div>
                </div>
            </div>
            <div class="surface-sem rounded-lg p-4 border border-sem">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-pd-text">{{ $stats['in_progress'] }}</p>
                        <p class="text-sm text-pd-text-muted">Dikerjakan</p>
                    </div>
                </div>
            </div>
            <div class="surface-sem rounded-lg p-4 border border-sem">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-pd-text">{{ $stats['overdue'] }}</p>
                        <p class="text-sm text-pd-text-muted">Terlambat</p>
                    </div>
                </div>
            </div>
            <div class="surface-sem rounded-lg p-4 border border-sem">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-pd-text">{{ $stats['completed_today'] }}</p>
                        <p class="text-sm text-pd-text-muted">Selesai Hari Ini</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Actions -->
        <div class="surface-sem rounded-lg border border-sem mb-6">
            <div class="p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Filter by ownership -->
                    <select x-model="filterOwnership"
                            @change="applyFilters()"
                            class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500">
                        <option value="my_tasks">Tugas Saya</option>
                        <option value="assigned_by_me">Ditugaskan Saya</option>
                        <option value="all">Semua Tugas</option>
                    </select>

                    <!-- Filter by status -->
                    <select x-model="filterStatus"
                            @change="applyFilters()"
                            class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500">
                        <option value="active">Aktif</option>
                        <option value="pending">Menunggu</option>
                        <option value="in_progress">Dikerjakan</option>
                        <option value="completed">Selesai</option>
                        <option value="all">Semua Status</option>
                    </select>
                </div>

                <button @click="openCreateModal()"
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
        <div class="surface-sem rounded-lg border border-sem overflow-hidden">
            @if($tasks->isEmpty())
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-pd-text">Tidak ada tugas</h3>
                    <p class="mt-2 text-sm text-pd-text-muted">Belum ada tugas yang perlu dikerjakan.</p>
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($tasks as $task)
                        @include('tasks.partials.task-card', ['task' => $task])
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $tasks->links() }}
                </div>
            @endif
        </div>

        <!-- Create/Edit Task Modal -->
        @include('tasks.partials.task-modal')
    </div>

    @push('scripts')
    <script>
        function taskManager() {
            return {
                filterOwnership: '{{ $filter }}',
                filterStatus: '{{ $currentStatus }}',
                showModal: false,
                isEditing: false,
                loading: false,
                form: {
                    title: '',
                    description: '',
                    assigned_to: '',
                    priority: 'normal',
                    due_at: '',
                    notify_whatsapp: true,
                    test_request_id: null
                },
                editingTaskId: null,

                init() {
                    // Initialize from URL params
                },

                applyFilters() {
                    const params = new URLSearchParams();
                    params.set('filter', this.filterOwnership);
                    params.set('status', this.filterStatus);
                    window.location.href = '{{ route("tasks.index") }}?' + params.toString();
                },

                openCreateModal() {
                    this.isEditing = false;
                    this.editingTaskId = null;
                    this.resetForm();
                    this.showModal = true;
                },

                openEditModal(task) {
                    this.isEditing = true;
                    this.editingTaskId = task.id;
                    this.form = {
                        title: task.title,
                        description: task.description || '',
                        assigned_to: task.assigned_to,
                        priority: task.priority,
                        due_at: task.due_at ? task.due_at.split('T')[0] : '',
                        notify_whatsapp: task.notify_whatsapp,
                        test_request_id: task.test_request_id
                    };
                    this.showModal = true;
                },

                resetForm() {
                    this.form = {
                        title: '',
                        description: '',
                        assigned_to: '',
                        priority: 'normal',
                        due_at: '',
                        notify_whatsapp: true,
                        test_request_id: null
                    };
                },

                async submitForm() {
                    this.loading = true;
                    try {
                        const url = this.isEditing
                            ? `/tasks/${this.editingTaskId}`
                            : '{{ route("tasks.store") }}';
                        const method = this.isEditing ? 'PUT' : 'POST';

                        const response = await fetch(url, {
                            method: method,
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.form)
                        });

                        const data = await response.json();

                        if (response.ok) {
                            this.showModal = false;
                            window.location.reload();
                        } else {
                            alert(data.message || 'Terjadi kesalahan');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan saat menyimpan tugas');
                    } finally {
                        this.loading = false;
                    }
                },

                async updateStatus(taskId, newStatus) {
                    try {
                        const response = await fetch(`/tasks/${taskId}/status`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ status: newStatus })
                        });

                        if (response.ok) {
                            window.location.reload();
                        } else {
                            const data = await response.json();
                            alert(data.message || 'Gagal memperbarui status');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan');
                    }
                },

                async deleteTask(taskId) {
                    if (!confirm('Apakah Anda yakin ingin menghapus tugas ini?')) return;

                    try {
                        const response = await fetch(`/tasks/${taskId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        });

                        if (response.ok) {
                            window.location.reload();
                        } else {
                            const data = await response.json();
                            alert(data.message || 'Gagal menghapus tugas');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan');
                    }
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
