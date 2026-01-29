@php
    use Illuminate\Support\Str;

    $requestsCount = $requests->total();
    $latestRequest = $requests->first();
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Detail Penyidik"
            :breadcrumbs="[[ 'label' => 'Penyidik', 'href' => route('investigators.index') ], [ 'label' => $investigator->name ]]"
        >
            <x-slot name="actions">
                <div class="flex items-center gap-2">
                    @can('investigators.edit')
                        <a href="{{ route('investigators.edit', $investigator) }}"
                            class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Edit</a>
                    @endcan
                    <a href="{{ route('investigators.index') }}"
                        class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-600">Kembali</a>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if(session('success'))
            <div class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <x-page-section title="Biodata Penyidik">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="text-lg font-semibold text-pd-text">{{ trim($investigator->full_name) }}</div>
                    <div class="mt-1 text-sm text-pd-body">NRP: {{ $investigator->nrp ?? '-' }}</div>
                    <div class="text-sm text-pd-body">{{ $investigator->is_polri ? 'Satker' : 'Instansi' }}: {{ $investigator->jurisdiction ?? '-' }}</div>
                    <div class="mt-2 text-sm text-pd-body">No. HP: {{ $investigator->phone ?? '-' }}</div>
                    <div class="text-sm text-pd-body">Email: {{ $investigator->email ?? '-' }}</div>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <x-status-badge
                            variant="secondary"
                            :label="$investigator->is_polri ? 'Polri' : 'Non-Polri'"
                            subtle
                        />
                    </div>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm lg:col-span-2">
                    <h3 class="text-sm font-semibold text-gray-900">Ringkasan</h3>
                    <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-gray-100 p-4">
                            <div class="text-xs uppercase text-pd-muted">Jumlah Permintaan</div>
                            <div class="text-sm font-semibold text-pd-text">{{ $requestsCount }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-100 p-4">
                            <div class="text-xs uppercase text-pd-muted">Permintaan Terakhir</div>
                            <div class="text-sm font-semibold text-pd-text">
                                {{ $latestRequest?->submitted_at?->format('d M Y') ?? $latestRequest?->created_at?->format('d M Y') ?? '-' }}
                            </div>
                            @if($latestRequest?->submitted_at || $latestRequest?->created_at)
                                <div class="text-xs text-pd-muted">
                                    {{ ($latestRequest->submitted_at ?? $latestRequest->created_at)->format('H:i') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </x-page-section>

        <x-page-section title="Riwayat Permintaan">
            <div class="overflow-visible rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">No. Surat</th>
                            <th class="px-4 py-3 text-left">No. Perkara</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse($requests as $request)
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">{{ $request->request_number }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $request->case_number ?? '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-status-badge
                                        variant="secondary"
                                        :label="Str::of($request->status)->replace('_', ' ')->title()"
                                        subtle
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">
                                        {{ $request->submitted_at?->format('d M Y') ?? $request->created_at?->format('d M Y') ?? '-' }}
                                    </div>
                                    @if($request->submitted_at || $request->created_at)
                                        <div class="text-xs text-gray-500">
                                            {{ ($request->submitted_at ?? $request->created_at)->format('H:i') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('requests.show', $request) }}" class="text-sm font-semibold text-primary-600 hover:text-primary-700">Lihat</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada permintaan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $requests->links() }}
            </div>
        </x-page-section>

        <x-page-section title="Aksi Penyidik">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                @can('investigators.delete')
                    @if($requestsCount > 0)
                        <div class="text-sm text-gray-600">Penyidik tidak bisa dihapus karena sudah memiliki permintaan.</div>
                    @else
                        <form method="POST" action="{{ route('investigators.destroy', $investigator) }}" x-data>
                            @csrf
                            @method('DELETE')
                            <button type="button"
                                class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700"
                                @click.prevent="showConfirmDialog({
                                    type: 'danger',
                                    title: 'Hapus Penyidik',
                                    message: 'Hapus penyidik ini? Data permintaan tetap tersimpan.',
                                    confirmButtonText: 'Ya, Hapus',
                                    onConfirm: () => $el.closest('form').submit()
                                })">
                                Hapus Penyidik
                            </button>
                        </form>
                    @endif
                @else
                    <div class="text-sm text-gray-600">Tidak ada aksi yang tersedia untuk akun ini.</div>
                @endcan
            </div>
        </x-page-section>
    </div>
</x-app-layout>
