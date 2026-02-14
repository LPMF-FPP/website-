<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="QMH Office Editor"
            :breadcrumbs="[
                ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                ['label' => 'Office Editor'],
            ]"
        />
    </x-slot>

    <div class="space-y-4 sm:px-6 lg:px-8">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ $revision->document?->doc_code ?? 'QMH' }} - {{ $revision->version_label }}</h3>
                    <p class="text-xs text-gray-500">Editor embed untuk autosave Office.</p>
                </div>
                <a href="{{ route('quality.documents.show', $revision->document_id) }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Kembali
                </a>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" x-data="qmhOfficeEmbedPage({
            revisionId: @js($revision->id),
            token: @js($token),
            callbackUrl: @js('/api/quality/revisions/'.$revision->id.'/office-callback'),
            csrfToken: @js(csrf_token()),
        })" x-init="init()">
            <div x-show="errorMessage" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="errorMessage"></div>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-3">
                <p class="text-xs text-gray-500">Autosave: <span x-text="saveLabel()"></span></p>
                <p class="text-xs text-gray-500" x-show="lastSavedAt" x-text="`Tersimpan: ${lastSavedAt}`"></p>
            </div>

            <div x-data="qmhEditor({ initialContent: @js($revision->content_html ?? '<p></p>') })" x-init="init()" @qmh-editor-change="onEditorChange($event.detail)">
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

    @push('scripts')
    <script>
        function qmhOfficeEmbedPage(config) {
            return {
                revisionId: config.revisionId,
                token: config.token,
                callbackUrl: config.callbackUrl,
                csrfToken: config.csrfToken,
                errorMessage: '',
                saveState: 'idle',
                lastSavedAt: '',
                pendingTimer: null,
                contentHtml: '',
                editorJson: null,

                init() {
                    if (!this.revisionId || !this.callbackUrl || !this.token) {
                        this.errorMessage = 'Konfigurasi Office tidak lengkap.';
                    }
                },

                saveLabel() {
                    if (this.saveState === 'saving') return 'Menyimpan...';
                    if (this.saveState === 'saved') return 'Tersimpan';
                    if (this.saveState === 'dirty') return 'Belum tersimpan';
                    return 'Siap';
                },

                onEditorChange(detail) {
                    this.contentHtml = detail.html || '';
                    this.editorJson = detail.editor_json || null;
                    this.saveState = 'dirty';
                    this.queueAutosave();
                },

                queueAutosave() {
                    if (!this.callbackUrl) return;

                    if (this.pendingTimer) {
                        window.clearTimeout(this.pendingTimer);
                    }

                    this.pendingTimer = window.setTimeout(() => {
                        this.saveNow();
                    }, 1200);
                },

                async saveNow() {
                    if (!this.callbackUrl) return;
                    if (this.saveState !== 'dirty') return;

                    this.saveState = 'saving';
                    this.errorMessage = '';

                    const result = await this.apiPost(this.callbackUrl, {
                        status: 2,
                        token: this.token,
                        content_html: this.contentHtml || '<p></p>',
                        editor_json: this.editorJson,
                    });

                    if (!result.ok) {
                        this.saveState = 'dirty';
                        this.errorMessage = result.message || 'Gagal autosave Office.';
                        return;
                    }

                    this.saveState = 'saved';
                    this.lastSavedAt = new Date().toLocaleString('id-ID', { hour12: false });
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
                    } catch (error) {
                    }

                    return fallback;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
