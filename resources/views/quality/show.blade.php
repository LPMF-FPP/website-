@php
    $currentRevision = $document->currentRevision;
    $status = $currentRevision?->status;
    $statusVariant = match ($status) {
        'draft' => 'neutral',
        'in_review' => 'warning',
        'in_approval' => 'info',
        'published' => 'success',
        'obsolete' => 'danger',
        default => 'neutral',
    };

    $statusLabel = match ($status) {
        'in_review' => 'In Review',
        'in_approval' => 'In Approval',
        default => $status ? ucfirst(str_replace('_', ' ', $status)) : '-',
    };

    $usersById = $users->keyBy('id');
    $lock = $currentRevision?->lock;
    $lockIsActive = $lock !== null && $lock->isActive();
    $lockedByAnotherUser = $lockIsActive && (int) $lock->locked_by !== (int) auth()->id();
    $currentUserId = (int) auth()->id();

    $canSubmit = $status === 'draft' && (int) ($currentRevision?->dibuat_oleh ?? 0) === $currentUserId;
    $submitReason = match (true) {
        $status !== 'draft' => 'Submit hanya tersedia saat status draft.',
        (int) ($currentRevision?->dibuat_oleh ?? 0) !== $currentUserId => 'Hanya pembuat revisi yang dapat submit.',
        default => null,
    };

    $canReview = $status === 'in_review' && (int) ($currentRevision?->diperiksa_oleh ?? 0) === $currentUserId;
    $reviewReason = match (true) {
        $status !== 'in_review' => 'Review hanya tersedia saat status in_review.',
        (int) ($currentRevision?->diperiksa_oleh ?? 0) !== $currentUserId => 'Hanya pemeriksa yang ditugaskan yang dapat review.',
        default => null,
    };

    $canApprove = $status === 'in_approval' && (int) ($currentRevision?->disahkan_oleh ?? 0) === $currentUserId;
    $approveReason = match (true) {
        $status !== 'in_approval' => 'Approve hanya tersedia saat status in_approval.',
        (int) ($currentRevision?->disahkan_oleh ?? 0) !== $currentUserId => 'Hanya pengesah yang ditugaskan yang dapat approve.',
        default => null,
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Dokumen QMH"
            :breadcrumbs="[
                ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                ['label' => 'Dokumen'],
                ['label' => $document->doc_code],
            ]"
        >
            <x-slot name="actions">
                <a href="{{ route('quality.documents.index') }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Kembali
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div
        class="space-y-6 sm:px-6 lg:px-8"
        x-data="qmhShowPage({
            revisionId: @js($currentRevision?->id),
            documentId: @js($document->id),
            currentStatus: @js($status),
            currentVersionLabel: @js($currentRevision?->version_label ?? 'E1-R0'),
            currentUserId: @js((int) auth()->id()),
            createdById: @js((int) ($currentRevision?->dibuat_oleh ?? 0)),
            reviewerById: @js((int) ($currentRevision?->diperiksa_oleh ?? 0)),
            approverById: @js((int) ($currentRevision?->disahkan_oleh ?? 0)),
            canCreate: @js(auth()->user()?->hasPermission('qmh.create') ?? false),
            canReport: @js(auth()->user()?->hasPermission('qmh.report') ?? false),
            isAdmin: @js((auth()->user()?->role ?? '') === 'admin'),
            csrfToken: @js(csrf_token()),
        })"
        x-init="init()"
    >
        @if($lock !== null)
            @if($lockIsActive)
                <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p>
                            Dokumen sedang diedit oleh
                            <strong>{{ $lock->owner?->name ?? 'Pengguna lain' }}</strong>
                            sejak {{ $lock->locked_at?->format('d-m-Y H:i') ?? '-' }}.
                        </p>
                        @if((auth()->user()?->role ?? '') === 'admin')
                            <button
                                type="button"
                                @click="openForceUnlockModal()"
                                class="inline-flex items-center rounded-md border border-amber-500 bg-white px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-100"
                            >
                                Force Unlock
                            </button>
                        @endif
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                    Lock kedaluwarsa. Dokumen dapat diambil alih untuk diedit.
                </div>
            @endif
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-2 lg:col-span-2">
                    <div class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                        <div><span class="font-medium text-gray-600">Kode Dokumen:</span> <span class="text-gray-900">{{ $document->doc_code }}</span></div>
                        <div><span class="font-medium text-gray-600">Versi:</span> <span class="text-gray-900">{{ $currentRevision?->version_label ?? '-' }}</span></div>
                        <div class="sm:col-span-2"><span class="font-medium text-gray-600">Judul:</span> <span class="text-gray-900">{{ $document->title }}</span></div>
                        <div><span class="font-medium text-gray-600">Klausul:</span> <span class="text-gray-900">{{ $document->clause }}</span></div>
                        <div><span class="font-medium text-gray-600">Jenis:</span> <span class="text-gray-900">{{ strtoupper($document->doc_type) }}</span></div>
                        <div><span class="font-medium text-gray-600">Status:</span> <x-status-badge :label="$statusLabel" :variant="$statusVariant" subtle="true" /></div>
                        <div><span class="font-medium text-gray-600">Template:</span> <span class="text-gray-900">{{ $currentRevision?->template_name ? $currentRevision->template_name.' (v'.$currentRevision->template_version.')' : '-' }}</span></div>
                        <div><span class="font-medium text-gray-600">Source DOCX:</span> <span class="text-gray-900">{{ $currentRevision?->source_docx_path ?? '-' }}</span></div>
                        <div><span class="font-medium text-gray-600">Dibuat oleh:</span> <span class="text-gray-900">{{ $usersById->get($currentRevision?->dibuat_oleh)?->name ?? '-' }}</span></div>
                        <div><span class="font-medium text-gray-600">Diperiksa:</span> <span class="text-gray-900">{{ $usersById->get($currentRevision?->diperiksa_oleh)?->name ?? '-' }}</span></div>
                        <div><span class="font-medium text-gray-600">Disahkan:</span> <span class="text-gray-900">{{ $usersById->get($currentRevision?->disahkan_oleh)?->name ?? '-' }}</span></div>
                        <div><span class="font-medium text-gray-600">Autosave terakhir:</span> <span class="text-gray-900">{{ $currentRevision?->last_autosaved_at?->format('d-m-Y H:i:s') ?? '-' }}</span></div>
                    </div>
                </div>

                <div class="space-y-2">
                    @if($currentRevision !== null)
                        @if(auth()->user()?->hasPermission('qmh.create'))
                            @if(Route::has('quality.documents.edit'))
                                @if($lockedByAnotherUser)
                                    <button
                                        type="button"
                                        class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-md border border-gray-300 bg-gray-100 px-3 py-2 text-sm font-medium text-gray-500"
                                        title="Dokumen dikunci oleh pengguna lain"
                                        disabled
                                    >
                                        Edit Dokumen
                                    </button>
                                @else
                                    <a
                                        href="{{ route('quality.documents.edit', $document) }}"
                                        class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                    >
                                        Edit Dokumen
                                    </a>
                                @endif
                            @else
                                <button
                                    type="button"
                                    class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-gray-100 px-3 py-2 text-sm font-medium text-gray-500"
                                    disabled
                                >
                                    Edit Dokumen
                                </button>
                            @endif
                        @endif

                        @if(auth()->user()?->hasPermission('qmh.create'))
                            @if(Route::has('quality.documents.edit-docx'))
                                @if($lockedByAnotherUser)
                                    <button
                                        type="button"
                                        class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-md border border-gray-300 bg-gray-100 px-3 py-2 text-sm font-medium text-gray-500"
                                        title="Dokumen dikunci oleh pengguna lain"
                                        disabled
                                    >
                                        Edit DOCX
                                    </button>
                                @else
                                    <a
                                        href="{{ route('quality.documents.edit-docx', $document) }}"
                                        class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                    >
                                        Edit DOCX
                                    </a>
                                @endif
                            @endif
                        @endif

                        @if(auth()->user()?->hasPermission('qmh.create'))
                            <button
                                type="button"
                                @click="openDownloadModal()"
                                class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Unduh PDF
                            </button>
                        @endif

                        @if(auth()->user()?->hasPermission('qmh.create'))
                            <div class="space-y-1">
                                <button
                                    type="button"
                                    @click="openSubmitModal()"
                                    @disabled(! $canSubmit)
                                    title="{{ $submitReason ?? '' }}"
                                    class="inline-flex w-full items-center justify-center rounded-md px-3 py-2 text-sm font-medium {{ $canSubmit ? 'bg-blue-600 text-white hover:bg-blue-700' : 'cursor-not-allowed bg-gray-100 text-gray-500' }}"
                                >
                                    Submit untuk Review
                                </button>
                                @if(! $canSubmit && $submitReason)
                                    <p class="text-xs text-gray-500">{{ $submitReason }}</p>
                                @endif
                                @if($status === 'draft' && (int) ($currentRevision?->dibuat_oleh ?? 0) !== $currentUserId)
                                    <p class="text-xs text-gray-500">Hanya pembuat revisi yang dapat submit.</p>
                                @endif
                            </div>

                            <div class="space-y-1">
                                <button
                                    type="button"
                                    @click="openReviewModal()"
                                    @disabled(! $canReview)
                                    title="{{ $reviewReason ?? '' }}"
                                    class="inline-flex w-full items-center justify-center rounded-md px-3 py-2 text-sm font-medium {{ $canReview ? 'bg-blue-600 text-white hover:bg-blue-700' : 'cursor-not-allowed bg-gray-100 text-gray-500' }}"
                                >
                                    Review Dokumen
                                </button>
                                @if(! $canReview && $reviewReason)
                                    <p class="text-xs text-gray-500">{{ $reviewReason }}</p>
                                @endif
                            </div>

                            <div class="space-y-1">
                                <button
                                    type="button"
                                    @click="openApproveModal()"
                                    @disabled(! $canApprove)
                                    title="{{ $approveReason ?? '' }}"
                                    class="inline-flex w-full items-center justify-center rounded-md px-3 py-2 text-sm font-medium {{ $canApprove ? 'bg-green-600 text-white hover:bg-green-700' : 'cursor-not-allowed bg-gray-100 text-gray-500' }}"
                                >
                                    Setujui dan Terbitkan
                                </button>
                                @if(! $canApprove && $approveReason)
                                    <p class="text-xs text-gray-500">{{ $approveReason }}</p>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200">
                <nav class="flex flex-wrap gap-2 px-4 py-3">
                    <button type="button" @click="activeTab = 'content'" :class="tabClass('content')" class="rounded-md px-3 py-2 text-sm font-medium">Konten</button>
                    <button type="button" @click="activeTab = 'revisions'" :class="tabClass('revisions')" class="rounded-md px-3 py-2 text-sm font-medium">Riwayat Revisi</button>
                    <button type="button" @click="openDownloadsTab()" :class="tabClass('downloads')" class="rounded-md px-3 py-2 text-sm font-medium">Riwayat Unduhan</button>
                </nav>
            </div>

            <div class="p-4">
                <div x-show="activeTab === 'content'" x-cloak>
                    @if($currentRevision?->content_html)
                        <article class="prose prose-sm max-w-none text-gray-700">
                            {!! $currentRevision->content_html !!}
                        </article>
                    @else
                        <p class="text-sm text-gray-500">Konten dokumen belum tersedia.</p>
                    @endif
                </div>

                <div x-show="activeTab === 'revisions'" x-cloak>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Versi</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Status</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Dibuat</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Pemeriksa</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Pengesah</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Tanggal</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($document->revisions as $revision)
                                @php
                                    $itemVariant = match ($revision->status) {
                                        'draft' => 'neutral',
                                        'in_review' => 'warning',
                                        'in_approval' => 'info',
                                        'published' => 'success',
                                        'obsolete' => 'danger',
                                        default => 'neutral',
                                    };
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 text-gray-900">{{ $revision->version_label }}</td>
                                    <td class="px-3 py-2"><x-status-badge :status="$revision->status" :variant="$itemVariant" subtle="true" /></td>
                                    <td class="px-3 py-2 text-gray-700">{{ $usersById->get($revision->dibuat_oleh)?->name ?? '-' }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $usersById->get($revision->diperiksa_oleh)?->name ?? '-' }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $usersById->get($revision->disahkan_oleh)?->name ?? '-' }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $revision->updated_at?->format('d-m-Y H:i') ?? '-' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div x-show="activeTab === 'downloads'" x-cloak class="space-y-3">
                    @cannot('qmh.report')
                        <p class="text-sm text-gray-500">Anda membutuhkan izin <strong>qmh.report</strong> untuk melihat riwayat unduhan.</p>
                    @else
                        <template x-if="downloadsLoading">
                            <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">Memuat riwayat unduhan...</div>
                        </template>
                        <template x-if="downloadsError">
                            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="downloadsError"></div>
                        </template>

                        <div class="overflow-x-auto" x-show="!downloadsLoading && downloadRows.length > 0">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Tanggal</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Pengunduh</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Versi</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Jenis Copy</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700">Alasan</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                <template x-for="row in downloadRows" :key="`${row.occurred_at}-${row.actor_id}`">
                                    <tr>
                                        <td class="px-3 py-2 text-gray-700" x-text="formatDate(row.occurred_at)"></td>
                                        <td class="px-3 py-2 text-gray-700" x-text="row.actor_name || '-'" ></td>
                                        <td class="px-3 py-2 text-gray-700" x-text="row.version_label || '-'" ></td>
                                        <td class="px-3 py-2 text-gray-700" x-text="row.copy_type || '-'" ></td>
                                        <td class="px-3 py-2 text-gray-700" x-text="row.reason || '-'" ></td>
                                    </tr>
                                </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex items-center justify-between" x-show="downloadsPagination.total > 0">
                            <p class="text-xs text-gray-500" x-text="`Menampilkan ${downloadRows.length} dari ${downloadsPagination.total} data`"></p>
                            <div class="flex gap-2">
                                <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs text-gray-700 disabled:opacity-50" @click="changeDownloadsPage(downloadsPagination.current_page - 1)" :disabled="downloadsPagination.current_page <= 1">Sebelumnya</button>
                                <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs text-gray-700 disabled:opacity-50" @click="changeDownloadsPage(downloadsPagination.current_page + 1)" :disabled="downloadsPagination.current_page >= downloadsPagination.last_page">Berikutnya</button>
                            </div>
                        </div>

                        <p x-show="!downloadsLoading && downloadRows.length === 0" class="text-sm text-gray-500">Belum ada riwayat unduhan untuk dokumen ini.</p>
                    @endcannot
                </div>
            </div>
        </div>

        <div x-show="submitModal.open" x-cloak x-trap.noscroll.inert="submitModal.open" class="fixed inset-0 z-pd-modal overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex min-h-dvh items-center justify-center px-4 py-8">
                <div class="fixed inset-0 bg-gray-900/50" @click="closeSubmitModal()"></div>
                <div class="relative w-full max-w-lg rounded-xl bg-white p-5 shadow-xl" x-transition>
                    <h3 class="text-lg font-semibold text-gray-900">Submit untuk Review</h3>
                    <p class="mt-1 text-sm text-gray-600">Pilih pemeriksa untuk melanjutkan alur dokumen.</p>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700" for="submit-reviewer">Pemeriksa</label>
                            <select id="submit-reviewer" x-model.number="submitModal.reviewerId" class="w-full rounded-md border border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                                <option value="">Pilih pemeriksa</option>
                                @foreach($users as $userOption)
                                    @if((int) $userOption->id !== (int) auth()->id() && (int) $userOption->id !== (int) ($currentRevision?->dibuat_oleh ?? 0))
                                        <option value="{{ $userOption->id }}">{{ $userOption->name }} ({{ $userOption->role }})</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div x-show="submitModal.error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="submitModal.error"></div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700" @click="closeSubmitModal()">Batal</button>
                        <button type="button" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50" :disabled="submitModal.loading" @click="submitForReview()" x-text="submitModal.loading ? 'Memproses...' : 'Submit'"></button>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="reviewModal.open" x-cloak x-trap.noscroll.inert="reviewModal.open" class="fixed inset-0 z-pd-modal overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex min-h-dvh items-center justify-center px-4 py-8">
                <div class="fixed inset-0 bg-gray-900/50" @click="closeReviewModal()"></div>
                <div class="relative w-full max-w-lg rounded-xl bg-white p-5 shadow-xl" x-transition>
                    <h3 class="text-lg font-semibold text-gray-900">Review Dokumen</h3>
                    <div class="mt-4 space-y-3 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" x-model="reviewModal.action" value="pass"> Lanjutkan ke Approval</label>
                        <label class="flex items-center gap-2"><input type="radio" x-model="reviewModal.action" value="return"> Kembalikan ke Draft</label>
                    </div>

                    <div class="mt-3" x-show="reviewModal.action === 'pass'">
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="review-approver">Pengesah</label>
                        <select id="review-approver" x-model.number="reviewModal.approverId" class="w-full rounded-md border border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="">Pilih pengesah</option>
                            @foreach($users as $userOption)
                                @if(
                                    (int) $userOption->id !== (int) ($currentRevision?->dibuat_oleh ?? 0)
                                    && (int) $userOption->id !== (int) ($currentRevision?->diperiksa_oleh ?? 0)
                                )
                                    <option value="{{ $userOption->id }}">{{ $userOption->name }} ({{ $userOption->role }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-3" x-show="reviewModal.action === 'return'">
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="review-note">Catatan</label>
                        <textarea id="review-note" rows="3" x-model="reviewModal.note" class="w-full rounded-md border border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" placeholder="Tuliskan alasan pengembalian ke draft"></textarea>
                    </div>

                    <div class="mt-3" x-show="reviewModal.error">
                        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="reviewModal.error"></div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700" @click="closeReviewModal()">Batal</button>
                        <button type="button" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50" :disabled="reviewModal.loading" @click="submitReview()" x-text="reviewModal.loading ? 'Memproses...' : 'Kirim'"></button>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="approveModal.open" x-cloak x-trap.noscroll.inert="approveModal.open" class="fixed inset-0 z-pd-modal overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex min-h-dvh items-center justify-center px-4 py-8">
                <div class="fixed inset-0 bg-gray-900/50" @click="closeApproveModal()"></div>
                <div class="relative w-full max-w-lg rounded-xl bg-white p-5 shadow-xl" x-transition>
                    <h3 class="text-lg font-semibold text-gray-900">Setujui dan Terbitkan</h3>
                    <p class="mt-1 text-sm text-gray-600">Versi berikutnya: <strong x-text="nextVersionLabel()"></strong></p>

                    <div class="mt-3 space-y-3">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" x-model="approveModal.promoteToNewEdition">
                            Naikkan ke Edisi baru
                        </label>

                        <div x-show="approveModal.promoteToNewEdition">
                            <label class="mb-1 block text-sm font-medium text-gray-700" for="approve-reason">Alasan</label>
                            <textarea id="approve-reason" rows="3" x-model="approveModal.reason" class="w-full rounded-md border border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" placeholder="Wajib diisi jika naik edisi"></textarea>
                        </div>

                        <div x-show="approveModal.error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="approveModal.error"></div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700" @click="closeApproveModal()">Batal</button>
                        <button type="button" class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50" :disabled="approveModal.loading" @click="submitApprove()" x-text="approveModal.loading ? 'Memproses...' : 'Setujui'"></button>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="downloadModal.open" x-cloak x-trap.noscroll.inert="downloadModal.open" class="fixed inset-0 z-pd-modal overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex min-h-dvh items-center justify-center px-4 py-8">
                <div class="fixed inset-0 bg-gray-900/50" @click="closeDownloadModal()"></div>
                <div class="relative w-full max-w-lg rounded-xl bg-white p-5 shadow-xl" x-transition>
                    <h3 class="text-lg font-semibold text-gray-900">Unduh PDF</h3>
                    <div class="mt-3 space-y-3 text-sm">
                        <label class="flex items-center gap-2">
                            <input type="radio" x-model="downloadModal.copyType" value="controlled" :disabled="currentStatus !== 'published'">
                            Controlled Copy <span class="text-xs text-gray-500" x-show="currentStatus !== 'published'">(hanya saat published)</span>
                        </label>
                        <label class="flex items-center gap-2"><input type="radio" x-model="downloadModal.copyType" value="uncontrolled"> Uncontrolled Copy</label>
                    </div>

                    <div class="mt-3 space-y-3" x-show="downloadModal.copyType === 'uncontrolled'">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700" for="download-reason">Alasan</label>
                            <textarea id="download-reason" rows="3" x-model="downloadModal.reason" class="w-full rounded-md border border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" placeholder="Wajib diisi untuk uncontrolled copy"></textarea>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700" for="download-target">Target Distribusi (opsional)</label>
                            <input id="download-target" type="text" x-model="downloadModal.distributionTarget" class="w-full rounded-md border border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" placeholder="Contoh: Unit Validasi" />
                        </div>
                    </div>

                    <div class="mt-3" x-show="downloadModal.error">
                        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="downloadModal.error"></div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700" @click="closeDownloadModal()">Batal</button>
                        <button type="button" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50" :disabled="downloadModal.loading" @click="submitDownload()" x-text="downloadModal.loading ? 'Memproses...' : 'Unduh'"></button>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="forceUnlockModal.open" x-cloak x-trap.noscroll.inert="forceUnlockModal.open" class="fixed inset-0 z-pd-modal overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex min-h-dvh items-center justify-center px-4 py-8">
                <div class="fixed inset-0 bg-gray-900/50" @click="closeForceUnlockModal()"></div>
                <div class="relative w-full max-w-lg rounded-xl bg-white p-5 shadow-xl" x-transition>
                    <h3 class="text-lg font-semibold text-gray-900">Force Unlock</h3>
                    <p class="mt-1 text-sm text-gray-600">Tindakan ini akan membuka lock dokumen yang sedang aktif.</p>

                    <div class="mt-3">
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="force-unlock-reason">Alasan</label>
                        <textarea id="force-unlock-reason" rows="3" x-model="forceUnlockModal.reason" class="w-full rounded-md border border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" placeholder="Wajib diisi"></textarea>
                    </div>

                    <div class="mt-3" x-show="forceUnlockModal.error">
                        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="forceUnlockModal.error"></div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700" @click="closeForceUnlockModal()">Batal</button>
                        <button type="button" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50" :disabled="forceUnlockModal.loading" @click="submitForceUnlock()" x-text="forceUnlockModal.loading ? 'Memproses...' : 'Force Unlock'"></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function qmhShowPage(config) {
            return {
                activeTab: 'content',
                revisionId: config.revisionId,
                documentId: config.documentId,
                currentStatus: config.currentStatus,
                currentVersionLabel: config.currentVersionLabel,
                csrfToken: config.csrfToken,
                downloadRows: [],
                downloadsLoading: false,
                downloadsError: '',
                downloadsPagination: {
                    current_page: 1,
                    last_page: 1,
                    total: 0,
                    per_page: 10,
                },
                submitModal: { open: false, reviewerId: '', loading: false, error: '' },
                reviewModal: { open: false, action: 'pass', approverId: '', note: '', loading: false, error: '' },
                approveModal: { open: false, promoteToNewEdition: false, reason: '', loading: false, error: '' },
                downloadModal: { open: false, copyType: 'controlled', reason: '', distributionTarget: '', loading: false, error: '' },
                forceUnlockModal: { open: false, reason: '', loading: false, error: '' },

                init() {
                    if (!this.revisionId || !this.documentId) {
                        return;
                    }
                },

                tabClass(tab) {
                    if (this.activeTab === tab) {
                        return 'bg-primary-100 text-primary-700';
                    }

                    return 'text-gray-600 hover:bg-gray-100';
                },

                openDownloadsTab() {
                    this.activeTab = 'downloads';
                    if (this.downloadRows.length === 0 && !this.downloadsLoading) {
                        this.fetchDownloadHistory(1);
                    }
                },

                async fetchDownloadHistory(page = 1) {
                    this.downloadsLoading = true;
                    this.downloadsError = '';

                    const params = new URLSearchParams({
                        document_id: String(this.documentId),
                        per_page: String(this.downloadsPagination.per_page),
                        page: String(page),
                    });

                    try {
                        const response = await fetch(`/api/quality/reports/download-history?${params.toString()}`, {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json' },
                        });

                        if (!response.ok) {
                            this.downloadsError = await this.extractErrorMessage(response, 'Gagal memuat riwayat unduhan.');
                            return;
                        }

                        const payload = await response.json();
                        this.downloadRows = payload.data || [];
                        this.downloadsPagination = {
                            current_page: payload.current_page || 1,
                            last_page: payload.last_page || 1,
                            total: payload.total || 0,
                            per_page: payload.per_page || 10,
                        };
                    } catch (error) {
                        this.downloadsError = 'Terjadi gangguan jaringan saat memuat riwayat unduhan.';
                    } finally {
                        this.downloadsLoading = false;
                    }
                },

                changeDownloadsPage(page) {
                    if (page < 1 || page > this.downloadsPagination.last_page) {
                        return;
                    }

                    this.fetchDownloadHistory(page);
                },

                formatDate(value) {
                    if (!value) return '-';
                    const date = new Date(value);
                    if (Number.isNaN(date.getTime())) return value;
                    return date.toLocaleString('id-ID', { hour12: false });
                },

                openSubmitModal() {
                    this.submitModal.open = true;
                    this.submitModal.error = '';
                },

                closeSubmitModal() {
                    if (this.submitModal.loading) return;
                    this.submitModal.open = false;
                    this.submitModal.error = '';
                },

                async submitForReview() {
                    this.submitModal.loading = true;
                    this.submitModal.error = '';

                    const response = await this.apiPost(`/api/quality/revisions/${this.revisionId}/submit`, {
                        reviewer_id: this.submitModal.reviewerId,
                    });

                    if (!response.ok) {
                        this.submitModal.error = response.message;
                        this.submitModal.loading = false;
                        return;
                    }

                    window.location.reload();
                },

                openReviewModal() {
                    this.reviewModal.open = true;
                    this.reviewModal.error = '';
                },

                closeReviewModal() {
                    if (this.reviewModal.loading) return;
                    this.reviewModal.open = false;
                    this.reviewModal.error = '';
                },

                async submitReview() {
                    this.reviewModal.loading = true;
                    this.reviewModal.error = '';

                    const body = { action: this.reviewModal.action };
                    if (this.reviewModal.action === 'pass') {
                        body.approver_id = this.reviewModal.approverId;
                    } else {
                        body.note = this.reviewModal.note;
                    }

                    const response = await this.apiPost(`/api/quality/revisions/${this.revisionId}/review`, body);
                    if (!response.ok) {
                        this.reviewModal.error = response.message;
                        this.reviewModal.loading = false;
                        return;
                    }

                    window.location.reload();
                },

                openApproveModal() {
                    this.approveModal.open = true;
                    this.approveModal.error = '';
                },

                closeApproveModal() {
                    if (this.approveModal.loading) return;
                    this.approveModal.open = false;
                    this.approveModal.error = '';
                },

                nextVersionLabel() {
                    const currentLabel = this.currentVersionLabel || 'E1-R0';
                    const match = String(currentLabel).match(/E(\d+)-R(\d+)/);

                    if (!match) {
                        return currentLabel;
                    }

                    const edition = Number.parseInt(match[1], 10);
                    const revision = Number.parseInt(match[2], 10);

                    if (this.approveModal.promoteToNewEdition) {
                        return `E${edition + 1}-R0`;
                    }

                    if (revision + 1 >= 10) {
                        return `E${edition + 1}-R0`;
                    }

                    return `E${edition}-R${revision + 1}`;
                },

                async submitApprove() {
                    this.approveModal.loading = true;
                    this.approveModal.error = '';

                    const response = await this.apiPost(`/api/quality/revisions/${this.revisionId}/approve`, {
                        promote_to_new_edition: this.approveModal.promoteToNewEdition,
                        reason: this.approveModal.promoteToNewEdition ? this.approveModal.reason : null,
                    });

                    if (!response.ok) {
                        this.approveModal.error = response.message;
                        this.approveModal.loading = false;
                        return;
                    }

                    window.location.reload();
                },

                openDownloadModal() {
                    this.downloadModal.open = true;
                    this.downloadModal.error = '';
                },

                closeDownloadModal() {
                    if (this.downloadModal.loading) return;
                    this.downloadModal.open = false;
                    this.downloadModal.error = '';
                },

                async submitDownload() {
                    this.downloadModal.loading = true;
                    this.downloadModal.error = '';

                    try {
                        const response = await fetch(`/api/quality/revisions/${this.revisionId}/download`, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/pdf, application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            body: JSON.stringify({
                                copy_type: this.downloadModal.copyType,
                                reason: this.downloadModal.copyType === 'uncontrolled' ? this.downloadModal.reason : null,
                                distribution_target: this.downloadModal.copyType === 'uncontrolled' ? this.downloadModal.distributionTarget : null,
                            }),
                        });

                        if (!response.ok) {
                            this.downloadModal.error = await this.extractErrorMessage(response, 'Gagal mengunduh PDF.');
                            this.downloadModal.loading = false;
                            return;
                        }

                        const blob = await response.blob();
                        const objectUrl = URL.createObjectURL(blob);
                        const anchor = document.createElement('a');
                        const contentDisposition = response.headers.get('Content-Disposition') || '';
                        const filenameMatch = contentDisposition.match(/filename="?([^";]+)"?/i);

                        anchor.href = objectUrl;
                        anchor.download = filenameMatch ? filenameMatch[1] : 'dokumen-qmh.pdf';
                        document.body.append(anchor);
                        anchor.click();
                        anchor.remove();
                        URL.revokeObjectURL(objectUrl);

                        this.downloadModal.open = false;
                        if (this.activeTab === 'downloads') {
                            this.fetchDownloadHistory(this.downloadsPagination.current_page || 1);
                        }
                    } catch (error) {
                        this.downloadModal.error = 'Terjadi gangguan jaringan saat mengunduh file.';
                    } finally {
                        this.downloadModal.loading = false;
                    }
                },

                openForceUnlockModal() {
                    this.forceUnlockModal.open = true;
                    this.forceUnlockModal.error = '';
                },

                closeForceUnlockModal() {
                    if (this.forceUnlockModal.loading) return;
                    this.forceUnlockModal.open = false;
                    this.forceUnlockModal.error = '';
                },

                async submitForceUnlock() {
                    this.forceUnlockModal.loading = true;
                    this.forceUnlockModal.error = '';

                    const response = await this.apiPost(`/api/quality/revisions/${this.revisionId}/unlock`, {
                        force: true,
                        reason: this.forceUnlockModal.reason,
                    });

                    if (!response.ok) {
                        this.forceUnlockModal.error = response.message;
                        this.forceUnlockModal.loading = false;
                        return;
                    }

                    window.location.reload();
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
                        return { ok: false, message: 'Terjadi gangguan jaringan. Coba lagi.' };
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
