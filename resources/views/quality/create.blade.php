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
        class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8"
        x-data="qmhCreatePage({
            templatesUrl: @js('/api/quality/templates'),
            initialClause: @js((int) old('clause', 4)),
            initialDocType: @js(old('doc_type', 'sop')),
            initialTemplateId: @js((int) old('template_id', 0)),
            initialParentSopId: @js((int) old('parent_sop_id', 0)),
            initialPairedIkId: @js((int) old('paired_ik_id', 0)),
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
            <p class="text-sm text-gray-600">Draft awal akan dibuat sebagai versi E1-R0.</p>

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

            <form method="POST" action="{{ route('quality.documents.store') }}" class="mt-4 space-y-4" @submit="isSubmitting = true">
                @csrf
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="doc_code">Kode Dokumen</label>
                        <input
                            id="doc_code"
                            name="doc_code"
                            type="text"
                            value="{{ old('doc_code') }}"
                            class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('doc_code') border-red-400 @else border-gray-300 @enderror"
                            required
                        >
                        @error('doc_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="clause">Klausul</label>
                        <select
                            id="clause"
                            name="clause"
                            x-model.number="clause"
                            @change="handleHierarchyDependencies(); fetchTemplates()"
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
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="title">Judul</label>
                    <input
                        id="title"
                        name="title"
                        type="text"
                        value="{{ old('title') }}"
                        class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('title') border-red-400 @else border-gray-300 @enderror"
                        required
                    >
                    @error('title')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="doc_type">Jenis Dokumen</label>
                    <select
                        id="doc_type"
                        name="doc_type"
                        x-model="docType"
                        @change="handleHierarchyDependencies(); fetchTemplates()"
                        class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('doc_type') border-red-400 @else border-gray-300 @enderror"
                        required
                    >
                        <option value="sop" @selected(old('doc_type', 'sop') === 'sop')>SOP</option>
                        <option value="ik" @selected(old('doc_type') === 'ik')>IK</option>
                        <option value="fr" @selected(old('doc_type') === 'fr')>FR</option>
                    </select>
                    @error('doc_type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="template_id">Template Office</label>
                    <select
                        id="template_id"
                        name="template_id"
                        x-model.number="templateId"
                        :disabled="templatesLoading || templates.length === 0"
                        class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('template_id') border-red-400 @else border-gray-300 @enderror"
                        required
                    >
                        <option value="">Pilih template</option>
                        <template x-for="template in templates" :key="template.id">
                            <option :value="template.id" x-text="`${template.name} (v${template.version})`"></option>
                        </template>
                    </select>
                    <p class="mt-1 text-xs text-gray-500" x-show="templatesLoading">Memuat template...</p>
                    <p class="mt-1 text-xs text-amber-700" x-show="!templatesLoading && templates.length === 0">
                        Template aktif untuk kombinasi klausul dan jenis dokumen belum tersedia.
                    </p>
                    <p class="mt-1 text-xs text-red-600" x-show="templatesError" x-text="templatesError"></p>
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
                        class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('change_summary') border-red-400 @else border-gray-300 @enderror"
                    >{{ old('change_summary') }}</textarea>
                    @error('change_summary')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between border-t border-gray-200 pt-6">
                    <a
                        href="{{ route('quality.documents.index') }}"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Kembali
                    </a>
                    <button
                        type="submit"
                        :disabled="isSubmitting || !templateId || templates.length === 0"
                        :class="{ 'cursor-not-allowed opacity-50': isSubmitting || !templateId || templates.length === 0 }"
                        class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    >
                        <template x-if="isSubmitting">
                            <svg class="-ml-1 mr-2 h-4 w-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </template>
                        <span x-text="isSubmitting ? 'Menyimpan...' : 'Simpan Dokumen'">Simpan Dokumen</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

@push('scripts')
    <script>
        function qmhCreatePage(config) {
            return {
                templatesUrl: config.templatesUrl,
                clause: config.initialClause,
                docType: config.initialDocType,
                templateId: config.initialTemplateId,
                parentSopId: config.initialParentSopId,
                pairedIkId: config.initialPairedIkId,
                sopOptions: config.sopOptions || [],
                ikOptions: config.ikOptions || [],
                templates: [],
                templatesLoading: false,
                templatesError: '',
                isSubmitting: false,

                init() {
                    this.handleHierarchyDependencies();
                    this.fetchTemplates();
                },

                handleHierarchyDependencies() {
                    if (!this.requiresParentSop()) {
                        this.parentSopId = 0;
                        this.pairedIkId = 0;
                        return;
                    }

                    const hasParent = this.filteredSopOptions().some((item) => Number(item.id) === Number(this.parentSopId));
                    if (!hasParent) {
                        this.parentSopId = 0;
                        this.pairedIkId = 0;
                        return;
                    }

                    if (!this.requiresPairedIk()) {
                        this.pairedIkId = 0;
                        return;
                    }

                    const hasPairedIk = this.filteredIkOptions().some((item) => Number(item.id) === Number(this.pairedIkId));
                    if (!hasPairedIk) {
                        this.pairedIkId = 0;
                    }
                },

                async fetchTemplates() {
                    this.templatesLoading = true;
                    this.templatesError = '';

                    try {
                        const params = new URLSearchParams({
                            clause: String(this.clause),
                            doc_type: this.docType,
                        });

                        const response = await fetch(`${this.templatesUrl}?${params.toString()}`, {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json' },
                        });

                        if (!response.ok) {
                            this.templates = [];
                            this.templateId = 0;
                            this.templatesError = 'Gagal memuat template. Silakan coba lagi.';
                            return;
                        }

                        const payload = await response.json();
                        this.templates = Array.isArray(payload.data) ? payload.data : [];

                        const hasCurrent = this.templates.some((item) => Number(item.id) === Number(this.templateId));
                        if (!hasCurrent) {
                            this.templateId = this.templates.length > 0 ? Number(this.templates[0].id) : 0;
                        }
                    } catch (error) {
                        this.templates = [];
                        this.templateId = 0;
                        this.templatesError = 'Terjadi gangguan jaringan saat memuat template.';
                    } finally {
                        this.templatesLoading = false;
                    }
                },

                requiresParentSop() {
                    return this.docType === 'ik' || this.docType === 'fr';
                },

                requiresPairedIk() {
                    return this.docType === 'fr';
                },

                filteredSopOptions() {
                    return this.sopOptions.filter((item) => Number(item.clause) === Number(this.clause));
                },

                filteredIkOptions() {
                    if (!this.parentSopId) {
                        return [];
                    }

                    return this.ikOptions.filter((item) => Number(item.parent_sop_id) === Number(this.parentSopId));
                },
            };
        }
    </script>
@endpush
