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
                    <div class="text-lg font-semibold text-pd-text">{{ $analyst->display_name_with_title }}</div>
                    <div class="mt-1 text-sm text-pd-body">{{ $analyst->email }}</div>
                    <div class="text-sm text-pd-body">Telepon: {{ $analyst->phone ?? '-' }}</div>
                    <div class="mt-2 text-sm text-pd-body">Pangkat: {{ $analyst->rank ?? '-' }}</div>
                    <div class="text-sm text-pd-body">NRP: {{ $analyst->nrp ?? '-' }} | NIP: {{ $analyst->nip ?? '-' }}</div>
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
                    <h3 class="text-sm font-semibold text-pd-text">Ringkasan</h3>
                    <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-gray-100 p-4">
                            <div class="text-xs uppercase text-pd-muted">ID Pengguna</div>
                            <div class="text-sm font-semibold text-pd-text">#{{ $analyst->id }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-100 p-4">
                            <div class="text-xs uppercase text-pd-muted">Aktivitas Terakhir</div>
                            <div class="text-sm font-semibold text-pd-text">
                                {{ $lastActivity?->diffForHumans() ?? 'Belum ada' }}
                            </div>
                            @if($lastActivity)
                                <div class="text-xs text-pd-muted">{{ $lastActivity->format('d M Y H:i') }}</div>
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
                                <form method="POST" action="{{ route('analysts.disable', $analyst) }}" class="space-y-3" x-data>
                                    @csrf
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">Alasan (opsional)</label>
                                        <textarea name="reason" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
                                    </div>
                                    <button type="button" 
                                        class="w-full rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700"
                                        @click.prevent="showConfirmDialog({
                                            type: 'warning',
                                            title: 'Nonaktifkan Pengguna',
                                            message: 'Nonaktifkan pengguna ini?',
                                            confirmButtonText: 'Ya, Nonaktifkan',
                                            onConfirm: () => $el.closest('form').submit()
                                        })">Nonaktifkan</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('analysts.enable', $analyst) }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">Aktifkan</button>
                                </form>
                            @endif
                        @endif

                        @if(auth()->id() !== $analyst->id)
                            <form method="POST" action="{{ route('analysts.destroy', $analyst) }}" x-data>
                                @csrf
                                @method('DELETE')
                                <button type="button" 
                                    class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800"
                                    @click.prevent="showConfirmDialog({
                                        type: 'danger',
                                        title: 'Hapus Pengguna',
                                        message: 'Hapus pengguna ini? Data akan diarsipkan (soft delete).',
                                        confirmButtonText: 'Ya, Hapus',
                                        onConfirm: () => $el.closest('form').submit()
                                    })">Hapus Pengguna</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </x-page-section>

        {{-- Section: Akses Halaman --}}
        <x-page-section title="Akses Halaman">
            <div class="rounded-lg bg-white p-6 shadow-sm" x-data="permissionManager()">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Atur halaman apa saja yang boleh diakses oleh pengguna ini.</p>
                        <p class="mt-1 text-xs text-gray-500">
                            <span class="inline-flex items-center gap-1">
                                <span class="h-3 w-3 rounded-full bg-gray-200 border border-gray-300"></span>
                                <span>Default dari role</span>
                            </span>
                            <span class="ml-3 inline-flex items-center gap-1">
                                <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                                <span>Custom (granted)</span>
                            </span>
                            <span class="ml-3 inline-flex items-center gap-1">
                                <span class="h-3 w-3 rounded-full bg-red-500"></span>
                                <span>Custom (revoked)</span>
                            </span>
                        </p>
                    </div>
                    @if(auth()->id() !== $analyst->id)
                        <form method="POST" action="{{ route('analysts.permissions.reset', $analyst) }}" x-ref="resetForm">
                            @csrf
                            <button type="button"
                                class="text-sm text-gray-500 hover:text-gray-700 underline"
                                @click.prevent="showConfirmDialog({
                                    type: 'warning',
                                    title: 'Reset Permission',
                                    message: 'Reset semua permission ke default role?',
                                    confirmButtonText: 'Ya, Reset',
                                    onConfirm: () => $refs.resetForm.submit()
                                })">
                                Reset ke Default
                            </button>
                        </form>
                    @endif
                </div>

                <form method="POST" action="{{ route('analysts.permissions.update', $analyst) }}" x-ref="permForm">
                    @csrf
                    @method('PUT')

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Halaman</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500 w-20">Lihat</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500 w-20">Tambah</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500 w-20">Edit</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500 w-20">Hapus</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500 w-20">Export</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @php
                                    $moduleOrder = [
                                        'dashboard' => 'Dashboard',
                                        'permintaan' => 'Permintaan',
                                        'kaji-ulang' => 'Kaji Ulang Permintaan',
                                        'pengujian' => 'Pengujian',
                                        'penyerahan' => 'Penyerahan',
                                        'tracking' => 'Tracking',
                                        'pencarian' => 'Pencarian',
                                        'statistik' => 'Statistik',
                                        'monitoring' => 'Monitoring Suhu',
                                        'inventori' => 'Inventori',
                                        'changelogs' => 'Changelogs',
                                        'analysts' => 'Manajemen Staff',
                                        'settings' => 'Pengaturan Sistem',
                                    ];
                                    $allActions = ['view', 'create', 'edit', 'delete', 'export'];
                                @endphp

                                @foreach($moduleOrder as $moduleKey => $moduleName)
                                    @php
                                        $moduleData = $permissionsData[$moduleKey] ?? null;
                                        $availableActions = $allModules[$moduleKey] ?? [];
                                        $isReferensi = in_array($moduleKey, ['tracking', 'pencarian', 'statistik', 'monitoring', 'inventori', 'changelogs', 'analysts', 'settings']);
                                    @endphp
                                    <tr class="{{ $isReferensi ? 'bg-gray-50/50' : '' }}">
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            @if($isReferensi)
                                                <span class="text-gray-400 mr-1">└</span>
                                            @endif
                                            {{ $moduleName }}
                                        </td>
                                        @foreach($allActions as $action)
                                            <td class="px-4 py-3 text-center">
                                                @if(in_array($action, $availableActions) && $moduleData)
                                                    @php
                                                        $actionData = $moduleData['actions'][$action] ?? null;
                                                        $hasAccess = $actionData['has_access'] ?? false;
                                                        $isCustom = $actionData['is_custom'] ?? false;
                                                        $isRoleDefault = $actionData['is_role_default'] ?? false;
                                                        $permId = $actionData['id'] ?? null;
                                                    @endphp
                                                    @if($permId)
                                                        <label class="inline-flex items-center justify-center cursor-pointer"
                                                            @if(auth()->id() === $analyst->id) title="Tidak dapat mengubah permission sendiri" @endif>
                                                            <input type="hidden" name="permissions[{{ $permId }}]" value="0">
                                                            <input type="checkbox"
                                                                name="permissions[{{ $permId }}]"
                                                                value="1"
                                                                {{ $hasAccess ? 'checked' : '' }}
                                                                {{ auth()->id() === $analyst->id ? 'disabled' : '' }}
                                                                @change="markChanged()"
                                                                class="h-5 w-5 rounded transition-colors
                                                                    {{ $isCustom ? ($hasAccess ? 'text-emerald-600 border-emerald-600' : 'text-red-600 border-red-600') : 'text-gray-400 border-gray-300' }}
                                                                    focus:ring-primary-500 focus:ring-offset-0
                                                                    {{ auth()->id() === $analyst->id ? 'opacity-50 cursor-not-allowed' : '' }}">
                                                        </label>
                                                    @endif
                                                @else
                                                    <span class="text-gray-300">-</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if(auth()->id() !== $analyst->id)
                        <div class="mt-6 flex items-center justify-between border-t border-gray-100 pt-4">
                            <p class="text-xs text-gray-500" x-show="hasChanges" x-cloak>
                                <span class="text-amber-600">*</span> Ada perubahan yang belum disimpan
                            </p>
                            <div class="flex gap-3 ml-auto">
                                <button type="button"
                                    @click="resetForm()"
                                    x-show="hasChanges"
                                    x-cloak
                                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                    Batal
                                </button>
                                <button type="submit"
                                    class="rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-50"
                                    :disabled="!hasChanges">
                                    Simpan Akses
                                </button>
                            </div>
                        </div>
                    @endif
                </form>
            </div>

            <script>
                function permissionManager() {
                    return {
                        hasChanges: false,
                        initialState: null,

                        init() {
                            this.saveInitialState();
                        },

                        saveInitialState() {
                            const form = this.$refs.permForm;
                            if (form) {
                                this.initialState = new FormData(form);
                            }
                        },

                        markChanged() {
                            this.hasChanges = true;
                        },

                        resetForm() {
                            const form = this.$refs.permForm;
                            if (form && this.initialState) {
                                form.reset();
                                // Restore checkboxes to initial state
                                for (const [key, value] of this.initialState.entries()) {
                                    const input = form.querySelector(`[name="${key}"]`);
                                    if (input && input.type === 'checkbox') {
                                        input.checked = value === '1';
                                    }
                                }
                            }
                            this.hasChanges = false;
                        }
                    }
                }
            </script>
        </x-page-section>

        <x-page-section title="Aktivitas Terakhir">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase text-pd-muted">
                        <tr>
                            <th class="px-4 py-3 text-left">Waktu</th>
                            <th class="px-4 py-3 text-left">Aksi</th>
                            <th class="px-4 py-3 text-left">Objek</th>
                            <th class="px-4 py-3 text-left">Ringkas Perubahan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-pd-body">

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
                                    <div class="text-pd-text">{{ $log->created_at->format('d M Y H:i') }}</div>
                                    <div class="text-xs text-pd-muted">{{ $log->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-pd-text">{{ Str::of($log->action)->replace('_', ' ')->title() }}</div>
                                    @if($log->actor)
                                        <div class="text-xs text-pd-muted">Aktor: {{ $log->actor->name }}</div>
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
