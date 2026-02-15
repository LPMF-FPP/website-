<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Buat Dokumen QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Buat Dokumen'],
                ]"
            />

            <x-qmh-subnav active="create" />
        </div>
    </x-slot>

    <div
        class="space-y-6"
        x-data="window.qmhCreatePage({
            templatesUrl: @js('/api/quality/templates'),
            initialDocCode: @js(old('doc_code', '')),
            initialTitle: @js(old('title', '')),
            initialChangeSummary: @js(old('change_summary', '')),
            users: @js($users ?? []),
            dibuatOleh: @js((int) old('dibuat_oleh', auth()->id())),
            diperiksaOleh: @js((int) old('diperiksa_oleh', 0)),
            disahkanOleh: @js((int) old('disahkan_oleh', 0)),
            currentUserRole: @js(auth()->user()->role),
            currentUserId: @js(auth()->id()),
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
        @qmh-form-schema-change.window="onFormSchemaChanged($event.detail.schema)"
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

            <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Alur Pembuatan Dokumen</p>
                        <p class="mt-1 text-xs text-gray-600">Ikuti langkah bertahap untuk menghindari data yang terlewat.</p>
                    </div>
                    <div class="text-xs text-gray-600">
                        <span class="font-medium">Step</span>
                        <span x-text="step"></span>
                        <span>/4</span>
                    </div>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-4">
                    <button type="button" @click="goToStep(1)" class="rounded-lg border px-3 py-2 text-left text-xs transition" :class="step === 1 ? 'border-primary-300 bg-white text-primary-900' : (canGoToStep(1) ? 'border-gray-200 bg-white text-gray-700 hover:border-primary-300' : 'border-gray-100 bg-white text-gray-300')">
                        <div class="font-semibold">1. Struktur</div>
                        <div class="mt-0.5">Klausul & jenis</div>
                    </button>
                    <button type="button" @click="goToStep(2)" class="rounded-lg border px-3 py-2 text-left text-xs transition" :class="step === 2 ? 'border-primary-300 bg-white text-primary-900' : (canGoToStep(2) ? 'border-gray-200 bg-white text-gray-700 hover:border-primary-300' : 'border-gray-100 bg-white text-gray-300')">
                        <div class="font-semibold">2. Metadata</div>
                        <div class="mt-0.5">Kode, judul</div>
                    </button>
                    <button type="button" @click="goToStep(3)" class="rounded-lg border px-3 py-2 text-left text-xs transition" :class="step === 3 ? 'border-primary-300 bg-white text-primary-900' : (canGoToStep(3) ? 'border-gray-200 bg-white text-gray-700 hover:border-primary-300' : 'border-gray-100 bg-white text-gray-300')">
                        <div class="font-semibold">3. Template & Signatories</div>
                        <div class="mt-0.5">Pertanyaan & Penanda Tangan</div>
                    </button>
                    <button type="button" @click="goToStep(4)" class="rounded-lg border px-3 py-2 text-left text-xs transition" :class="step === 4 ? 'border-primary-300 bg-white text-primary-900' : (canGoToStep(4) ? 'border-gray-200 bg-white text-gray-700 hover:border-primary-300' : 'border-gray-100 bg-white text-gray-300')">
                        <div class="font-semibold">4. Review</div>
                        <div class="mt-0.5">Preview & simpan</div>
                    </button>
                </div>

                <p x-show="stepError" x-cloak class="mt-3 text-sm text-red-600" x-text="stepError"></p>
            </div>

            <form method="POST" action="{{ route('quality.documents.store') }}" class="mt-4 space-y-5" x-ref="draftForm" @submit.prevent="if (onSubmit()) $el.submit()">
                @csrf

                <input type="hidden" name="template_id" :value="templateId">
                <input type="hidden" name="dibuat_oleh" :value="dibuatOleh">
                <input type="hidden" name="diperiksa_oleh" :value="diperiksaOleh">
                <input type="hidden" name="disahkan_oleh" :value="disahkanOleh">

                <template x-for="field in answerFormFields()" :key="`${field.name}:${field.value}`">
                    <input type="hidden" :name="field.name" :value="field.value">
                </template>

                <div x-show="step === 1" x-cloak class="space-y-5">
                    <div class="rounded-lg border border-primary-100 bg-primary-50 p-4">
                        <h3 class="text-sm font-semibold text-primary-900">Pilih Struktur Dokumen</h3>
                        <p class="mt-1 text-xs text-primary-800">Pilih klausul dan jenis dokumen terlebih dahulu.</p>
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
                                <option value="ik" @selected(old('doc_type') === 'ik')>Instruksi Kerja (IK)</option>
                                <option value="fr" @selected(old('doc_type') === 'fr')>Formulir (FR)</option>
                            </select>
                            @error('doc_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end">
                        <button type="button" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" @click="nextStep()">
                            Lanjut
                        </button>
                    </div>
                </div>

                <div x-show="step === 2" x-cloak class="space-y-5">
                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Metadata Dokumen</h3>
                        <p class="mt-1 text-xs text-gray-500">Lengkapi kode, judul, dan relasi (SOP/IK) jika diperlukan.</p>
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
                        {{-- Effective date is now auto-set on publish --}}
                    </div>
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

                    <div class="flex items-center justify-between border-t border-gray-200 pt-4">
                        <button type="button" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="prevStep()">
                            Kembali
                        </button>
                        <button type="button" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" @click="nextStep()">
                            Lanjut
                        </button>
                    </div>
                </div>

                <div x-show="step === 3" x-cloak class="space-y-5">
                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Template & Signatories</h3>
                        <p class="mt-1 text-xs text-gray-500">Pilih template aktif, isi/atur pertanyaan, dan tentukan penanda tangan.</p>
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

                    <div class="mt-4" x-show="!templatesLoading && templates.length > 0" x-cloak>
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <h3 class="mb-3 text-sm font-semibold text-gray-900">Penanda Tangan</h3>
                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-700" for="dibuat_oleh">Dibuat Oleh</label>
                                    <select
                                        id="dibuat_oleh"
                                        name="dibuat_oleh"
                                        x-model.number="dibuatOleh"
                                        :disabled="currentUserRole !== 'admin'"
                                        class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('dibuat_oleh') border-red-400 @else border-gray-300 @enderror disabled:bg-gray-100 disabled:text-gray-500"
                                    >
                                        <template x-for="user in users" :key="user.id">
                                            <option :value="user.id" x-text="`${user.name} (${user.role})`"></option>
                                        </template>
                                    </select>
                                    @error('dibuat_oleh')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-700" for="diperiksa_oleh">Diperiksa Oleh</label>
                                    <select
                                        id="diperiksa_oleh"
                                        name="diperiksa_oleh"
                                        x-model.number="diperiksaOleh"
                                        class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('diperiksa_oleh') border-red-400 @else border-gray-300 @enderror"
                                    >
                                        <option value="0">Pilih Pemeriksa</option>
                                        <template x-for="user in users" :key="user.id">
                                            <option :value="user.id" x-text="`${user.name} (${user.role})`" :disabled="user.id === dibuatOleh"></option>
                                        </template>
                                    </select>
                                    @error('diperiksa_oleh')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-700" for="disahkan_oleh">Disahkan Oleh</label>
                                    <select
                                        id="disahkan_oleh"
                                        name="disahkan_oleh"
                                        x-model.number="disahkanOleh"
                                        class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('disahkan_oleh') border-red-400 @else border-gray-300 @enderror"
                                    >
                                        <option value="0">Pilih Pengesah</option>
                                        <template x-for="user in users" :key="user.id">
                                            <option :value="user.id" x-text="`${user.name} (${user.role})`" :disabled="user.id === dibuatOleh || user.id === diperiksaOleh"></option>
                                        </template>
                                    </select>
                                    @error('disahkan_oleh')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500" x-show="signatoriesError" x-text="signatoriesError" class="text-red-600"></p>
                        </div>
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
                            <a :href="selectedTemplatePreviewUrl()" target="_blank" rel="noopener" class="inline-flex items-center rounded-md border border-primary-300 bg-white px-3 py-1.5 text-xs font-medium text-primary-700 hover:bg-primary-50">
                                Preview Template
                            </a>
                        </div>
                    </div>
                    @error('template_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    </div>

                <div x-show="docType" x-cloak class="grid gap-6 lg:grid-cols-12">
                    <div class="lg:col-span-12" data-qmh-question-form>
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
                                <div class="mb-4" x-show="docType === 'fr'" x-cloak>
                                    <div
                                        class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
                                        x-data="qmhFormBuilder({
                                            docType: docType,
                                            initialSchema: schema,
                                            initialJson: '',
                                        })"
                                        x-init="init()"
                                        @qmh-form-schema-reset.window="loadSchema($event.detail.schema); syncJsonNow()"
                                    >
                                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 pb-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <button type="button" class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-700" @click="addQuestion('text')">
                                                    + Pertanyaan
                                                </button>
                                                <button type="button" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="addQuestion('section')">
                                                    + Section
                                                </button>
                                                <button type="button" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="showRawJson = !showRawJson">
                                                    <span x-text="showRawJson ? 'Sembunyikan JSON' : 'Tampilkan JSON'"></span>
                                                </button>
                                            </div>

                                            <div class="text-xs text-gray-500">
                                                <span x-text="questions.length"></span> pertanyaan
                                            </div>
                                        </div>

                                        <template x-if="jsonError">
                                            <div class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800" x-text="jsonError"></div>
                                        </template>

                                        <div class="space-y-3">
                                            <template x-for="(q, idx) in questions" :key="idx">
                                                <div class="rounded-lg border border-gray-200 p-3">
                                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                                        <div class="flex-1 min-w-[240px]">
                                                            <div class="grid gap-2 sm:grid-cols-12">
                                                                <div class="sm:col-span-5">
                                                                    <label class="block text-[11px] font-semibold text-gray-600">Label</label>
                                                                    <input
                                                                        type="text"
                                                                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                                                                        x-model="q.label"
                                                                        @input.debounce.150ms="onLabelChanged(idx)"
                                                                        placeholder="Contoh: Nama Petugas"
                                                                    />
                                                                </div>

                                                                <div class="sm:col-span-3">
                                                                    <label class="block text-[11px] font-semibold text-gray-600">Tipe</label>
                                                                    <select
                                                                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                                                                        x-model="q.type"
                                                                        @change="onTypeChanged(idx)"
                                                                    >
                                                                        <option value="section">Section</option>
                                                                        <option value="text">Text</option>
                                                                        <option value="textarea">Textarea</option>
                                                                        <option value="list">List</option>
                                                                        <option value="select">Select</option>
                                                                        <option value="checkbox">Checkbox</option>
                                                                        <option value="date">Date</option>
                                                                        <option value="number">Number</option>
                                                                    </select>
                                                                </div>

                                                                <div class="sm:col-span-3">
                                                                    <label class="block text-[11px] font-semibold text-gray-600">ID</label>
                                                                    <input
                                                                        type="text"
                                                                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 font-mono text-xs"
                                                                        x-model="q.id"
                                                                        @input.debounce.150ms="q.auto_id = false; syncJson()"
                                                                        placeholder="field_name"
                                                                    />
                                                                    <p class="mt-1 text-[10px] text-gray-500">a-z, 0-9, _ (max 64)</p>
                                                                </div>

                                                                <div class="sm:col-span-1">
                                                                    <label class="block text-[11px] font-semibold text-gray-600">Wajib</label>
                                                                        <input
                                                                            type="checkbox"
                                                                            class="mt-3 h-4 w-4 rounded border-gray-300 text-primary-600"
                                                                            x-model="q.required"
                                                                            :disabled="q.type === 'section'"
                                                                            @change="syncJson()"
                                                                        />
                                                                </div>
                                                            </div>

                                                            <div class="mt-2 grid gap-2 sm:grid-cols-12">
                                                                <div class="sm:col-span-6">
                                                                    <label class="block text-[11px] font-semibold text-gray-600">Help (opsional)</label>
                                                                    <input
                                                                        type="text"
                                                                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                                                                        x-model="q.help"
                                                                        @input.debounce.150ms="syncJson()"
                                                                        placeholder="Contoh: isi sesuai format di label"
                                                                    />
                                                                </div>
                                                                <div class="sm:col-span-6">
                                                                    <label class="block text-[11px] font-semibold text-gray-600">Placeholder (opsional)</label>
                                                                    <input
                                                                        type="text"
                                                                        class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                                                                        x-model="q.placeholder"
                                                                        @input.debounce.150ms="syncJson()"
                                                                        placeholder="Contoh: 2026-02-15"
                                                                    />
                                                                </div>
                                                            </div>

                                                            <template x-if="q.type === 'select'">
                                                                <div class="mt-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                                                                    <div class="flex items-center justify-between">
                                                                        <div class="text-xs font-semibold text-gray-700">Options</div>
                                                                        <button type="button" class="text-xs font-medium text-primary-700 hover:underline" @click="addSelectOption(idx)">
                                                                            + Tambah option
                                                                        </button>
                                                                    </div>

                                                                    <div class="mt-2 space-y-2">
                                                                        <template x-for="(opt, optIdx) in q.options" :key="optIdx">
                                                                            <div class="grid grid-cols-12 gap-2 items-center">
                                                                                <div class="col-span-5">
                                                                                    <input type="text" class="w-full rounded-md border border-gray-300 px-2 py-1.5 font-mono text-xs" x-model="opt.value" @input.debounce.150ms="syncJson()" placeholder="value" />
                                                                                </div>
                                                                                <div class="col-span-6">
                                                                                    <input type="text" class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm" x-model="opt.label" @input.debounce.150ms="syncJson()" placeholder="label" />
                                                                                </div>
                                                                                <div class="col-span-1 text-right">
                                                                                    <button type="button" class="text-xs text-red-600 hover:underline" @click="deleteSelectOption(idx, optIdx)">Hapus</button>
                                                                                </div>
                                                                            </div>
                                                                        </template>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </div>

                                                        <div class="flex flex-col items-end gap-2">
                                                            <div class="flex gap-1">
                                                                <button type="button" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 hover:bg-gray-50" @click="moveUp(idx)" :disabled="idx === 0">Up</button>
                                                                <button type="button" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 hover:bg-gray-50" @click="moveDown(idx)" :disabled="idx === questions.length - 1">Down</button>
                                                            </div>
                                                            <button type="button" class="rounded-md border border-red-200 bg-red-50 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-100" @click="deleteQuestion(idx)">
                                                                Hapus
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>

                                            <template x-if="questions.length === 0">
                                                <div class="rounded-md border border-dashed border-gray-300 p-4 text-sm text-gray-600">
                                                    Belum ada pertanyaan. Klik <span class="font-semibold">+ Pertanyaan</span> untuk mulai.
                                                </div>
                                            </template>
                                        </div>

                                        <textarea
                                            id="form_schema_json"
                                            name="form_schema_json"
                                            rows="10"
                                            class="hidden"
                                            x-ref="schemaJson"
                                        >{{ old('form_schema_json', '') }}</textarea>

                                        <template x-if="showRawJson">
                                            <div class="mt-4">
                                                <div class="mb-2 text-xs font-semibold text-gray-700">JSON Preview</div>
                                                <pre class="max-h-80 overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-3 text-[11px] leading-relaxed" x-text="schemaJson()"></pre>
                                            </div>
                                        </template>
                                    </div>

                                    @error('form_schema_json')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <template x-if="schemaQuestions().length === 0">
                                    <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                        Belum ada pertanyaan untuk template ini.
                                    </div>
                                </template>

                                <template x-if="schemaQuestions().length > 0">
                                    <div class="space-y-4">
                                        <template x-for="(q, idx) in schemaQuestions()" :key="`${templateId}:${q.id || idx}`">
                                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <p class="text-xs font-semibold text-gray-500" x-text="`Q${idx + 1}`"></p>
                                                        <label class="mt-1 block text-sm font-semibold text-gray-900" :for="`q-${q.id}`">
                                                            <span x-text="q.label || q.id"></span>
                                                            <span class="ml-1 text-xs font-semibold text-red-600" x-show="q.required">*</span>
                                                        </label>
                                                    </div>
                                                    <p class="text-[11px] text-gray-500" x-text="questionTypeLabel(q.type)"></p>
                                                </div>

                                                <div class="mt-3" x-show="docType === 'fr'" x-cloak>
                                                    <template x-if="q.type === 'section'">
                                                        <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-900" x-text="(q.label || q.id).toUpperCase()"></div>
                                                    </template>

                                                    <template x-if="q.type === 'text'">
                                                        <input
                                                            :id="`q-${q.id}`"
                                                            type="text"
                                                            x-model.trim="answers[q.id]"
                                                            :placeholder="q.placeholder || ''"
                                                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary-600 focus:ring-primary-600"
                                                        />
                                                    </template>

                                                    <template x-if="q.type === 'textarea'">
                                                        <textarea
                                                            :id="`q-${q.id}`"
                                                            rows="4"
                                                            x-model="answers[q.id]"
                                                            :placeholder="q.placeholder || ''"
                                                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary-600 focus:ring-primary-600"
                                                        ></textarea>
                                                    </template>

                                                    <template x-if="q.type === 'list'">
                                                        <textarea
                                                            :id="`q-${q.id}`"
                                                            rows="4"
                                                            x-model="listAnswerText[q.id]"
                                                            @input="syncListAnswer(q.id)"
                                                            :placeholder="q.placeholder || 'Satu item per baris'"
                                                            class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs leading-relaxed focus:border-primary-600 focus:ring-primary-600"
                                                        ></textarea>
                                                    </template>

                                                    <template x-if="q.type === 'select'">
                                                        <select
                                                            :id="`q-${q.id}`"
                                                            x-model="answers[q.id]"
                                                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-600 focus:ring-primary-600"
                                                        >
                                                            <option value="">Pilih...</option>
                                                            <template x-for="(opt, optIdx) in (Array.isArray(q.options) ? q.options : [])" :key="optIdx">
                                                                <option :value="opt.value" x-text="opt.label || opt.value"></option>
                                                            </template>
                                                        </select>
                                                    </template>

                                                    <template x-if="q.type === 'checkbox'">
                                                        <label class="flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800">
                                                            <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600" x-model="answers[q.id]" />
                                                            <span x-text="q.placeholder || 'Ya / Tidak'"></span>
                                                        </label>
                                                    </template>

                                                    <template x-if="q.type === 'date'">
                                                        <input
                                                            :id="`q-${q.id}`"
                                                            type="date"
                                                            x-model="answers[q.id]"
                                                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary-600 focus:ring-primary-600"
                                                        />
                                                    </template>

                                                    <template x-if="q.type === 'number'">
                                                        <input
                                                            :id="`q-${q.id}`"
                                                            type="number"
                                                            inputmode="numeric"
                                                            x-model="answers[q.id]"
                                                            :placeholder="q.placeholder || ''"
                                                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary-600 focus:ring-primary-600"
                                                        />
                                                    </template>

                                                    <template x-if="q.help">
                                                        <p class="mt-1 text-xs text-gray-500" x-text="q.help"></p>
                                                    </template>
                                                </div>

                                                <div class="mt-3" x-show="docType !== 'fr'" x-cloak>
                                                    <div x-show="q.type === 'list'">
                                                        <div
                                                            class="rounded-xl border border-gray-200 bg-white p-3"
                                                            x-data="qmhEditor({ initialContent: answerEditorInitialValue(q.id), editorId: `qmh-list-${q.id}` })"
                                                            x-init="init()"
                                                            @qmh-editor-change="onRichTextListAnswerChange(q.id, $event.detail.html)"
                                                        >
                                                            <div class="mb-3 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                                                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('bulletList') }" @click="toggleBulletList()">Bullets</button>
                                                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('orderedList') }" @click="toggleOrderedList()">Number</button>
                                                            </div>

                                                            <div class="qmh-editor-surface qmh-editor-surface--compact" x-ref="editor"></div>
                                                            <input type="hidden" x-ref="hiddenInput">
                                                        </div>
                                                        <p class="mt-1 text-xs text-gray-500">Tip: satu item per baris. Gunakan Bullets/Number untuk daftar.</p>
                                                    </div>

                                                    <div x-show="q.type !== 'list'">
                                                        <div
                                                            class="rounded-xl border border-gray-200 bg-white p-3"
                                                            x-data="qmhEditor({ initialContent: answerEditorInitialValue(q.id), editorId: `qmh-answer-${q.id}` })"
                                                            x-init="init()"
                                                            @qmh-editor-change="onRichTextAnswerChange(q.id, $event.detail.html)"
                                                        >
                                                            <div class="mb-3 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                                                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('bold') }" @click="toggleBold()">B</button>
                                                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('italic') }" @click="toggleItalic()">I</button>
                                                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('underline') }" @click="toggleUnderline()">U</button>
                                                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('bulletList') }" @click="toggleBulletList()">Bullets</button>
                                                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('orderedList') }" @click="toggleOrderedList()">Number</button>
                                                            </div>

                                                            <div class="qmh-editor-surface qmh-editor-surface--compact" x-ref="editor"></div>
                                                            <input type="hidden" x-ref="hiddenInput">
                                                        </div>
                                                    </div>
                                                </div>

                                                <p class="mt-2 text-sm text-red-600" x-show="fieldErrors[q.id]" x-text="fieldErrors[q.id]"></p>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                    <div class="flex items-center justify-between border-t border-gray-200 pt-4">
                        <button type="button" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="prevStep()">
                            Kembali
                        </button>
                        <button type="button" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" @click="nextStep()">
                            Review
                        </button>
                    </div>
                </div>

                <div x-show="step === 4" x-cloak class="space-y-5">
                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <h3 class="text-sm font-semibold text-gray-900">Review & Simpan</h3>
                        <p class="mt-1 text-xs text-gray-500">Gunakan preview untuk memastikan format sudah benar sebelum menyimpan draft.</p>
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
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="prevStep()">
                            Kembali
                        </button>
                        <a href="{{ route('quality.documents.index') }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Batal
                        </a>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="openQuickPreview()"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Preview
                        </button>

                        <button
                            type="button"
                            @click="openPdfPreview()"
                            :disabled="pdfPreviewLoading"
                            :class="{ 'cursor-not-allowed opacity-50': pdfPreviewLoading }"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <span x-text="pdfPreviewLoading ? 'Membuka PDF...' : 'Preview PDF'"></span>
                        </button>

                        <button type="submit"
                                :disabled="isSubmitting || !canSubmit()"
                                :class="{ 'cursor-not-allowed opacity-50': isSubmitting || !canSubmit() }"
                                class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                            <template x-if="isSubmitting">
                                <svg class="-ml-1 mr-2 h-4 w-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </template>
                            <span x-text="isSubmitting ? 'Menyimpan...' : 'Simpan Draft'">Simpan Draft</span>
                        </button>
                    </div>
                </div>
                    <p x-show="pdfPreviewError" x-cloak class="text-sm text-red-600" x-text="pdfPreviewError"></p>
                </div>
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
                        <h3 class="text-base font-semibold text-gray-900" x-text="previewMode === 'submit' ? 'Preview Dokumen Sebelum Simpan Draft' : 'Preview Dokumen'"></h3>
                        <p class="mt-1 text-xs text-gray-500" x-show="previewMode === 'submit'">Cek konten terlebih dahulu, lalu lanjutkan simpan draft jika sudah sesuai.</p>
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
                        x-text="previewMode === 'submit' ? 'Kembali Edit' : 'Tutup'"
                    >
                    </button>
                    <button
                        type="button"
                        @click="confirmSubmitPreview()"
                        x-show="previewMode === 'submit'"
                        class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        Lanjut Simpan Draft
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
