@php
    $revisionStatus = (string) ($revision?->status ?? '');
    $currentUserId = (int) auth()->id();
    $createdById = (int) ($revision?->dibuat_oleh ?? 0);

    $canSubmitForReview = $revisionStatus === 'draft' && $createdById === $currentUserId;
    $submitReason = match (true) {
        $revisionStatus !== 'draft' => 'Submit hanya tersedia saat status draft.',
        $createdById !== $currentUserId => 'Hanya pembuat revisi yang dapat submit.',
        default => null,
    };

    $reviewerOptions = collect($users ?? [])
        ->filter(fn ($u) => (int) ($u->id ?? 0) > 0)
        ->filter(fn ($u) => (int) $u->id !== $currentUserId)
        ->filter(fn ($u) => (int) $u->id !== $createdById)
        ->values()
        ->map(fn ($u) => [
            'id' => (int) $u->id,
            'name' => (string) $u->name,
            'role' => (string) $u->role,
        ]);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Editor Dokumen QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Dokumen', 'route' => 'quality.documents.show', 'params' => ['document' => $document->id]],
                    ['label' => 'Editor'],
                ]"
            >
                <x-slot name="actions">
                    <a href="{{ route('quality.documents.show', $document) }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Kembali
                    </a>
                </x-slot>
            </x-page-header>

            <x-qmh-subnav active="documents" />
        </div>
    </x-slot>

    <div
        class="space-y-4"
        x-data="qmhEditPage({
            revisionId: @js($revision?->id),
            revisionStatus: @js($revision?->status),
            docCode: @js($document->doc_code),
            documentTitle: @js($document->title),
            clause: @js($document->clause),
            templateId: @js((int) ($revision?->template_id ?? 0)),
            canSubmitForReview: @js($canSubmitForReview),
            submitReason: @js($submitReason),
            reviewerOptions: @js($reviewerOptions),
            docType: @js($document->doc_type),
            isFormulir: @js(($document?->doc_type ?? '') === 'formulir'),
            initialSchema: @js($revision?->form_schema_json ?? data_get($revision?->template?->metadata, 'form_schema')),
            initialAnswersJson: @js($revision?->answers_json ?? []),
            initialContent: @js($revision?->content_html ?? '<p></p>'),
            showUrl: @js(route('quality.documents.show', $document)),
            saveUrl: @js($revision ? '/api/quality/revisions/'.$revision->id.'/content' : null),
            lockUrl: @js($revision ? '/api/quality/revisions/'.$revision->id.'/lock' : null),
            heartbeatUrl: @js($revision ? '/api/quality/revisions/'.$revision->id.'/heartbeat' : null),
            unlockUrl: @js($revision ? '/api/quality/revisions/'.$revision->id.'/unlock' : null),
            csrfToken: @js(csrf_token()),
        })"
        @qmh-form-schema-change.window="onFormSchemaChanged($event.detail.schema)"
        x-init="init()"
    >
        <div x-show="errorMessage" x-cloak class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="errorMessage"></div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ $document->doc_code }} - {{ $revision?->version_label ?? '-' }}</h3>
                    <p class="text-xs text-gray-500">Status simpan: <span x-text="saveStatusLabel()"></span></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-700">
                        Status: <span class="ml-1" x-text="revisionStatus || '-'">-</span>
                    </span>
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium" :class="hasLock ? 'border-primary-200 bg-primary-50 text-primary-900' : 'border-amber-200 bg-amber-50 text-amber-800'">
                        <span x-text="hasLock ? 'Lock aktif' : 'Tanpa lock'"></span>
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-12 lg:col-span-8">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    @if(($document?->doc_type ?? '') === 'formulir')
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                            Editor Formulir menggunakan schema pertanyaan revisi (snapshot) dan disimpan sebagai `answers_json`.
                        </div>

                        <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4" x-show="revisionStatus === 'draft'" x-cloak>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Pertanyaan Formulir</p>
                                    <p class="mt-1 text-xs text-gray-500">Bisa diedit hanya saat draft dan pemilik lock.</p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                    @click="showSchemaEditor = !showSchemaEditor"
                                >
                                    <span x-text="showSchemaEditor ? 'Tutup Editor' : 'Edit Pertanyaan'"></span>
                                </button>
                            </div>

                            <template x-if="showSchemaEditor">
                                <div class="mt-4" :class="hasLock ? '' : 'pointer-events-none opacity-60'">
                                    <div
                                        class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
                                        x-data="qmhFormBuilder({
                                            docType: 'fr',
                                            initialSchema: schema,
                                            initialJson: '',
                                        })"
                                        x-init="init()"
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

                                        <textarea class="hidden" x-ref="schemaJson" rows="4"></textarea>

                                        <template x-if="showRawJson">
                                            <div class="mt-4">
                                                <div class="mb-2 text-xs font-semibold text-gray-700">JSON Preview</div>
                                                <pre class="max-h-80 overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-3 text-[11px] leading-relaxed" x-text="schemaJson()"></pre>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="mt-4" x-show="schemaQuestions().length === 0" x-cloak>
                            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                Form schema belum diatur untuk template formulir ini.
                            </div>
                        </div>

                        <div class="mt-4 space-y-4" x-show="schemaQuestions().length > 0" x-cloak>
                            <template x-for="(q, idx) in schemaQuestions()" :key="`fr-${q.id || idx}`">
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

                                    <div class="mt-3">
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

                                    <p class="mt-2 text-sm text-red-600" x-show="fieldErrors[q.id]" x-text="fieldErrors[q.id]"></p>
                                </div>
                            </template>
                        </div>
                    @else
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" x-data="qmhEditor({ initialContent: @js($revision?->content_html ?? '<p></p>') })" x-init="init()" @qmh-editor-change="onEditorChange($event.detail)">
                            <div class="mb-3 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('bold') }" @click="toggleBold()">B</button>
                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('italic') }" @click="toggleItalic()">I</button>
                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('underline') }" @click="toggleUnderline()">U</button>
                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('heading', { level: 1 }) }" @click="setHeading(1)">H1</button>
                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('heading', { level: 2 }) }" @click="setHeading(2)">H2</button>
                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('heading', { level: 3 }) }" @click="setHeading(3)">H3</button>
                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('bulletList') }" @click="toggleBulletList()">Bullets</button>
                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('orderedList') }" @click="toggleOrderedList()">Number</button>
                                <button type="button" class="qmh-editor-btn" @click="setAlign('left')">Kiri</button>
                                <button type="button" class="qmh-editor-btn" @click="setAlign('center')">Tengah</button>
                                <button type="button" class="qmh-editor-btn" @click="setAlign('right')">Kanan</button>
                                <button type="button" class="qmh-editor-btn" @click="insertTable()">Tabel</button>
                                <button type="button" class="qmh-editor-btn" @click="addTableRowBefore()">+Baris Atas</button>
                                <button type="button" class="qmh-editor-btn" @click="addTableRowAfter()">+Baris Bawah</button>
                                <button type="button" class="qmh-editor-btn" @click="deleteTableRow()">-Baris</button>
                                <button type="button" class="qmh-editor-btn" @click="addTableColumnBefore()">+Kolom Kiri</button>
                                <button type="button" class="qmh-editor-btn" @click="addTableColumnAfter()">+Kolom Kanan</button>
                                <button type="button" class="qmh-editor-btn" @click="deleteTableColumn()">-Kolom</button>
                                <button type="button" class="qmh-editor-btn" @click="mergeTableCells()">Merge Sel</button>
                                <button type="button" class="qmh-editor-btn" @click="splitTableCell()">Split Sel</button>
                                <button type="button" class="qmh-editor-btn" @click="toggleTableHeaderRow()">Header Baris</button>
                                <button type="button" class="qmh-editor-btn" @click="toggleTableHeaderColumn()">Header Kolom</button>
                                <button type="button" class="qmh-editor-btn" @click="deleteTable()">Hapus Tabel</button>
                            </div>

                            <div class="qmh-editor-surface" x-ref="editor"></div>
                            <input type="hidden" x-ref="hiddenInput" name="content_html">
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4">
                <div class="sticky top-6 space-y-4">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Aksi</p>
                                <p class="mt-1 text-xs text-gray-500">Simpan draft dan lanjutkan workflow saat siap.</p>
                            </div>
                            <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-700">
                                <span x-text="hasLock ? 'Lock aktif' : 'Tanpa lock'"></span>
                            </span>
                        </div>

                        <div class="mt-4 space-y-2">
                            <button
                                type="button"
                                @click="saveNow()"
                                :disabled="saving || !hasLock"
                                class="inline-flex w-full items-center justify-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                            >
                                <span x-text="saving ? 'Menyimpan...' : 'Simpan Draft'"></span>
                            </button>

                            <button
                                type="button"
                                @click="openSubmitModal()"
                                :disabled="!canSubmitForReview || !hasLock"
                                :title="submitReason || ''"
                                class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Submit untuk Review
                            </button>

                            <p x-show="(!canSubmitForReview && submitReason)" x-cloak class="text-xs text-gray-500" x-text="submitReason"></p>

                            <button
                                type="button"
                                @click="goBack()"
                                class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Buka Detail Dokumen
                            </button>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-semibold text-gray-900">Checklist</p>
                        <p class="mt-1 text-xs text-gray-500">Bantu memastikan dokumen audit-ready.</p>

                        <div class="mt-3 space-y-2 text-sm text-gray-700">
                            <label class="flex items-start gap-2">
                                <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600" x-model="checklist.complete_required" />
                                <span>Jawaban wajib sudah terisi (khusus Formulir).</span>
                            </label>
                            <label class="flex items-start gap-2">
                                <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600" x-model="checklist.review_content" />
                                <span>Konten sudah dicek via preview.</span>
                            </label>
                            <label class="flex items-start gap-2">
                                <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600" x-model="checklist.ready_submit" />
                                <span>Siap disubmit (penomoran/metadata final).</span>
                            </label>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-semibold text-gray-900">Preview</p>
                        <p class="mt-1 text-xs text-gray-500">Lihat ringkasan sebelum submit.</p>

                        <div class="mt-3">
                            <button type="button" class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="openPreviewModal()">
                                Buka Preview
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div x-show="submitModal.open" x-cloak x-trap.noscroll.inert="submitModal.open" class="fixed inset-0 z-pd-modal overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex min-h-dvh items-center justify-center px-4 py-8">
            <div class="fixed inset-0 bg-gray-900/50" @click="closeSubmitModal()"></div>
            <div class="relative w-full max-w-lg rounded-xl bg-white p-5 shadow-xl" x-transition>
                <h3 class="text-lg font-semibold text-gray-900">Submit untuk Review</h3>
                <p class="mt-1 text-sm text-gray-600">Pilih pemeriksa untuk melanjutkan workflow dokumen.</p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="submit-reviewer">Pemeriksa</label>
                        <select id="submit-reviewer" x-model.number="submitModal.reviewerId" class="w-full rounded-md border border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="">Pilih pemeriksa</option>
                            <template x-for="opt in reviewerOptions" :key="opt.id">
                                <option :value="opt.id" x-text="`${opt.name} (${opt.role})`"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-xs text-gray-500" x-show="!hasLock" x-cloak>Anda harus memegang lock untuk submit.</p>
                    </div>

                    <div x-show="submitModal.error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="submitModal.error"></div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700" @click="closeSubmitModal()">Batal</button>
                    <button
                        type="button"
                        class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="submitModal.loading"
                        @click="submitForReview()"
                        x-text="submitModal.loading ? 'Memproses...' : 'Submit'"
                    ></button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="previewModalOpen" x-cloak x-trap.noscroll.inert="previewModalOpen" class="fixed inset-0 z-pd-modal overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex min-h-dvh items-center justify-center px-4 py-8">
            <div class="fixed inset-0 bg-gray-900/50" @click="closePreviewModal()"></div>
            <div class="relative w-full max-w-4xl rounded-xl bg-white p-5 shadow-xl" x-transition>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Preview Dokumen</h3>
                        <p class="mt-1 text-sm text-gray-600">Ringkasan konten saat ini (belum tentu tersimpan).</p>
                    </div>
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="closePreviewModal()">Tutup</button>
                </div>

                <div class="mt-4 max-h-[70vh] overflow-auto rounded-xl border border-gray-200 bg-white p-4">
                    <template x-if="isFormulir">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700">No</th>
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700">Label</th>
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700">Isi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="(q, idx) in schemaQuestions()" :key="`preview-${q.id || idx}`">
                                        <tr>
                                            <td class="px-3 py-2 text-gray-600" x-text="idx + 1"></td>
                                            <td class="px-3 py-2 font-medium text-gray-900">
                                                <span x-text="q.label || q.id"></span>
                                                <span class="ml-1 text-xs font-semibold text-red-600" x-show="q.required">*</span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <template x-if="q.type === 'section'">
                                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-600" x-text="q.label || q.id"></div>
                                                </template>

                                                <template x-if="q.type !== 'section' && q.type !== 'list'">
                                                    <div class="whitespace-pre-wrap text-gray-800" x-text="formatPreviewAnswer(q)"></div>
                                                </template>

                                                <template x-if="q.type === 'list'">
                                                    <ul class="list-disc space-y-1 pl-5 text-gray-800">
                                                        <template x-for="(item, itemIdx) in (Array.isArray(answers[q.id]) ? answers[q.id] : [])" :key="itemIdx">
                                                            <li x-text="item"></li>
                                                        </template>
                                                    </ul>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>

                    <template x-if="!isFormulir">
                        <article class="prose prose-sm max-w-none text-gray-800" x-html="contentHtml"></article>
                    </template>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <p class="text-xs text-gray-500">Tip: gunakan <strong>Simpan Draft</strong> untuk memastikan preview sesuai versi tersimpan.</p>
                    <button type="button" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" @click="closePreviewModal()">Selesai</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function qmhEditPage(config) {
            return {
                revisionId: config.revisionId,
                revisionStatus: typeof config.revisionStatus === 'string' ? config.revisionStatus : '',
                docCode: typeof config.docCode === 'string' ? config.docCode : '',
                documentTitle: typeof config.documentTitle === 'string' ? config.documentTitle : '',
                clause: Number.isFinite(Number(config.clause)) ? Number(config.clause) : 4,
                templateId: Number.isFinite(Number(config.templateId)) ? Number(config.templateId) : 0,
                canSubmitForReview: Boolean(config.canSubmitForReview),
                submitReason: typeof config.submitReason === 'string' ? config.submitReason : '',
                reviewerOptions: Array.isArray(config.reviewerOptions) ? config.reviewerOptions : [],
                docType: typeof config.docType === 'string' ? config.docType : '',
                isFormulir: Boolean(config.isFormulir),
                schema: config.initialSchema && typeof config.initialSchema === 'object' ? config.initialSchema : null,
                answers: config.initialAnswersJson && typeof config.initialAnswersJson === 'object' ? { ...config.initialAnswersJson } : {},
                listAnswerText: {},
                fieldErrors: {},
                showSchemaEditor: false,
                checklist: {
                    complete_required: false,
                    review_content: false,
                    ready_submit: false,
                },
                previewModalOpen: false,
                submitModal: { open: false, reviewerId: '', loading: false, error: '' },
                initialContent: typeof config.initialContent === 'string' ? config.initialContent : '<p></p>',
                showUrl: config.showUrl,
                saveUrl: config.saveUrl,
                lockUrl: config.lockUrl,
                heartbeatUrl: config.heartbeatUrl,
                unlockUrl: config.unlockUrl,
                csrfToken: config.csrfToken,
                hasLock: false,
                heartbeatTimer: null,
                saving: false,
                dirty: false,
                contentHtml: typeof config.initialContent === 'string' && config.initialContent.trim() !== '' ? config.initialContent : '<p></p>',
                editorJson: null,
                errorMessage: '',
                saveState: 'idle',

                init() {
                    if (!this.revisionId) {
                        this.errorMessage = 'Revisi dokumen tidak ditemukan.';
                        return;
                    }

                    if (this.isFormulir) {
                        if (!this.schema || typeof this.schema !== 'object') {
                            this.schema = { version: 1, doc_type: 'fr', questions: [] };
                        }
                        this.syncSchemaDefaults();
                    }

                    this.acquireLock();
                    this.registerBeforeUnload();
                },

                onFormSchemaChanged(nextSchema) {
                    if (!this.isFormulir) return;
                    if (!nextSchema || typeof nextSchema !== 'object') return;

                    this.schema = nextSchema;
                    this.fieldErrors = {};
                    this.syncSchemaDefaults();
                    this.dirty = true;
                    this.saveState = 'dirty';
                },

                onEditorChange(detail) {
                    this.contentHtml = detail.html || '';
                    this.editorJson = detail.editor_json || null;
                    this.dirty = true;
                    this.saveState = 'dirty';
                },

                schemaQuestions() {
                    const questions = this.schema?.questions;
                    return Array.isArray(questions) ? questions : [];
                },

                questionTypeLabel(type) {
                    const t = String(type || 'text');
                    if (t === 'section') return 'Section';
                    if (t === 'text') return 'Teks';
                    if (t === 'textarea') return 'Paragraf';
                    if (t === 'list') return 'Daftar';
                    if (t === 'select') return 'Pilihan';
                    if (t === 'checkbox') return 'Centang';
                    if (t === 'date') return 'Tanggal';
                    if (t === 'number') return 'Angka';
                    return 'Isian';
                },

                coerceBoolean(value) {
                    if (typeof value === 'boolean') return value;
                    if (typeof value === 'number') return value === 1;
                    if (typeof value === 'string') {
                        const v = value.trim().toLowerCase();
                        if (['1', 'true', 'on', 'yes', 'y'].includes(v)) return true;
                        if (['0', 'false', 'off', 'no', 'n', ''].includes(v)) return false;
                    }
                    return false;
                },

                htmlToPlainText(html) {
                    if (typeof html !== 'string') return '';
                    const div = document.createElement('div');
                    div.innerHTML = html;
                    const txt = div.textContent || '';
                    return txt.replace(/\u00a0/g, ' ').trim();
                },

                extractListItemsFromHtml(html) {
                    if (typeof html !== 'string' || !html.trim()) return [];
                    const div = document.createElement('div');
                    div.innerHTML = html;
                    const lis = Array.from(div.querySelectorAll('li'));
                    if (lis.length > 0) {
                        return lis
                            .map((li) => (li.textContent || '').trim())
                            .filter((v) => v !== '');
                    }
                    const txt = (div.textContent || '').trim();
                    if (!txt) return [];
                    return txt
                        .split('\n')
                        .map((line) => line.trim())
                        .filter((line) => line !== '');
                },

                syncSchemaDefaults() {
                    const questions = this.schemaQuestions();
                    const nextListText = { ...this.listAnswerText };

                    questions.forEach((q) => {
                        const qid = typeof q?.id === 'string' ? q.id : '';
                        if (!qid) return;

                        if (q.type === 'section') {
                            delete this.answers[qid];
                            delete nextListText[qid];
                            return;
                        }

                        if (q.type === 'checkbox') {
                            this.answers[qid] = this.coerceBoolean(this.answers[qid]);
                            return;
                        }

                        if (q.type === 'list') {
                            const existing = this.answers[qid];
                            if (typeof existing === 'string') {
                                const items = this.extractListItemsFromHtml(existing);
                                this.answers[qid] = items;
                                nextListText[qid] = items.join('\n');
                                return;
                            }

                            const items = Array.isArray(existing)
                                ? existing
                                      .filter((v) => typeof v === 'string')
                                      .map((v) => v.trim())
                                      .filter((v) => v !== '')
                                : [];
                            this.answers[qid] = items;
                            nextListText[qid] = items.join('\n');
                            return;
                        }

                        const existing = this.answers[qid];
                        if (typeof existing === 'string') {
                            this.answers[qid] = existing.trim();
                            return;
                        }
                        if (existing == null) {
                            this.answers[qid] = '';
                            return;
                        }
                        this.answers[qid] = String(existing);
                    });

                    this.listAnswerText = nextListText;
                },

                syncListAnswer(qid) {
                    const raw = typeof this.listAnswerText[qid] === 'string' ? this.listAnswerText[qid] : '';
                    const items = raw
                        .split('\n')
                        .map((line) => line.trim())
                        .filter((line) => line !== '');
                    this.answers[qid] = items;
                    this.dirty = true;
                    this.saveState = 'dirty';
                },

                validateFormAnswers() {
                    this.fieldErrors = {};

                    this.schemaQuestions().forEach((q) => {
                        const qid = typeof q?.id === 'string' ? q.id : '';
                        if (!qid) return;
                        const isRequired = Boolean(q.required);

                        if (q.type === 'section') return;

                        if (q.type === 'checkbox') {
                            if (isRequired && !this.coerceBoolean(this.answers[qid])) {
                                this.fieldErrors[qid] = 'Wajib dicentang.';
                            }
                            return;
                        }

                        if (q.type === 'list') {
                            const items = Array.isArray(this.answers[qid]) ? this.answers[qid] : [];
                            if (isRequired && items.length === 0) {
                                this.fieldErrors[qid] = 'Wajib diisi.';
                            }
                            return;
                        }

                        const val = typeof this.answers[qid] === 'string' ? this.answers[qid].trim() : '';
                        if (isRequired && !val) {
                            this.fieldErrors[qid] = 'Wajib diisi.';
                            return;
                        }

                        if (q.type === 'select') {
                            const opts = Array.isArray(q?.options) ? q.options : [];
                            const allowed = opts
                                .map((opt) => (typeof opt?.value === 'string' ? opt.value : ''))
                                .filter((v) => v !== '');
                            if (val && allowed.length > 0 && !allowed.includes(val)) {
                                this.fieldErrors[qid] = 'Pilihan tidak valid.';
                            }
                        }

                        if (q.type === 'date' && val && !/^\d{4}-\d{2}-\d{2}$/.test(val)) {
                            this.fieldErrors[qid] = 'Tanggal tidak valid.';
                        }

                        if (q.type === 'number' && val && !/^[+-]?\d+(\.\d+)?$/.test(val)) {
                            this.fieldErrors[qid] = 'Angka tidak valid.';
                        }
                    });

                    return Object.keys(this.fieldErrors).length === 0;
                },

                saveStatusLabel() {
                    if (this.saveState === 'saving') return 'Menyimpan...';
                    if (this.saveState === 'saved') return 'Tersimpan';
                    if (this.saveState === 'dirty') return 'Belum tersimpan';
                    return 'Siap';
                },

                openSubmitModal() {
                    if (!this.canSubmitForReview) {
                        return;
                    }

                    this.submitModal.open = true;
                    this.submitModal.loading = false;
                    this.submitModal.error = '';
                    this.submitModal.reviewerId = '';
                },

                closeSubmitModal() {
                    if (this.submitModal.loading) {
                        return;
                    }

                    this.submitModal.open = false;
                    this.submitModal.error = '';
                },

                async submitForReview() {
                    if (!this.hasLock) {
                        this.submitModal.error = 'Anda harus memegang lock untuk submit.';
                        return;
                    }

                    if (!this.submitModal.reviewerId) {
                        this.submitModal.error = 'Pilih pemeriksa terlebih dahulu.';
                        return;
                    }

                    this.submitModal.loading = true;
                    this.submitModal.error = '';

                    const result = await this.apiPost(`/api/quality/revisions/${this.revisionId}/submit`, {
                        reviewer_id: this.submitModal.reviewerId,
                    });

                    if (!result.ok) {
                        this.submitModal.error = result.message || 'Gagal submit untuk review.';
                        this.submitModal.loading = false;
                        return;
                    }

                    await this.releaseLock();
                    window.location.href = this.showUrl;
                },

                openPreviewModal() {
                    this.previewModalOpen = true;
                },

                closePreviewModal() {
                    this.previewModalOpen = false;
                },

                formatPreviewAnswer(q) {
                    const qid = typeof q?.id === 'string' ? q.id : '';
                    const type = String(q?.type || 'text');
                    const val = qid ? this.answers[qid] : '';

                    if (type === 'checkbox') {
                        return this.coerceBoolean(val) ? 'Ya' : 'Tidak';
                    }

                    if (val == null) {
                        return '';
                    }

                    if (Array.isArray(val)) {
                        return val.join(', ');
                    }

                    return String(val);
                },

                async acquireLock() {
                    const result = await this.apiPost(this.lockUrl, {});
                    if (!result.ok) {
                        this.errorMessage = result.message || 'Dokumen sedang diedit oleh pengguna lain.';
                        setTimeout(() => {
                            window.location.href = this.showUrl;
                        }, 1500);
                        return;
                    }

                    this.hasLock = true;
                    this.startHeartbeat();
                },

                startHeartbeat() {
                    this.stopHeartbeat();
                    this.heartbeatTimer = window.setInterval(async () => {
                        if (!this.hasLock) return;
                        const result = await this.apiPost(this.heartbeatUrl, {});
                        if (!result.ok) {
                            this.stopHeartbeat();
                            this.hasLock = false;
                            this.errorMessage = 'Lock hilang. Editor berubah read-only, kembali ke detail...';
                            setTimeout(() => {
                                window.location.href = this.showUrl;
                            }, 1500);
                        }
                    }, 30000);
                },

                stopHeartbeat() {
                    if (this.heartbeatTimer) {
                        window.clearInterval(this.heartbeatTimer);
                        this.heartbeatTimer = null;
                    }
                },

                async saveNow() {
                    if (!this.hasLock || !this.saveUrl) {
                        return;
                    }

                    this.saving = true;
                    this.saveState = 'saving';
                    this.errorMessage = '';

                    try {
                        let payload = {
                            content_html: this.contentHtml || '<p></p>',
                            content_css: null,
                            editor_json: this.editorJson,
                        };

                        if (this.isFormulir) {
                            this.errorMessage = '';
                            if (!this.validateFormAnswers()) {
                                this.errorMessage = 'Lengkapi jawaban wajib sebelum menyimpan.';
                                this.saveState = 'dirty';
                                return;
                            }

                            // Ensure list answers are synced from textareas.
                            this.schemaQuestions().forEach((q) => {
                                if (q?.type === 'list' && typeof q?.id === 'string') {
                                    this.syncListAnswer(q.id);
                                }
                            });

                            payload = {
                                answers_json: this.answers,
                                form_schema_json: this.schema,
                            };
                        }

                        const response = await fetch(this.saveUrl, {
                            method: 'PUT',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            body: JSON.stringify(payload),
                        });

                        if (!response.ok) {
                            const extracted = await this.extractErrorMessage(response, 'Gagal menyimpan konten dokumen.');
                            this.errorMessage = extracted.message;
                            if (this.isFormulir && extracted.errors) {
                                this.fieldErrors = extracted.errors;
                            }
                            this.saveState = 'dirty';
                            return;
                        }

                        this.dirty = false;
                        this.saveState = 'saved';
                    } catch (error) {
                        this.errorMessage = 'Terjadi gangguan jaringan saat menyimpan.';
                        this.saveState = 'dirty';
                    } finally {
                        this.saving = false;
                    }
                },

                async goBack() {
                    await this.releaseLock();
                    window.location.href = this.showUrl;
                },

                async releaseLock() {
                    this.stopHeartbeat();
                    if (!this.hasLock) return;

                    await this.apiPost(this.unlockUrl, { force: false });
                    this.hasLock = false;
                },

                registerBeforeUnload() {
                    window.addEventListener('beforeunload', (event) => {
                        this.stopHeartbeat();

                        if (this.hasLock && this.unlockUrl) {
                            const payload = JSON.stringify({ force: false });
                            const blob = new Blob([payload], { type: 'application/json' });
                            if (navigator.sendBeacon) {
                                navigator.sendBeacon(this.unlockUrl, blob);
                            } else {
                                fetch(this.unlockUrl, {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    keepalive: true,
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': this.csrfToken,
                                    },
                                    body: payload,
                                });
                            }
                        }

                        if (this.dirty) {
                            event.preventDefault();
                            event.returnValue = '';
                        }
                    });
                },

                async apiPost(url, payload) {
                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            body: JSON.stringify(payload),
                        });

                        if (response.ok) {
                            return { ok: true, data: await response.json(), message: '', errors: null };
                        }

                        const extracted = await this.extractErrorMessage(response, 'Permintaan gagal diproses.');
                        return { ok: false, data: null, message: extracted.message, errors: extracted.errors };
                    } catch (error) {
                        return { ok: false, data: null, message: 'Terjadi gangguan jaringan.', errors: null };
                    }
                },

                async extractErrorMessage(response, fallback) {
                    const out = { message: fallback, errors: null };
                    try {
                        const payload = await response.json();
                        if (payload?.message) {
                            out.message = payload.message;
                        }

                        if (payload?.errors) {
                            const mapped = {};
                            Object.keys(payload.errors).forEach((key) => {
                                const arr = payload.errors[key];
                                if (!Array.isArray(arr) || arr.length === 0) return;
                                if (key.startsWith('answers_json.')) {
                                    mapped[key.replace('answers_json.', '')] = arr[0];
                                }
                            });

                            if (Object.keys(mapped).length > 0) {
                                out.errors = mapped;
                                const firstKey = Object.keys(mapped)[0];
                                out.message = mapped[firstKey];
                            }
                        }
                    } catch (error) {
                    }

                    return out;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
