@push('html-attrs') data-ui="minimal" data-theme="light" @endpush
@push('styles')
    @vite(['resources/css/ui-scope.css'])
@endpush
@push('scripts')
    <script>
        document.documentElement.classList.remove('dark');
        document.documentElement.setAttribute('data-theme', 'light');
    </script>
@endpush

<x-app-layout>

    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-primary-900 leading-tight">
                📋 Daftar Permintaan Pengujian
            </h2>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        @if (session('success'))
            <div class="mb-4 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-success-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="card overflow-hidden" x-data="{ loading: false }">
            <div class="space-y-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-lg font-semibold text-primary-900">Daftar Permintaan</h3>
                    <a href="{{ route('requests.create') }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white transition hover:bg-primary-700">
                        <span>➕</span>
                        <span>Buat Permintaan Baru</span>
                    </a>
                </div>

                <template x-if="loading">
                    <x-skeleton-table :columns="6" :rows="8" />
                </template>

                @if($requests->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-primary-100">
                            <thead class="bg-primary-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary-700">No. Resi</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary-700">Penyidik</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary-700">Tersangka</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary-700">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary-700">Tanggal</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-primary-700">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-primary-100 bg-white">
                                @foreach($requests as $request)
                                    <tr class="transition hover:bg-primary-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-primary-900">
                                            {{ $request->receipt_number ?? $request->request_number }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-primary-800">
                                            {{ $request->investigator->name }} ({{ $request->investigator->rank }})
                                        </td>
                                        <td class="px-6 py-4 text-sm text-primary-800">
                                            @if($request->suspects->count() > 0)
                                                <div class="flex flex-col gap-1">
                                                    <span class="font-medium">{{ $request->suspects->first()->name }}</span>
                                                    @if($request->suspects->count() > 1)
                                                        <span class="text-xs text-primary-500">+{{ $request->suspects->count() - 1 }} tersangka lainnya</span>
                                                    @endif
                                                </div>
                                            @else
                                                {{ $request->suspect_name ?? '-' }}
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <x-status-badge :status="$request->status" />
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-accent-600">
                                            {{ $request->created_at->format('d M Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex flex-wrap items-center gap-2">
                                                {{-- Detail Button --}}
                                                <a href="{{ route('requests.show', $request) }}"
                                                   class="inline-flex items-center gap-1.5 rounded-lg border border-teal-200 bg-teal-50 px-3 py-1.5 text-xs font-medium text-teal-700 transition-colors duration-150 hover:border-teal-300 hover:bg-teal-100">
                                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                    Detail
                                                </a>

                                                {{-- Edit Button --}}
                                                <a href="{{ route('requests.edit', $request) }}"
                                                   class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 transition-colors duration-150 hover:border-amber-300 hover:bg-amber-100">
                                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                    Edit
                                                </a>

                                                {{-- Delete Button --}}
                                                <form method="POST" action="{{ route('requests.destroy', $request) }}" class="inline" x-data>
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button"
                                                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-red-600 transition-colors duration-150 hover:border-red-200 hover:bg-red-50 hover:text-red-700"
                                                        @click.prevent="showConfirmDialog({
                                                            type: 'danger',
                                                            title: 'Hapus Permintaan',
                                                            message: 'Yakin ingin menghapus permintaan ini? Data yang sudah dihapus tidak dapat dikembalikan.',
                                                            confirmButtonText: 'Ya, Hapus',
                                                            onConfirm: () => $el.closest('form').submit()
                                                        })">
                                                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                        Hapus
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div>
                        {{ $requests->links() }}
                    </div>
                @else
                    <x-empty-state
                        title="Belum ada permintaan pengujian"
                        description="Mulai dengan membuat permintaan pertama untuk pengujian."
                        :actionHref="route('requests.create')"
                        actionText="Buat Permintaan Pertama"
                        icon="document"
                    />
                @endif
            </div>
        </div>
    </div>

</x-app-layout>
