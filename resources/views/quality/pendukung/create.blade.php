<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Upload Dokumen Pendukung"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Dokumen Pendukung', 'route' => 'quality.pendukung.index'],
                    ['label' => 'Upload'],
                ]"
            />

            <x-qmh-subnav active="documents" />
        </div>
    </x-slot>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @if($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('quality.pendukung.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="doc_code" class="mb-1 block text-sm font-medium text-gray-700">Kode Dokumen</label>
                    <input
                        id="doc_code"
                        name="doc_code"
                        type="text"
                        value="{{ old('doc_code') }}"
                        placeholder="DP-4.001"
                        class="w-full rounded-md border border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600"
                        required
                    >
                    <p class="mt-1 text-xs text-gray-500">Format: DP-{klausul}.###, contoh DP-4.001</p>
                </div>

                <div>
                    <label for="clause" class="mb-1 block text-sm font-medium text-gray-700">Klausul</label>
                    <select id="clause" name="clause" class="w-full rounded-md border border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" required>
                        @foreach([4, 5, 6, 7, 8] as $clause)
                            <option value="{{ $clause }}" @selected((int) old('clause', 4) === $clause)>{{ $clause }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="title" class="mb-1 block text-sm font-medium text-gray-700">Judul</label>
                <input
                    id="title"
                    name="title"
                    type="text"
                    value="{{ old('title') }}"
                    class="w-full rounded-md border border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600"
                    required
                >
            </div>

            <div>
                <label for="file" class="mb-1 block text-sm font-medium text-gray-700">File Pendukung</label>
                <input
                    id="file"
                    name="file"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    class="w-full rounded-md border border-gray-300 bg-white text-sm file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-gray-700"
                    required
                >
                <p class="mt-1 text-xs text-gray-500">Format: JPG, PNG, WebP, PDF. Ukuran maksimal 30 MB.</p>
            </div>

            <div>
                <label for="change_summary" class="mb-1 block text-sm font-medium text-gray-700">Catatan (opsional)</label>
                <textarea id="change_summary" name="change_summary" rows="3" class="w-full rounded-md border border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">{{ old('change_summary') }}</textarea>
            </div>

            <div class="flex items-center justify-between border-t border-gray-200 pt-4">
                <a href="{{ route('quality.pendukung.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</a>
                <button type="submit" class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Simpan</button>
            </div>
        </form>
    </div>
</x-app-layout>
