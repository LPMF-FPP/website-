<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Template QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Template QMH'],
                ]"
            >
                <x-slot name="actions">
                    <a href="#upload-template"
                       class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        Buat Template
                    </a>
                </x-slot>
            </x-page-header>

            <x-qmh-subnav active="templates" />
        </div>
    </x-slot>

    <div class="space-y-6"
         x-data="{
            templateCards: @js($templates->getCollection()->map(fn ($template) => [
                'id' => (int) $template->id,
                'name' => (string) $template->name,
                'doc_type' => strtoupper((string) $template->doc_type),
                'clause' => (int) $template->clause,
                'version' => (int) $template->version,
                'is_active' => (bool) $template->is_active,
                'is_archived' => $template->archived_at !== null,
                'updated_at' => $template->updated_at?->format('d-m-Y H:i') ?? '-',
                'edit_url' => route('quality.templates.edit', $template),
                'preview_url' => route('quality.templates.preview', $template),
                'activate_url' => route('quality.templates.activate', $template),
                'deactivate_url' => route('quality.templates.deactivate', $template),
            ])->values()),
            selectedTemplateId: @js(optional($templates->first())->id),
            selectedTemplate() {
                return this.templateCards.find((item) => item.id === this.selectedTemplateId) || null;
            }
         }">
        @if(session('success'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Manajemen Template</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Pilih template untuk aksi cepat, lalu gunakan filter untuk audit daftar versi.
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-12">
                <div class="lg:col-span-8">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <template x-for="tpl in templateCards" :key="tpl.id">
                            <button
                                type="button"
                                @click="selectedTemplateId = tpl.id"
                                :class="selectedTemplateId === tpl.id ? 'ring-2 ring-primary-500 border-primary-400' : 'border-gray-200 hover:border-primary-300'"
                                class="rounded-lg border bg-white px-4 py-3 text-left transition"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900" x-text="tpl.name"></p>
                                        <p class="mt-1 text-xs text-gray-500">
                                            <span x-text="tpl.doc_type"></span>
                                            • Klausul <span x-text="tpl.clause"></span>
                                            • v<span x-text="tpl.version"></span>
                                        </p>
                                    </div>
                                    <span x-show="selectedTemplateId === tpl.id" class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary-100 text-primary-700">
                                        ✓
                                    </span>
                                </div>
                                <div class="mt-2">
                                    <span x-show="tpl.is_active" class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">Active</span>
                                    <span x-show="!tpl.is_active && tpl.is_archived" class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">Archived</span>
                                    <span x-show="!tpl.is_active && !tpl.is_archived" class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                                </div>
                            </button>
                        </template>

                        <div x-show="templateCards.length === 0" class="sm:col-span-2 xl:col-span-3 rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">
                            Belum ada template pada halaman ini.
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-4">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4" x-show="selectedTemplate()">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Template Terpilih</p>
                        <h3 class="mt-1 text-base font-semibold text-gray-900" x-text="selectedTemplate()?.name"></h3>
                        <p class="mt-1 text-xs text-gray-600">
                            Update terakhir: <span x-text="selectedTemplate()?.updated_at"></span>
                        </p>

                        <div class="mt-4 grid gap-2">
                            <a :href="selectedTemplate()?.edit_url"
                               class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Edit
                            </a>
                            <a :href="selectedTemplate()?.preview_url"
                               target="_blank"
                               rel="noopener"
                               class="inline-flex items-center justify-center rounded-md border border-primary-300 bg-white px-3 py-2 text-sm font-medium text-primary-700 hover:bg-primary-50">
                                Preview
                            </a>

                            <form x-show="selectedTemplate()?.is_active" method="POST" :action="selectedTemplate()?.deactivate_url">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Nonaktifkan
                                </button>
                            </form>

                            <form x-show="selectedTemplate() && !selectedTemplate()?.is_active" method="POST" :action="selectedTemplate()?.activate_url">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="w-full rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                                    Aktifkan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('quality.templates.index') }}" class="grid gap-3 md:grid-cols-5">

                <select name="doc_type" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Jenis</option>
                    <option value="sop" @selected(request('doc_type') === 'sop')>SOP</option>
                    <option value="ik" @selected(request('doc_type') === 'ik')>IK</option>
                    <option value="fr" @selected(request('doc_type') === 'fr')>FR</option>
                </select>

                <select name="clause" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Klausul</option>
                    @foreach([4, 5, 6, 7, 8] as $clause)
                        <option value="{{ $clause }}" @selected((string) request('clause') === (string) $clause)>Klausul {{ $clause }}</option>
                    @endforeach
                </select>

                <select name="layout_profile" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Profile FR</option>
                    <option value="structured_form" @selected(request('layout_profile') === 'structured_form')>Structured Form</option>
                    <option value="risk_matrix" @selected(request('layout_profile') === 'risk_matrix')>Risk Matrix</option>
                    <option value="declaration" @selected(request('layout_profile') === 'declaration')>Declaration</option>
                </select>

                <select name="status" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Status</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    <option value="archived" @selected(request('status') === 'archived')>Archived</option>
                </select>

                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama template"
                       class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">

                <div class="flex gap-2 md:col-span-5">
                    <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Filter</button>
                    <a href="{{ route('quality.templates.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </div>

        @php
            $shouldOpenUpload = $errors->any();
        @endphp

        <details
            id="upload-template"
            class="rounded-xl border border-gray-200 bg-white shadow-sm"
            x-data="{
                openFromHash() {
                    if (window.location.hash === '#upload-template') {
                        this.$el.open = true;
                    }
                }
            }"
            x-init="openFromHash(); window.addEventListener('hashchange', () => openFromHash())"
            @if($shouldOpenUpload) open @endif
        >
            <summary class="cursor-pointer list-none px-6 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Buat Template</h2>
                        <p class="mt-1 text-sm text-gray-600">Kelola template langsung dari editor browser. Bagian ini bisa disembunyikan agar daftar template tetap mudah dibaca.</p>
                    </div>
                    <div class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700">
                        Buka / Tutup
                    </div>
                </div>
            </summary>

            <div class="px-6 pb-6">
                <form
                    method="POST"
                    action="{{ route('quality.templates.store') }}"
                    class="mt-2 grid gap-4 md:grid-cols-2"
                    x-data="{
                        selectedDocType: @js(old('doc_type', 'sop')),
                        layoutProfile: @js(old('layout_profile', 'structured_form')),
                        logoSource: @js(old('logo_source', 'settings'))
                    }"
                >
                    @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="name">Nama Template</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}"
                           class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('name') border-red-400 @else border-gray-300 @enderror"
                           required>
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="doc_type">Jenis Dokumen</label>
                    <select id="doc_type" name="doc_type" x-model="selectedDocType" class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('doc_type') border-red-400 @else border-gray-300 @enderror" required>
                        <option value="sop" @selected(old('doc_type') === 'sop')>SOP</option>
                        <option value="ik" @selected(old('doc_type') === 'ik')>IK</option>
                        <option value="fr" @selected(old('doc_type') === 'fr')>FR</option>
                    </select>
                    @error('doc_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="clause">Klausul</label>
                    <select id="clause" name="clause" class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('clause') border-red-400 @else border-gray-300 @enderror" required>
                        @foreach([4, 5, 6, 7, 8] as $clause)
                            <option value="{{ $clause }}" @selected((string) old('clause', '4') === (string) $clause)>{{ $clause }}</option>
                        @endforeach
                    </select>
                    @error('clause')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="version_notes">Catatan Versi (opsional)</label>
                    <textarea id="version_notes" name="version_notes" rows="3"
                               class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('version_notes') border-red-400 @else border-gray-300 @enderror">{{ old('version_notes') }}</textarea>
                    @error('version_notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <template x-if="selectedDocType === 'fr'">
                    <div class="md:col-span-2 rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
                        <p class="text-sm font-semibold text-gray-800">Konfigurasi Layout FR</p>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700" for="layout_profile">Profil Layout</label>
                                <select id="layout_profile" name="layout_profile" x-model="layoutProfile" class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('layout_profile') border-red-400 @else border-gray-300 @enderror">
                                    <option value="structured_form">Structured Form (default)</option>
                                    <option value="risk_matrix">Risk Matrix</option>
                                    <option value="declaration">Declaration (body-only)</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Declaration tidak menampilkan header/footer FR. Structured Form dan Risk Matrix tetap memakai shell FR standar.</p>
                                @error('layout_profile')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700" for="logo_source">Sumber Logo</label>
                                <select id="logo_source" name="logo_source" x-model="logoSource" class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('logo_source') border-red-400 @else border-gray-300 @enderror">
                                    <option value="settings">Settings Sistem</option>
                                    <option value="custom">Custom Path</option>
                                    <option value="default">Default Aset</option>
                                </select>
                                @error('logo_source')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div x-show="logoSource === 'custom'">
                            <label class="mb-1 block text-sm font-medium text-gray-700" for="logo_path">Path Logo Custom</label>
                            <input id="logo_path" name="logo_path" type="text" value="{{ old('logo_path') }}"
                                   class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('logo_path') border-red-400 @else border-gray-300 @enderror"
                                   placeholder="contoh: images/logo-custom.png atau storage/logo/custom.png">
                            @error('logo_path')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700" for="declaration_header">Header Declaration (opsional)</label>
                            <input id="declaration_header" name="declaration_header" type="text" value="{{ old('declaration_header') }}"
                                   class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('declaration_header') border-red-400 @else border-gray-300 @enderror"
                                   placeholder="contoh: Pernyataan Ketidakberpihakan">
                            @error('declaration_header')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div x-show="layoutProfile === 'risk_matrix'">
                            <label class="mb-1 block text-sm font-medium text-gray-700" for="risk_matrix_columns_csv">Kolom Risk Matrix</label>
                            <input id="risk_matrix_columns_csv" name="risk_matrix_columns_csv" type="text" value="{{ old('risk_matrix_columns_csv', 'Aspek Risiko, Nilai Risiko, Keterangan') }}"
                                   class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('risk_matrix_columns_csv') border-red-400 @else border-gray-300 @enderror"
                                   placeholder="Aspek Risiko, Nilai Risiko, Keterangan">
                            <p class="mt-1 text-xs text-gray-500">Pisahkan dengan koma. Minimal 2 kolom, maksimal 6 kolom.</p>
                            @error('risk_matrix_columns_csv')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </template>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Konten Template (Editor Browser)</label>
                    <p class="mb-2 text-xs text-gray-500">Konten ini akan digunakan saat user memilih template pada form Buat Dokumen.</p>

                    <div
                        class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
                        x-data="qmhEditor({ initialContent: @js(old('content_html', '<p></p>')), editorId: 'qmh-template-create-editor' })"
                        x-init="init()"
                        @qmh-editor-change="$refs.contentHtml.value = $event.detail.html"
                    >
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
                        <input type="hidden" x-ref="contentHtml" name="content_html" value="{{ old('content_html', '<p></p>') }}">
                    </div>
                    @error('content_html')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        Simpan & Aktifkan
                    </button>
                </div>
                </form>
            </div>
        </details>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Nama</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Jenis</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Klausul</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Versi</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Konten</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Updated</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($templates as $template)
                    <tr>
                        <td class="px-4 py-3 text-gray-900">{{ $template->name }}</td>
                        <td class="px-4 py-3 text-gray-700">
                            <div class="flex items-center gap-2">
                                <span>{{ strtoupper($template->doc_type) }}</span>
                                @if($template->doc_type === 'fr')
                                    @php
                                        $layoutProfile = \App\Support\QmhFrLayoutProfile::runtimeProfileFromMetadata(is_array($template->metadata) ? $template->metadata : []);
                                        $layoutLabel = match ($layoutProfile) {
                                            'structured_form' => 'STRUCTURED FORM',
                                            'risk_matrix' => 'RISK MATRIX',
                                            'declaration' => 'DECLARATION',
                                            default => 'LEGACY',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">{{ $layoutLabel }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $template->clause }}</td>
                        <td class="px-4 py-3 text-gray-700">v{{ $template->version }}</td>
                        <td class="px-4 py-3">
                            @if($template->is_active)
                                <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">Active</span>
                            @elseif($template->archived_at)
                                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">Archived</span>
                            @else
                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-700 break-all">
                            <span class="text-xs font-medium text-gray-900">Editor HTML</span>
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $template->updated_at?->format('d-m-Y H:i') ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('quality.templates.edit', $template) }}"
                               class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                Edit
                            </a>

                            <a href="{{ route('quality.templates.preview', $template) }}"
                               target="_blank"
                               rel="noopener"
                               class="inline-flex items-center rounded-md border border-primary-300 px-3 py-1.5 text-xs font-medium text-primary-700 hover:bg-primary-50">
                                Preview
                            </a>

                            @if($template->is_active)
                                <form method="POST" action="{{ route('quality.templates.deactivate', $template) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                        Nonaktifkan
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('quality.templates.activate', $template) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="inline-flex items-center rounded-md bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700">
                                        Aktifkan
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada template QMH. Buat template pertama dari editor browser.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $templates->links() }}
        </div>
    </div>
</x-app-layout>
