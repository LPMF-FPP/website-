<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Kunjungan" />
    </x-slot>

    <form method="POST" action="{{ route('guest-book.update', $visit) }}">
        @csrf
        @method('PUT')

        @include('guest-book._form')

        {{-- NDA read-only --}}
        <div class="mt-6 bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-2">🔒 Perjanjian Kerahasiaan</h3>
            <p class="text-sm text-green-700">
                ✅ Disetujui — {{ $visit->nda_accepted_at?->format('d M Y, H:i') }} WIB
            </p>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <a href="{{ route('guest-book.show', $visit) }}"
               class="inline-flex items-center rounded bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                Batal
            </a>
            <button type="submit"
                    class="inline-flex items-center rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                Simpan Perubahan
            </button>
        </div>
    </form>
</x-app-layout>
