<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Broadcast WhatsApp"
            :breadcrumbs="[['label' => 'Pengaturan', 'url' => route('settings.index')], ['label' => 'Broadcast']]"
        />
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-6"
         x-data="broadcastManager()"
         x-init="init()">

        <!-- Header Actions -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-pd-text">Daftar Broadcast</h2>
                <p class="text-sm text-pd-text-muted mt-1">Kirim pesan WhatsApp ke banyak penerima sekaligus</p>
            </div>
            <a href="{{ route('broadcasts.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Buat Broadcast
            </a>
        </div>

        <!-- Filter -->
        <div class="surface-sem rounded-lg border border-sem mb-6">
            <div class="p-4">
                <select x-model="filterStatus"
                        @change="applyFilter()"
                        class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500">
                    <option value="all">Semua Status</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Broadcast List -->
        <div class="surface-sem rounded-lg border border-sem overflow-hidden">
            @if($broadcasts->isEmpty())
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-pd-text">Belum ada broadcast</h3>
                    <p class="mt-2 text-sm text-pd-text-muted">Buat broadcast pertama untuk mengirim pesan ke banyak penerima.</p>
                    <a href="{{ route('broadcasts.create') }}"
                       class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Buat Broadcast
                    </a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Judul</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Target</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Penerima</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dibuat</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($broadcasts as $broadcast)
                                @php
                                    $statusColors = [
                                        'draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                        'scheduled' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                                        'sending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
                                        'sent' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                        'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                                    ];
                                    $targetLabels = [
                                        'investigators' => 'Penyidik',
                                        'users' => 'Staff',
                                        'custom' => 'Kustom',
                                    ];
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-pd-text">{{ $broadcast->title }}</div>
                                        <div class="text-xs text-pd-text-muted line-clamp-1">{{ Str::limit($broadcast->message, 50) }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-pd-text">
                                        {{ $targetLabels[$broadcast->target_type] ?? $broadcast->target_type }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-pd-text">{{ $broadcast->total_recipients }} penerima</div>
                                        @if($broadcast->status === 'sent')
                                            <div class="text-xs text-pd-text-muted">
                                                <span class="text-green-600">{{ $broadcast->sent_count }} terkirim</span>
                                                @if($broadcast->failed_count > 0)
                                                    <span class="text-red-600 ml-2">{{ $broadcast->failed_count }} gagal</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$broadcast->status] ?? $statusColors['draft'] }}">
                                            {{ $statuses[$broadcast->status] ?? $broadcast->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-pd-text-muted">
                                        {{ $broadcast->created_at->format('d M Y H:i') }}
                                        <div class="text-xs">oleh {{ $broadcast->creator->name ?? '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            @if($broadcast->canSend())
                                                <button @click="sendBroadcast({{ $broadcast->id }})"
                                                        type="button"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                                    </svg>
                                                    Kirim
                                                </button>
                                            @endif

                                            @if($broadcast->canEdit())
                                                <a href="{{ route('broadcasts.create', ['edit' => $broadcast->id]) }}"
                                                   class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 transition-colors"
                                                   title="Edit">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                </a>

                                                <button @click="deleteBroadcast({{ $broadcast->id }})"
                                                        type="button"
                                                        class="p-1.5 rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-900/20 dark:hover:text-red-400 transition-colors"
                                                        title="Hapus">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            @endif

                                            @if($broadcast->canCancel())
                                                <button @click="cancelBroadcast({{ $broadcast->id }})"
                                                        type="button"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-700 bg-red-100 hover:bg-red-200 dark:text-red-400 dark:bg-red-900/30 dark:hover:bg-red-900/50 rounded-lg transition-colors">
                                                    Batalkan
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $broadcasts->links() }}
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function broadcastManager() {
            return {
                filterStatus: '{{ request("status", "all") }}',

                init() {},

                applyFilter() {
                    const params = new URLSearchParams();
                    if (this.filterStatus !== 'all') {
                        params.set('status', this.filterStatus);
                    }
                    window.location.href = '{{ route("broadcasts.index") }}' + (params.toString() ? '?' + params.toString() : '');
                },

                async sendBroadcast(id) {
                    if (!confirm('Kirim broadcast ini ke semua penerima?')) return;

                    try {
                        const response = await fetch(`/broadcasts/${id}/send`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();

                        if (response.ok) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Gagal mengirim broadcast');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan');
                    }
                },

                async cancelBroadcast(id) {
                    if (!confirm('Batalkan broadcast ini?')) return;

                    try {
                        const response = await fetch(`/broadcasts/${id}/cancel`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        });

                        if (response.ok) {
                            window.location.reload();
                        } else {
                            const data = await response.json();
                            alert(data.message || 'Gagal membatalkan broadcast');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan');
                    }
                },

                async deleteBroadcast(id) {
                    if (!confirm('Hapus broadcast ini?')) return;

                    try {
                        const response = await fetch(`/broadcasts/${id}`, {
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
                            alert(data.message || 'Gagal menghapus broadcast');
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
