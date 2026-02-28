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

    @php
        $rawLayoutMetadata = is_array($template->metadata) ? $template->metadata : [];
        $layoutMeta = \App\Support\QmhFrLayoutProfile::fromMetadata($rawLayoutMetadata);
        $hasExplicitLayoutProfile = array_key_exists('layout_profile', $rawLayoutMetadata);
        $layoutProfileInput = old('layout_profile', $hasExplicitLayoutProfile ? $layoutMeta['layout_profile'] : '');
        $riskMatrixColumnsCsv = implode(', ', is_array($layoutMeta['risk_matrix_columns'] ?? null) ? $layoutMeta['risk_matrix_columns'] : []);
        $initialSchema = data_get($template->metadata, 'form_schema');
        $initialJson = old('form_schema_json', $initialSchema ? json_encode($initialSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '');
    @endphp

    <div
        class="space-y-6"
        x-data="qmhTemplateEditor({
            templateId: @js((int) $template->id),
            templateName: @js((string) $template->name),
            docType: @js((string) strtoupper($template->doc_type)),
            versionLabel: @js('v' . (int) $template->version),
            updateUrl: @js(route('quality.templates.update', $template)),
            csrfToken: @js(csrf_token()),
            initialContent: @js(old('content_html', $resolvedContentHtml ?? '<p></p>')),
            initialVersions: @js($relatedVersions),
            latestPreviewUrl: @js(route('quality.templates.preview', $template)),
        })"
        x-init="init()"
    >
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

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <template x-for="tpl in versions" :key="tpl.id">
                    <button
                        type="button"
                        @click="goToVersion(tpl.edit_url)"
                        :class="selectedTemplateId === tpl.id ? 'ring-2 ring-primary-500 border-primary-400' : 'border-gray-200 hover:border-primary-300'"
                        class="relative rounded-lg border-2 bg-white px-4 py-4 text-left shadow-sm transition-all"
                    >
                        <div class="flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900" x-text="tpl.name"></p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <span x-text="docType"></span>
                                    •
                                    <span x-text="tpl.updated_at_label"></span>
                                </p>
                                <p class="mt-1 text-xs text-gray-600">Versi <span x-text="tpl.version"></span></p>
                            </div>
                            <div x-show="selectedTemplateId === tpl.id" class="ml-3">
                                <svg class="h-5 w-5 text-primary-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>
                        <span
                            x-show="tpl.is_active"
                            class="mt-2 inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-[11px] font-medium text-green-700"
                        >Active</span>
                    </button>
                </template>
            </div>
        </div>

        <form
            id="qmh-template-edit-form"
            method="POST"
            action="{{ route('quality.templates.update', $template) }}"
            class="space-y-4"
            x-ref="form"
            x-data="{
                selectedDocType: @js(old('doc_type', $template->doc_type)),
                layoutProfile: @js($layoutProfileInput),
                logoSource: @js(old('logo_source', $layoutMeta['logo_source'])),
                advancedSchemaMode: false
            }"
        >
            @csrf
            @method('PATCH')

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between border-b border-gray-200 pb-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="saveTemplate()"
                            :disabled="saving || !hasChanges"
                            class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <svg x-show="!saving" class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                            </svg>
                            <svg x-show="saving" class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="saving ? 'Menyimpan...' : 'Simpan'"></span>
                        </button>

                        <button
                            type="button"
                            @click="showPreview = true"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >Preview</button>

                        <button
                            type="button"
                            @click="showHistory = true"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >Riwayat</button>

                        <button
                            type="button"
                            @click="revertChanges()"
                            :disabled="!hasChanges"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >Batal</button>
                    </div>

                    <div class="text-xs text-gray-500">
                        <span x-show="hasChanges" class="font-semibold text-amber-700">• Belum disimpan</span>
                        <span class="ml-3">Baris: <span x-text="editorInfo.line"></span> | Kolom: <span x-text="editorInfo.column"></span></span>
                    </div>
                </div>

                <div class="mb-4 grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="name">Nama Template</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $template->name) }}"
                               class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('name') border-red-400 @else border-gray-300 @enderror"
                               @input="onMetaChanged()"
                               required>
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="doc_type">Jenis Dokumen</label>
                        <select id="doc_type" name="doc_type" x-model="selectedDocType"
                                class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('doc_type') border-red-400 @else border-gray-300 @enderror"
                                @change="onMetaChanged()"
                                required>
                            <option value="sop" @selected(old('doc_type', $template->doc_type) === 'sop')>SOP</option>
                            <option value="ik" @selected(old('doc_type', $template->doc_type) === 'ik')>IK</option>
                            <option value="fr" @selected(old('doc_type', $template->doc_type) === 'fr')>FR</option>
                        </select>
                        @error('doc_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="clause">Klausul</label>
                        <select id="clause" name="clause"
                                class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('clause') border-red-400 @else border-gray-300 @enderror"
                                @change="onMetaChanged()"
                                required>
                            @foreach([4, 5, 6, 7, 8] as $clause)
                                <option value="{{ $clause }}" @selected((string) old('clause', (string) $template->clause) === (string) $clause)>{{ $clause }}</option>
                            @endforeach
                        </select>
                        @error('clause')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="version_notes">Catatan</label>
                    <textarea id="version_notes" name="version_notes" rows="2"
                              class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('version_notes') border-red-400 @else border-gray-300 @enderror"
                              @input="onMetaChanged()">{{ old('version_notes', data_get($template->metadata, 'version_notes')) }}</textarea>
                    @error('version_notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <template x-if="selectedDocType === 'fr'">
                    <div class="mb-4 rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
                        <p class="text-sm font-semibold text-gray-800">Konfigurasi Layout FR</p>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700" for="layout_profile">Profil Layout</label>
                                <select id="layout_profile" name="layout_profile" x-model="layoutProfile" class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('layout_profile') border-red-400 @else border-gray-300 @enderror" @change="onMetaChanged()">
                                    @if(! $hasExplicitLayoutProfile)
                                        <option value="">Legacy (tanpa profile eksplisit)</option>
                                    @endif
                                    <option value="structured_form">Structured Form</option>
                                    <option value="risk_matrix">Risk Matrix</option>
                                    <option value="declaration">Declaration</option>
                                </select>
                                @error('layout_profile')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700" for="logo_source">Sumber Logo</label>
                                <select id="logo_source" name="logo_source" x-model="logoSource" class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('logo_source') border-red-400 @else border-gray-300 @enderror" @change="onMetaChanged()">
                                    <option value="settings">Settings Sistem</option>
                                    <option value="custom">Custom Path</option>
                                    <option value="default">Default Aset</option>
                                </select>
                                @error('logo_source')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div x-show="logoSource === 'custom'">
                            <label class="mb-1 block text-sm font-medium text-gray-700" for="logo_path">Path Logo Custom</label>
                            <input id="logo_path" name="logo_path" type="text" value="{{ old('logo_path', $layoutMeta['logo_path']) }}"
                                   class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('logo_path') border-red-400 @else border-gray-300 @enderror"
                                   @input="onMetaChanged()"
                                   placeholder="contoh: images/logo-custom.png atau storage/logo/custom.png">
                            @error('logo_path')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700" for="declaration_header">Header Declaration (opsional)</label>
                            <input id="declaration_header" name="declaration_header" type="text" value="{{ old('declaration_header', $layoutMeta['declaration_header']) }}"
                                   class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('declaration_header') border-red-400 @else border-gray-300 @enderror"
                                   @input="onMetaChanged()"
                                   placeholder="contoh: Pernyataan Ketidakberpihakan">
                            @error('declaration_header')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div x-show="layoutProfile === 'risk_matrix'">
                            <label class="mb-1 block text-sm font-medium text-gray-700" for="risk_matrix_columns_csv">Kolom Risk Matrix</label>
                            <input id="risk_matrix_columns_csv" name="risk_matrix_columns_csv" type="text" value="{{ old('risk_matrix_columns_csv', $riskMatrixColumnsCsv) }}"
                                   class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('risk_matrix_columns_csv') border-red-400 @else border-gray-300 @enderror"
                                   @input="onMetaChanged()"
                                   placeholder="Aspek Risiko, Nilai Risiko, Keterangan">
                            @error('risk_matrix_columns_csv')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </template>

                <div
                    class="rounded-lg border border-gray-200"
                    x-data="qmhEditor({ initialContent: @js(old('content_html', $resolvedContentHtml ?? '<p></p>')), editorId: 'qmh-template-editor' })"
                    x-init="init()"
                    @qmh-editor-change="onEditorChanged($event.detail.html)"
                    @qmh-editor-cursor.window="editorInfo = { line: Number($event.detail?.line || 1), column: Number($event.detail?.column || 1) }"
                >
                    <div class="border-b border-gray-200 bg-gray-50 p-3">
                        <div class="flex flex-wrap gap-2">
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
                            <button type="button" class="qmh-editor-btn" @click="openPendukungPicker({ clause: Number(document.getElementById('clause')?.value || 4) })">Link Pendukung</button>
                        </div>
                    </div>

                    <div class="qmh-editor-surface p-4" x-ref="editor"></div>
                    <input type="hidden" x-ref="hiddenInput" name="unused_template_editor_content">
                    <input type="hidden" x-ref="templateContentHtml" name="content_html" value="{{ old('content_html', $resolvedContentHtml ?? '<p></p>') }}">
                </div>
                @error('content_html')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

                <div class="mt-5">
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="form_schema_json">Struktur Pertanyaan Formulir</label>
                    <p class="mb-2 text-xs text-gray-500">Mode standar disederhanakan agar mudah dipakai user non-teknis. Opsi lanjutan hanya untuk admin teknis.</p>

                    <div class="mb-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600" x-model="advancedSchemaMode" @change="onMetaChanged()">
                            <span>Tampilkan mode lanjutan (ID field, JSON, dan pengaturan teknis)</span>
                        </label>
                    </div>

                    <template x-if="selectedDocType === 'fr'">
                        <div
                            class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
                            x-data="qmhFormBuilder({
                                docType: selectedDocType,
                                initialSchema: @js($initialSchema),
                                initialJson: @js($initialJson),
                            })"
                            x-init="init()"
                            @input.debounce.100ms="syncJson(); $dispatch('qmh-meta-change')"
                            @change.debounce.100ms="syncJson(); $dispatch('qmh-meta-change')"
                        >
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 pb-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-700" @click="addQuestion('text')">
                                        + Pertanyaan
                                    </button>
                                    <button type="button" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="addQuestion('section')">
                                        + Section
                                    </button>
                                    <button type="button" x-show="advancedSchemaMode" x-cloak class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="showRawJson = !showRawJson">
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
                                                        <input type="text" class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm" x-model="q.label" @input.debounce.150ms="onLabelChanged(idx)" placeholder="Contoh: Nama Petugas" />
                                                    </div>

                                                    <div class="sm:col-span-3">
                                                        <label class="block text-[11px] font-semibold text-gray-600">Format Jawaban</label>
                                                        <select class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm" x-model="q.type" @change="onTypeChanged(idx)">
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

                                                    <div class="sm:col-span-3" x-show="advancedSchemaMode" x-cloak>
                                                        <label class="block text-[11px] font-semibold text-gray-600">ID</label>
                                                        <input type="text" class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 font-mono text-xs" x-model="q.id" @input.debounce.150ms="q.auto_id = false; syncJson()" placeholder="field_name" />
                                                    </div>

                                                    <div class="sm:col-span-1">
                                                        <label class="block text-[11px] font-semibold text-gray-600">Wajib</label>
                                                        <input type="checkbox" class="mt-3 h-4 w-4 rounded border-gray-300 text-primary-600" x-model="q.required" :disabled="q.type === 'section'" @change="syncJson()" />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex flex-col items-end gap-2">
                                                <div class="flex gap-1">
                                                    <button type="button" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 hover:bg-gray-50" @click="moveUp(idx)" :disabled="idx === 0">Up</button>
                                                    <button type="button" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 hover:bg-gray-50" @click="moveDown(idx)" :disabled="idx === questions.length - 1">Down</button>
                                                </div>
                                                <button type="button" class="rounded-md border border-red-200 bg-red-50 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-100" @click="deleteQuestion(idx)">Hapus</button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <textarea id="form_schema_json" name="form_schema_json" rows="10" class="hidden" x-ref="schemaJson">{{ $initialJson }}</textarea>
                        </div>
                    </template>

                    <template x-if="selectedDocType !== 'fr'">
                        <div>
                            <textarea id="form_schema_json" name="form_schema_json" rows="10" class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs leading-relaxed focus:border-primary-600 focus:ring-primary-600" @input="onMetaChanged()">{{ $initialJson }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">(Non-FR) Edit JSON langsung.</p>
                        </div>
                    </template>

                    @error('form_schema_json')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </form>

        <div
            x-show="showPreview"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto"
            @keydown.escape.window="showPreview = false"
        >
            <div class="flex min-h-screen items-center justify-center px-4 py-6">
                <div class="fixed inset-0 bg-gray-900/50" @click="showPreview = false"></div>

                <div class="relative z-10 w-full max-w-6xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Preview Template</h3>
                            <p class="text-xs text-gray-500"><span x-text="templateName"></span> • <span x-text="docType"></span> • <span x-text="versionLabel"></span> (draft)</p>
                        </div>
                        <button type="button" class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="showPreview = false">Tutup</button>
                    </div>

                    <div class="max-h-[75vh] overflow-auto bg-gray-50 p-4">
                        <iframe class="h-[65vh] w-full rounded-lg border border-gray-200 bg-white" sandbox="" :srcdoc="currentContent" title="Preview Template QMH"></iframe>
                    </div>
                </div>
            </div>
        </div>

        <div
            x-show="showHistory"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto"
            @keydown.escape.window="showHistory = false"
        >
            <div class="flex min-h-screen items-center justify-center px-4 py-6">
                <div class="fixed inset-0 bg-gray-900/50" @click="showHistory = false"></div>

                <div class="relative z-10 w-full max-w-3xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                        <h3 class="text-base font-semibold text-gray-900">Riwayat Versi Template</h3>
                        <button type="button" class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="showHistory = false">Tutup</button>
                    </div>

                    <div class="max-h-[70vh] overflow-auto p-4">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Versi</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Updated</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Status</th>
                                    <th class="px-3 py-2 text-right font-semibold text-gray-700">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="tpl in versions" :key="tpl.id">
                                    <tr>
                                        <td class="px-3 py-2 text-gray-900">v<span x-text="tpl.version"></span></td>
                                        <td class="px-3 py-2 text-gray-700" x-text="tpl.updated_at_label"></td>
                                        <td class="px-3 py-2 text-gray-700">
                                            <span x-show="tpl.is_active" class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Active</span>
                                            <span x-show="!tpl.is_active" class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button" class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="goToVersion(tpl.edit_url)">Buka</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="notification.show" x-transition class="fixed bottom-4 right-4 z-50 max-w-sm" @click="notification.show = false">
            <div
                class="rounded-lg border p-4 shadow-lg"
                :class="notification.type === 'success' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800'"
            >
                <p class="text-sm font-medium" x-text="notification.message"></p>
            </div>
        </div>
    </div>

    @include('partials.qmh-pendukung-picker')

    @push('scripts')
    <script>
        function qmhTemplateEditor(config) {
            return {
                selectedTemplateId: Number(config.templateId || 0),
                templateName: String(config.templateName || ''),
                docType: String(config.docType || ''),
                versionLabel: String(config.versionLabel || ''),
                updateUrl: String(config.updateUrl || ''),
                csrfToken: String(config.csrfToken || ''),
                versions: Array.isArray(config.initialVersions) ? config.initialVersions : [],
                latestPreviewUrl: String(config.latestPreviewUrl || ''),
                currentContent: String(config.initialContent || '<p></p>'),
                originalContent: String(config.initialContent || '<p></p>'),
                hasChanges: false,
                saving: false,
                showPreview: false,
                showHistory: false,
                editorInfo: { line: 1, column: 1 },
                notification: { show: false, type: 'success', message: '' },

                init() {
                    window.addEventListener('qmh-meta-change', () => {
                        this.hasChanges = true;
                    });
                },

                onEditorChanged(html) {
                    this.currentContent = String(html || '<p></p>');
                    this.hasChanges = this.normalizeHtml(this.currentContent) !== this.normalizeHtml(this.originalContent);
                },

                onMetaChanged() {
                    this.hasChanges = true;
                },

                normalizeHtml(value) {
                    return String(value || '').replace(/\s+/g, ' ').trim();
                },

                async saveTemplate() {
                    if (this.saving) return;

                    const form = document.getElementById('qmh-template-edit-form');
                    if (!form) return;

                    this.saving = true;

                    try {
                        const formData = new FormData(form);
                        formData.set('_method', 'PATCH');

                        const response = await fetch(this.updateUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json, text/html',
                            },
                            body: formData,
                        });

                        const contentType = response.headers.get('content-type') || '';
                        if (response.ok) {
                            this.showNotification('success', 'Template berhasil disimpan sebagai versi baru. Membuka versi terbaru...');
                            if (contentType.includes('application/json')) {
                                const data = await response.json().catch(() => null);
                                const nextUrl = data?.redirect_url || data?.redirect || this.latestPreviewUrl;
                                if (typeof nextUrl === 'string' && nextUrl.length > 0) {
                                    window.location.assign(nextUrl.replace('/preview', '/edit'));
                                    return;
                                }
                            }

                            const redirectedTo = response.url || '';
                            if (redirectedTo) {
                                window.location.assign(redirectedTo);
                                return;
                            }

                            form.submit();
                            return;
                        }

                        if (contentType.includes('application/json')) {
                            const payload = await response.json().catch(() => null);
                            const message = payload?.message || 'Gagal menyimpan template.';
                            this.showNotification('error', message);
                        } else {
                            this.showNotification('error', 'Gagal menyimpan template. Silakan cek form lalu coba lagi.');
                        }
                    } catch (error) {
                        this.showNotification('error', 'Gagal menyimpan template: ' + (error?.message || 'unknown error'));
                    } finally {
                        this.saving = false;
                    }
                },

                revertChanges() {
                    window.location.reload();
                },

                goToVersion(url) {
                    if (this.hasChanges) {
                        if (!window.confirm('Ada perubahan yang belum disimpan. Tetap pindah versi?')) {
                            return;
                        }
                    }

                    window.location.assign(url);
                },

                showNotification(type, message) {
                    this.notification = {
                        show: true,
                        type,
                        message,
                    };

                    window.setTimeout(() => {
                        this.notification.show = false;
                    }, 5000);
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
