@php
    $revisionStatus = (string) ($revision?->status ?? '');
    $currentUserId = (int) auth()->id();
    $createdById = (int) ($revision?->dibuat_oleh ?? 0);
    $canManageTemplate = auth()->user()?->hasPermission('qmh.template.manage') ?? false;

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
            users: @js($users ?? []),
            canManageTemplate: @js($canManageTemplate),
            dibuatOleh: @js((int) ($revision?->dibuat_oleh ?? 0)),
             diperiksaOleh: @js((int) ($revision?->diperiksa_oleh ?? 0)),
             disahkanOleh: @js((int) ($revision?->disahkan_oleh ?? 0)),
             docType: @js($document->doc_type),
            isFormulir: @js(($document?->doc_type ?? '') === 'formulir'),
            initialSchema: @js(($document?->doc_type ?? '') === 'formulir'
                ? ($revision?->form_schema_json ?? data_get($revision?->template?->metadata, 'form_schema'))
                : null),
             initialAnswersJson: @js($revision?->answers_json ?? []),
             initialContent: @js($revision?->content_html ?? '<p></p>'),
             initialContentVersion: @js((int) ($revision?->content_version ?? 1)),
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
                    <p class="text-xs text-gray-500" aria-live="polite">Status simpan: <span x-text="saveStatusLabel()"></span></p>
                    <p
                        class="mt-1 text-xs text-amber-700"
                        aria-live="polite"
                        x-show="saveGuidanceMessage()"
                        x-cloak
                        x-text="saveGuidanceMessage()"
                    ></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        @click="toggleFocusMode()"
                    >
                        <span x-text="focusMode ? 'Tampilkan Panel Aksi' : 'Mode Fokus Menulis'"></span>
                    </button>
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
            <div class="col-span-12 transition-all" :class="focusMode ? 'lg:col-span-12' : 'lg:col-span-8'">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700" x-show="schemaQuestions().length > 0" x-cloak>
                        Editor menggunakan schema pertanyaan revisi (snapshot) dan disimpan sebagai answers_json.
                    </div>

                    <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4" x-show="isFormulir || schemaQuestions().length > 0" x-cloak>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900" x-text="isFormulir ? 'Isi Formulir' : 'Isi Dokumen Terstruktur'"></p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <span x-show="revisionStatus === 'draft' && hasLock" x-cloak>Bisa diedit hanya saat draft dan pemilik lock.</span>
                                    <span x-show="!(revisionStatus === 'draft' && hasLock)" x-cloak>Read-only (bukan draft atau tidak memegang lock).</span>
                                </p>
                            </div>
                        </div>

                        <div class="mt-4" x-show="schemaQuestions().length === 0" x-cloak>
                            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                Schema pertanyaan belum diatur untuk template dokumen ini. Gunakan editor di bawah sebagai fallback.
                            </div>
                        </div>

                        <div class="mt-4 space-y-4" x-show="schemaQuestions().length > 0" x-cloak>
                            <template x-for="(q, idx) in schemaQuestions()" :key="`fr-${q.id || idx}`">
                                <div class="rounded-lg border border-gray-200 bg-white p-4" :id="`q-wrap-${q.id}`">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-semibold text-gray-500" x-text="`Q${idx + 1}`"></p>
                                            <label class="mt-1 block text-sm font-semibold text-gray-900" :for="`q-${q.id}`">
                                                <span x-text="q.label || q.id"></span>
                                                <span class="ml-1 text-xs font-semibold text-red-600" x-show="q.required" x-cloak>*</span>
                                            </label>
                                        </div>
                                        <p class="text-[11px] text-gray-500" x-text="questionTypeLabel(q.type)"></p>
                                    </div>

                                    <div class="mt-3">
                                        <template x-if="q.type === 'section'">
                                            <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-900" x-text="(q.label || q.id).toUpperCase()"></div>
                                        </template>

                                        <template x-if="q.type === 'text' && isFormulir">
                                            <input
                                                :id="`q-${q.id}`"
                                                type="text"
                                                x-model.trim="answers[q.id]"
                                                @input="dirty = true; saveState = 'dirty'"
                                                :placeholder="q.placeholder || ''"
                                                :disabled="!hasLock || revisionStatus !== 'draft'"
                                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary-600 focus:ring-primary-600"
                                            />
                                        </template>

                                        <template x-if="q.type === 'text' && !isFormulir">
                                            <div
                                                class="rounded-xl border border-gray-200 bg-white p-3"
                                                x-data="qmhEditor({ initialContent: answerEditorInitialValue(q.id), editorId: `qmh-answer-${q.id}`, readOnly: !hasLock || revisionStatus !== 'draft' })"
                                                x-init="init()"
                                                @qmh-editor-change="onRichTextAnswerChange(q.id, $event.detail.html); dirty = true; saveState = 'dirty'"
                                            >
                                                <div class="mb-3 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('bold') }" @click="toggleBold()">B</button>
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('italic') }" @click="toggleItalic()">I</button>
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('underline') }" @click="toggleUnderline()">U</button>
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('bulletList') }" @click="toggleBulletList()">Bullets</button>
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('orderedList') }" @click="toggleOrderedList()">Number</button>
                                                </div>

                                                <div class="qmh-editor-surface qmh-editor-surface--compact" x-ref="editor"></div>
                                            </div>
                                        </template>

                                        <template x-if="q.type === 'textarea' && isFormulir">
                                            <textarea
                                                :id="`q-${q.id}`"
                                                rows="4"
                                                x-model="answers[q.id]"
                                                @input="dirty = true; saveState = 'dirty'"
                                                :placeholder="q.placeholder || ''"
                                                :disabled="!hasLock || revisionStatus !== 'draft'"
                                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary-600 focus:ring-primary-600"
                                            ></textarea>
                                        </template>

                                        <template x-if="q.type === 'textarea' && !isFormulir">
                                            <div
                                                class="rounded-xl border border-gray-200 bg-white p-3"
                                                x-data="qmhEditor({ initialContent: answerEditorInitialValue(q.id), editorId: `qmh-answer-${q.id}`, readOnly: !hasLock || revisionStatus !== 'draft' })"
                                                x-init="init()"
                                                @qmh-editor-change="onRichTextAnswerChange(q.id, $event.detail.html); dirty = true; saveState = 'dirty'"
                                            >
                                                <div class="mb-3 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('bold') }" @click="toggleBold()">B</button>
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('italic') }" @click="toggleItalic()">I</button>
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('underline') }" @click="toggleUnderline()">U</button>
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('bulletList') }" @click="toggleBulletList()">Bullets</button>
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('orderedList') }" @click="toggleOrderedList()">Number</button>
                                                </div>

                                                <div class="qmh-editor-surface qmh-editor-surface--compact" x-ref="editor"></div>
                                            </div>
                                        </template>

                                        <template x-if="q.type === 'list' && isFormulir">
                                            <textarea
                                                :id="`q-${q.id}`"
                                                rows="4"
                                                x-model="listAnswerText[q.id]"
                                                @input="syncListAnswer(q.id); dirty = true; saveState = 'dirty'"
                                                :placeholder="q.placeholder || 'Satu item per baris'"
                                                :disabled="!hasLock || revisionStatus !== 'draft'"
                                                class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs leading-relaxed focus:border-primary-600 focus:ring-primary-600"
                                            ></textarea>
                                        </template>

                                        <template x-if="q.type === 'list' && !isFormulir">
                                            <div
                                                class="rounded-xl border border-gray-200 bg-white p-3"
                                                x-data="qmhEditor({ initialContent: answerEditorInitialValue(q.id), editorId: `qmh-list-${q.id}`, readOnly: !hasLock || revisionStatus !== 'draft' })"
                                                x-init="init()"
                                                @qmh-editor-change="onRichTextListAnswerChange(q.id, $event.detail.html); dirty = true; saveState = 'dirty'"
                                            >
                                                <div class="mb-3 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('bulletList') }" @click="toggleBulletList()">Bullets</button>
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('orderedList') }" @click="toggleOrderedList()">Number</button>
                                                </div>

                                                <div class="qmh-editor-surface qmh-editor-surface--compact" x-ref="editor"></div>
                                            </div>
                                        </template>

                                        <template x-if="q.type !== 'list' && q.type !== 'text' && q.type !== 'textarea' && q.type !== 'section' && q.type !== 'select' && q.type !== 'checkbox' && q.type !== 'date' && q.type !== 'number' && !isFormulir">
                                            {{-- Fallback Rich Text for unknown types in non-formulir --}}
                                            <div
                                                class="rounded-xl border border-gray-200 bg-white p-3"
                                                x-data="qmhEditor({ initialContent: answerEditorInitialValue(q.id), editorId: `qmh-answer-${q.id}`, readOnly: !hasLock || revisionStatus !== 'draft' })"
                                                x-init="init()"
                                                @qmh-editor-change="onRichTextAnswerChange(q.id, $event.detail.html); dirty = true; saveState = 'dirty'"
                                            >
                                                <div class="mb-3 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('bold') }" @click="toggleBold()">B</button>
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('italic') }" @click="toggleItalic()">I</button>
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('underline') }" @click="toggleUnderline()">U</button>
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('bulletList') }" @click="toggleBulletList()">Bullets</button>
                                                    <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('orderedList') }" @click="toggleOrderedList()">Number</button>
                                                </div>

                                                <div class="qmh-editor-surface qmh-editor-surface--compact" x-ref="editor"></div>
                                            </div>
                                        </template>

                                        <template x-if="q.type === 'select'">
                                            <select
                                                :id="`q-${q.id}`"
                                                x-model="answers[q.id]"
                                                @change="dirty = true; saveState = 'dirty'"
                                                :disabled="!hasLock || revisionStatus !== 'draft'"
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
                                                <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600" x-model="answers[q.id]" @change="dirty = true; saveState = 'dirty'" :disabled="!hasLock || revisionStatus !== 'draft'" />
                                                <span x-text="q.placeholder || 'Ya / Tidak'"></span>
                                            </label>
                                        </template>

                                        <template x-if="q.type === 'date'">
                                            <input
                                                :id="`q-${q.id}`"
                                                type="date"
                                                x-model="answers[q.id]"
                                                @input="dirty = true; saveState = 'dirty'"
                                                :disabled="!hasLock || revisionStatus !== 'draft'"
                                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary-600 focus:ring-primary-600"
                                            />
                                        </template>

                                        <template x-if="q.type === 'number'">
                                            <input
                                                :id="`q-${q.id}`"
                                                type="number"
                                                inputmode="numeric"
                                                x-model="answers[q.id]"
                                                @input="dirty = true; saveState = 'dirty'"
                                                :placeholder="q.placeholder || ''"
                                                :disabled="!hasLock || revisionStatus !== 'draft'"
                                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary-600 focus:ring-primary-600"
                                            />
                                        </template>

                                        <template x-if="q.help">
                                            <p class="mt-1 text-xs text-gray-500" x-text="q.help"></p>
                                        </template>
                                    </div>

                                    <p class="mt-2 text-sm text-red-600" x-show="fieldErrors[q.id]" x-cloak x-text="fieldErrors[q.id]"></p>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div x-show="schemaQuestions().length === 0" x-cloak>
                        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" x-data="qmhEditor({ initialContent: @js($revision?->content_html ?? '<p></p>'), readOnly: !hasLock || revisionStatus !== 'draft' })" x-init="init()" @qmh-editor-change="onEditorChange($event.detail)">
                            <div class="mb-3 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('bold') }" @click="toggleBold()">B</button>
                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('italic') }" @click="toggleItalic()">I</button>
                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('underline') }" @click="toggleUnderline()">U</button>
                                @if($canManageTemplate)
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
                                @else
                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('bulletList') }" @click="toggleBulletList()">Bullets</button>
                                <button type="button" class="qmh-editor-btn" :class="{ 'is-active': isActive('orderedList') }" @click="toggleOrderedList()">Number</button>
                                @endif
                            </div>

                            <div class="qmh-editor-surface" x-ref="editor"></div>
                            <input type="hidden" x-ref="hiddenInput" name="content_html">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4" x-show="!focusMode" x-cloak>
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
                                @click="safeOpenSubmitModal()"
                                :disabled="!readyToSubmitStatus()"
                                :title="readyToSubmitStatus() ? '' : readyToSubmitDisabledReason()"
                                class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Submit untuk Review
                            </button>

                            <p x-show="!readyToSubmitStatus()" x-cloak class="text-xs text-gray-500" x-text="readyToSubmitDisabledReason()"></p>

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
                        <p class="mt-1 text-xs text-gray-500">Gunakan ini sebagai panduan cepat sebelum submit.</p>

                        <div class="mt-3 space-y-3 text-sm text-gray-700">
                            <div class="rounded-lg border px-3 py-2" :class="requiredAnswersMissingCount() === 0 ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'">
                                <p class="text-xs font-semibold">Jawaban wajib</p>
                                <p class="mt-1 text-xs" aria-live="polite" x-text="requiredAnswersStatusText()"></p>
                                <button type="button" class="mt-2 text-xs font-medium text-primary-700 underline" x-show="requiredAnswersMissingCount() > 0" x-cloak @click="safeJumpToFirstRequiredIssue()">Perbaiki sekarang</button>
                            </div>

                            <div class="rounded-lg border px-3 py-2" :class="previewChecked ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50'">
                                <p class="text-xs font-semibold">Review konten</p>
                                <p class="mt-1 text-xs" aria-live="polite" x-text="previewChecked ? 'Sudah cek preview dokumen.' : 'Belum cek preview. Buka preview sebelum submit.'"></p>
                                <button type="button" class="mt-2 text-xs font-medium text-primary-700 underline" x-show="!previewChecked" x-cloak @click="safeOpenPreviewModal()">Buka preview sekarang</button>
                            </div>

                            <div class="rounded-lg border px-3 py-2" :class="readyToSubmitStatus() ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50'">
                                <p class="text-xs font-semibold">Kesiapan submit</p>
                                <p class="mt-1 text-xs" aria-live="polite" x-text="readyToSubmitStatusText()"></p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-semibold text-gray-900">Preview</p>
                        <p class="mt-1 text-xs text-gray-500">Lihat ringkasan sebelum submit.</p>

                        <div class="mt-3">
                            <button type="button" class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="safeOpenPreviewModal()">
                                Buka Preview
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <div x-show="submitModal.open" x-cloak x-trap.noscroll="submitModal.open" class="fixed inset-0 z-pd-modal overflow-y-auto" role="dialog" aria-modal="true">
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

                    <div x-show="submitModal.error" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="submitModal.error"></div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" x-ref="submitModalCloseBtn" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700" @click="closeSubmitModal()">Batal</button>
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

    <div x-show="previewModalOpen" x-cloak x-trap.noscroll="previewModalOpen" class="fixed inset-0 z-pd-modal overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex min-h-dvh items-center justify-center px-4 py-8">
            <div class="fixed inset-0 bg-gray-900/50" @click="closePreviewModal()"></div>
            <div class="relative w-full max-w-5xl rounded-xl bg-white p-5 shadow-xl h-[85vh] flex flex-col" x-transition>
                <div class="flex items-start justify-between gap-3 border-b border-gray-200 pb-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Preview PDF</h3>
                        <p class="mt-1 text-sm text-gray-600">Preview lengkap dengan header, footer, dan signatories. (Effective Date akan muncul setelah dipublish)</p>
                    </div>
                    <button type="button" x-ref="previewModalCloseBtn" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="closePreviewModal()">Tutup</button>
                </div>

                <div class="mt-4 flex-1 overflow-hidden rounded-xl border border-gray-200 bg-gray-50">
                    <template x-if="pdfPreviewUrl">
                        <iframe :src="pdfPreviewUrl" class="h-full w-full border-0" title="PDF Preview"></iframe>
                    </template>
                    <template x-if="!pdfPreviewUrl && pdfPreviewLoading">
                        <div class="flex h-full items-center justify-center">
                            <p class="text-gray-500">Memuat preview PDF...</p>
                        </div>
                    </template>
                    <template x-if="!pdfPreviewUrl && !pdfPreviewLoading">
                        <div class="flex h-full items-center justify-center">
                            <p class="text-gray-500">Gagal memuat preview.</p>
                        </div>
                    </template>
                </div>
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
                users: Array.isArray(config.users) ? config.users : [],
                canManageTemplate: Boolean(config.canManageTemplate),
                dibuatOleh: config.dibuatOleh,
                diperiksaOleh: config.diperiksaOleh,
                disahkanOleh: config.disahkanOleh,
                docType: typeof config.docType === 'string' ? config.docType : '',
                isFormulir: Boolean(config.isFormulir),
                schema: config.initialSchema && typeof config.initialSchema === 'object' ? config.initialSchema : null,
                answers: config.initialAnswersJson && typeof config.initialAnswersJson === 'object' ? { ...config.initialAnswersJson } : {},
                listAnswerText: {},
                fieldErrors: {},
                firstInvalidQuestionId: '',
                showSchemaEditor: false,
                previewChecked: false,
                previewModalOpen: false,
                pdfPreviewUrl: null,
                pdfPreviewLoading: false,
                submitModal: { open: false, reviewerId: '', loading: false, error: '' },
                lastFocusedElement: null,
                focusMode: false,
                initialContent: typeof config.initialContent === 'string' ? config.initialContent : '<p></p>',
                contentVersion: Number.isFinite(Number(config.initialContentVersion)) ? Number(config.initialContentVersion) : 1,
                showUrl: config.showUrl,
                saveUrl: config.saveUrl,
                lockUrl: config.lockUrl,
                heartbeatUrl: config.heartbeatUrl,
                unlockUrl: config.unlockUrl,
                csrfToken: config.csrfToken,
                hasLock: false,
                heartbeatTimer: null,
                autoSaveTimer: null,
                saving: false,
                dirty: false,
                contentHtml: typeof config.initialContent === 'string' && config.initialContent.trim() !== '' ? config.initialContent : '<p></p>',
                editorJson: null,
                errorMessage: '',
                saveState: 'idle',
                lastSavedAt: '',

                init() {
                    if (!this.revisionId) {
                        this.errorMessage = 'Revisi dokumen tidak ditemukan.';
                        return;
                    }

                    if (!this.schema || typeof this.schema !== 'object') {
                        this.schema = null;
                    }

                    this.ensureSchemaFromAnswers();
                    this.normalizeAnswersPayload();

                    if (this.isFormulir) {
                        if (!this.schema || typeof this.schema !== 'object') {
                            this.schema = { version: 1, doc_type: 'fr', questions: [] };
                        }
                    }

                    if (this.schemaQuestions().length > 0) {
                        this.syncSchemaDefaults();
                    }

                    this.focusMode = false;
                    this.acquireLock();
                    this.registerBeforeUnload();
                },

                toggleFocusMode() {
                    this.focusMode = !this.focusMode;
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

                hasStructuredAnswers() {
                    if (!this.answers || typeof this.answers !== 'object') {
                        return false;
                    }

                    return Object.values(this.answers).some((value) => {
                        if (typeof value === 'string') {
                            return this.htmlToPlainText(value) !== '';
                        }

                        if (Array.isArray(value)) {
                            return value.some((item) => typeof item === 'string' && this.htmlToPlainText(item) !== '');
                        }

                        if (typeof value === 'number') {
                            return Number.isFinite(value);
                        }

                        if (typeof value === 'boolean') {
                            return value;
                        }

                        return false;
                    });
                },

                defaultSchemaForDocType() {
                    if (this.docType === 'ik') {
                        return {
                            version: 1,
                            doc_type: 'ik',
                            questions: [
                                { id: 'purpose', label: 'Tujuan', type: 'textarea', required: true },
                                { id: 'scope', label: 'Ruang Lingkup', type: 'textarea', required: true },
                                { id: 'responsibilities', label: 'Tanggung Jawab', type: 'textarea', required: false },
                                { id: 'reference', label: 'Acuan', type: 'textarea', required: false },
                                { id: 'instructions', label: 'Instruksi Kerja', type: 'textarea', required: true },
                                { id: 'required_docs', label: 'Dokumentasi Yang Diperlukan', type: 'list', required: false },
                                { id: 'closing', label: 'Penutup', type: 'textarea', required: false },
                            ],
                        };
                    }

                    if (this.docType !== 'sop') {
                        return null;
                    }

                    return {
                        version: 1,
                        doc_type: 'sop',
                        questions: [
                            { id: 'purpose', label: 'Tujuan', type: 'textarea', required: true },
                            { id: 'scope', label: 'Ruang Lingkup', type: 'textarea', required: true },
                            { id: 'definitions', label: 'Definisi', type: 'list', required: false },
                            { id: 'references', label: 'Referensi', type: 'list', required: false },
                            { id: 'procedure', label: 'Prosedur', type: 'textarea', required: true },
                            { id: 'records', label: 'Rekaman / Form Terkait', type: 'list', required: false },
                            { id: 'responsibilities', label: 'Tanggung Jawab', type: 'textarea', required: false },
                            { id: 'attachments', label: 'Lampiran', type: 'list', required: false },
                        ],
                    };
                },

                ensureSchemaFromAnswers() {
                    if (this.schemaQuestions().length > 0) {
                        return;
                    }

                    if (!this.hasStructuredAnswers()) {
                        return;
                    }

                    const fallback = this.defaultSchemaForDocType();
                    if (!fallback) {
                        return;
                    }

                    this.schema = fallback;
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
                    const normalized = this.stripXmlDeclaration(html);
                    const div = document.createElement('div');
                    div.innerHTML = normalized;
                    const txt = div.textContent || '';
                    return txt.replace(/\u00a0/g, ' ').trim();
                },

                stripXmlDeclaration(value) {
                    if (typeof value !== 'string') {
                        return '';
                    }

                    return value.replace(/^<\?xml[^>]*\?>\s*/i, '').trim();
                },

                looksLikeHtml(value) {
                    if (typeof value !== 'string') {
                        return false;
                    }

                    return /<\/?[a-z][\s\S]*>/i.test(value);
                },

                normalizePlainText(value) {
                    const raw = typeof value === 'string' ? value : '';
                    return this.stripXmlDeclaration(raw);
                },

                escapeHtml(value) {
                    return String(value ?? '')
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#39;');
                },

                normalizeEditorHtml(value) {
                    const raw = this.stripXmlDeclaration(typeof value === 'string' ? value : '');
                    const trimmed = raw.trim();

                    if (!trimmed) {
                        return '<p></p>';
                    }

                    return trimmed;
                },

                plainTextToEditorHtml(value) {
                    const normalized = this.normalizePlainText(value);

                    if (!normalized) {
                        return '<p></p>';
                    }

                    return `<p>${this.escapeHtml(normalized).replaceAll('\n', '<br>')}</p>`;
                },

                listToEditorHtml(value) {
                    const items = Array.isArray(value) ? value : [];
                    const normalizedItems = items
                        .map((item) => typeof item === 'string' ? this.stripXmlDeclaration(item) : String(item ?? ''))
                        .map((item) => item.trim())
                        .filter((item) => item !== '')
                        .map((item) => {
                            if (this.looksLikeHtml(item)) {
                                return item;
                            }

                            return `<p>${this.escapeHtml(item)}</p>`;
                        });

                    if (normalizedItems.length === 0) {
                        return '<p></p>';
                    }

                    return `<ul>${normalizedItems.map((item) => `<li>${item}</li>`).join('')}</ul>`;
                },

                answerEditorInitialValue(qid) {
                    const current = this.answers[qid];

                    if (Array.isArray(current)) {
                        return this.listToEditorHtml(current);
                    }

                    if (this.looksLikeHtml(current)) {
                        return this.normalizeEditorHtml(current);
                    }

                    return this.plainTextToEditorHtml(current);
                },

                onRichTextAnswerChange(qid, html) {
                    if (typeof qid !== 'string' || !qid) {
                        return;
                    }

                    this.answers[qid] = this.normalizeEditorHtml(html);
                },

                onRichTextListAnswerChange(qid, html) {
                    if (typeof qid !== 'string' || !qid) {
                        return;
                    }

                    const normalized = this.normalizeEditorHtml(html);
                    const listHtml = this.extractListContainerHtml(normalized);

                    if (listHtml) {
                        this.answers[qid] = listHtml;
                        this.listAnswerText[qid] = this.htmlToPlainText(listHtml);

                        return;
                    }

                    const plain = this.htmlToPlainText(normalized);
                    const items = plain
                        .split('\n')
                        .map((line) => line.trim())
                        .filter((line) => line !== '')
                        .map((line) => `<li><p>${this.escapeHtml(line)}</p></li>`)
                        .join('');

                    const fallback = items ? `<ul>${items}</ul>` : '<p></p>';

                    this.answers[qid] = fallback;
                    this.listAnswerText[qid] = this.htmlToPlainText(fallback);
                },

                extractListContainerHtml(html) {
                    if (typeof html !== 'string') {
                        return '';
                    }

                    const container = document.createElement('div');
                    container.innerHTML = this.stripXmlDeclaration(html);

                    const list = container.querySelector('ol, ul');
                    if (!list) {
                        return '';
                    }

                    return this.normalizeEditorHtml(list.outerHTML || '');
                },

                normalizeAnswersPayload() {
                    if (!this.answers || typeof this.answers !== 'object') {
                        this.answers = {};
                        return;
                    }

                    Object.keys(this.answers).forEach((key) => {
                        const val = this.answers[key];

                        if (typeof val === 'string') {
                            this.answers[key] = this.stripXmlDeclaration(val);
                            return;
                        }

                        if (Array.isArray(val)) {
                            this.answers[key] = val
                                .map((item) => typeof item === 'string' ? this.stripXmlDeclaration(item) : item)
                                .filter((item) => !(typeof item === 'string' && item.trim() === ''));
                        }
                    });
                },

                extractListItemsFromHtml(html) {
                    if (typeof html !== 'string' || !html.trim()) return [];
                    const div = document.createElement('div');
                    div.innerHTML = this.stripXmlDeclaration(html);
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
                            if (this.isFormulir) {
                                if (typeof existing === 'string') {
                                    const items = this.extractListItemsFromHtml(existing);
                                    this.answers[qid] = items;
                                    nextListText[qid] = items.join('\n');
                                    return;
                                }

                                const items = Array.isArray(existing)
                                    ? existing
                                          .filter((v) => typeof v === 'string')
                                          .map((v) => this.stripXmlDeclaration(v))
                                          .map((v) => v.trim())
                                          .filter((v) => v !== '')
                                    : [];
                                this.answers[qid] = items;
                                nextListText[qid] = items.join('\n');
                                return;
                            }

                            if (typeof existing === 'string') {
                                const normalizedHtml = this.normalizeEditorHtml(existing);
                                this.answers[qid] = normalizedHtml;
                                nextListText[qid] = this.htmlToPlainText(normalizedHtml);
                                return;
                            }

                            if (Array.isArray(existing)) {
                                const normalizedHtml = this.listToEditorHtml(existing);
                                this.answers[qid] = normalizedHtml;
                                nextListText[qid] = this.htmlToPlainText(normalizedHtml);
                                return;
                            }

                            this.answers[qid] = '<p></p>';
                            nextListText[qid] = '';
                            return;
                        }

                        const existing = this.answers[qid];
                        if (typeof existing === 'string') {
                            this.answers[qid] = this.stripXmlDeclaration(existing);
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
                            if (this.isFormulir) {
                                const items = Array.isArray(this.answers[qid]) ? this.answers[qid] : [];
                                if (isRequired && items.length === 0) {
                                    this.fieldErrors[qid] = 'Wajib diisi.';
                                }
                                return;
                            }

                            const val = this.answers[qid];
                            const hasContent = Array.isArray(val)
                                ? val.length > 0
                                : (typeof val === 'string' && this.htmlToPlainText(val) !== '');

                            if (isRequired && !hasContent) {
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

                    this.firstInvalidQuestionId = Object.keys(this.fieldErrors)[0] || '';

                    return Object.keys(this.fieldErrors).length === 0;
                },

                requiredAnswersMissingCount() {
                    this.validateFormAnswers();
                    return Object.keys(this.fieldErrors).length;
                },

                requiredAnswersStatusText() {
                    const missing = this.requiredAnswersMissingCount();
                    if (missing === 0) {
                        return 'Semua jawaban wajib sudah lengkap.';
                    }

                    return `${missing} jawaban wajib masih perlu dilengkapi.`;
                },

                jumpToFirstRequiredIssue() {
                    this.validateFormAnswers();

                    if (!this.firstInvalidQuestionId) {
                        return;
                    }

                    const directInput = document.getElementById(`q-${this.firstInvalidQuestionId}`);
                    const wrapper = document.getElementById(`q-wrap-${this.firstInvalidQuestionId}`);
                    const target = directInput || wrapper;

                    if (!target) {
                        return;
                    }

                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    if (directInput && typeof directInput.focus === 'function') {
                        directInput.focus({ preventScroll: true });
                    }
                },

                safeJumpToFirstRequiredIssue() {
                    if (typeof this.jumpToFirstRequiredIssue === 'function') {
                        this.jumpToFirstRequiredIssue();
                    }
                },

                readyToSubmitStatus() {
                    return this.canSubmitForReview
                        && this.hasLock
                        && this.requiredAnswersMissingCount() === 0
                        && this.previewChecked;
                },

                readyToSubmitDisabledReason() {
                    if (!this.canSubmitForReview) {
                        return this.submitReason || 'Submit belum tersedia.';
                    }

                    if (!this.hasLock) {
                        return 'Ambil lock terlebih dahulu untuk submit.';
                    }

                    if (this.requiredAnswersMissingCount() > 0) {
                        return 'Lengkapi jawaban wajib terlebih dahulu.';
                    }

                    if (!this.previewChecked) {
                        return 'Buka preview dokumen sebelum submit.';
                    }

                    return '';
                },

                readyToSubmitStatusText() {
                    if (!this.canSubmitForReview) {
                        return this.submitReason || 'Submit belum tersedia.';
                    }

                    if (!this.hasLock) {
                        return 'Ambil lock terlebih dahulu untuk submit.';
                    }

                    if (this.requiredAnswersMissingCount() > 0) {
                        return 'Lengkapi jawaban wajib terlebih dahulu.';
                    }

                    if (!this.previewChecked) {
                        return 'Lakukan review preview dokumen sebelum submit.';
                    }

                    return 'Dokumen siap diajukan untuk review.';
                },

                saveStatusLabel() {
                    if (this.saveState === 'saving') return 'Menyimpan...';
                    if (this.saveState === 'saved' && this.lastSavedAt) return `Tersimpan ${this.lastSavedAt}`;
                    if (this.saveState === 'saved') return 'Tersimpan';
                    if (this.saveState === 'dirty') return 'Belum tersimpan';
                    return 'Siap';
                },

                saveGuidanceMessage() {
                    if (this.saveState !== 'saved') {
                        return '';
                    }

                    const missing = this.requiredAnswersMissingCount();
                    if (missing > 0) {
                        return `Draft tersimpan. ${missing} jawaban wajib masih perlu dilengkapi sebelum submit.`;
                    }

                    if (!this.previewChecked) {
                        return 'Draft tersimpan. Buka preview dokumen sebelum submit.';
                    }

                    if (!this.readyToSubmitStatus()) {
                        return 'Draft tersimpan. Lengkapi syarat submit yang tersisa.';
                    }

                    return 'Draft tersimpan. Dokumen siap diajukan untuk review.';
                },

                openSubmitModal() {
                    if (!this.readyToSubmitStatus()) {
                        this.errorMessage = this.readyToSubmitDisabledReason();
                        if (this.requiredAnswersMissingCount() > 0) {
                            this.jumpToFirstRequiredIssue();
                        }

                        return;
                    }

                    this.lastFocusedElement = document.activeElement;
                    this.submitModal.open = true;
                    this.submitModal.loading = false;
                    this.submitModal.error = '';
                    this.submitModal.reviewerId = '';
                    this.$nextTick(() => {
                        this.$refs.submitModalCloseBtn?.focus();
                    });
                },

                safeOpenSubmitModal() {
                    if (typeof this.openSubmitModal === 'function') {
                        this.openSubmitModal();
                    }
                },

                closeSubmitModal() {
                    if (this.submitModal.loading) {
                        return;
                    }

                    this.submitModal.open = false;
                    this.submitModal.error = '';

                    if (this.lastFocusedElement && typeof this.lastFocusedElement.focus === 'function') {
                        this.$nextTick(() => {
                            this.lastFocusedElement.focus();
                            this.lastFocusedElement = null;
                        });
                    }
                },

                async submitForReview() {
                    if (!this.readyToSubmitStatus()) {
                        this.submitModal.error = this.readyToSubmitDisabledReason() || 'Dokumen belum siap diajukan.';
                        if (this.requiredAnswersMissingCount() > 0) {
                            this.jumpToFirstRequiredIssue();
                        }
                        return;
                    }

                    if (!this.validateFormAnswers()) {
                        this.submitModal.error = 'Masih ada jawaban wajib yang belum lengkap.';
                        this.jumpToFirstRequiredIssue();
                        return;
                    }

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
                    this.lastFocusedElement = document.activeElement;
                    this.previewModalOpen = true;
                    this.previewChecked = true;
                    this.pdfPreviewUrl = null;
                    this.pdfPreviewLoading = true;
                    this.$nextTick(() => {
                        this.$refs.previewModalCloseBtn?.focus();
                    });
                    this.loadPdfPreview();
                },

                safeOpenPreviewModal() {
                    if (typeof this.openPreviewModal === 'function') {
                        this.openPreviewModal();
                    }
                },

                closePreviewModal() {
                    this.previewModalOpen = false;
                    this.pdfPreviewUrl = null;

                    if (this.lastFocusedElement && typeof this.lastFocusedElement.focus === 'function') {
                        this.$nextTick(() => {
                            this.lastFocusedElement.focus();
                            this.lastFocusedElement = null;
                        });
                    }
                },

                async loadPdfPreview() {
                    this.pdfPreviewLoading = true;
                    try {
                        const hasSchema = this.schemaQuestions().length > 0;

                        const payload = {
                            doc_type: this.docType || '',
                            clause: this.clause,
                            doc_code: this.docCode,
                            title: this.documentTitle,
                            template_id: this.templateId ? Number(this.templateId) : null,
                            change_summary: null,
                            answers_json: { ...this.answers },
                            content_html: hasSchema ? null : (this.contentHtml || '<p></p>'),
                            dibuat_oleh: this.dibuatOleh,
                            diperiksa_oleh: this.diperiksaOleh,
                            disahkan_oleh: this.disahkanOleh,
                        };

                        const response = await fetch(`/api/quality/revisions/${this.revisionId}/preview/pdf`, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/pdf',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            body: JSON.stringify(payload),
                        });

                        if (!response.ok) {
                            throw new Error('Gagal memuat preview');
                        }

                        const blob = await response.blob();
                        this.pdfPreviewUrl = URL.createObjectURL(blob);
                    } catch (error) {
                        console.error(error);
                        this.pdfPreviewUrl = null;
                    } finally {
                        this.pdfPreviewLoading = false;
                    }
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
                        if (!this.hasLock) {
                            return;
                        }

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

                    this.startAutoSave();
                },

                stopHeartbeat() {
                    if (this.heartbeatTimer) {
                        window.clearInterval(this.heartbeatTimer);
                        this.heartbeatTimer = null;
                    }

                    this.stopAutoSave();
                },

                startAutoSave() {
                    this.stopAutoSave();
                    this.autoSaveTimer = window.setInterval(() => {
                        if (!this.hasLock || this.saving || !this.dirty) {
                            return;
                        }

                        this.saveNow();
                    }, 8000);
                },

                stopAutoSave() {
                    if (this.autoSaveTimer) {
                        window.clearInterval(this.autoSaveTimer);
                        this.autoSaveTimer = null;
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
                        const hasSchema = this.schemaQuestions().length > 0;

                        // Ensure list answers are synced from textareas.
                        if (hasSchema && this.isFormulir) {
                            this.schemaQuestions().forEach((q) => {
                                if (q?.type === 'list' && typeof q?.id === 'string') {
                                    this.syncListAnswer(q.id);
                                }
                            });
                        }

                        let payload;

                        if (hasSchema) {
                            payload = {
                                content_version: this.contentVersion,
                                answers_json: { ...this.answers },
                                dibuat_oleh: this.dibuatOleh,
                                diperiksa_oleh: this.diperiksaOleh,
                                disahkan_oleh: this.disahkanOleh,
                            };
                        } else {
                            payload = {
                                content_version: this.contentVersion,
                                content_html: this.contentHtml || '<p></p>',
                                content_css: null,
                                editor_json: this.editorJson,
                                dibuat_oleh: this.dibuatOleh,
                                diperiksa_oleh: this.diperiksaOleh,
                                disahkan_oleh: this.disahkanOleh,
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
                            if (extracted.conflict && Number.isFinite(Number(extracted.conflict.current_content_version))) {
                                this.contentVersion = Number(extracted.conflict.current_content_version);
                            }
                            if (this.isFormulir && extracted.errors) {
                                this.fieldErrors = extracted.errors;
                            }
                            this.saveState = 'dirty';
                            return;
                        }

                        const responseData = await response.json();

                        this.dirty = false;
                        this.saveState = 'saved';
                        const nextVersion = Number(responseData?.data?.content_version);
                        if (Number.isFinite(nextVersion) && nextVersion > 0) {
                            this.contentVersion = nextVersion;
                        }
                        this.lastSavedAt = new Intl.DateTimeFormat('id-ID', {
                            hour: '2-digit',
                            minute: '2-digit',
                        }).format(new Date());
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
                    const out = { message: fallback, errors: null, conflict: null };
                    try {
                        const payload = await response.json();
                        if (payload?.message) {
                            out.message = payload.message;
                        }

                        if (payload?.conflict) {
                            out.conflict = payload.conflict;
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
