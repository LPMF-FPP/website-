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
        x-data="window.qmhCreatePage({
            templatesUrl: @js('/api/quality/templates'),
            initialDocCode: @js(old('doc_code', '')),
            initialTitle: @js(old('title', '')),
            initialChangeSummary: @js(old('change_summary', '')),
            initialClause: @js((int) old('clause', 4)),
            initialDocType: @js(old('doc_type', 'sop')),
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

            <div class="mt-4 rounded-lg border border-blue-100 bg-blue-50 p-4">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="rounded-full px-3 py-1 font-medium"
                          :class="currentStep === 1 ? 'bg-blue-600 text-white' : 'bg-white text-blue-700 border border-blue-200'">
                        1. Struktur Dokumen
                    </span>
                    <span class="text-blue-400">-></span>
                    <span class="rounded-full px-3 py-1 font-medium"
                          :class="currentStep === 2 ? 'bg-blue-600 text-white' : 'bg-white text-blue-700 border border-blue-200'">
                        2. Preview & Konfirmasi
                    </span>
                </div>
                <p class="mt-2 text-xs text-blue-800">
                    Input metadata dilakukan di sini. Input konten dokumen dilakukan setelah draft dibuat melalui menu <strong>Edit Dokumen</strong>.
                </p>
            </div>

            <form method="POST" action="{{ route('quality.documents.store') }}" class="mt-4 space-y-4" @submit="isSubmitting = true">
                @csrf
                <div x-show="currentStep === 1" x-cloak class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="doc_code">Kode Dokumen</label>
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
                        @change="currentStep = 1"
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
                    <p class="mt-1 text-xs text-amber-800" x-show="!templatesLoading && templates.length === 0 && canManageTemplate">
                        Silakan tambah template di
                        <a :href="templateManageUrl" class="font-medium underline">QMH > Template</a>.
                    </p>
                    <p class="mt-1 text-xs text-amber-800" x-show="!templatesLoading && templates.length === 0 && !canManageTemplate">
                        Hubungi admin untuk menambahkan template melalui menu QMH > Template.
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
                        @change="currentStep = 1; handleHierarchyDependencies()"
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
                        @change="currentStep = 1"
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
                </div>

                <div x-show="currentStep === 2" x-cloak class="space-y-4">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Konfirmasi Metadata</h3>
                        <dl class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-gray-500">Kode Dokumen</dt>
                                <dd class="font-medium text-gray-900" x-text="docCode || '-'">-</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Judul</dt>
                                <dd class="font-medium text-gray-900" x-text="title || '-'">-</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Klausul</dt>
                                <dd class="font-medium text-gray-900" x-text="clause || '-'">-</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Jenis Dokumen</dt>
                                <dd class="font-medium text-gray-900" x-text="(docType || '').toUpperCase() || '-'">-</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">SOP Induk</dt>
                                <dd class="font-medium text-gray-900" x-text="selectedParentSopLabel()">-</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">IK Pasangan</dt>
                                <dd class="font-medium text-gray-900" x-text="selectedPairedIkLabel()">-</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Preview Template</h3>
                        <p class="mt-1 text-xs text-gray-500">Pratinjau ini read-only untuk memastikan pilihan template sudah benar sebelum draft dibuat.</p>

                        <div class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <p class="text-gray-500">Template</p>
                                <p class="font-medium text-gray-900" x-text="selectedTemplateName()">-</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Versi</p>
                                <p class="font-medium text-gray-900" x-text="selectedTemplateVersion()">-</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Source File</p>
                                <p class="font-medium text-gray-900 break-all" x-text="selectedTemplateSourcePath()">-</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Terakhir Update</p>
                                <p class="font-medium text-gray-900" x-text="selectedTemplateUpdatedAt()">-</p>
                            </div>
                        </div>

                        <div class="mt-3" x-show="selectedTemplatePreviewUrl()" x-cloak>
                            <a :href="selectedTemplatePreviewUrl()"
                               target="_blank"
                               rel="noopener"
                               class="inline-flex items-center rounded-md border border-blue-300 bg-white px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                                Preview Template DOCX
                            </a>
                        </div>

                        <div class="mt-3 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-sm text-blue-900" x-text="templatePreviewSummary()"></div>
                        <p class="mt-2 text-xs text-gray-600">Setelah draft dibuat, lanjutkan ke <strong>Edit Dokumen</strong> untuk mengisi konten/form dokumen secara penuh.</p>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-gray-200 pt-6">
                    <a href="{{ route('quality.documents.index') }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Kembali
                    </a>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                x-show="currentStep === 2"
                                x-cloak
                                @click="currentStep = 1"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Kembali Edit Metadata
                        </button>

                        <button type="button"
                                x-show="currentStep === 1"
                                @click="goToPreview()"
                                :disabled="!canProceedStep1()"
                                :class="{ 'cursor-not-allowed opacity-50': !canProceedStep1() }"
                                class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            Lanjut ke Preview
                        </button>

                        <button type="submit"
                                x-show="currentStep === 2"
                                x-cloak
                                :disabled="isSubmitting || !canProceedStep1()"
                                :class="{ 'cursor-not-allowed opacity-50': isSubmitting || !canProceedStep1() }"
                                class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            <template x-if="isSubmitting">
                                <svg class="-ml-1 mr-2 h-4 w-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </template>
                            <span x-text="isSubmitting ? 'Menyimpan...' : 'Konfirmasi Buat Draft'">Konfirmasi Buat Draft</span>
                        </button>
                    </div>
                </div>

                <p x-show="stepError" x-cloak class="text-sm text-red-600" x-text="stepError"></p>
            </form>
        </div>
    </div>
</x-app-layout>
