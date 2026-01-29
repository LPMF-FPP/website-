<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Edit Penyidik"
            :breadcrumbs="[[ 'label' => 'Penyidik', 'href' => route('investigators.index') ], [ 'label' => $investigator->name ]]"
        >
            <x-slot name="actions">
                <a href="{{ route('investigators.show', $investigator) }}"
                    class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-600">Kembali</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if(session('error'))
            <div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('investigators.update', $investigator) }}" class="space-y-6">
                @method('PUT')
                @include('investigators._form')

                <div class="flex justify-end gap-3">
                    <a href="{{ route('investigators.show', $investigator) }}"
                        class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-600">Batal</a>
                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
