<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Catat Kunjungan Baru" />
    </x-slot>

    <form method="POST" action="{{ route('guest-book.store') }}">
        @csrf

        @php $visit = null; @endphp
        @include('guest-book._form')

        {{-- NDA --}}
        <div class="mt-6 bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-4">🔒 Perjanjian Kerahasiaan</h3>

            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="nda_accepted" value="1"
                       class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                       required>
                <span class="text-sm text-gray-700">
                    Saya (pihak yang datang) telah membaca, memahami, dan menyetujui Perjanjian Kerahasiaan LPMF Pusdokkes Polri *
                </span>
            </label>

            <button type="button" x-data @click="$dispatch('open-nda-modal')"
                    class="mt-2 text-sm text-blue-600 hover:text-blue-500 underline">
                📄 Baca selengkapnya →
            </button>

            @error('nda_accepted')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-6 flex items-center justify-end gap-3">
            <a href="{{ route('guest-book.index') }}"
               class="inline-flex items-center rounded bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                Batal
            </a>
            <button type="submit"
                    class="inline-flex items-center rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                Simpan Kunjungan
            </button>
        </div>
    </form>

    {{-- NDA Modal --}}
    <x-modal name="nda-modal" maxWidth="lg">
        <div class="p-6">
            @include('guest-book._nda-modal')
            <div class="mt-4 flex justify-end">
                <button type="button" @click="$dispatch('close-modal', 'nda-modal')"
                        class="rounded bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">
                    Tutup
                </button>
            </div>
        </div>
    </x-modal>
</x-app-layout>
