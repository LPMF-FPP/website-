<div x-data="{
    filterStatus: 'all',
    init() {
        // Watch for filter changes if needed, or just rely on manual refresh
    },
    refresh() {
        this.loadTabData('broadcasts');
    },
    async deleteBroadcast(id) {
        if (!confirm('Are you sure you want to delete this broadcast?')) return;
        try {
            const res = await fetch(`/whatsapp/broadcasts/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
            });
            if (res.ok) {
                this.refresh();
            } else {
                alert('Failed to delete broadcast');
            }
        } catch(e) { console.error(e); alert('Error deleting broadcast'); }
    },
    async sendBroadcast(id, mentionAll = false) {
        if (!confirm(`Are you sure you want to send this broadcast?${mentionAll ? ' This will mention all recipients!' : ''}`)) return;
        try {
            const res = await fetch(`/whatsapp/broadcasts/${id}/send`, {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ mention_all: mentionAll })
            });
            if (res.ok) {
                this.refresh();
                alert('Broadcast sending started');
            } else {
                alert('Failed to send broadcast');
            }
        } catch(e) { console.error(e); alert('Error sending broadcast'); }
    },
    async cancelBroadcast(id) {
        if (!confirm('Apakah Anda yakin ingin membatalkan broadcast ini?')) return;
        try {
            const res = await fetch(`/whatsapp/broadcasts/${id}/cancel`, {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                    'Accept': 'application/json' 
                }
            });
            if (res.ok) {
                this.refresh();
                alert('Broadcast dibatalkan');
            } else {
                alert('Gagal membatalkan broadcast');
            }
        } catch(e) { 
            console.error(e); 
            alert('Error membatalkan broadcast'); 
        }
    }
}">
    <!-- Header Actions -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Broadcast Messages</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Send manual messages to multiple recipients</p>
        </div>
        <button
            @click="$dispatch('open-broadcast-modal')"
            class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Create Broadcast
        </button>
    </div>

    <!-- Filter -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 mb-6 p-4">
        <select x-model="filterStatus" @change="loadTabData('broadcasts')" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500">
            <option value="all">All Statuses</option>
            <template x-for="(label, key) in broadcastsData.statuses" :key="key">
                <option :value="key" x-text="label"></option>
            </template>
        </select>
    </div>

    <!-- Broadcast List -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Target</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recipients</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <template x-if="!broadcastsData.broadcasts || broadcastsData.broadcasts.data.length === 0">
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                No broadcasts found. Create one to get started.
                            </td>
                        </tr>
                    </template>
                    <template x-for="broadcast in broadcastsData.broadcasts.data" :key="broadcast.id">
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="broadcast.title"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 line-clamp-1" x-text="broadcast.message"></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                <span x-text="broadcast.target_type"></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 dark:text-gray-100" x-text="broadcast.total_recipients + ' recipients'"></div>
                                <template x-if="broadcast.status === 'sent'">
                                    <div class="text-xs">
                                        <span class="text-green-600" x-text="broadcast.sent_count + ' sent'"></span>
                                        <span class="text-red-600 ml-1" x-show="broadcast.failed_count > 0" x-text="broadcast.failed_count + ' failed'"></span>
                                    </div>
                                </template>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                      :class="{
                                          'bg-gray-100 text-gray-800': broadcast.status === 'draft',
                                          'bg-blue-100 text-blue-800': broadcast.status === 'scheduled',
                                          'bg-amber-100 text-amber-800': broadcast.status === 'sending',
                                          'bg-green-100 text-green-800': broadcast.status === 'sent',
                                          'bg-red-100 text-red-800': broadcast.status === 'cancelled'
                                      }"
                                      x-text="broadcast.status">
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <div x-text="new Date(broadcast.created_at).toLocaleString()"></div>
                                <div class="text-xs" x-text="'by ' + (broadcast.creator ? broadcast.creator.name : '-')"></div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Send Button -->
                                    <button x-show="['draft', 'scheduled'].includes(broadcast.status)"
                                            @click="sendBroadcast(broadcast.id, false)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors"
                                            title="Send Now">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                        </svg>
                                        Send
                                    </button>
                                    
                                    <!-- Edit -->
                                    <button x-show="['draft', 'scheduled'].includes(broadcast.status)"
                                            @click="$dispatch('open-broadcast-modal', { broadcast: broadcast })"
                                            class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 transition-colors"
                                            title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>

                                    <!-- Delete -->
                                    <button x-show="['draft', 'scheduled'].includes(broadcast.status)"
                                            @click="deleteBroadcast(broadcast.id)"
                                            class="p-1.5 rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-900/20 dark:hover:text-red-400 transition-colors"
                                            title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                    
                                    <!-- Cancel -->
                                    <button x-show="broadcast.status === 'scheduled'"
                                            @click="cancelBroadcast(broadcast.id)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-700 bg-red-100 hover:bg-red-200 dark:text-red-400 dark:bg-red-900/30 dark:hover:bg-red-900/50 rounded-lg transition-colors">
                                        Cancel
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <!-- Pagination would go here if we implemented it fully client-side or handled page links -->
    </div>
</div>
