@php use Illuminate\Support\Str; @endphp

<div class="space-y-6">
    <p class="text-sm text-gray-600">Kelola role, status, dan audit aktivitas pengguna laboratorium.</p>

    @if(session('success'))
        <div class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <x-page-section title="Filter Pengguna">
        <form method="GET" action="{{ route('personnel.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <input type="hidden" name="tab" value="staff">
            <div>
                <label class="block text-sm font-medium text-gray-700">Role</label>
                <select name="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    <option value="">Semua role</option>
                    @foreach($availableRoles as $roleOption)
                        <option value="{{ $roleOption }}" @selected(($filters['role'] ?? '') === $roleOption)>
                            {{ Str::of($roleOption)->replace('_', ' ')->title() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    <option value="">Semua status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Kata kunci</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama / Email / NRP"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            </div>
            <div class="flex items-end gap-3">
                <button type="submit"
                    class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Terapkan</button>
                <a href="{{ route('personnel.index', ['tab' => 'staff']) }}"
                    class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-600">Reset</a>
            </div>
        </form>
    </x-page-section>

    <x-page-section title="Daftar Pengguna">
        <div class="overflow-visible rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Nama</th>
                        <th class="px-4 py-3 text-left">Kontak</th>
                        <th class="px-4 py-3 text-left">Role</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Aktivitas Terakhir</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                    @forelse($staff as $analyst)
                        <tr class="hover:bg-gray-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">{{ $analyst->display_name_with_title }}</div>
                                <div class="text-xs text-gray-500">{{ $analyst->rank ?? '-' }}</div>
                                <div class="text-xs text-gray-500">NRP: {{ $analyst->nrp ?? '-' }} | NIP: {{ $analyst->nip ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-gray-900">{{ $analyst->email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <x-status-badge
                                    variant="secondary"
                                    :label="Str::of($analyst->role)->replace('_', ' ')->title()"
                                    subtle
                                />
                            </td>
                            <td class="px-4 py-3">
                                <x-status-badge
                                    :variant="$analyst->is_active ? 'success' : 'danger'"
                                    :label="$analyst->is_active ? 'Aktif' : 'Nonaktif'"
                                    subtle
                                />
                            </td>
                            <td class="px-4 py-3">
                                @if($analyst->last_activity_at)
                                    <div class="font-medium text-gray-900">{{ $analyst->last_activity_at->diffForHumans() }}</div>
                                    <div class="text-xs text-gray-500">{{ $analyst->last_activity_at->format('d M Y H:i') }}</div>
                                @else
                                    <span class="text-xs text-gray-400">Belum ada aktivitas</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-dropdown align="right" width="48">
                                    <x-slot name="trigger">
                                        <span class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-600">
                                            Aksi
                                        </span>
                                    </x-slot>
                                    <x-slot name="content">
                                        <x-dropdown-link :href="route('analysts.show', $analyst)">
                                            Lihat
                                        </x-dropdown-link>
                                        <x-dropdown-link :href="route('analysts.show', $analyst)">
                                            Ubah role
                                        </x-dropdown-link>
                                        <x-dropdown-link :href="route('analysts.logs', $analyst)">
                                            Log aktivitas
                                        </x-dropdown-link>

                                        <div class="border-t border-gray-100"></div>

                                        @if(auth()->id() === $analyst->id)
                                            <div class="px-4 py-2 text-xs text-gray-400">Akun Anda</div>
                                        @else
                                            @if($analyst->is_active)
                                                <form method="POST" action="{{ route('analysts.disable', $analyst) }}" x-data>
                                                    @csrf
                                                    <x-dropdown-link href="#"
                                                        @click.prevent="showConfirmDialog({
                                                            type: 'warning',
                                                            title: 'Nonaktifkan Pengguna',
                                                            message: 'Nonaktifkan pengguna ini?',
                                                            confirmButtonText: 'Ya, Nonaktifkan',
                                                            onConfirm: () => $el.closest('form').submit()
                                                        })">
                                                        Nonaktifkan
                                                    </x-dropdown-link>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('analysts.enable', $analyst) }}">
                                                    @csrf
                                                    <x-dropdown-link :href="route('analysts.enable', $analyst)"
                                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                                        Aktifkan
                                                    </x-dropdown-link>
                                                </form>
                                            @endif

                                            <form method="POST" action="{{ route('analysts.destroy', $analyst) }}" x-data>
                                                @csrf
                                                @method('DELETE')
                                                <x-dropdown-link href="#"
                                                    @click.prevent="showConfirmDialog({
                                                        type: 'danger',
                                                        title: 'Hapus Pengguna',
                                                        message: 'Hapus pengguna ini? Data akan diarsipkan (soft delete).',
                                                        confirmButtonText: 'Ya, Hapus',
                                                        onConfirm: () => $el.closest('form').submit()
                                                    })">
                                                    Hapus
                                                </x-dropdown-link>
                                            </form>
                                        @endif
                                    </x-slot>
                                </x-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">Belum ada data pengguna.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $staff->links() }}
        </div>
    </x-page-section>
</div>
