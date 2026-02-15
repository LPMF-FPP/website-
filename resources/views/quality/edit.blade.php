<x-app-layout>
    <x-slot name="header">
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
    </x-slot>

    <div
        class="space-y-4 sm:px-6 lg:px-8"
        x-data="qmhEditPage({
            revisionId: @js($revision?->id),
            docType: @js($document->doc_type),
            isFormulir: @js(($document?->doc_type ?? '') === 'formulir'),
            initialSchema: @js(data_get($revision?->template?->metadata, 'form_schema')),
            initialAnswersJson: @js($revision?->answers_json ?? []),
            initialContent: @js($revision?->content_html ?? '<p></p>'),
            showUrl: @js(route('quality.documents.show', $document)),
            saveUrl: @js($revision ? '/api/quality/revisions/'.$revision->id.'/content' : null),
            lockUrl: @js($revision ? '/api/quality/revisions/'.$revision->id.'/lock' : null),
            heartbeatUrl: @js($revision ? '/api/quality/revisions/'.$revision->id.'/heartbeat' : null),
            unlockUrl: @js($revision ? '/api/quality/revisions/'.$revision->id.'/unlock' : null),
            csrfToken: @js(csrf_token()),
        })"
        x-init="init()"
    >
        <div x-show="errorMessage" x-cloak class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="errorMessage"></div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ $document->doc_code }} - {{ $revision?->version_label ?? '-' }}</h3>
                    <p class="text-xs text-gray-500">Status simpan: <span x-text="saveStatusLabel()"></span></p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="goBack()" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Kembali
                    </button>
                    <button type="button" @click="saveNow()" :disabled="saving || !hasLock" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        <span x-text="saving ? 'Menyimpan...' : 'Simpan Draft'"></span>
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-12 lg:col-span-8">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    @if(($document?->doc_type ?? '') === 'formulir')
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                            Editor Formulir menggunakan schema pertanyaan template dan disimpan sebagai `answers_json`.
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
                                                <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" x-model="answers[q.id]" />
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
                <div class="sticky top-6 rounded-xl border border-blue-200 bg-blue-50 p-4 shadow-sm" x-data="qmhSidekick({ clause: @js($document->clause) })" x-init="init()">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="font-semibold text-blue-900">
                            <span class="mr-2">🤖</span> Sidekick ISO 17025
                        </h3>
                        <span class="rounded bg-blue-200 px-2 py-0.5 text-xs font-bold text-blue-800" x-text="'Klausul ' + clause"></span>
                    </div>

                    <div x-show="loading" class="text-sm text-blue-700 animate-pulse">Memuat tips...</div>

                    <div x-show="!loading && tips" x-transition>
                        <div class="mb-4 rounded-lg bg-white p-3 shadow-sm border border-blue-100">
                            <p class="text-sm text-gray-700 font-medium" x-text="tips.requirement"></p>
                        </div>

                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-blue-800">Checklist Wajib</h4>
                        <ul class="space-y-2">
                            <template x-for="(item, index) in tips.checklist" :key="index">
                                <li class="flex items-start gap-2 text-sm text-gray-700">
                                    <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span x-text="item"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function qmhSidekick(config) {
            return {
                clause: config.clause,
                loading: true,
                tips: null,

                init() {
                    this.fetchTips();
                },

                async fetchTips() {
                    try {
                        const res = await fetch(`/api/quality/dashboard/tips?clause=${this.clause}`);
                        if (res.ok) {
                            this.tips = await res.json();
                        }
                    } catch (e) {
                        console.error('Failed to load sidekick tips', e);
                    } finally {
                        this.loading = false;
                    }
                }
            };
        }

        function qmhEditPage(config) {
            return {
                revisionId: config.revisionId,
                docType: typeof config.docType === 'string' ? config.docType : '',
                isFormulir: Boolean(config.isFormulir),
                schema: config.initialSchema && typeof config.initialSchema === 'object' ? config.initialSchema : null,
                answers: config.initialAnswersJson && typeof config.initialAnswersJson === 'object' ? { ...config.initialAnswersJson } : {},
                listAnswerText: {},
                fieldErrors: {},
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
                            return { ok: true, data: await response.json() };
                        }

                        return { ok: false, message: await this.extractErrorMessage(response, 'Permintaan gagal diproses.') };
                    } catch (error) {
                        return { ok: false, message: 'Terjadi gangguan jaringan.' };
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
