<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tambah Proses Pengujian</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <a href="{{ route('sample-processes.index') }}"
            class="inline-flex items-center text-sm font-semibold text-primary-700 hover:text-primary-800">&larr; Kembali ke daftar</a>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('sample-processes.store') }}" class="space-y-6">
                @csrf

                @include('sample-processes._form')

                <div class="flex justify-end gap-3">
                    <a href="{{ route('sample-processes.index') }}"
                        class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-700">Batal</a>
                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
