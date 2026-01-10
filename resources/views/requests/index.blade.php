@push('html-attrs') data-ui="minimal" data-theme="light" @endpush
@push('styles')
    @vite(['resources/css/ui-scope.css'])
@endpush
@push('scripts')
    <script type="module" src="/scripts/ui.theme-toggle.js"></script>
@endpush

<x-app-layout>

    <x-slot name="header">
        <div>
            <x-breadcrumbs :items="[[ 'label' => 'Permintaan' ]]" />
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
                                            @php($firstSampleId = optional($request->samples->first())->id)
                                            <div class="flex flex-wrap gap-2">
                                                <a href="{{ route('samples.test.create', ['request_id' => $request->id]) }}"
                                                   class="inline-flex items-center rounded-lg border border-primary-600 px-3 py-1 text-sm font-semibold text-primary-600 transition hover:bg-primary-50">
                                                    Pengujian
                                                </a>
                                                @if($firstSampleId)
                                                    <a href="{{ route('process.show', $request) }}"
                                                       class="inline-flex items-center rounded-lg border border-primary-200 px-3 py-1 text-sm font-semibold text-primary-700 transition hover:border-primary-500 hover:text-primary-600">
                                                        Proses
                                                    </a>
                                                @endif
                                                @if($request->status === 'ready_for_delivery')
                                                    <a href="{{ route('delivery.show', $request) }}"
                                                       class="inline-flex items-center rounded-lg bg-secondary-400 px-3 py-1 text-sm font-semibold text-accent-900 transition hover:bg-secondary-500">
                                                        Penyerahan
                                                    </a>
                                                @endif
                                            </div>
                                            <div class="mt-2 flex flex-wrap gap-3 text-sm">
                                                <a href="{{ route('requests.show', $request) }}"
                                                   class="text-primary-600 transition hover:text-primary-700">Detail</a>
                                                <a href="{{ route('requests.edit', $request) }}"
                                                   class="text-warning-600 transition hover:text-warning-700">Edit</a>
                                                <form method="POST" action="{{ route('requests.destroy', $request) }}"
                                                      class="inline" x-data>
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" 
                                                        class="text-danger-600 transition hover:text-danger-700"
                                                        @click.prevent="showConfirmDialog({
                                                            type: 'danger',
                                                            title: 'Hapus Permintaan',
                                                            message: 'Yakin ingin menghapus?',
                                                            confirmButtonText: 'Ya, Hapus',
                                                            onConfirm: () => $el.closest('form').submit()
                                                        })">Hapus</button>
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
