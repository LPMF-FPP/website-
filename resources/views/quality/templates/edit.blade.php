<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Edit Template QMH"
            :breadcrumbs="[
                ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                ['label' => 'Template QMH', 'route' => 'quality.templates.index'],
                ['label' => 'Edit Template'],
            ]"
        />
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
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
            <form method="POST" action="{{ route('quality.templates.update', $template) }}" enctype="multipart/form-data" class="space-y-4">
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
                    <select id="doc_type" name="doc_type" class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('doc_type') border-red-400 @else border-gray-300 @enderror" required>
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
                        </div>

                        <div class="qmh-editor-surface" x-ref="editor"></div>
                        <input type="hidden" x-ref="hiddenInput" name="unused_template_editor_content">
                        <input type="hidden" x-ref="templateContentHtml" name="content_html" value="{{ old('content_html', data_get($template->metadata, 'content_html', '<p></p>')) }}">
                    </div>
                    @error('content_html')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center justify-between border-t border-gray-200 pt-4">
                    <a href="{{ route('quality.templates.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Batal
                    </a>
                    <button type="submit" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
