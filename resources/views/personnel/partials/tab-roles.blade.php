@php use Illuminate\Support\Str; @endphp

<div class="space-y-6">
    <p class="text-sm text-gray-600">Kelola jenis role dan default permission untuk staff laboratorium.</p>

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

    <x-page-section title="Kelola Jenis Role">
        <form method="POST" action="{{ route('analysts.roles.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
            @csrf
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Nama role baru</label>
                <input
                    type="text"
                    name="role_name"
                    value="{{ old('role_name') }}"
                    placeholder="Contoh: Auditor Mutu"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                >
                <p class="mt-1 text-xs text-gray-500">Role akan otomatis disimpan sebagai slug, contoh <code>auditor_mutu</code>.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Salin permission dari</label>
                <select name="clone_from" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    @foreach($manageableRoles as $roleOption)
                        <option value="{{ $roleOption }}" @selected(old('clone_from', 'analis') === $roleOption)>
                            {{ Str::of($roleOption)->replace('_', ' ')->title() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                    Tambah Role
                </button>
            </div>
        </form>
    </x-page-section>

    <x-page-section title="Role Tersedia">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($roleUsage as $roleData)
                @php
                    $isCoreRole = in_array($roleData['role'], $coreStaffRoles ?? [], true);
                    $isUsedByUser = (int) $roleData['total'] > 0;
                @endphp
                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <div class="text-sm font-semibold text-gray-900">
                        {{ Str::of($roleData['role'])->replace('_', ' ')->title() }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ number_format((int) $roleData['total']) }} pengguna
                    </div>

                    <div class="mt-4 space-y-3 border-t border-gray-100 pt-3">
                        <form method="POST" action="{{ route('analysts.roles.update', ['role' => $roleData['role']]) }}" class="space-y-2">
                            @csrf
                            @method('PATCH')
                            <label class="block text-xs font-medium text-gray-600">Ubah nama role</label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    name="role_name"
                                    value="{{ old('role_name') && request()->routeIs('personnel.*') ? old('role_name') : Str::of($roleData['role'])->replace('_', ' ')->title() }}"
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    @disabled($isCoreRole)
                                >
                                <button
                                    type="submit"
                                    class="inline-flex items-center rounded-md border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-700 transition hover:bg-primary-100 disabled:cursor-not-allowed disabled:opacity-50"
                                    @disabled($isCoreRole)
                                >
                                    Edit
                                </button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('analysts.roles.destroy', ['role' => $roleData['role']]) }}" onsubmit="return confirm('Yakin ingin menghapus role ini?');">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                                @disabled($isCoreRole || $isUsedByUser)
                                title="{{ $isCoreRole ? 'Role inti sistem tidak dapat dihapus.' : ($isUsedByUser ? 'Role masih digunakan oleh pengguna.' : 'Hapus role') }}"
                            >
                                Hapus
                            </button>
                        </form>

                        @if($isCoreRole)
                            <p class="text-[11px] text-amber-700">Role inti sistem tidak dapat diedit/hapus.</p>
                        @elseif($isUsedByUser)
                            <p class="text-[11px] text-gray-500">Role sedang dipakai pengguna, hapus dinonaktifkan.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-page-section>
</div>
