<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ubah Data Analis</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between">
            <a href="{{ route('analysts.index') }}"
                class="inline-flex items-center text-sm font-semibold text-primary-600 hover:text-primary-700">&larr; Kembali ke daftar analis</a>

            <form method="POST" action="{{ route('analysts.destroy', $analyst) }}"
                onsubmit="return confirm('Hapus analis ini? Tindakan tidak dapat dibatalkan.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-700">Hapus</button>
            </form>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('analysts.update', $analyst) }}" class="space-y-6">
                @method('PUT')
                @include('analysts._form')

                <div class="flex justify-end gap-3">
                    <a href="{{ route('analysts.index') }}"
                        class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-600">Batal</a>
                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Perbarui</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
