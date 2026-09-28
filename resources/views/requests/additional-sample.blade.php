<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Tambah Sampel pada Pengujian Aktif" :breadcrumbs="[]" />
    </x-slot>

    <main class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-lg border border-amber-200 bg-amber-50 p-5" aria-labelledby="active-request-heading">
            <h2 id="active-request-heading" class="text-base font-semibold text-amber-950">Permintaan {{ $testRequest->receipt_number }}</h2>
            <p class="mt-1 text-sm text-amber-900">Sampel baru akan dikaji ulang secara terpisah. Sampel dan hasil yang sudah diproses tidak diubah.</p>
            <p class="mt-2 text-sm text-amber-900">Penyidik: {{ $testRequest->investigator?->name ?? '-' }} · Status: Pengujian</p>
        </section>

        @if ($errors->any())
            <x-alert type="error" title="Data sampel belum lengkap" class="rounded-lg border">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <form method="POST" action="{{ route('testing.additional-samples.store-new', $testRequest) }}" class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            @csrf

            @include('requests.partials.additional-sample-fields')

            <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-between">
                <a href="{{ route('testing.show', $testRequest) }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">Batal</a>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">Simpan dan Kaji Ulang Sampel</button>
            </div>
        </form>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const category = document.getElementById('sample_category');
            const wrapper = document.getElementById('other-sample-category-wrapper');
            const otherCategory = document.getElementById('other_sample_category');

            const updateOtherCategory = () => {
                const visible = category.value === 'other';
                wrapper.classList.toggle('hidden', !visible);
                otherCategory.required = visible;
            };

            category.addEventListener('change', updateOtherCategory);
            updateOtherCategory();
        });
    </script>
</x-app-layout>
