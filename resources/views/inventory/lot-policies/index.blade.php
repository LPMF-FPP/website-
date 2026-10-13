<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Kebijakan Pelacakan Lot"
            :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Lokasi', 'href' => route('inventory.locations.index')], ['label' => 'Kebijakan Lot']]"
        />
    </x-slot>

    <div class="max-w-5xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('success'))
            <p role="status" class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</p>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p>Kebijakan belum tersimpan. Periksa kembali isian berikut:</p>
                <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="card space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Matriks lot per tipe item</h2>
                <p class="mt-1 text-sm text-gray-600">Aturan nomor lot harus mengikuti keputusan pemilik inventori. Jenis barang yang belum memiliki aturan belum dapat dimasukkan ke pencatatan stok awal.</p>
            </div>
            <form method="POST" action="{{ route('inventory.baseline.policies.update') }}">
                @csrf
                @method('PUT')
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                            <tr><th class="px-3 py-2">Tipe item</th><th class="px-3 py-2">Klasifikasi</th><th class="px-3 py-2">Ditetapkan oleh</th><th class="px-3 py-2">Item aktif</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm">
                            @foreach($itemTypes as $itemType => $label)
                                @php($policy = $policies->get($itemType))
                                <tr>
                                    <th scope="row" class="px-3 py-3 text-left font-medium">{{ $label }}</th>
                                    <td class="px-3 py-3">
                                        <label class="sr-only" for="policy-{{ $itemType }}">Kebijakan lot untuk {{ $label }}</label>
                                        <select id="policy-{{ $itemType }}" name="policies[{{ $itemType }}]" class="min-h-11 rounded-md border-gray-300">
                                            <option value="" @selected(old('policies.'.$itemType, $policy?->lot_tracking_mode) === null)>Belum ditetapkan</option>
                                            <option value="lot_tracked" @selected(old('policies.'.$itemType, $policy?->lot_tracking_mode) === 'lot_tracked')>Wajib menggunakan lot</option>
                                            <option value="untracked" @selected(old('policies.'.$itemType, $policy?->lot_tracking_mode) === 'untracked')>Tanpa pelacakan lot</option>
                                        </select>
                                        @if($policy?->decider)
                                            <p class="mt-1 text-xs text-gray-600">Ditetapkan {{ $policy->decided_at?->format('d/m/Y H:i T') }} oleh {{ $policy->decider->name }}. Bukti: {{ $policy->evidence_reference }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">{{ $policy?->decider?->name ?? 'Belum ditetapkan' }}</td>
                                    <td class="px-3 py-3">{{ (int) ($activeCounts[$itemType] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 max-w-2xl">
                    <label for="approval-reference" class="block text-sm font-medium text-gray-700">Referensi persetujuan pemilik inventori <span class="text-red-600">*</span></label>
                    <input id="approval-reference" name="evidence_reference" value="{{ old('evidence_reference') }}" required maxlength="255" class="mt-1 min-h-11 w-full rounded-md border-gray-300">
                    <p class="mt-1 text-xs text-gray-600">Gunakan nomor keputusan atau referensi bukti persetujuan. Perubahan matriks dicatat dalam audit permanen.</p>
                </div>
                <button type="submit" class="btn btn-primary mt-5 min-h-11 rounded-md px-4 py-2 font-semibold">Simpan kebijakan yang disetujui</button>
            </form>
        </section>

        <section class="rounded-lg bg-white p-6 shadow-sm" aria-labelledby="policy-history-heading">
            <h2 id="policy-history-heading" class="text-lg font-semibold">Riwayat perubahan kebijakan</h2>
            @forelse($events as $event)
                <p class="break-words border-b py-3 text-sm text-gray-700">
                    {{ $event->occurred_at->format('d/m/Y H:i T') }} · {{ $event->item_type }} ·
                    {{ $event->previous_lot_tracking_mode ?? 'belum ditetapkan' }} → {{ $event->lot_tracking_mode ?? 'belum ditetapkan' }}
                    oleh {{ $event->actor?->name ?? 'Pengguna tidak tersedia' }}. Bukti: {{ $event->evidence_reference }}
                </p>
            @empty
                <p class="mt-3 text-sm text-gray-600">Belum ada klasifikasi lot yang disetujui.</p>
            @endforelse
            @if($events->hasPages())<div class="mt-4">{{ $events->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>
