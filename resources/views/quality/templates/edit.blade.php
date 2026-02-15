<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Edit Template QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Template QMH', 'route' => 'quality.templates.index'],
                    ['label' => 'Edit Template'],
                ]"
            />

            <x-qmh-subnav active="templates" />
        </div>
    </x-slot>

    <div class="space-y-6">
        @if($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-medium">Terjadi kesalahan validasi:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('quality.templates.update', $template) }}" enctype="multipart/form-data" class="space-y-4" x-data="{ selectedDocType: @js(old('doc_type', $template->doc_type)) }">
                @csrf
                @method('PATCH')

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="name">Nama Template</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $template->name) }}"
                           class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('name') border-red-400 @else border-gray-300 @enderror"
                           required>
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="doc_type">Jenis Dokumen</label>
                    <select id="doc_type" name="doc_type" x-model="selectedDocType" class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('doc_type') border-red-400 @else border-gray-300 @enderror" required>
                        <option value="sop" @selected(old('doc_type', $template->doc_type) === 'sop')>SOP</option>
                        <option value="ik" @selected(old('doc_type', $template->doc_type) === 'ik')>IK</option>
                        <option value="fr" @selected(old('doc_type', $template->doc_type) === 'fr')>FR</option>
                    </select>
                    @error('doc_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="file">Ganti File DOCX (Opsional)</label>
                    <input id="file" name="file" type="file" accept=".docx"
                           class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('file') border-red-400 @else border-gray-300 @enderror">
                    <p class="mt-1 text-xs text-gray-500">Kosongkan jika tidak ingin mengganti file.</p>
                    @error('file')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="version_notes">Catatan</label>
                    <textarea id="version_notes" name="version_notes" rows="3"
                              class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('version_notes') border-red-400 @else border-gray-300 @enderror">{{ old('version_notes', data_get($template->metadata, 'version_notes')) }}</textarea>
                    @error('version_notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Edit Template di Browser</label>
                    <p class="mb-2 text-xs text-gray-500">Konten ini akan dimuat otomatis saat user memilih template pada form Buat Dokumen.</p>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
                         x-data="qmhEditor({ initialContent: @js(old('content_html', data_get($template->metadata, 'content_html', '<p></p>'))), editorId: 'qmh-template-editor' })"
                         x-init="init()"
                         @qmh-editor-change="$refs.templateContentHtml.value = $event.detail.html">
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
                        <input type="hidden" x-ref="hiddenInput" name="unused_template_editor_content">
                        <input type="hidden" x-ref="templateContentHtml" name="content_html" value="{{ old('content_html', data_get($template->metadata, 'content_html', '<p></p>')) }}">
                    </div>
                    @error('content_html')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="form_schema_json">Schema Pertanyaan (JSON)</label>
                    <p class="mb-2 text-xs text-gray-500">Gunakan Form Builder untuk mengatur schema pertanyaan. JSON tetap disimpan sebagai output untuk kompatibilitas.</p>

                    @php
                        $initialSchema = data_get($template->metadata, 'form_schema');
                        $initialJson = old('form_schema_json', $initialSchema ? json_encode($initialSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '');
                    @endphp

                    <template x-if="selectedDocType === 'fr'">
                        <div
                            class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
                            x-data="qmhFormBuilder({
                                docType: selectedDocType,
                                initialSchema: @js($initialSchema),
                                initialJson: @js($initialJson),
                            })"
                            x-init="init()"
                            @input.debounce.100ms="syncJson()"
                            @change.debounce.100ms="syncJson()"
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
                            >{{ $initialJson }}</textarea>

                            <template x-if="showRawJson">
                                <div class="mt-4">
                                    <div class="mb-2 text-xs font-semibold text-gray-700">JSON Preview</div>
                                    <pre class="max-h-80 overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-3 text-[11px] leading-relaxed" x-text="schemaJson()"></pre>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="selectedDocType !== 'fr'">
                        <div>
                            <textarea
                                id="form_schema_json"
                                name="form_schema_json"
                                rows="10"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs leading-relaxed focus:border-primary-600 focus:ring-primary-600"
                            >{{ $initialJson }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">(Non-FR) Edit JSON langsung.</p>
                        </div>
                    </template>

                    @error('form_schema_json')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center justify-between border-t border-gray-200 pt-4">
                    <a href="{{ route('quality.templates.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Batal
                    </a>
                    <button type="submit" class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
