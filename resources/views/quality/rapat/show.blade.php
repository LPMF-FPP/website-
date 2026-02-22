<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                :title="$rapat->title"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Rapat', 'route' => 'quality.rapat.index'],
                    ['label' => 'Detail Rapat'],
                ]"
            />

            <x-qmh-subnav active="rapat" />
        </div>
    </x-slot>

    <div class="space-y-6" x-data="qmhRapatPage()">
        <div class="grid gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Jenis</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">{{ ucfirst(str_replace('_', ' ', $rapat->meeting_type)) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Jadwal</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">{{ $rapat->scheduled_at?->format('d M Y H:i') ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Lokasi</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">{{ $rapat->location ?: '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Status</p>
                <p class="mt-1">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadgeClass('{{ $rapat->status }}')">
                        {{ strtoupper($rapat->status) }}
                    </span>
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900">Agenda</h3>
            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $rapat->agenda ?: 'Belum ada agenda.' }}</p>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Peserta</h3>
                </div>

                <div class="space-y-2 text-sm">
                    @forelse($rapat->pesertas as $peserta)
                        <div class="rounded-md border border-gray-200 px-3 py-2">
                            <p class="font-medium text-gray-900">{{ $peserta->user?->name ?? 'User tidak ditemukan' }}</p>
                            <p class="text-xs text-gray-500">
                                Status:
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 font-semibold text-gray-700">{{ str_replace('_', ' ', strtoupper($peserta->attendance_status)) }}</span>
                            </p>
                        </div>
                    @empty
                        <p class="text-gray-500">Belum ada peserta.</p>
                    @endforelse
                </div>

                @if($canManage)
                    <form method="POST" action="{{ route('quality.rapat.peserta.store', $rapat) }}" class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                        @csrf
                        <select name="user_id" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" required>
                            <option value="">Pilih peserta</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                        <select name="attendance_status" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="hadir">Hadir</option>
                            <option value="tidak_hadir">Tidak Hadir</option>
                            <option value="izin">Izin</option>
                        </select>
                        <button type="submit" class="rounded-md bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-700">Simpan Peserta</button>
                    </form>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">Notulensi</h3>

                <div class="mt-3 space-y-3 text-sm">
                    @forelse($rapat->notulensis as $notulensi)
                        <div class="rounded-md border border-gray-200 px-3 py-2">
                            <p class="text-xs font-semibold text-gray-500">Versi {{ $notulensi->version }}</p>
                            <p class="mt-1 whitespace-pre-line text-gray-700">{{ $notulensi->content }}</p>
                        </div>
                    @empty
                        <p class="text-gray-500">Belum ada notulensi.</p>
                    @endforelse
                </div>

                @if($canManage)
                    <form method="POST" action="{{ route('quality.rapat.notulensi.store', $rapat) }}" class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                        @csrf
                        <textarea name="content" rows="4" placeholder="Tulis notulensi rapat" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" required></textarea>
                        <button type="submit" class="rounded-md bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-700">Tambah Notulensi</button>
                    </form>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">Action Items</h3>

                <div class="mt-3 space-y-2 text-sm">
                    @forelse($rapat->actionItems as $item)
                        <div class="rounded-md border border-gray-200 px-3 py-2">
                            <p class="font-medium text-gray-900">{{ $item->title }}</p>
                            <p class="text-xs text-gray-500">PIC: {{ $item->assignee?->name ?? '-' }} | Jatuh tempo: {{ $item->due_date?->format('d M Y') ?? '-' }}</p>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusBadgeClass('{{ $item->status }}')">{{ strtoupper($item->status) }}</span>
                                @if($canManage)
                                    <form method="POST" action="{{ route('quality.rapat.action-items.status', [$rapat, $item]) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="rounded-md border-gray-300 text-xs focus:border-primary-600 focus:ring-primary-600">
                                            @foreach(['in_progress', 'resolved', 'verified', 'closed'] as $status)
                                                <option value="{{ $status }}" @selected($item->status === $status)>{{ strtoupper($status) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">Update</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500">Belum ada action item.</p>
                    @endforelse
                </div>

                @if($canManage)
                    <form method="POST" action="{{ route('quality.rapat.action-items.store', $rapat) }}" class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                        @csrf
                        <input type="text" name="title" placeholder="Judul action item" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" required>
                        <textarea name="description" rows="3" placeholder="Deskripsi" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600"></textarea>
                        <select name="assignee_id" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="">Pilih penanggung jawab</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                            @endforeach
                        </select>
                        <input type="date" name="due_date" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                        <button type="submit" class="rounded-md bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-700">Tambah Action Item</button>
                    </form>
                @endif
            </div>
        </div>

        <div
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
            x-data="{
                dragOver: false,
                uploadBusy: false,
                previewFiles: [],
                lightboxOpen: false,
                lightboxSrc: '',
                lightboxTitle: '',
                formatSize(size) {
                    if (!size || size < 1024) {
                        return `${size || 0} B`;
                    }
                    if (size < 1024 * 1024) {
                        return `${(size / 1024).toFixed(1)} KB`;
                    }
                    return `${(size / (1024 * 1024)).toFixed(2)} MB`;
                },
                syncFiles() {
                    const files = Array.from(this.$refs.attachmentInput?.files || []);
                    this.previewFiles = files.map((file) => ({
                        name: file.name,
                        type: file.type,
                        size: file.size,
                        previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : '',
                    }));
                },
                onDrop(event) {
                    this.dragOver = false;
                    const files = event.dataTransfer?.files;
                    if (!files || !this.$refs.attachmentInput) {
                        return;
                    }
                    this.$refs.attachmentInput.files = files;
                    this.syncFiles();
                },
                openLightbox(src, title) {
                    this.lightboxSrc = src;
                    this.lightboxTitle = title;
                    this.lightboxOpen = true;
                },
            }"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Dokumentasi Rapat (Foto/PDF)</h3>
                    <p class="mt-1 text-xs text-gray-500">Dokumentasikan bukti pelaksanaan, pembahasan, dan keputusan rapat.</p>
                </div>
                <span class="rounded-full bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700">{{ $rapat->attachments->count() }} file</span>
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-3">
                @forelse($rapat->attachments as $attachment)
                    <div class="group rounded-xl border border-gray-200 bg-white p-3 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary-200 hover:shadow-sm">
                        @if($attachment->isImage())
                            <button
                                type="button"
                                @click="openLightbox('{{ route('quality.rapat.attachments.file', [$rapat, $attachment]) }}', '{{ addslashes($attachment->file_name) }}')"
                                class="mb-3 block w-full overflow-hidden rounded-lg border border-gray-200"
                            >
                                <img src="{{ route('quality.rapat.attachments.file', [$rapat, $attachment]) }}" alt="Dokumentasi rapat" class="h-32 w-full object-cover transition-transform duration-200 group-hover:scale-[1.02]">
                            </button>
                        @endif

                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">{{ $attachment->file_name }}</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ strtoupper((string) $attachment->file_mime) }}
                                    @if($attachment->file_size)
                                        • {{ number_format($attachment->file_size / 1024, 1) }} KB
                                    @endif
                                </p>
                            </div>
                            @if($canManage)
                                <form method="POST" action="{{ route('quality.rapat.attachments.destroy', [$rapat, $attachment]) }}" onsubmit="return confirm('Hapus dokumentasi ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">Hapus</button>
                                </form>
                            @endif
                        </div>

                        <p class="mt-2 text-xs text-gray-500">Uploader: {{ $attachment->uploader?->name ?? '-' }}</p>
                        @if($attachment->notes)
                            <p class="mt-2 line-clamp-2 text-xs text-gray-600">{{ $attachment->notes }}</p>
                        @endif

                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="{{ route('quality.rapat.attachments.file', [$rapat, $attachment]) }}" target="_blank" rel="noopener" class="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">Lihat</a>
                            <a href="{{ route('quality.rapat.attachments.file', [$rapat, $attachment, 'download' => 1]) }}" class="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">Unduh</a>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Belum ada dokumentasi rapat.</p>
                @endforelse
            </div>

            @if($canManage)
                <form method="POST" action="{{ route('quality.rapat.attachments.store', $rapat) }}" enctype="multipart/form-data" class="mt-4 space-y-3 border-t border-gray-100 pt-4" @submit="uploadBusy = true">
                    @csrf

                    <div
                        class="rounded-xl border border-dashed p-4 transition-all duration-200"
                        :class="dragOver ? 'border-primary-400 bg-primary-50' : 'border-gray-300 bg-white'"
                        @dragover.prevent="dragOver = true"
                        @dragleave.prevent="dragOver = false"
                        @drop.prevent="onDrop($event)"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Tarik file ke sini atau pilih manual</p>
                                <p class="mt-1 text-xs text-gray-500">Format: JPG, PNG, WebP, PDF. Bisa upload beberapa file sekaligus.</p>
                            </div>
                            <button type="button" @click="$refs.attachmentInput.click()" class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Pilih File</button>
                        </div>

                        <input
                            x-ref="attachmentInput"
                            id="files"
                            name="files[]"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,application/pdf"
                            multiple
                            required
                            class="sr-only"
                            @change="syncFiles()"
                        >

                        <template x-if="previewFiles.length > 0">
                            <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <p class="text-xs font-semibold text-gray-700" x-text="`${previewFiles.length} file siap diunggah`"></p>
                                <div class="mt-2 grid gap-2 lg:grid-cols-2">
                                    <template x-for="file in previewFiles" :key="`${file.name}-${file.size}`">
                                        <div class="rounded-md border border-gray-200 bg-white p-2">
                                            <div class="flex items-center gap-2">
                                                <template x-if="file.previewUrl">
                                                    <img :src="file.previewUrl" alt="Preview" class="h-10 w-10 rounded object-cover">
                                                </template>
                                                <div class="min-w-0">
                                                    <p class="truncate text-xs font-medium text-gray-800" x-text="file.name"></p>
                                                    <p class="text-[11px] text-gray-500" x-text="formatSize(file.size)"></p>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700" for="attachment-notes">Catatan (opsional)</label>
                        <textarea id="attachment-notes" name="notes" rows="2" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" placeholder="Contoh: Dokumentasi pembukaan rapat dan sesi diskusi.">{{ old('notes') }}</textarea>
                    </div>
                    @error('attachments')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <div>
                        <button type="submit" class="rounded-md bg-primary-600 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="uploadBusy">
                            <span x-show="!uploadBusy">Unggah Dokumentasi</span>
                            <span x-show="uploadBusy" x-cloak>Mengunggah...</span>
                        </button>
                    </div>
                </form>
            @endif

            <div
                x-show="lightboxOpen"
                x-cloak
                @keydown.escape.window="lightboxOpen = false"
                class="fixed inset-0 z-[90] flex items-center justify-center bg-black/70 p-6"
            >
                <div class="relative w-full max-w-4xl rounded-xl bg-white p-3 shadow-2xl">
                    <button type="button" @click="lightboxOpen = false" class="absolute right-3 top-3 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">Tutup</button>
                    <p class="mb-2 pr-16 text-xs font-medium text-gray-700" x-text="lightboxTitle"></p>
                    <img :src="lightboxSrc" alt="Preview dokumentasi" class="max-h-[70vh] w-full rounded-lg object-contain">
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900">Aksi Cepat</h3>
            <div class="mt-3 flex flex-wrap gap-2 text-sm">
                <a href="{{ route('quality.rapat.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50">Kembali ke Daftar Rapat</a>
                <a href="{{ route('quality.governance.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50">Buka Tata Kelola</a>
                <a href="{{ route('quality.rapat.pdf', $rapat) }}" class="rounded-md border border-primary-200 bg-primary-50 px-3 py-2 text-primary-700 hover:bg-primary-100">Unduh PDF Hasil Rapat</a>
            </div>

            @if($canManage)
                <div
                    class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4"
                    x-data="{
                        panelOpen: {{ ($errors->has('whatsapp') || old('recipient_type')) ? 'true' : 'false' }},
                        recipientType: '{{ old('recipient_type', 'individual') }}',
                        groups: [],
                        groupsLoaded: false,
                        loadingGroups: false,
                        sending: false,
                        groupError: '',
                        async loadGroups() {
                            if (this.groupsLoaded || this.loadingGroups) {
                                return;
                            }
                            this.loadingGroups = true;
                            this.groupError = '';
                            try {
                                const response = await fetch('{{ route('quality.rapat.whatsapp.groups') }}', {
                                    headers: {
                                        Accept: 'application/json',
                                    },
                                });
                                const payload = await response.json();
                                if (!response.ok) {
                                    this.groupError = payload.message || 'Gagal memuat daftar grup.';
                                    return;
                                }
                                this.groups = Array.isArray(payload.groups) ? payload.groups : [];
                                this.groupsLoaded = true;
                            } catch (error) {
                                this.groupError = 'Gagal memuat daftar grup.';
                            } finally {
                                this.loadingGroups = false;
                            }
                        },
                    }"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-md px-2 py-1 text-left"
                        @click="panelOpen = !panelOpen"
                    >
                        <span>
                            <span class="text-sm font-semibold text-gray-900">Kirim PDF Hasil Rapat ke WhatsApp</span>
                            <span class="mt-1 block text-xs text-gray-600">Kirim ke perorangan atau grup, otomatis tercatat di WhatsApp Hub.</span>
                        </span>
                        <span class="rounded-full border border-gray-300 bg-white px-2 py-1 text-xs font-semibold text-gray-700" x-text="panelOpen ? 'Tutup' : 'Buka'"></span>
                    </button>

                    <form method="POST" action="{{ route('quality.rapat.whatsapp.send', $rapat) }}" class="mt-3 space-y-3" x-show="panelOpen" x-cloak @submit="sending = true">
                        @csrf

                        <div class="grid gap-3 lg:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-700" for="recipient_type">Tipe Penerima</label>
                                <select
                                    id="recipient_type"
                                    name="recipient_type"
                                    x-model="recipientType"
                                    @change="if (recipientType === 'group') { loadGroups(); }"
                                    class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600"
                                >
                                    <option value="individual">Perorangan</option>
                                    <option value="group">Grup</option>
                                </select>
                            </div>

                            <div x-show="recipientType === 'individual'" x-cloak>
                                <label class="mb-1 block text-xs font-medium text-gray-700" for="recipient_value_individual">Nomor WhatsApp Tujuan</label>
                                <input
                                    id="recipient_value_individual"
                                    name="recipient_value"
                                    type="text"
                                    :disabled="recipientType !== 'individual'"
                                    value="{{ old('recipient_value') }}"
                                    placeholder="Contoh: 081234567890 atau 6281234567890"
                                    class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600"
                                >
                            </div>

                            <div x-show="recipientType === 'group'" x-cloak>
                                <label class="mb-1 block text-xs font-medium text-gray-700" for="recipient_value_group">Grup WhatsApp</label>
                                <div class="space-y-2">
                                    <input
                                        id="recipient_value_group"
                                        name="recipient_value"
                                        type="text"
                                        list="wa-group-list"
                                        :disabled="recipientType !== 'group'"
                                        value="{{ old('recipient_value') }}"
                                        placeholder="Contoh: 1203630xxxx@g.us"
                                        class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600"
                                        @focus="loadGroups()"
                                    >
                                    <datalist id="wa-group-list">
                                        <template x-for="group in groups" :key="group.jid">
                                            <option :value="group.jid" x-text="group.name"></option>
                                        </template>
                                    </datalist>
                                    <p class="text-xs text-gray-500" x-show="loadingGroups">Memuat daftar grup...</p>
                                    <p class="text-xs text-red-600" x-show="groupError" x-text="groupError"></p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700" for="message">Pesan Pendamping (opsional)</label>
                            <textarea
                                id="message"
                                name="message"
                                rows="3"
                                placeholder="Contoh: Mohon ditinjau, berikut hasil rapat terbaru."
                                class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600"
                            >{{ old('message') }}</textarea>
                        </div>

                        @error('whatsapp')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror

                        <div>
                            <button type="submit" class="rounded-md bg-primary-600 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="sending">
                                <span x-show="!sending">Kirim PDF ke WhatsApp</span>
                                <span x-show="sending" x-cloak>Mengirim...</span>
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
