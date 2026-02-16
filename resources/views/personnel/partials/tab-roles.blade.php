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
                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <div class="text-sm font-semibold text-gray-900">
                        {{ Str::of($roleData['role'])->replace('_', ' ')->title() }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ number_format((int) $roleData['total']) }} pengguna
                    </div>
                </div>
            @endforeach
        </div>
    </x-page-section>
</div>
