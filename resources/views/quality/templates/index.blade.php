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
                        Buat / Upload
                    </a>
                </x-slot>
            </x-page-header>

            <x-qmh-subnav active="templates" />
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('quality.templates.index') }}" class="grid gap-3 md:grid-cols-3">

                <select name="doc_type" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Jenis</option>
                    <option value="sop" @selected(request('doc_type') === 'sop')>SOP</option>
                    <option value="ik" @selected(request('doc_type') === 'ik')>IK</option>
                    <option value="fr" @selected(request('doc_type') === 'fr')>FR</option>
                </select>

                <select name="status" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Status</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>

                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama template"
                       class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">

                <div class="flex gap-2 md:col-span-3">
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
            x-data
            x-init="if (window.location.hash === '#upload-template') $el.open = true"
            @if($shouldOpenUpload) open @endif
        >
            <summary class="cursor-pointer list-none px-6 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Buat / Upload Template</h2>
                        <p class="mt-1 text-sm text-gray-600">Buat template via editor browser (tanpa DOCX) atau upload DOCX sebagai sumber awal. Bagian ini bisa disembunyikan agar daftar template tetap scannable.</p>
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
                    enctype="multipart/form-data"
                    class="mt-2 grid gap-4 md:grid-cols-2"
                    x-data="{
                        selectedDocType: @js(old('doc_type', 'sop')),
                        layoutProfile: @js(old('layout_profile', 'declaration')),
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
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="file">File DOCX (opsional)</label>
                    <input id="file" name="file" type="file" accept=".docx"
                           class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('file') border-red-400 @else border-gray-300 @enderror"
                    >
                    <p class="mt-1 text-xs text-gray-500">Jika DOCX diupload dan editor kosong, konten awal akan diambil dari DOCX.</p>
                    @error('file')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
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
                                    <option value="declaration">Declaration</option>
                                    <option value="risk_matrix">Risk Matrix</option>
                                </select>
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
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Versi</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Sumber Template</th>
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
                                        $layoutProfile = \App\Support\QmhFrLayoutProfile::fromMetadata(is_array($template->metadata) ? $template->metadata : [])['layout_profile'];
                                    @endphp
                                    <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">{{ strtoupper(str_replace('_', ' ', $layoutProfile)) }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-700">v{{ $template->version }}</td>
                        <td class="px-4 py-3">
                            @if($template->is_active)
                                <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">Active</span>
                            @else
                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-700 break-all">
                            @if($template->source_docx_path)
                                <div class="text-xs font-medium text-gray-900">DOCX (arsip)</div>
                                <div>{{ $template->source_docx_path }}</div>
                            @else
                                <span class="text-xs font-medium text-gray-900">HTML-only</span>
                            @endif
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
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada template QMH. Buat template via editor browser (HTML) atau upload DOCX opsional sebagai sumber awal.</td>
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
