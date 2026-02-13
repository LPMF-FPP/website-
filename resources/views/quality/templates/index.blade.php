<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Template QMH"
            :breadcrumbs="[
                ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                ['label' => 'Template QMH'],
            ]"
        >
            <x-slot name="actions">
                <a href="#upload-template"
                   class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Upload Template
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('quality.templates.index') }}" class="grid gap-3 md:grid-cols-4">
                <select name="clause" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <option value="">Semua Klausul</option>
                    @foreach([4, 5, 6, 7, 8] as $clause)
                        <option value="{{ $clause }}" @selected((string) request('clause') === (string) $clause)>Klausul {{ $clause }}</option>
                    @endforeach
                </select>

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

                <div class="flex gap-2 md:col-span-4">
                    <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Filter</button>
                    <a href="{{ route('quality.templates.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </div>

        <div id="upload-template" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Upload Template</h2>
            <p class="mt-1 text-sm text-gray-600">Upload file DOCX untuk kombinasi klausul dan jenis dokumen yang dipilih. Upload baru otomatis menjadi template aktif.</p>

            <form method="POST" action="{{ route('quality.templates.store') }}" enctype="multipart/form-data" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="name">Nama Template</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}"
                           class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('name') border-red-400 @else border-gray-300 @enderror"
                           required>
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="file">File DOCX</label>
                    <input id="file" name="file" type="file" accept=".docx"
                           class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('file') border-red-400 @else border-gray-300 @enderror"
                           required>
                    @error('file')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="clause">Klausul</label>
                    <select id="clause" name="clause" class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('clause') border-red-400 @else border-gray-300 @enderror" required>
                        @foreach([4, 5, 6, 7, 8] as $clause)
                            <option value="{{ $clause }}" @selected((string) old('clause') === (string) $clause)>{{ $clause }}</option>
                        @endforeach
                    </select>
                    @error('clause')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="doc_type">Jenis Dokumen</label>
                    <select id="doc_type" name="doc_type" class="w-full rounded-md border text-sm focus:border-primary-600 focus:ring-primary-600 @error('doc_type') border-red-400 @else border-gray-300 @enderror" required>
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

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Simpan & Aktifkan
                    </button>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Nama</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Klausul</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Jenis</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Versi</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Source DOCX</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Updated</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($templates as $template)
                    <tr>
                        <td class="px-4 py-3 text-gray-900">{{ $template->name }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $template->clause }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ strtoupper($template->doc_type) }}</td>
                        <td class="px-4 py-3 text-gray-700">v{{ $template->version }}</td>
                        <td class="px-4 py-3">
                            @if($template->is_active)
                                <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">Active</span>
                            @else
                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-700 break-all">{{ $template->source_docx_path ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $template->updated_at?->format('d-m-Y H:i') ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">
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
                                    <button type="submit" class="inline-flex items-center rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">
                                        Aktifkan
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada template QMH. Upload template pertama untuk mulai menggunakan flow Buat Dokumen.</td>
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
