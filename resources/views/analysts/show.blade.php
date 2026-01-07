@php
    use Illuminate\Support\Str;

    $formatValue = function ($value) {
        if (is_array($value)) {
            return json_encode($value);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    };

    $buildChanges = function ($before, $after) use ($formatValue) {
        $before = is_array($before) ? $before : [];
        $after = is_array($after) ? $after : [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $changes = [];

        foreach ($keys as $key) {
            $beforeValue = $before[$key] ?? null;
            $afterValue = $after[$key] ?? null;

            if ($beforeValue === $afterValue) {
                continue;
            }

            $changes[] = $key.': '.$formatValue($beforeValue).' -> '.$formatValue($afterValue);
        }

        return $changes;
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Detail Pengguna"
            :breadcrumbs="[[ 'label' => 'Pengguna', 'href' => route('analysts.index') ], [ 'label' => $analyst->name ]]"
        >
            <x-slot name="actions">
                <a href="{{ route('analysts.index') }}"
                    class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-600">Kembali</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
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

        <x-page-section title="Profil Pengguna">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="text-lg font-semibold text-gray-900">{{ $analyst->display_name_with_title }}</div>
                    <div class="mt-1 text-sm text-gray-600">{{ $analyst->email }}</div>
                    <div class="mt-2 text-sm text-gray-600">Pangkat: {{ $analyst->rank ?? '-' }}</div>
                    <div class="text-sm text-gray-600">NRP: {{ $analyst->nrp ?? '-' }} | NIP: {{ $analyst->nip ?? '-' }}</div>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <x-status-badge
                            variant="secondary"
                            :label="Str::of($analyst->role)->replace('_', ' ')->title()"
                            subtle
                        />
                        <x-status-badge
                            :variant="$analyst->is_active ? 'success' : 'danger'"
                            :label="$analyst->is_active ? 'Aktif' : 'Nonaktif'"
                            subtle
                        />
                    </div>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm lg:col-span-2">
                    <h3 class="text-sm font-semibold text-gray-900">Ringkasan</h3>
                    <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-gray-100 p-4">
                            <div class="text-xs uppercase text-gray-500">ID Pengguna</div>
                            <div class="text-sm font-semibold text-gray-900">#{{ $analyst->id }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-100 p-4">
                            <div class="text-xs uppercase text-gray-500">Aktivitas Terakhir</div>
                            <div class="text-sm font-semibold text-gray-900">
                                {{ $lastActivity?->diffForHumans() ?? 'Belum ada' }}
                            </div>
                            @if($lastActivity)
                                <div class="text-xs text-gray-500">{{ $lastActivity->format('d M Y H:i') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </x-page-section>

        <x-page-section title="Aksi Pengguna">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-lg bg-white p-6 shadow-sm lg:col-span-2">
                    <h3 class="text-sm font-semibold text-gray-900">Ubah Role</h3>
                    <form method="POST" action="{{ route('analysts.role.update', $analyst) }}" class="mt-4 space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Role baru</label>
                            <select name="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                @if(auth()->id() === $analyst->id) disabled @endif>
                                @foreach($roles as $roleOption)
                                    <option value="{{ $roleOption }}" @selected($analyst->role === $roleOption)>
                                        {{ Str::of($roleOption)->replace('_', ' ')->title() }}
                                    </option>
                                @endforeach
                            </select>
                            @if(auth()->id() === $analyst->id)
                                <p class="mt-2 text-xs text-gray-500">Anda tidak dapat mengubah role sendiri.</p>
                            @endif
                        </div>
                        <div class="flex justify-end">
                            <button type="submit"
                                class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700"
                                @if(auth()->id() === $analyst->id) disabled @endif>
                                Simpan Role
                            </button>
                        </div>
                    </form>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">Status Akun</h3>
                    <div class="mt-3 text-sm text-gray-600">Status saat ini: <span class="font-semibold">{{ $analyst->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
                    <div class="mt-4 space-y-3">
                        @if(auth()->id() === $analyst->id)
                            <p class="text-xs text-gray-500">Anda tidak dapat menonaktifkan akun sendiri.</p>
                        @else
                            @if($analyst->is_active)
                                <form method="POST" action="{{ route('analysts.disable', $analyst) }}" class="space-y-3"
                                    onsubmit="return confirm('Nonaktifkan pengguna ini?');">
                                    @csrf
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Alasan (opsional)</label>
                                        <textarea name="reason" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                                    </div>
                                    <button type="submit" class="w-full rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">Nonaktifkan</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('analysts.enable', $analyst) }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">Aktifkan</button>
                                </form>
                            @endif
                        @endif

                        @if(auth()->id() !== $analyst->id)
                            <form method="POST" action="{{ route('analysts.destroy', $analyst) }}"
                                onsubmit="return confirm('Hapus pengguna ini? Data akan diarsipkan (soft delete).');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800">Hapus Pengguna</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </x-page-section>

        <x-page-section title="Aktivitas Terakhir">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Waktu</th>
                            <th class="px-4 py-3 text-left">Aksi</th>
                            <th class="px-4 py-3 text-left">Objek</th>
                            <th class="px-4 py-3 text-left">Ringkas Perubahan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse($recentLogs as $log)
                            @php
                                $changes = $buildChanges($log->before, $log->after);
                                $subjectLabel = $log->subject_type ? class_basename($log->subject_type) : '-';
                                $subjectId = $log->subject_id ? '#'.$log->subject_id : '';
                                $metaSummary = [];
                                if (is_array($log->meta ?? null)) {
                                    foreach ($log->meta as $metaKey => $metaValue) {
                                        if (is_array($metaValue)) {
                                            continue;
                                        }
                                        $metaSummary[] = $metaKey.': '.$formatValue($metaValue);
                                    }
                                }
                            @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $log->created_at->format('d M Y H:i') }}</div>
                                    <div class="text-xs text-gray-500">{{ $log->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">{{ Str::of($log->action)->replace('_', ' ')->title() }}</div>
                                    @if($log->actor)
                                        <div class="text-xs text-gray-500">Aktor: {{ $log->actor->name }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    {{ $subjectLabel }} {{ $subjectId }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($changes)
                                        <div class="text-xs text-gray-600">
                                            {{ implode(', ', array_slice($changes, 0, 3)) }}
                                        </div>
                                    @elseif($metaSummary)
                                        <div class="text-xs text-gray-600">
                                            {{ implode(', ', array_slice($metaSummary, 0, 3)) }}
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500">Belum ada aktivitas tercatat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end">
                <a href="{{ route('analysts.logs', $analyst) }}"
                    class="inline-flex items-center text-sm font-semibold text-primary-600 hover:text-primary-700">Lihat semua log</a>
            </div>
        </x-page-section>
    </div>
</x-app-layout>
