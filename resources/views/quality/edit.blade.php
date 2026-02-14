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
                <a href="{{ route('quality.documents.edit-docx', $document) }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Office (Legacy)
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div
        class="space-y-4 sm:px-6 lg:px-8"
        x-data="qmhStructuredEditPage({
            revisionId: @js($revision?->id),
            showUrl: @js(route('quality.documents.show', $document)),
            templatesUrl: @js('/api/quality/templates'),
            docType: @js($document->doc_type === 'formulir' ? 'fr' : $document->doc_type),
            templateId: @js((int) ($revision?->template_id ?? 0)),
            docCode: @js((string) ($document->doc_code ?? '')),
            title: @js((string) ($document->title ?? '')),
            versionLabel: @js((string) ($revision?->version_label ?? '')),
            initialEffectiveDate: @js($revision?->effective_date?->format('Y-m-d') ?? ''),
            initialAnswersJson: @js($revision?->answers_json ?? []),
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
                    <h3 class="text-sm font-semibold text-gray-900" x-text="`${docCode} - ${versionLabel || '-'}`"></h3>
                    <p class="text-xs text-gray-500">Status simpan: <span x-text="saveStatusLabel()"></span></p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="goBack()" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Kembali
                    </button>
                    <button type="button" @click="saveNow()" :disabled="saving || !hasLock" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        <span x-text="saving ? 'Menyimpan...' : 'Simpan'"></span>
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-7" data-qmh-question-form>
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Pertanyaan Template</h3>
                            <p class="mt-1 text-xs text-gray-500">Edit jawaban terstruktur. Tidak ada editor Word-fidelity di halaman ini.</p>
                        </div>
                        <div class="text-right">
                            <label class="text-[11px] font-medium text-gray-700" for="effective_date_edit">Tgl. Efektif</label>
                            <input id="effective_date_edit" type="date" class="mt-1 w-full rounded-md border border-gray-300 text-xs focus:border-primary-600 focus:ring-primary-600" x-model="effectiveDate" @input="dirty = true; saveState = 'dirty'">
                        </div>
                    </div>

                    <div class="mt-4" x-show="templatesLoading">
                        <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">Memuat schema pertanyaan...</div>
                    </div>

                    <div class="mt-4" x-show="templatesError" x-cloak>
                        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="templatesError"></div>
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
                                                <label class="mt-1 block text-sm font-semibold text-gray-900" :for="`q-edit-${q.id}`">
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
                                                :id="`q-edit-${q.id}`"
                                                :placeholder="'Satu item per baris'"
                                                x-model="listAnswerText[q.id]"
                                                @input="syncListAnswer(q.id)"
                                            ></textarea>
                                            <p class="mt-1 text-xs text-gray-500">Tip: satu item per baris.</p>
                                        </div>

                                                <div class="mt-3" x-show="q.type !== 'list'">
                                                    <textarea
                                                        class="w-full rounded-md border border-gray-300 text-sm leading-relaxed focus:border-primary-600 focus:ring-primary-600"
                                                        rows="5"
                                                        :id="`q-edit-${q.id}`"
                                                        :placeholder="q.required ? 'Wajib diisi' : 'Opsional'"
                                                        x-model.trim="answers[q.id]"
                                                        @input="dirty = true; saveState = 'dirty'"
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
                                                    <td class="border border-gray-800 px-2 py-1" x-text="(versionLabel || 'E1-R0').replace('-', '/')"></td>
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
                                    <template x-for="(q, idx) in schemaQuestions()" :key="`pv-edit-${q.id}-${idx}`">
                                        <div>
                                            <p class="text-xs font-semibold text-gray-900" x-text="`${idx + 1}. ${q.label || q.id}`"></p>
                                            <template x-if="q.type === 'list'">
                                                <ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs text-gray-700">
                                                    <template x-for="(item, ii) in (Array.isArray(answers[q.id]) ? answers[q.id] : [])" :key="`li-edit-${q.id}-${ii}`">
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
    </div>

    @push('scripts')
    <script>
        function qmhStructuredEditPage(config) {
            return {
                revisionId: config.revisionId,
                showUrl: config.showUrl,
                templatesUrl: config.templatesUrl,
                docType: config.docType,
                templateId: Number(config.templateId || 0),
                docCode: config.docCode || "",
                title: config.title || "",
                versionLabel: config.versionLabel || "",
                effectiveDate: config.initialEffectiveDate || "",
                answers:
                    config.initialAnswersJson &&
                    typeof config.initialAnswersJson === "object" &&
                    !Array.isArray(config.initialAnswersJson)
                        ? { ...config.initialAnswersJson }
                        : {},
                listAnswerText: {},
                fieldErrors: {},

                templates: [],
                templatesLoading: false,
                templatesError: "",

                saveUrl: config.saveUrl,
                lockUrl: config.lockUrl,
                heartbeatUrl: config.heartbeatUrl,
                unlockUrl: config.unlockUrl,
                csrfToken: config.csrfToken,
                hasLock: false,
                heartbeatTimer: null,
                saving: false,
                dirty: false,
                errorMessage: '',
                saveState: 'idle',

                init() {
                    if (!this.revisionId) {
                        this.errorMessage = 'Revisi dokumen tidak ditemukan.';
                        return;
                    }

                    this.fetchTemplates();
                    this.acquireLock();
                    this.registerBeforeUnload();
                },

                saveStatusLabel() {
                    if (this.saveState === 'saving') return 'Menyimpan...';
                    if (this.saveState === 'saved') return 'Tersimpan';
                    if (this.saveState === 'dirty') return 'Belum tersimpan';
                    return 'Siap';
                },

                previewDocTypeLabel() {
                    if (this.docType === 'sop') return 'PROSEDUR';
                    if (this.docType === 'ik') return 'INSTRUKSI KERJA';
                    if (this.docType === 'fr') return 'FORMULIR';
                    return 'DOKUMEN';
                },

                selectedTemplate() {
                    return (
                        this.templates.find(
                            (item) => Number(item.id) === Number(this.templateId),
                        ) || null
                    );
                },

                schemaQuestions() {
                    const schema = this.selectedTemplate()?.form_schema || null;
                    const questions = schema?.questions;
                    return Array.isArray(questions) ? questions : [];
                },

                syncSchemaDefaults() {
                    this.fieldErrors = {};

                    const questions = this.schemaQuestions();
                    const nextListText = { ...this.listAnswerText };

                    questions.forEach((q) => {
                        const qid = typeof q?.id === 'string' ? q.id : '';
                        if (!qid) return;

                        if (q.type === 'list') {
                            const existing = this.answers[qid];
                            const items = Array.isArray(existing)
                                ? existing.filter(
                                      (val) =>
                                          typeof val === 'string' &&
                                          val.trim() !== '',
                                  )
                                : [];
                            this.answers[qid] = items;
                            nextListText[qid] = items.join('\n');
                            return;
                        }

                        const existing = this.answers[qid];
                        if (typeof existing !== 'string') {
                            this.answers[qid] = '';
                        }
                    });

                    this.listAnswerText = nextListText;
                },

                syncListAnswer(qid) {
                    const raw =
                        typeof this.listAnswerText[qid] === 'string'
                            ? this.listAnswerText[qid]
                            : '';
                    const items = raw
                        .split('\n')
                        .map((line) => line.trim())
                        .filter((line) => line !== '');
                    this.answers[qid] = items;
                    this.dirty = true;
                    this.saveState = 'dirty';
                },

                validateAnswers() {
                    this.fieldErrors = {};
                    const questions = this.schemaQuestions();

                    questions.forEach((q) => {
                        const qid = typeof q?.id === 'string' ? q.id : '';
                        if (!qid) return;
                        if (!q.required) return;

                        if (q.type === 'list') {
                            const items = Array.isArray(this.answers[qid])
                                ? this.answers[qid].filter(
                                      (val) =>
                                          typeof val === 'string' &&
                                          val.trim() !== '',
                                  )
                                : [];
                            if (items.length === 0) {
                                this.fieldErrors[qid] = 'Wajib diisi.';
                            }
                            return;
                        }

                        const val =
                            typeof this.answers[qid] === 'string'
                                ? this.answers[qid]
                                : '';
                        if (!val.trim()) {
                            this.fieldErrors[qid] = 'Wajib diisi.';
                        }
                    });

                    return Object.keys(this.fieldErrors).length === 0;
                },

                answerPayload() {
                    const out = {};
                    const questions = this.schemaQuestions();

                    questions.forEach((q) => {
                        const qid = typeof q?.id === 'string' ? q.id : '';
                        if (!qid) return;

                        if (q.type === 'list') {
                            const items = Array.isArray(this.answers[qid])
                                ? this.answers[qid]
                                      .filter(
                                          (val) =>
                                              typeof val === 'string' &&
                                              val.trim() !== '',
                                      )
                                      .map((val) => val.trim())
                                : [];
                            if (items.length > 0) {
                                out[qid] = items;
                            }
                            return;
                        }

                        const val =
                            typeof this.answers[qid] === 'string'
                                ? this.answers[qid]
                                : '';
                        if (val.trim()) {
                            out[qid] = val;
                        }
                    });

                    return out;
                },

                async fetchTemplates() {
                    if (!this.templatesUrl || !this.docType) {
                        return;
                    }

                    this.templatesLoading = true;
                    this.templatesError = '';

                    try {
                        const params = new URLSearchParams({
                            doc_type: this.docType,
                        });
                        const response = await fetch(
                            `${this.templatesUrl}?${params.toString()}`,
                            {
                                credentials: 'same-origin',
                                headers: { Accept: 'application/json' },
                            },
                        );

                        if (!response.ok) {
                            this.templates = [];
                            this.templatesError =
                                'Gagal memuat template. Silakan muat ulang halaman.';
                            return;
                        }

                        const payload = await response.json();
                        this.templates = Array.isArray(payload.data)
                            ? payload.data
                            : [];

                        const hasCurrent = this.templates.some(
                            (item) =>
                                Number(item.id) === Number(this.templateId),
                        );
                        if (!hasCurrent) {
                            this.templateId =
                                this.templates.length > 0
                                    ? Number(this.templates[0].id)
                                    : 0;
                        }

                        this.syncSchemaDefaults();
                    } catch {
                        this.templates = [];
                        this.templatesError =
                            'Terjadi gangguan jaringan saat memuat template.';
                    } finally {
                        this.templatesLoading = false;
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

                    if (!this.validateAnswers()) {
                        this.errorMessage =
                            'Lengkapi jawaban wajib sebelum menyimpan.';
                        return;
                    }

                    this.saving = true;
                    this.saveState = 'saving';
                    this.errorMessage = '';

                    try {
                        const response = await fetch(this.saveUrl, {
                            method: 'PUT',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            body: JSON.stringify({
                                answers_json: this.answerPayload(),
                                effective_date: this.effectiveDate || null,
                            }),
                        });

                        if (!response.ok) {
                            this.errorMessage = await this.extractErrorMessage(response, 'Gagal menyimpan konten dokumen.');
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
                    try {
                        const payload = await response.json();
                        if (payload?.message) {
                            return payload.message;
                        }

                        if (payload?.errors) {
                            const firstKey = Object.keys(payload.errors)[0];
                            if (firstKey && payload.errors[firstKey]?.length) {
                                return payload.errors[firstKey][0];
                            }
                        }
                    } catch (error) {
                    }

                    return fallback;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
