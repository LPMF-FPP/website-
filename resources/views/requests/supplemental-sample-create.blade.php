<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Buat Permintaan Suplemen Sampel" :breadcrumbs="[]" />
    </x-slot>

    <main class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-lg border border-blue-200 bg-blue-50 p-5" aria-labelledby="parent-request-heading">
            <h2 id="parent-request-heading" class="text-base font-semibold text-blue-950">Permintaan awal tetap tersimpan</h2>
            <p class="mt-1 text-sm text-blue-900">Suplemen ini membuat nomor resi dan siklus pengujian/penyerahan tersendiri yang tertaut ke permintaan awal.</p>
            <dl class="mt-3 grid gap-2 text-sm text-blue-950 sm:grid-cols-2">
                <div><dt class="text-blue-700">Resi awal</dt><dd class="font-semibold">{{ $testRequest->receipt_number }}</dd></div>
                <div><dt class="text-blue-700">Nomor permintaan</dt><dd class="font-semibold">{{ $testRequest->request_number }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-blue-700">Penyidik</dt><dd class="font-semibold">{{ $testRequest->investigator?->name ?? '-' }}</dd></div>
            </dl>
        </section>

        @if ($errors->any())
            <x-alert type="error" title="Data suplemen belum lengkap" class="rounded-lg border">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <form method="POST" action="{{ route('requests.supplemental-samples.store', $testRequest) }}" class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            @csrf
            @include('requests.partials.additional-sample-fields')
            <label for="collection-confirmation" class="flex items-start gap-3 rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950">
                <input id="collection-confirmation" type="checkbox" name="collection_confirmation" value="1" required class="mt-0.5 min-h-5 min-w-5 rounded border-blue-400 text-primary-700 focus:ring-primary-600">
                <span>Saya memastikan hasil dari permintaan awal sudah diambil dan suplemen ini akan menjalani siklus pengujian serta penyerahan tersendiri.</span>
            </label>
            @error('collection_confirmation')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-between">
                <a href="{{ route('delivery.show', $testRequest) }}" class="inline-flex min-h-11 items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">Batal</a>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">Buat Suplemen Tertaut</button>
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
