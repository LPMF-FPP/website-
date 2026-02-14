<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Buat Dokumen"
            :breadcrumbs="[
                ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                ['label' => 'Buat Dokumen'],
            ]"
        />
    </x-slot>

    <div
        class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8"
        x-data="qmhCreatePage({
            templatesUrl: @js('/api/quality/templates'),
            initialDocCode: @js(old('doc_code', '')),
            initialTitle: @js(old('title', '')),
            initialChangeSummary: @js(old('change_summary', '')),
            initialClause: @js((int) old('clause', 4)),
            initialDocType: @js(old('doc_type', '')),
            initialTemplateId: @js((int) old('template_id', 0)),
            initialParentSopId: @js((int) old('parent_sop_id', 0)),
            initialPairedIkId: @js((int) old('paired_ik_id', 0)),
            templateManageUrl: @js(route('quality.templates.index')),
            canManageTemplate: @js(auth()->user()?->hasPermission('qmh.template.manage') ?? false),
            sopOptions: @js(($sopOptions ?? collect())->map(fn ($item) => [
                'id' => $item->id,
                'clause' => $item->clause,
                'label' => $item->doc_code.' - '.$item->title,
            ])->values()),
            ikOptions: @js(($ikOptions ?? collect())->map(fn ($item) => [
                'id' => $item->id,
                'clause' => $item->clause,
                'parent_sop_id' => $item->parent_sop_id,
                'label' => $item->doc_code.' - '.$item->title,
            ])->values()),
        })"
        x-init="init()"
    >
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-600">Penomoran dokumen diisi manual oleh user.</p>

            @if($errors->any())
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-medium">Terjadi kesalahan validasi:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('quality.documents.store') }}" class="mt-4 space-y-5" x-ref="draftForm" @submit.prevent="if (onSubmit()) $el.submit()">
                @csrf

                <input type="hidden" name="template_id" :value="templateId">

                <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                    <h3 class="text-sm font-semibold text-blue-900">Pilih Struktur Dokumen</h3>
                    <p class="mt-1 text-xs text-blue-800">Urutan: pilih klausul, pilih jenis dokumen, lalu isi konten di editor.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="clause">Klausul</label>
                        <select
                            id="clause"
                            name="clause"
                            x-model.number="clause"
                            @change="onStructureChanged()"
                            class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('clause') border-red-400 @else border-gray-300 @enderror"
                            required
                        >
                            @foreach([4, 5, 6, 7, 8] as $clause)
                                <option value="{{ $clause }}" @selected((string) old('clause', '4') === (string) $clause)>{{ $clause }}</option>
                            @endforeach
                        </select>
                        @error('clause')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="doc_type">Jenis Dokumen</label>
                        <select
                            id="doc_type"
                            name="doc_type"
                            x-model="docType"
                            @change="onStructureChanged()"
                            class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('doc_type') border-red-400 @else border-gray-300 @enderror"
                            required
                        >
                            <option value="">Pilih jenis dokumen</option>
                            <option value="sop" @selected(old('doc_type') === 'sop')>SOP</option>
                            <option value="ik" @selected(old('doc_type') === 'ik')>IK</option>
                            <option value="fr" @selected(old('doc_type') === 'fr')>FR</option>
                        </select>
                        @error('doc_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="doc_code">Kode Dokumen (Manual)</label>
                        <input
                            id="doc_code"
                            name="doc_code"
                            type="text"
                            value="{{ old('doc_code') }}"
                            x-model.trim="docCode"
                            class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('doc_code') border-red-400 @else border-gray-300 @enderror"
                            required
                        >
                        @error('doc_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="title">Judul</label>
                        <input
                            id="title"
                            name="title"
                            type="text"
                            value="{{ old('title') }}"
                            x-model.trim="title"
                            class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('title') border-red-400 @else border-gray-300 @enderror"
                            required
                        >
                        @error('title')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div x-show="docType" x-cloak>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Template Aktif</label>
                    <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                        <p x-show="templatesLoading">Memuat template...</p>
                        <p x-show="!templatesLoading && templateId">Template aktif tersedia. Pilih template untuk dimuat ke editor.</p>
                        <p x-show="!templatesLoading && !templateId" class="text-amber-700">Belum ada template aktif untuk jenis dokumen ini.</p>
                        <p x-show="templatesError" class="text-red-600" x-text="templatesError"></p>
                        <p class="mt-1 text-xs" x-show="!templatesLoading && !templateId && canManageTemplate">
                            Tambah template di <a :href="templateManageUrl" class="font-medium underline">QMH > Template</a>.
                        </p>
                    </div>

                    <div class="mt-2" x-show="!templatesLoading && templates.length > 0" x-cloak>
                        <label class="mb-1 block text-xs font-medium text-gray-600" for="template_id">Pilih Template</label>
                        <select
                            id="template_id"
                            x-model.number="templateId"
                            @change="onTemplateChanged()"
                            class="w-full rounded-md border border-gray-300 bg-white text-sm focus:border-primary-600 focus:ring-primary-600"
                        >
                            <template x-for="template in templates" :key="template.id">
                                <option :value="template.id" x-text="`${template.name} (v${template.version})`"></option>
                            </template>
                        </select>
                        <div class="mt-2" x-show="selectedTemplatePreviewUrl()" x-cloak>
                            <a :href="selectedTemplatePreviewUrl()" target="_blank" rel="noopener" class="inline-flex items-center rounded-md border border-blue-300 bg-white px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-50">
                                Preview Template
                            </a>
                        </div>
                    </div>
                    @error('template_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div x-show="requiresParentSop()" x-cloak>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="parent_sop_id">SOP Induk</label>
                    <select
                        id="parent_sop_id"
                        name="parent_sop_id"
                        x-model.number="parentSopId"
                        @change="handleHierarchyDependencies()"
                        class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('parent_sop_id') border-red-400 @else border-gray-300 @enderror"
                        :required="requiresParentSop()"
                    >
                        <option value="">Pilih SOP induk</option>
                        <template x-for="item in filteredSopOptions()" :key="item.id">
                            <option :value="item.id" x-text="item.label"></option>
                        </template>
                    </select>
                    @error('parent_sop_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div x-show="requiresPairedIk()" x-cloak>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="paired_ik_id">IK Pasangan (Opsional)</label>
                    <select
                        id="paired_ik_id"
                        name="paired_ik_id"
                        x-model.number="pairedIkId"
                        class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('paired_ik_id') border-red-400 @else border-gray-300 @enderror"
                    >
                        <option value="">Tanpa pasangan IK</option>
                        <template x-for="item in filteredIkOptions()" :key="item.id">
                            <option :value="item.id" x-text="item.label"></option>
                        </template>
                    </select>
                    @error('paired_ik_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="change_summary">Ringkasan Perubahan</label>
                    <textarea
                        id="change_summary"
                        name="change_summary"
                        rows="4"
                        x-model.trim="changeSummary"
                        class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('change_summary') border-red-400 @else border-gray-300 @enderror"
                    >{{ old('change_summary') }}</textarea>
                    @error('change_summary')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between border-t border-gray-200 pt-6">
                    <a href="{{ route('quality.documents.index') }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Kembali
                    </a>

                    <button type="submit"
                            :disabled="isSubmitting || !canSubmit()"
                            :class="{ 'cursor-not-allowed opacity-50': isSubmitting || !canSubmit() }"
                            class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <template x-if="isSubmitting">
                            <svg class="-ml-1 mr-2 h-4 w-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </template>
                        <span x-text="isSubmitting ? 'Menyimpan...' : 'Simpan Draft'">Simpan Draft</span>
                    </button>
                </div>

                <p x-show="stepError" x-cloak class="text-sm text-red-600" x-text="stepError"></p>
            </form>
        </div>

        <div
            x-show="previewBeforeSubmitOpen"
            x-cloak
            x-transition.opacity
            @keydown.escape.window="cancelSubmitPreview()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4"
        >
            <div class="max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-xl bg-white shadow-2xl" @click.outside="cancelSubmitPreview()">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Preview Dokumen Sebelum Simpan Draft</h3>
                        <p class="mt-1 text-xs text-gray-500">Cek konten terlebih dahulu, lalu lanjutkan simpan draft jika sudah sesuai.</p>
                    </div>
                    <button
                        type="button"
                        @click="cancelSubmitPreview()"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Tutup
                    </button>
                </div>

                <div class="max-h-[calc(90vh-8.5rem)] overflow-auto px-6 py-4">
                    <div class="mb-4 grid gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-xs text-gray-700 md:grid-cols-4">
                        <div><span class="font-medium">Klausul:</span> <span x-text="clause || '-'">-</span></div>
                        <div><span class="font-medium">Jenis:</span> <span x-text="(docType || '-').toUpperCase()">-</span></div>
                        <div><span class="font-medium">Kode:</span> <span x-text="docCode || '-'">-</span></div>
                        <div><span class="font-medium">Template:</span> <span x-text="selectedTemplate()?.name || '-'">-</span></div>
                    </div>

                    <div class="prose prose-sm max-w-none rounded-lg border border-gray-200 bg-white p-4" x-html="livePreviewHtml()"></div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-6 py-4">
                    <button
                        type="button"
                        @click="cancelSubmitPreview()"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Kembali Edit
                    </button>
                    <button
                        type="button"
                        @click="confirmSubmitPreview()"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    >
                        Lanjut Simpan Draft
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
