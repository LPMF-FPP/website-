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

                    this.acquireLock();
                    this.registerBeforeUnload();
                },

                onEditorChange(detail) {
                    this.contentHtml = detail.html || '';
                    this.editorJson = detail.editor_json || null;
                    this.dirty = true;
                    this.saveState = 'dirty';
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
                        const response = await fetch(this.saveUrl, {
                            method: 'PUT',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            body: JSON.stringify({
                                content_html: this.contentHtml || '<p></p>',
                                content_css: null,
                                editor_json: this.editorJson,
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
