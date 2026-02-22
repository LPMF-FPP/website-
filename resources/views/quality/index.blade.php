<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header title="Dokumen QMH">
                <x-slot name="actions">
                    <a href="{{ route('quality.documents.create') }}"
                       class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                        + Buat Dokumen
                    </a>
                </x-slot>
            </x-page-header>

            <x-qmh-subnav active="documents" />
        </div>
    </x-slot>

    @php
        $activeScope = $docScope ?? request('doc_scope', 'semua');
        $scopeOptions = [
            ['value' => 'semua', 'label' => 'Semua'],
            ['value' => 'utama', 'label' => 'Dokumen Utama'],
            ['value' => 'pendukung', 'label' => 'Dokumen Pendukung'],
        ];
        $hasActiveFilter = request()->filled('search')
            || request()->filled('clause')
            || request()->filled('doc_type')
            || request()->filled('status')
            || request()->filled('edition_number')
            || request()->filled('revision_number')
            || request()->filled('from')
            || request()->filled('to');
        $activeFilterCount = collect([
            request('search'),
            request('clause'),
            request('doc_type'),
            request('status'),
            request('edition_number'),
            request('revision_number'),
            request('from'),
            request('to'),
        ])->filter(fn ($value) => filled($value))->count();
        $documentActionMap = $documents->mapWithKeys(function ($document) {
            return [(string) $document->id => [
                'show' => route('quality.documents.show', $document),
                'edit' => auth()->user()?->hasPermission('qmh.create') ? route('quality.documents.edit', $document) : null,
            ]];
        });
    @endphp

    <div class="space-y-6" x-data="qmhDocumentTable(@js($documentActionMap))">
        <section class="relative overflow-hidden rounded-2xl border border-sky-100 bg-gradient-to-r from-white via-white to-sky-50/70 p-4 shadow-sm lg:p-5">
            <div class="pointer-events-none absolute -right-10 -top-10 h-24 w-24 rounded-full bg-sky-200/30 blur-2xl"></div>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700">Filter Dokumen</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">Pilih area kerja dokumen</h3>
                </div>
                @if(auth()->user()?->hasPermission('qmh.template.manage'))
                    <a
                        href="{{ route('quality.templates.index') }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-sky-200 bg-white/90 px-3 py-2 text-sm font-medium text-sky-700 transition hover:-translate-y-0.5 hover:bg-white hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 4h10v4a2 2 0 0 0 2 2h1v10H4V10h1a2 2 0 0 0 2-2V4Zm5 8h.01" />
                        </svg>
                        Kelola Template
                    </a>
                @endif
            </div>

            <div class="mt-4 inline-flex flex-wrap gap-2" role="tablist" aria-label="Kategori dokumen">
                @foreach($scopeOptions as $scope)
                    @php
                        $isScopeActive = $activeScope === $scope['value'];
                    @endphp
                    <a
                        href="{{ route('quality.documents.index', array_merge(request()->except('page'), ['doc_scope' => $scope['value']])) }}"
                        role="tab"
                        aria-selected="{{ $isScopeActive ? 'true' : 'false' }}"
                        class="inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1 {{ $isScopeActive ? 'border-primary-300 bg-primary-100 text-primary-900 shadow-sm' : 'border-slate-300 bg-white text-slate-700 hover:border-sky-200 hover:bg-sky-50/70' }}"
                    >
                        <span class="mr-2 h-1.5 w-1.5 rounded-full {{ $isScopeActive ? 'bg-primary-700' : 'bg-slate-400' }}" aria-hidden="true"></span>
                        {{ $scope['label'] }}
                    </a>
                @endforeach
            </div>
        </section>

        <details class="group rounded-2xl border border-slate-200 bg-white shadow-sm" @if($hasActiveFilter) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
                <div>
                    <p class="text-sm font-semibold text-slate-900">Filter Lanjutan</p>
                    <p class="text-xs text-slate-500">Atur kombinasi klausul, jenis, status, periode, dan versi secara fleksibel.</p>
                </div>
                <div class="flex items-center gap-2">
                    @if($hasActiveFilter)
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">{{ $activeFilterCount }} filter aktif</span>
                    @endif
                    <span class="inline-flex items-center rounded-md border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 transition group-open:bg-slate-100">Buka / Tutup</span>
                </div>
            </summary>

            <div class="border-t border-slate-100 bg-slate-50/40 px-5 pb-5">
                <form method="GET" action="{{ route('quality.documents.index') }}" class="mt-4 grid gap-3 md:grid-cols-6">
                    <input type="hidden" name="doc_scope" value="{{ $activeScope }}">
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm md:col-span-2">
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500" for="doc-search">Cari</label>
                        <input id="doc-search" type="text" name="search" value="{{ request('search') }}" placeholder="Cari kode atau judul"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500" for="doc-clause">Klausul</label>
                        <select id="doc-clause" name="clause" class="w-full rounded-lg border-slate-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="">Semua Klausul</option>
                            @foreach([4, 5, 6, 7, 8] as $clause)
                                <option value="{{ $clause }}" @selected((string) request('clause') === (string) $clause)>Klausul {{ $clause }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500" for="doc-type">Jenis</label>
                        <select id="doc-type" name="doc_type" class="w-full rounded-lg border-slate-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="">Semua Jenis</option>
                            <option value="sop" @selected(request('doc_type') === 'sop')>SOP</option>
                            <option value="ik" @selected(request('doc_type') === 'ik')>IK</option>
                            <option value="formulir" @selected(request('doc_type') === 'formulir')>Formulir</option>
                            <option value="pendukung" @selected(request('doc_type') === 'pendukung')>Pendukung</option>
                        </select>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500" for="doc-status">Status</label>
                        <select id="doc-status" name="status" class="w-full rounded-lg border-slate-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="">Semua Status</option>
                            <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                            <option value="in_review" @selected(request('status') === 'in_review')>In Review</option>
                            <option value="in_approval" @selected(request('status') === 'in_approval')>In Approval</option>
                            <option value="published" @selected(request('status') === 'published')>Published</option>
                            <option value="obsolete" @selected(request('status') === 'obsolete')>Obsolete</option>
                        </select>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500" for="doc-edition">Edisi</label>
                        <input id="doc-edition" type="number" min="0" name="edition_number" value="{{ request('edition_number') }}" placeholder="Edisi"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500" for="doc-revision">Revisi</label>
                        <input id="doc-revision" type="number" min="0" name="revision_number" value="{{ request('revision_number') }}" placeholder="Revisi"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500" for="doc-from">Dari Tanggal</label>
                        <input id="doc-from" type="date" name="from" value="{{ request('from') }}"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500" for="doc-to">Sampai</label>
                        <input id="doc-to" type="date" name="to" value="{{ request('to') }}"
                               class="w-full rounded-lg border-slate-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    </div>
                    <div class="flex items-end gap-2 md:col-span-2">
                        <button type="submit" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-md">
                            Cari
                        </button>
                        <a href="{{ route('quality.documents.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </details>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                <div>
                    <p class="text-sm font-semibold text-gray-900">Daftar Dokumen</p>
                    <p class="text-xs text-gray-500">Menampilkan {{ $documents->count() }} data di halaman ini.</p>
                </div>
                <div class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                    {{ $documents->total() }} total data
                </div>
            </div>

            <div x-cloak x-show="selectedCount > 0" class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-medium text-gray-800">
                        Aksi
                        <span class="text-gray-500" x-text="`(${selectedCount} dipilih)`"></span>
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <template x-if="selectedCount === 1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a :href="currentActionUrl('show')" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">Lihat Detail</a>
                                <a x-show="canEditSelected()" :href="currentActionUrl('edit')" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">Ubah Metadata</a>
                                <button type="button" @click="setHelperMessage('📦 Arsipkan dokumen akan segera tersedia untuk mode tabel ini.')" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">Arsipkan</button>
                            </div>
                        </template>
                        <template x-if="selectedCount > 1">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="setHelperMessage('📦 Arsipkan massal akan segera tersedia untuk dokumen terpilih.')" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">Arsipkan Terpilih</button>
                                <button type="button" @click="setHelperMessage('📤 Ekspor massal sedang disiapkan untuk dokumen terpilih.')" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">Ekspor Terpilih</button>
                                <button type="button" @click="setHelperMessage('🏷️ Penandaan massal akan tersedia pada iterasi berikutnya.')" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">Tandai Terpilih</button>
                            </div>
                        </template>
                    </div>
                </div>
                <p x-cloak x-show="helperMessage" class="mt-2 text-xs text-gray-500" x-text="helperMessage"></p>
            </div>

            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                <tr>
                    <th class="w-10 px-4 py-3 text-left">
                        <label class="sr-only" for="qmh-select-all">Pilih semua dokumen</label>
                        <input id="qmh-select-all" type="checkbox" :checked="allSelectedOnPage" @change="toggleSelectAll($event.target.checked)" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600">
                    </th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Kode</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Judul</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Klausul</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Jenis</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Versi</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($documents as $document)
                    @php
                        $status = $document->currentRevision?->status;
                        $statusVariant = match ($status) {
                            'draft' => 'neutral',
                            'in_review' => 'warning',
                            'in_approval' => 'info',
                            'published' => 'success',
                            'obsolete' => 'danger',
                            default => 'neutral',
                        };
                    @endphp
                    <tr x-bind:class="isSelected({{ $document->id }}) ? 'bg-primary-50/70' : 'bg-white'" class="transition-colors hover:bg-gray-50">
                        <td class="px-4 py-3 align-top">
                            <label class="sr-only" for="doc-{{ $document->id }}">Pilih dokumen {{ $document->doc_code }}</label>
                            <input
                                id="doc-{{ $document->id }}"
                                type="checkbox"
                                :checked="isSelected({{ $document->id }})"
                                @change="toggleSelection({{ $document->id }}, $event.target.checked)"
                                class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600"
                            >
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $document->doc_code }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $document->title }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $document->clause }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ strtoupper($document->doc_type) }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $document->currentRevision?->version_label ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-700">
                            <x-status-badge :status="$status" :variant="$statusVariant" subtle="true" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('quality.documents.show', $document) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">Lihat</a>
                                @if(auth()->user()?->hasPermission('qmh.create'))
                                    <a href="{{ route('quality.documents.edit', $document) }}" class="inline-flex items-center rounded-md border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-medium text-primary-700 hover:bg-primary-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">Edit</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center text-gray-500">
                            <div class="mx-auto flex max-w-sm flex-col items-center gap-2">
                                <span class="text-3xl" aria-hidden="true">📄</span>
                                <p class="font-medium text-gray-700">Belum ada dokumen QMH.</p>
                                <p class="text-sm text-gray-500">Klik tombol <strong>Buat Dokumen</strong> untuk menambahkan draft pertama.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $documents->links() }}
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('qmhDocumentTable', (actionMap) => ({
                actionMap,
                selectedIds: [],
                helperMessage: '',

                get documentIds() {
                    return Object.keys(this.actionMap || {});
                },

                get selectedCount() {
                    return this.selectedIds.length;
                },

                get allSelectedOnPage() {
                    return this.documentIds.length > 0 && this.selectedIds.length === this.documentIds.length;
                },

                toggleSelectAll(checked) {
                    this.selectedIds = checked ? [...this.documentIds] : [];
                    this.helperMessage = '';
                },

                toggleSelection(id, checked) {
                    const key = String(id);

                    if (checked && !this.selectedIds.includes(key)) {
                        this.selectedIds.push(key);
                    }

                    if (!checked) {
                        this.selectedIds = this.selectedIds.filter((selectedId) => selectedId !== key);
                    }

                    this.helperMessage = '';
                },

                isSelected(id) {
                    return this.selectedIds.includes(String(id));
                },

                currentActionUrl(type) {
                    if (this.selectedCount !== 1) {
                        return '#';
                    }

                    const selectedId = this.selectedIds[0];
                    return this.actionMap[selectedId]?.[type] || '#';
                },

                canEditSelected() {
                    if (this.selectedCount !== 1) {
                        return false;
                    }

                    const selectedId = this.selectedIds[0];
                    return Boolean(this.actionMap[selectedId]?.edit);
                },

                setHelperMessage(message) {
                    this.helperMessage = message;
                }
            }));
        });
    </script>
    @endpush
</x-app-layout>
