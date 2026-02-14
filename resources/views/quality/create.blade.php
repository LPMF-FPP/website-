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
        class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8"
        x-data="window.qmhCreatePage({
            templatesUrl: @js('/api/quality/templates'),
            initialDocCode: @js(old('doc_code', '')),
            initialTitle: @js(old('title', '')),
            initialChangeSummary: @js(old('change_summary', '')),
            initialEffectiveDate: @js(old('effective_date', '')),
            initialAnswersJson: @js(old('answers_json', [])),
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

            <form method="POST" action="{{ route('quality.documents.store') }}" class="mt-4 space-y-5" @submit.prevent="if (onSubmit()) $el.submit()">
                @csrf

                <input type="hidden" name="template_id" :value="templateId">

                <template x-for="field in answerFormFields()" :key="`${field.name}:${field.value}`">
                    <input type="hidden" :name="field.name" :value="field.value">
                </template>

                <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                    <h3 class="text-sm font-semibold text-blue-900">Pilih Struktur Dokumen</h3>
                    <p class="mt-1 text-xs text-blue-800">Urutan: pilih klausul, pilih jenis dokumen, pilih template, lalu jawab pertanyaan.</p>
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

                <div class="grid gap-4 md:grid-cols-6">
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

                    <div class="md:col-span-2">
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
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="effective_date">Tgl. Efektif (Opsional)</label>
                        <input
                            id="effective_date"
                            name="effective_date"
                            type="date"
                            x-model="effectiveDate"
                            class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('effective_date') border-red-400 @else border-gray-300 @enderror"
                        >
                        @error('effective_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div x-show="docType" x-cloak>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Template Aktif</label>
                    <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                        <p x-show="templatesLoading">Memuat template...</p>
                        <p x-show="!templatesLoading && templateId">Template aktif tersedia. Pilih template untuk memuat schema pertanyaan.</p>
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

                <div x-show="docType" x-cloak class="grid gap-6 lg:grid-cols-12">
                    <div class="lg:col-span-7" data-qmh-question-form>
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">Pertanyaan Template</h3>
                                    <p class="mt-1 text-xs text-gray-500">Jawaban akan disimpan sebagai data terstruktur dan dipakai untuk cetak PDF.</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[11px] font-medium text-gray-700">Status</p>
                                    <p class="text-xs text-gray-500">Draft</p>
                                </div>
                            </div>

                            <div class="mt-4" x-show="templatesLoading">
                                <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">Memuat schema pertanyaan...</div>
                            </div>

                            <div class="mt-4" x-show="!templatesLoading" x-cloak>
                                <template x-if="schemaQuestions().length === 0">
                                    <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                        Belum ada pertanyaan untuk template ini.
                                    </div>
                                </template>

                                <template x-if="schemaQuestions().length > 0">
                                    <div class="space-y-4">
                                        <template x-for="(q, idx) in schemaQuestions()" :key="q.id || idx">
                                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <p class="text-xs font-semibold text-gray-500" x-text="`Q${idx + 1}`"></p>
                                                        <label class="mt-1 block text-sm font-semibold text-gray-900" :for="`q-${q.id}`">
                                                            <span x-text="q.label || q.id"></span>
                                                            <span class="ml-1 text-xs font-semibold text-red-600" x-show="q.required">*</span>
                                                        </label>
                                                    </div>
                                                    <p class="text-[11px] text-gray-500" x-text="q.type === 'list' ? 'Daftar' : 'Paragraf'"></p>
                                                </div>

                                                <div class="mt-3" x-show="q.type === 'list'">
                                                    <textarea
                                                        class="w-full rounded-md border border-gray-300 text-sm leading-relaxed focus:border-primary-600 focus:ring-primary-600"
                                                        rows="4"
                                                        :id="`q-${q.id}`"
                                                        :placeholder="'Satu item per baris'"
                                                        x-model="listAnswerText[q.id]"
                                                        @input="syncListAnswer(q.id)"
                                                    ></textarea>
                                                    <p class="mt-1 text-xs text-gray-500">Tip: satu item per baris. Kosongkan untuk tidak ada.</p>
                                                </div>

                                                <div class="mt-3" x-show="q.type !== 'list'">
                                                    <textarea
                                                        class="w-full rounded-md border border-gray-300 text-sm leading-relaxed focus:border-primary-600 focus:ring-primary-600"
                                                        rows="5"
                                                        :id="`q-${q.id}`"
                                                        :placeholder="q.required ? 'Wajib diisi' : 'Opsional'"
                                                        x-model.trim="answers[q.id]"
                                                    ></textarea>
                                                </div>

                                                <p class="mt-2 text-sm text-red-600" x-show="fieldErrors[q.id]" x-text="fieldErrors[q.id]"></p>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-5" data-qmh-preview-panel>
                        <div class="sticky top-6 rounded-xl border border-gray-200 bg-gradient-to-br from-slate-50 to-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-900">Preview Dokumen</h3>
                                <p class="text-xs text-gray-500">HTML preview</p>
                            </div>

                            <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white">
                                <div class="relative bg-white p-4">
                                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                        <div class="select-none text-4xl font-bold tracking-[0.35em] text-slate-200" style="transform: rotate(-25deg);">
                                            DRAFT
                                        </div>
                                    </div>

                                    <div class="relative">
                                        <div class="border border-gray-800">
                                            <div class="grid grid-cols-3">
                                                <div class="border-r border-gray-800 p-3 text-center">
                                                    <img src="/images/logo-pusdokkes-polri.png" alt="Logo" class="mx-auto h-10 w-auto" onerror="this.style.display='none'">
                                                    <p class="mt-2 text-[10px] font-bold uppercase leading-tight text-gray-900">Laboratorium Pengujian Mutu<br>Farmapol Pusdokkes Polri</p>
                                                </div>
                                                <div class="border-r border-gray-800 p-3 text-center">
                                                    <p class="text-xs font-bold uppercase tracking-wide text-gray-900" x-text="previewDocTypeLabel()"></p>
                                                    <p class="mt-2 text-[11px] font-bold uppercase text-gray-900" x-text="`[${(title || 'JUDUL PROSEDUR').toUpperCase()}]`"></p>
                                                </div>
                                                <div class="p-0">
                                                    <table class="w-full border-collapse text-[10px]">
                                                        <tr>
                                                            <td class="border border-gray-800 px-2 py-1 font-medium">No. Dokumen</td>
                                                            <td class="border border-gray-800 px-2 py-1" x-text="docCode || '-' "></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="border border-gray-800 px-2 py-1 font-medium">Edisi/Revisi</td>
                                                            <td class="border border-gray-800 px-2 py-1">E1/R0</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="border border-gray-800 px-2 py-1 font-medium">Tgl. Efektif</td>
                                                            <td class="border border-gray-800 px-2 py-1" x-text="effectiveDate || '-' "></td>
                                                        </tr>
                                                        <tr>
                                                            <td class="border border-gray-800 px-2 py-1 font-medium">Halaman</td>
                                                            <td class="border border-gray-800 px-2 py-1">1 DARI 1</td>
                                                        </tr>
                                                        <tr>
                                                            <td class="border border-gray-800 px-2 py-1 font-medium">Status</td>
                                                            <td class="border border-gray-800 px-2 py-1">DRAFT</td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4 space-y-3">
                                            <template x-for="(q, idx) in schemaQuestions()" :key="`pv-${q.id}-${idx}`">
                                                <div>
                                                    <p class="text-xs font-semibold text-gray-900" x-text="`${idx + 1}. ${q.label || q.id}`"></p>
                                                    <template x-if="q.type === 'list'">
                                                        <ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs text-gray-700">
                                                            <template x-for="(item, ii) in (Array.isArray(answers[q.id]) ? answers[q.id] : [])" :key="`li-${q.id}-${ii}`">
                                                                <li x-text="item"></li>
                                                            </template>
                                                            <li x-show="!Array.isArray(answers[q.id]) || answers[q.id].length === 0" class="list-none text-gray-400">-</li>
                                                        </ul>
                                                    </template>
                                                    <template x-if="q.type !== 'list'">
                                                        <p class="mt-1 whitespace-pre-line text-xs text-gray-700" x-text="answers[q.id] ? answers[q.id] : '-' "></p>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>

                                        <div class="mt-4 border border-gray-800">
                                            <div class="grid grid-cols-4 text-[9px]">
                                                <div class="border-r border-gray-800 p-2 font-bold">Dibuat</div>
                                                <div class="border-r border-gray-800 p-2 font-bold">Diperiksa</div>
                                                <div class="border-r border-gray-800 p-2 font-bold">Disahkan</div>
                                                <div class="p-2 text-right">1/1</div>
                                            </div>
                                            <div class="border-t border-gray-800 p-2 text-center text-[9px] italic text-red-700">
                                                Isi Dokumen ini tidak diperkenankan untuk disalin atau digandakan tanpa persetujuan.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
    </div>
</x-app-layout>
