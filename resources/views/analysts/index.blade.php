<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Manajemen Analis"
            :breadcrumbs="[[ 'label' => 'Analis' ]]"
        >
            <x-slot name="actions">
                <a href="{{ route('analysts.create') }}"
                    class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Tambah Analis</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <p class="text-sm text-gray-600">Kelola akun analis laboratorium, termasuk gelar, pangkat, dan nomor identitas.</p>

        @if(session('success'))
            <div class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <x-page-section title="Daftar Analis">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Nama</th>
                            <th class="px-4 py-3 text-left">Pangkat</th>
                            <th class="px-4 py-3 text-left">NRP / NIP</th>
                            <th class="px-4 py-3 text-left">Email</th>
                            <th class="px-4 py-3 text-left">Peran</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse($analysts as $analyst)
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">{{ $analyst->display_name_with_title }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $analyst->rank ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div>NRP: {{ $analyst->nrp ?? '-' }}</div>
                                    <div>NIP: {{ $analyst->nip ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $analyst->email }}</td>
                                <td class="px-4 py-3">
                                    {{ \Illuminate\Support\Str::of($analyst->role)->replace('_', ' ')->title() }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('analysts.edit', $analyst) }}"
                                        class="text-sm font-semibold text-primary-600 hover:text-primary-700">Ubah</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">Belum ada data analis.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $analysts->links() }}
            </div>
        </x-page-section>
    </div>
</x-app-layout>
