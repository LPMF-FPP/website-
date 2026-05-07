<x-app-layout>
    <x-slot name="header">
        <x-page-header
            :title="'Edit Data Penyidik · ' . ($request->receipt_number ?? $request->request_number)"
            :breadcrumbs="[]"
        />
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
        <a href="{{ route('delivery.show', $request) }}"
            class="inline-flex items-center text-sm font-semibold text-primary-700 transition hover:text-primary-800">
            &larr; Kembali ke detail penyerahan
        </a>

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Halaman ini hanya memperbarui data penyidik/pemohon yang tampil pada proses penyerahan. Data surat permintaan,
            tersangka, sampel, dokumen, dan workflow pengujian tidak dapat diedit dari halaman ini. Jika penyidik terhubung
            dengan permintaan lain, perubahan harus dilakukan dari Manajemen Penyidik agar dampaknya ditinjau lebih dulu.
        </div>

        <form method="POST" action="{{ route('delivery.investigator.update', $request) }}" class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-900/5">
            @csrf
            @method('PATCH')

            @if ($investigator?->is_polri ?? true)
                <div class="mb-6 border-b border-gray-100 pb-4">
                    <h2 class="text-base font-semibold text-gray-900">Data Penyidik</h2>
                    <p class="mt-1 text-sm text-gray-500">Perubahan disimpan ke master penyidik yang terhubung dengan permintaan ini.</p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <label for="investigator_name" class="mb-2 block text-sm font-medium text-gray-700">Nama Penyidik <span class="text-red-500">*</span></label>
                        <input type="text" name="investigator_name" id="investigator_name" required
                            value="{{ old('investigator_name', $investigator?->name) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="investigator_nrp" class="mb-2 block text-sm font-medium text-gray-700">NRP <span class="text-red-500">*</span></label>
                        <input type="text" name="investigator_nrp" id="investigator_nrp" required
                            value="{{ old('investigator_nrp', $investigator?->nrp) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="investigator_rank" class="mb-2 block text-sm font-medium text-gray-700">Pangkat <span class="text-red-500">*</span></label>
                        <input type="text" name="investigator_rank" id="investigator_rank" required
                            value="{{ old('investigator_rank', $investigator?->rank) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="investigator_jurisdiction" class="mb-2 block text-sm font-medium text-gray-700">Satuan / Wilayah Hukum <span class="text-red-500">*</span></label>
                        <input type="text" name="investigator_jurisdiction" id="investigator_jurisdiction" required
                            value="{{ old('investigator_jurisdiction', $investigator?->jurisdiction) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="investigator_phone" class="mb-2 block text-sm font-medium text-gray-700">Nomor Telepon <span class="text-red-500">*</span></label>
                        <input type="text" name="investigator_phone" id="investigator_phone" required
                            value="{{ old('investigator_phone', $investigator?->phone) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="investigator_email" class="mb-2 block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="investigator_email" id="investigator_email"
                            value="{{ old('investigator_email', $investigator?->email) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div class="md:col-span-3">
                        <label for="investigator_address" class="mb-2 block text-sm font-medium text-gray-700">Alamat Penyidik</label>
                        <textarea name="investigator_address" id="investigator_address" rows="3"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('investigator_address', $investigator?->address) }}</textarea>
                    </div>
                </div>
            @else
                <div class="mb-6 border-b border-gray-100 pb-4">
                    <h2 class="text-base font-semibold text-gray-900">Data Pemohon Non-Polri</h2>
                    <p class="mt-1 text-sm text-gray-500">Perubahan disimpan ke master pemohon yang terhubung dengan permintaan ini.</p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label for="external_name" class="mb-2 block text-sm font-medium text-gray-700">Nama <span class="text-red-500">*</span></label>
                        <input type="text" name="external_name" id="external_name" required
                            value="{{ old('external_name', $investigator?->name) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="external_phone" class="mb-2 block text-sm font-medium text-gray-700">Nomor Telepon <span class="text-red-500">*</span></label>
                        <input type="text" name="external_phone" id="external_phone" required
                            value="{{ old('external_phone', $investigator?->alt_phone) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="external_institution" class="mb-2 block text-sm font-medium text-gray-700">Instansi <span class="text-red-500">*</span></label>
                        <input type="text" name="external_institution" id="external_institution" required
                            value="{{ old('external_institution', $investigator?->institution) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="external_hp" class="mb-2 block text-sm font-medium text-gray-700">Nomor HP <span class="text-red-500">*</span></label>
                        <input type="text" name="external_hp" id="external_hp" required
                            value="{{ old('external_hp', $investigator?->phone) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                    <div class="md:col-span-2">
                        <label for="external_occupation" class="mb-2 block text-sm font-medium text-gray-700">Pekerjaan <span class="text-red-500">*</span></label>
                        <input type="text" name="external_occupation" id="external_occupation" required
                            value="{{ old('external_occupation', $investigator?->occupation) }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    </div>
                </div>
            @endif

            <div class="mt-8 flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('delivery.show', $request) }}"
                    class="inline-flex justify-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Batal
                </a>
                <button type="submit"
                    class="inline-flex justify-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    Simpan Data Penyidik
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
