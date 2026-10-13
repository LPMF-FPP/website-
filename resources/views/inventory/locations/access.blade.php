<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Akses Lokasi Terbatas" :breadcrumbs="[['label' => 'Inventori', 'href' => auth()->user()?->hasPermission('inventori.view') ? route('inventory.dashboard') : route('inventory.locations.index')], ['label' => 'Lokasi', 'href' => route('inventory.locations.index')], ['label' => $location->name]]" />
    </x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        @if(session('success'))
            <p role="status" class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</p>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p>Perubahan akses belum tersimpan. Periksa kembali isian berikut:</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <p class="text-sm text-gray-600">Akses transaksi hanya memberi konteks minimum untuk operasi yang diizinkan; akses ini tidak mencakup riwayat atau laporan lokasi.</p>
        <p class="text-sm text-gray-600">Hilangkan kedua centang untuk mencabut akses pengguna; perubahan akan tercatat pada riwayat. Pengguna nonaktif hanya dapat dikurangi atau dicabut hak aksesnya.</p>
        <section class="overflow-hidden rounded-lg bg-white shadow-sm">
            <p class="px-4 pt-3 text-xs text-gray-600 sm:hidden">Geser tabel ke samping untuk melihat kolom alasan dan tindakan.</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr><th class="px-4 py-3">Pengguna</th><th class="px-4 py-3">Akses</th><th class="px-4 py-3">Alasan perubahan</th><th class="px-4 py-3">Tindakan</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        @forelse($users as $user)
                            @php($grant = $grants->get($user->id))
                            @php($submittedUser = (int) old('user_id', 0) === (int) $user->id)
                            <tr>
                                <td class="px-4 py-3"><span class="block font-medium text-gray-900">{{ $user->name }}</span><span class="text-gray-500">{{ $user->email }}</span>@unless($user->is_active)<span class="block font-medium">Nonaktif — hanya pencabutan</span>@endunless</td>
                                <td class="px-4 py-3">
                                    <form id="grant-{{ $user->id }}" method="POST" action="{{ route('inventory.locations.access.update', $location) }}" class="space-y-2">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="user_id" value="{{ $user->id }}">
                                        <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="can_view" value="1" @checked($submittedUser ? old('can_view') : $grant?->can_view) @disabled(!$user->is_active && !$grant?->can_view)> <span>Lihat riwayat dan laporan</span></label>
                                        <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="can_transact" value="1" @checked($submittedUser ? old('can_transact') : $grant?->can_transact) @disabled(!$user->is_active && !$grant?->can_transact)> <span>Transaksi (konteks minimum)</span></label>
                                    </form>
                                </td>
                                <td class="px-4 py-3"><label class="sr-only" for="reason-{{ $user->id }}">Alasan untuk {{ $user->name }}</label><input id="reason-{{ $user->id }}" form="grant-{{ $user->id }}" name="reason" maxlength="2000" value="{{ $submittedUser ? mb_substr((string) old('reason'), 0, 2000) : '' }}" class="w-full rounded-md border-gray-300" placeholder="Opsional"></td>
                                <td class="px-4 py-3"><button type="submit" form="grant-{{ $user->id }}" class="btn btn-primary min-h-11 rounded-md px-4 font-medium">Simpan</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-600">Belum ada pengguna aktif yang dapat menerima akses lokasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
                <div class="p-4">{{ $users->links() }}</div>
            @endif
        </section>
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Riwayat perubahan akses</h2>
            @forelse($events as $event)
                <p class="break-words border-b py-3 text-sm text-gray-700">
                    {{ $event->occurred_at->format('d/m/Y H:i T') }}: {{ $event->actor?->name ?? 'Pengguna tidak tersedia' }} mengubah hak akses {{ $event->target?->name ?? 'pengguna tidak tersedia' }} di {{ $location->name }}.
                    Aksi: {{ ['granted' => 'diberikan', 'revoked' => 'dicabut', 'updated' => 'diperbarui'][$event->action] ?? 'diubah' }}.
                    Lihat {{ $event->previous_can_view ? 'ya' : 'tidak' }} menjadi {{ $event->can_view ? 'ya' : 'tidak' }}; transaksi {{ $event->previous_can_transact ? 'ya' : 'tidak' }} menjadi {{ $event->can_transact ? 'ya' : 'tidak' }}.
                    @if($event->context['surface'] ?? false)<span class="text-gray-600">Perubahan dari Master Lokasi.</span>@endif
                    @if($event->reason)<span>Alasan: {{ $event->reason }}</span>@endif
                </p>
            @empty
                <p class="mt-3 text-sm text-gray-600">Belum ada perubahan akses.</p>
            @endforelse
            @if($events->hasPages())
                <div class="mt-4">{{ $events->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
