<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Buka Kembali untuk Sampel Tambahan" :breadcrumbs="[]" />
    </x-slot>

    <main class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-lg border border-amber-300 bg-amber-50 p-5" aria-labelledby="reopen-warning-heading">
            <h2 id="reopen-warning-heading" class="text-base font-semibold text-amber-950">Riwayat penyerahan akan dipertahankan</h2>
            <p class="mt-1 text-sm text-amber-900">Siklus penyerahan baru akan dibuat. Dokumen dan catatan notifikasi lama tetap tersimpan sebagai riwayat yang tidak lagi berlaku untuk siklus terbaru.</p>
            <p class="mt-2 text-sm text-amber-900">Permintaan {{ $testRequest->receipt_number }} · {{ $testRequest->investigator?->name ?? 'Tanpa penyidik' }}</p>
        </section>

        @if ($errors->any())
            <x-alert type="error" title="Data pembukaan kembali belum valid" class="rounded-lg border">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <form method="POST" action="{{ route('delivery.reopen-additional-sample.store', $testRequest) }}" class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            @csrf
            @include('requests.partials.additional-sample-fields')

            <label for="reopen-confirmation" class="flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                <input id="reopen-confirmation" type="checkbox" name="confirmation" value="1" required class="mt-0.5 min-h-5 min-w-5 rounded border-amber-400 text-amber-700 focus:ring-amber-600">
                <span>Saya memastikan hasil permintaan ini belum diambil dan menyetujui pembukaan kembali siklus penyerahan untuk memproses sampel tambahan.</span>
            </label>
            @error('confirmation')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-between">
                <a href="{{ route('delivery.show', $testRequest) }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">Batal</a>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-amber-700 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-700">Buka Kembali dan Catat Sampel</button>
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
